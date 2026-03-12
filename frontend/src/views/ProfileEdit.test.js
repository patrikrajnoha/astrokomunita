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

async function flush() {
  await Promise.resolve()
  await Promise.resolve()
}

function saveButton(wrapper) {
  return wrapper
    .findAll('button')
    .find((button) => button.text().trim() === 'Ulozit')
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
    const actionButtons = wrapper.findAll('.quickActions button').map((button) => button.text())
    expect(actionButtons).toEqual(expect.arrayContaining(['Pouzit GPS']))
  })

  it('uses unified location editor instead of a plain legacy location input', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    expect(wrapper.find('.locationCard').exists()).toBe(true)
    expect(wrapper.find('input[maxlength="60"]').exists()).toBe(false)
  })

  it('offers only major slovak cities in city select', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    const options = wrapper.findAll('select option').map((option) => option.text())
    expect(options).toEqual(expect.arrayContaining(['Bratislava', 'Kosice', 'Presov', 'Zilina', 'Nitra']))
    expect(options).not.toEqual(expect.arrayContaining(['Ivanka pri Nitre', 'Cesko', 'Europa', 'Mimo Europy']))
  })

  it('preset selection fills coordinates/timezone/label and saves with source preset', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('select').setValue('nitra')

    expect(wrapper.find('input[placeholder="Napr. Bratislava"]').exists()).toBe(true)
    expect(wrapper.find('input[placeholder="Napr. Bratislava"]').element.value).toBe('Nitra')
    expect(wrapper.text()).toContain('Nazov polohy sa prebera z vybraneho mesta.')
    expect(wrapper.find('input[type="number"]').exists()).toBe(false)
    expect(wrapper.find('input[placeholder="Europe/Bratislava"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Suradnice sa pouzivaju interne pre pocasie a nocnu oblohu.')

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
        location_source: 'preset',
      }),
    )
  })

  it('normalizes known slovak city labels to city mode', async () => {
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

    expect(wrapper.find('select').element.value).toBe('kosice')
    expect(wrapper.find('input[placeholder="Napr. Bratislava"]').exists()).toBe(true)
    expect(wrapper.find('input[placeholder="Napr. Bratislava"]').element.value).toBe('Kosice')
    expect(wrapper.text()).toContain('Nazov polohy sa prebera z vybraneho mesta.')
  })

  it('gps action uses geolocation and timezone from Intl with source gps', async () => {
    const resolvedOptionsSpy = vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockReturnValue({
      locale: 'sk-SK',
      calendar: 'gregory',
      numberingSystem: 'latn',
      timeZone: 'Europe/Prague',
      year: 'numeric',
      month: 'numeric',
      day: 'numeric',
    })

    const geolocationMock = {
      getCurrentPosition: vi.fn((resolve) => {
        resolve({
          coords: {
            latitude: 48.3064,
            longitude: 18.0764,
          },
        })
      }),
    }
    Object.defineProperty(window.navigator, 'geolocation', {
      configurable: true,
      value: geolocationMock,
    })

    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.findAll('button').find((button) => button.text().includes('Pouzit GPS')).trigger('click')
    await flush()

    await saveButton(wrapper).trigger('click')
    await flush()

    expect(geolocationMock.getCurrentPosition).toHaveBeenCalledTimes(1)
    expect(httpMock.patch).toHaveBeenCalledWith(
      '/me/location',
      expect.objectContaining({
        latitude: 48.3064,
        longitude: 18.0764,
        timezone: 'Europe/Prague',
        location_source: 'gps',
      }),
    )
    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location'])

    resolvedOptionsSpy.mockRestore()
  })

  it('calls only /profile when only profile fields changed', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('textarea').setValue('Nova bio')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/profile'])
  })

  it('calls only /me/location when only location fields changed', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('input[placeholder="Napr. Bratislava"]').setValue('Nitra')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location'])
  })

  it('calls location first and profile second when both payloads changed', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('textarea').setValue('Nova bio')
    await wrapper.find('input[placeholder="Napr. Bratislava"]').setValue('Nitra')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location', '/profile'])
  })

  it('fills coordinates and timezone from typed location label', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('input[placeholder="Napr. Bratislava"]').setValue('trencin')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await flush()

    expect(wrapper.find('input[placeholder="Napr. Bratislava"]').exists()).toBe(true)
    expect(wrapper.find('input[placeholder="Napr. Bratislava"]').element.value).toBe('Trencin')
    expect(wrapper.text()).toContain('Mesto bolo nastavene.')

    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch).toHaveBeenCalledWith(
      '/me/location',
      expect.objectContaining({
        latitude: 48.8945,
        longitude: 18.0444,
        timezone: 'Europe/Bratislava',
        location_label: 'Trencin',
        location_source: 'preset',
      }),
    )
  })

  it('clears stale coordinates when city is unknown', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('input[placeholder="Napr. Bratislava"]').setValue('Ivanka pri Nitre')
    await wrapper.find('.fillLocationBtn').trigger('click')
    await flush()

    expect(wrapper.find('input[type="number"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Vyber velke slovenske mesto zo zoznamu.')
  })
})
