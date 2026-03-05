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
      location: 'Bratislava',
      location_label: 'Bratislava',
      location_source: 'manual',
      location_data: {
        latitude: 48.1486,
        longitude: 17.1077,
        timezone: 'Europe/Bratislava',
        label: 'Bratislava',
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

  it('renders three location modes', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    const modeButtons = wrapper.findAll('.modeBtn').map((button) => button.text())
    expect(modeButtons).toEqual(expect.arrayContaining(['Predvolba mesta', 'Pouzit GPS', 'Manualne']))
  })

  it('uses the unified location editor instead of a legacy plain Location input', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    expect(wrapper.find('.locationCard').exists()).toBe(true)
    expect(wrapper.find('input[maxlength="60"]').exists()).toBe(false)
  })

  it('preset mode fills latitude/longitude/timezone/label and saves with source preset', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.findAll('.modeBtn')[0].trigger('click')
    await wrapper.find('select').setValue('nitra')

    const numberInputs = wrapper.findAll('input[type="number"]')
    expect(numberInputs[0].element.value).toBe('48.3064000')
    expect(numberInputs[1].element.value).toBe('18.0764000')
    expect(wrapper.find('input[placeholder="Napriklad Bratislava"]').element.value).toBe('Nitra')
    expect(wrapper.find('input[placeholder="Europe/Bratislava"]').element.value).toBe('Europe/Bratislava')

    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location'])

    expect(httpMock.patch).toHaveBeenCalledWith('/me/location', expect.objectContaining({
      latitude: 48.3064,
      longitude: 18.0764,
      timezone: 'Europe/Bratislava',
      location_label: 'Nitra',
      location_source: 'preset',
    }))
  })

  it('gps mode uses geolocation and timezone from Intl with source gps', async () => {
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

    await wrapper.findAll('.modeBtn')[1].trigger('click')
    await wrapper.findAll('button').find((button) => button.text().includes('Pouzit moju polohu')).trigger('click')
    await flush()

    await saveButton(wrapper).trigger('click')
    await flush()

    expect(geolocationMock.getCurrentPosition).toHaveBeenCalledTimes(1)
    expect(httpMock.patch).toHaveBeenCalledWith('/me/location', expect.objectContaining({
      latitude: 48.3064,
      longitude: 18.0764,
      timezone: 'Europe/Prague',
      location_source: 'gps',
    }))
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

    await wrapper.find('input[placeholder="Napriklad Bratislava"]').setValue('Nove mesto')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location'])
  })

  it('calls location first and profile second when both payloads changed', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    await wrapper.find('textarea').setValue('Nova bio')
    await wrapper.find('input[placeholder="Napriklad Bratislava"]').setValue('Nove mesto')
    await saveButton(wrapper).trigger('click')
    await flush()

    expect(httpMock.patch.mock.calls.map(([url]) => url)).toEqual(['/me/location', '/profile'])
  })
})
