import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileEdit from './ProfileEdit.vue'

const pushMock = vi.hoisted(() => vi.fn())

const authMock = vi.hoisted(() => ({
  user: null,
  initialized: true,
  csrf: vi.fn(async () => {}),
  fetchUser: vi.fn(async () => {}),
}))

const httpMock = vi.hoisted(() => ({
  patch: vi.fn(),
  put: vi.fn(),
  get: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/services/api', () => ({
  default: httpMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function saveButton(wrapper) {
  return wrapper
    .findAll('button')
    .find((button) => button.text().trim() === 'Ulozit')
}

describe('ProfileEdit', () => {
  beforeEach(() => {
    vi.clearAllMocks()

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
    httpMock.put.mockResolvedValue({ data: authMock.user })
    httpMock.get.mockResolvedValue({ data: authMock.user })
  })

  it('renders three location modes', async () => {
    const wrapper = mount(ProfileEdit)
    await flush()

    const modeButtons = wrapper.findAll('.modeBtn').map((button) => button.text())
    expect(modeButtons).toEqual(expect.arrayContaining(['Predvolba mesta', 'Pouzit GPS', 'Manualne']))
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

    expect(httpMock.patch).toHaveBeenCalledWith('/profile', expect.objectContaining({
      location_label: 'Nitra',
    }))
    expect(httpMock.put).toHaveBeenCalledWith('/me/location', expect.objectContaining({
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
    expect(httpMock.put).toHaveBeenCalledWith('/me/location', expect.objectContaining({
      latitude: 48.3064,
      longitude: 18.0764,
      timezone: 'Europe/Prague',
      location_source: 'gps',
    }))

    resolvedOptionsSpy.mockRestore()
  })
})
