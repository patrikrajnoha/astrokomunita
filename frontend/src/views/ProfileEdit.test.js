import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileEdit from './ProfileEdit.vue'

const pushMock = vi.hoisted(() => vi.fn())
const routeMock = vi.hoisted(() => ({ hash: '' }))

const authMock = vi.hoisted(() => ({
  user: null,
  initialized: true,
  csrf: vi.fn(async () => {}),
  fetchUser: vi.fn(async () => {}),
}))

const httpMock = vi.hoisted(() => ({
  patch: vi.fn(),
  get: vi.fn(),
}))
const searchOnboardingLocationsMock = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
  }),
  useRoute: () => routeMock,
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/services/api', () => ({
  default: httpMock,
}))

vi.mock('@/services/events', () => ({
  searchOnboardingLocations: (...args) => searchOnboardingLocationsMock(...args),
}))

async function flush() {
  await Promise.resolve()
  await Promise.resolve()
}

function normalizeText(value = '') {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
}

const locationInputSelector = 'input[placeholder="Napr. Nitra"]'

function saveButton(wrapper) {
  return wrapper
    .findAll('button')
    .find((button) => normalizeText(button.text().trim()) === 'Ulozit')
}

describe('ProfileEdit', () => {
  beforeEach(() => {
    vi.useRealTimers()
    vi.clearAllMocks()
    routeMock.hash = ''

    authMock.user = {
      id: 1,
      name: 'Tester',
      email: 'tester@example.com',
      bio: '',
      location: 'Moje miesto',
      location_label: 'Moje miesto',
      location_source: 'manual',
      location_data: {
        latitude: 0,
        longitude: 0,
        timezone: 'UTC',
        label: 'Moje miesto',
        source: 'manual',
      },
    }

    httpMock.patch.mockResolvedValue({ data: authMock.user })
    httpMock.get.mockResolvedValue({ data: authMock.user })
    searchOnboardingLocationsMock.mockResolvedValue({ data: { data: [] } })
  })

  it('scrolls to #location and toggles highlight state temporarily', async () => {
    vi.useFakeTimers()
    routeMock.hash = '#location'

    const originalScrollIntoView = HTMLElement.prototype.scrollIntoView
    const scrollIntoViewMock = vi.fn()
    HTMLElement.prototype.scrollIntoView = scrollIntoViewMock

    try {
      const wrapper = mount(ProfileEdit)
      await flush()

      expect(scrollIntoViewMock).toHaveBeenCalledTimes(1)
      expect(wrapper.find('#location').classes()).toContain('locationHighlight')

      await vi.advanceTimersByTimeAsync(2200)
      await flush()

      expect(wrapper.find('#location').classes()).not.toContain('locationHighlight')
    } finally {
      HTMLElement.prototype.scrollIntoView = originalScrollIntoView
      vi.useRealTimers()
    }
  })

  it('renders simplified location actions without legacy mode tabs', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    expect(wrapper.findAll('.modeBtn').length).toBe(0)
    expect(wrapper.find('.quickActions').exists()).toBe(false)
    expect(normalizeText(wrapper.text())).not.toContain('Pouzit GPS')
  })

  it('uses unified location editor instead of a plain legacy location input', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    expect(wrapper.find('.locationCard').exists()).toBe(true)
    expect(wrapper.find('input[maxlength="60"]').exists()).toBe(false)
  })

  it('uses manual city input without preset city select', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    expect(wrapper.find('select').exists()).toBe(false)
    expect(wrapper.find('datalist').exists()).toBe(false)
    expect(wrapper.find(locationInputSelector).exists()).toBe(true)
  })

  it('typed city fills coordinates/timezone/label and saves with source manual', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find(locationInputSelector).setValue('Nitra')
    await wrapper.find('.fillLocationBtn').trigger('click')

    expect(wrapper.find(locationInputSelector).exists()).toBe(true)
    expect(wrapper.find(locationInputSelector).element.value).toBe('Nitra')
    expect(wrapper.find('input[type="number"]').exists()).toBe(false)
    expect(wrapper.find('input[placeholder="Europe/Bratislava"]').exists()).toBe(false)
    expect(normalizeText(wrapper.text())).toContain('Suradnice doplnime automaticky z nazvu mesta.')

    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location'])
    expect(httpMock.patch).toHaveBeenCalledWith(
      '/me/location',
      expect.objectContaining({
        latitude: 48.3064,
        longitude: 18.0764,
        timezone: 'Europe/Bratislava',
        location_label: 'Nitra',
        location_source: 'manual',
      }),
      expect.objectContaining({ meta: expect.objectContaining({ skipErrorToast: true }) }),
    )
  })

  it('resolves arbitrary village via locations API and uses returned timezone', async () => {
    searchOnboardingLocationsMock.mockResolvedValue({
      data: {
        data: [
          {
            label: 'Ivanka pri Nitre, Nitriansky kraj, Slovakia',
            place_id: 'open_meteo:987654',
            lat: 48.293,
            lon: 18.1889,
            timezone: 'Europe/Bratislava',
            country: 'SK',
          },
        ],
      },
    })

    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find(locationInputSelector).setValue('Ivanka pri Nitre')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await flush()

    expect(searchOnboardingLocationsMock).toHaveBeenCalledWith('Ivanka pri Nitre', 8)
    expect(wrapper.find(locationInputSelector).element.value).toBe('Ivanka pri Nitre, Nitriansky kraj, Slovakia')
    expect(normalizeText(wrapper.text())).toContain('Poloha nastavena: Ivanka pri Nitre, Nitriansky kraj, Slovakia.')

    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch).toHaveBeenCalledWith(
      '/me/location',
      expect.objectContaining({
        latitude: 48.293,
        longitude: 18.1889,
        timezone: 'Europe/Bratislava',
        location_label: 'Ivanka pri Nitre, Nitriansky kraj, Slovakia',
        location_source: 'manual',
      }),
      expect.objectContaining({ meta: expect.objectContaining({ skipErrorToast: true }) }),
    )
  })

  it('resolves coordinates on save even when Doplnit was not clicked', async () => {
    searchOnboardingLocationsMock.mockResolvedValue({
      data: {
        data: [
          {
            label: 'Cierny Balog, Banskobystricky kraj, Slovakia',
            place_id: 'open_meteo:555',
            lat: 48.7473,
            lon: 19.6515,
            timezone: 'Europe/Bratislava',
            country: 'SK',
          },
        ],
      },
    })

    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find(locationInputSelector).setValue('Cierny Balog')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(searchOnboardingLocationsMock).toHaveBeenCalledWith('Cierny Balog', 8)
    expect(httpMock.patch).toHaveBeenCalledWith(
      '/me/location',
      expect.objectContaining({
        latitude: 48.7473,
        longitude: 19.6515,
        timezone: 'Europe/Bratislava',
        location_label: 'Cierny Balog, Banskobystricky kraj, Slovakia',
        location_source: 'manual',
      }),
      expect.objectContaining({ meta: expect.objectContaining({ skipErrorToast: true }) }),
    )
  })

  it('normalizes known slovak city labels in manual input', async () => {
    authMock.user = {
      ...authMock.user,
      location_label: 'Kosice',
      location_source: 'manual',
      location_data: {
        latitude: 48.7164,
        longitude: 21.2611,
        timezone: 'Europe/Bratislava',
        label: 'Kosice',
        source: 'manual',
      },
    }

    const wrapper = mount(ProfileEdit)
    await flush()

    expect(wrapper.find('select').exists()).toBe(false)
    expect(wrapper.find(locationInputSelector).exists()).toBe(true)
    expect(wrapper.find(locationInputSelector).element.value).toBe('Kosice')
  })

  it('calls only /profile when only profile fields changed', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('textarea').setValue('Nova bio')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/profile'])
  })

  it('refreshes auth via store after successful save and avoids direct /auth/me request', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('textarea').setValue('Nova bio')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(authMock.fetchUser).toHaveBeenCalledWith({
      source: 'profile-save',
      preserveStateOnError: true,
    })
    expect(httpMock.get).not.toHaveBeenCalled()
  })

  it('calls only /me/location when only location fields changed', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find(locationInputSelector).setValue('Nitra')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location'])
  })

  it('calls location first and profile second when both payloads changed', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('textarea').setValue('Nova bio')
    await wrapper.find(locationInputSelector).setValue('Nitra')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location', '/profile'])
  })

  it('fills coordinates and timezone from typed location label', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find(locationInputSelector).setValue('trencin')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await flush()

    expect(wrapper.find(locationInputSelector).exists()).toBe(true)
    expect(wrapper.find(locationInputSelector).element.value).toBe('Trencin')
    expect(normalizeText(wrapper.text())).toContain('Poloha nastavena: Trencin.')

    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch).toHaveBeenCalledWith(
      '/me/location',
      expect.objectContaining({
        latitude: 48.8945,
        longitude: 18.0444,
        timezone: 'Europe/Bratislava',
        location_label: 'Trencin',
        location_source: 'manual',
      }),
      expect.objectContaining({ meta: expect.objectContaining({ skipErrorToast: true }) }),
    )
  })

  it('clears stale coordinates when city is unknown', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find(locationInputSelector).setValue('Neznama test lokalita')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await flush()

    expect(wrapper.find('input[type="number"]').exists()).toBe(false)
    expect(normalizeText(wrapper.text())).toContain('Nepodarilo sa doplnit suradnice pre zadane mesto.')
  })

  it('shows onboarding-like location suggestions and applies selected suggestion', async () => {
    vi.useFakeTimers()
    searchOnboardingLocationsMock.mockResolvedValue({
      data: {
        data: [
          {
            label: 'Ivanka pri Nitre, Nitriansky kraj, Slovakia',
            place_id: 'open_meteo:987654',
            lat: 48.293,
            lon: 18.1889,
            timezone: 'Europe/Bratislava',
            country: 'SK',
          },
        ],
      },
    })

    try {
      const wrapper = mount(ProfileEdit)
      await flush()

      await wrapper.find(locationInputSelector).setValue('Ivanka')
      await vi.advanceTimersByTimeAsync(320)
      await flush()

      expect(wrapper.find('.locationSuggestions').exists()).toBe(true)
      expect(normalizeText(wrapper.text())).toContain('Ivanka pri Nitre, Nitriansky kraj, Slovakia')

      await wrapper.find('.locationSuggestionItem').trigger('click')
      await flush()

      expect(wrapper.find(locationInputSelector).element.value).toBe('Ivanka pri Nitre, Nitriansky kraj, Slovakia')
      expect(wrapper.find('.locationSuggestions').exists()).toBe(false)
      expect(normalizeText(wrapper.text())).toContain('Poloha nastavena: Ivanka pri Nitre, Nitriansky kraj, Slovakia.')
    } finally {
      vi.useRealTimers()
    }
  })
})
