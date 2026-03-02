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

function buildAstronomyResponse() {
  return {
    data: {
      moon_phase: 'waxing_crescent',
      moon_illumination_percent: 18,
      sunrise_at: '2026-02-27T06:47:00+01:00',
      sunset_at: '2026-02-27T17:22:00+01:00',
      civil_twilight_end_at: '2026-02-27T18:05:00+01:00',
      moonrise_at: '2026-02-27T09:19:00+01:00',
      moonset_at: '2026-02-27T20:35:00+01:00',
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
    expect(wrapper.text()).toContain('😄 Vyborne')
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

    const locationButton = wrapper.find('button[title="Zmenit lokalitu"]')
    await locationButton.trigger('click')

    expect(pushMock).toHaveBeenCalledWith('/profile/edit')
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
})
