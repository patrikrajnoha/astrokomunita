import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RightObservingSidebar from './RightObservingSidebar.vue'
import { resolveSidebarScopeFromPath } from '@/utils/sidebarScope'

const pushMock = vi.hoisted(() => vi.fn())
const getMock = vi.hoisted(() => vi.fn())

const authMock = vi.hoisted(() => ({
  isAuthed: true,
  initialized: true,
  isAdmin: false,
  user: { id: 10, is_admin: false, role: 'user' },
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

async function wait(ms = 90) {
  await vi.advanceTimersByTimeAsync(ms)
}

function buildAstronomyResponse(overrides = {}) {
  return {
    data: {
      moon_phase: 'waxing_crescent',
      moon_illumination_percent: 18,
      sunrise_at: '2026-02-27T06:47:00+01:00',
      sunset_at: '2026-02-27T17:22:00+01:00',
      civil_twilight_end_at: '2026-02-27T18:05:00+01:00',
      sample_at: '2026-02-27T19:30:00+01:00',
      sun_altitude_deg: -18.2,
      moonrise_at: '2026-02-27T09:19:00+01:00',
      moonset_at: '2026-02-27T20:35:00+01:00',
      ...overrides,
    },
  }
}

function buildGetResponse(url) {
  if (url === '/sky/weather') {
    return {
      data: {
        cloud_percent: 32,
        humidity_percent: 58,
        wind_speed: 6.5,
        wind_unit: 'km/h',
        observing_score: 85,
        temperature_c: 2.4,
        weather_label: 'Polojasno',
        updated_at: '2026-02-27T19:25:00+01:00',
        source: 'open_meteo',
      },
    }
  }

  if (url === '/sky/astronomy') {
    return buildAstronomyResponse()
  }

  if (url === '/sky/visible-planets') {
    return {
      data: {
        sample_at: '2026-02-27T19:30:00+01:00',
        sun_altitude_deg: -18.2,
        planets: [
          { name: 'Jupiter', direction: 'SE', altitude_deg: 52.2, elongation_deg: 132.1, best_time_window: '20:20-23:40' },
          { name: 'Mars', direction: 'E', altitude_deg: 12.3, elongation_deg: 28.4, best_time_window: '21:00-00:20' },
          { name: 'Saturn', direction: 'W', altitude_deg: 7.4, elongation_deg: 48.0, best_time_window: '18:40-19:20' },
          { name: 'Merkur', direction: 'W', altitude_deg: 11.2, elongation_deg: 14.9, best_time_window: '18:00-18:10' },
        ],
      },
    }
  }

  if (url === '/sky/iss-preview') {
    return {
      data: {
        available: true,
        next_pass_at: '2026-02-27T21:10:00+01:00',
        duration_sec: 420,
        max_altitude_deg: 61.5,
      },
    }
  }

  if (url === '/sky/light-pollution') {
    return {
      data: {
        bortle_class: 7,
        brightness_value: 0.123,
        confidence: 'low',
      },
    }
  }

  return { data: {} }
}

describe('RightObservingSidebar', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-02-27T19:30:00+01:00'))
    vi.clearAllMocks()
    authMock.isAuthed = true
    authMock.initialized = true
    authMock.isAdmin = false
    authMock.user = { id: 10, is_admin: false, role: 'user' }
    getMock.mockImplementation(async (url) => buildGetResponse(url))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('maps event, settings and observing routes to the right sidebar scopes', () => {
    expect(resolveSidebarScopeFromPath('/events')).toBe('events')
    expect(resolveSidebarScopeFromPath('/settings')).toBe('settings')
    expect(resolveSidebarScopeFromPath('/observations')).toBe('observing')
    expect(resolveSidebarScopeFromPath('/observing/sky-summary')).toBe('observing')
    expect(resolveSidebarScopeFromPath('/unknown-route')).toBeNull()
  })

  it('renders the title and excellent night score presentation', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(wrapper.text()).toContain('Astronomicke podmienky')
    expect(wrapper.text()).toContain('Dobre')
  })

  it('shows the edit pencil only for admin users', async () => {
    const nonAdminWrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(nonAdminWrapper.find('[data-testid="sky-widget-reorder-toggle"]').exists()).toBe(false)

    authMock.isAdmin = true
    authMock.user = { id: 1, is_admin: true, role: 'admin' }

    const adminWrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(adminWrapper.find('[data-testid="sky-widget-reorder-toggle"]').exists()).toBe(true)
  })

  it('navigates to profile edit after clicking the location name', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    const locationButton = wrapper.find('button[title="Zmeniť lokalitu"]')
    await locationButton.trigger('click')

    expect(pushMock).toHaveBeenCalledWith('/profile/edit#location')
  })

  it('shows simplified bortle copy for class 7', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(wrapper.text()).toContain('Svetelné znečistenie: vysoke')
    expect(wrapper.text()).toContain('Mesto (Bortle 7)')
    expect(wrapper.text()).toContain('Odhad podľa polohy')
  })

  it('shows planet visibility tags from the Planety 1.5 contract', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(wrapper.text()).toContain('Jupiter')
    expect(wrapper.text()).toContain('Viditeľná')
    expect(wrapper.text()).toContain('Nízko nad obzorom')
    expect(wrapper.text()).toContain('Blízko Slnka')
    expect(wrapper.text()).toContain('elongácia:')
  })

  it('hides the planet list until night based on sun_altitude_deg', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/visible-planets') {
        return {
          data: {
            sample_at: '2026-02-27T11:57:00+01:00',
            sun_altitude_deg: -8.0,
            planets: [
              { name: 'Jupiter', direction: 'SE', altitude_deg: 52.2, elongation_deg: 132.1, best_time_window: '20:20-23:40' },
            ],
          },
        }
      }

      return buildGetResponse(url)
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(wrapper.text()).toContain('Zobrazíme po zotmení.')
    expect(wrapper.text()).not.toContain('Jupiter')
  })

  it('uses astronomical night at Bratislava winter midnight when sun altitude is <= -18', async () => {
    vi.setSystemTime(new Date('2026-01-15T00:00:00+01:00'))
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') {
        return buildAstronomyResponse({
          sample_at: '2026-01-15T00:00:00+01:00',
          sun_altitude_deg: -32.4,
        })
      }
      return buildGetResponse(url)
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava', locationName: 'Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('/100')
    expect(wrapper.text()).not.toContain('Astronomicky sumrak')
  })

  it('uses twilight bucket at Bratislava summer midnight when sun altitude is above -18', async () => {
    vi.setSystemTime(new Date('2026-07-01T00:00:00+02:00'))
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') {
        return buildAstronomyResponse({
          sample_at: '2026-07-01T00:00:00+02:00',
          sun_altitude_deg: -16.4,
        })
      }
      if (url === '/sky/visible-planets') {
        return {
          data: {
            sample_at: '2026-07-01T00:00:00+02:00',
            sun_altitude_deg: -16.4,
            planets: [
              { name: 'Jupiter', direction: 'SE', altitude_deg: 52.2, elongation_deg: 132.1, best_time_window: '20:20-23:40' },
            ],
          },
        }
      }
      return buildGetResponse(url)
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava', locationName: 'Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Astronomicky sumrak')
  })

  it('shows N/A score during daylight-gated conditions', async () => {
    vi.setSystemTime(new Date('2026-07-01T19:00:00+02:00'))
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') {
        return buildAstronomyResponse({
          sample_at: '2026-07-01T19:00:00+02:00',
          sun_altitude_deg: -2.0,
        })
      }
      if (url === '/sky/visible-planets') {
        return {
          data: {
            sample_at: '2026-07-01T19:00:00+02:00',
            sun_altitude_deg: -2.0,
            planets: [],
          },
        }
      }
      return buildGetResponse(url)
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava', locationName: 'Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('N/A')
    expect(wrapper.text()).toContain('svetlo')
  })

  it('retries weather fetch after pressing retry button', async () => {
    let weatherAttempts = 0
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/weather') {
        weatherAttempts += 1
        if (weatherAttempts === 1) {
          throw new Error('weather unavailable')
        }
        return buildGetResponse(url)
      }
      return buildGetResponse(url)
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()
    expect(wrapper.text()).toContain('Skúsiť znova')

    const retryButtons = wrapper.findAll('button').filter((button) => button.text().includes('Skúsiť znova'))
    expect(retryButtons.length).toBeGreaterThan(0)
    await retryButtons[0].trigger('click')
    await wait()

    expect(weatherAttempts).toBeGreaterThanOrEqual(2)
  })

  it('renders score reasons in the expandable "Preco?" panel', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    const reasonsToggle = wrapper.findAll('button').find((button) => button.text().includes('Preco?'))
    expect(reasonsToggle).toBeDefined()
    await reasonsToggle.trigger('click')
    await wait()

    expect(wrapper.text()).toContain('Oblacnost')
  })

  it('does not fetch sky data when location is unset and shows location-required state', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: null, lon: null, tz: 'Europe/Bratislava', locationName: '' },
    })

    await wait()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Poloha: nenastavená')
    expect(wrapper.text()).toContain('Poloha nie je nastavená')
    expect(wrapper.text()).toContain('Nastaviť polohu')
  })

  it('shows fetch-error state with retry CTA when location exists but API fails', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/weather' || url === '/sky/astronomy' || url === '/sky/light-pollution') {
        throw new Error('service unavailable')
      }

      return buildGetResponse(url)
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(wrapper.text()).toContain('Nepodarilo sa načítať podmienky.')
    expect(wrapper.text()).toContain('Skúsiť znova')
    expect(wrapper.text()).not.toContain('Nastaviť polohu')
  })

  it('shows loading skeleton without CTA while primary data is loading', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/weather' || url === '/sky/astronomy') {
        return new Promise(() => {})
      }

      return buildGetResponse(url)
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(wrapper.find('.animate-pulse').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Nastaviť polohu')
    expect(wrapper.text()).not.toContain('Skúsiť znova')
  })
})
