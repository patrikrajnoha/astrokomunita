import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RightObservingSidebar from './RightObservingSidebar.vue'
import { resolveSidebarScopeFromPath } from '@/utils/sidebarScope'

const pushMock = vi.hoisted(() => vi.fn())
const getMock = vi.hoisted(() => vi.fn())

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

async function wait(ms = 100) {
  await vi.advanceTimersByTimeAsync(ms)
}

function weatherPayload() {
  return {
    data: {
      cloud_percent: 18,
      humidity_percent: 54,
      wind_speed: 7.2,
      wind_unit: 'km/h',
      observing_score: 74,
      temperature_c: 9.4,
      weather_label: 'Jasno',
      updated_at: '2026-03-08T20:05:00+01:00',
      source: 'open_meteo',
    },
  }
}

function astronomyPayload() {
  return {
    data: {
      moon_phase: 'waxing_gibbous',
      moon_illumination_percent: 77,
      sunrise_at: '2026-03-08T06:18:00+01:00',
      sunset_at: '2026-03-08T17:46:00+01:00',
      civil_twilight_end_at: '2026-03-08T18:20:00+01:00',
      sample_at: '2026-03-08T20:10:00+01:00',
      sun_altitude_deg: -22.4,
    },
  }
}

function buildGetResponse(url) {
  if (url === '/sky/weather') return weatherPayload()
  if (url === '/sky/astronomy') return astronomyPayload()
  return { data: {} }
}

describe('RightObservingSidebar', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-08T20:10:00+01:00'))
    vi.clearAllMocks()
    getMock.mockImplementation(async (url) => buildGetResponse(url))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('maps routes to observing/sidebar scopes', () => {
    expect(resolveSidebarScopeFromPath('/events')).toBe('home')
    expect(resolveSidebarScopeFromPath('/observations')).toBe('home')
    expect(resolveSidebarScopeFromPath('/observing/sky-summary')).toBe('home')
    expect(resolveSidebarScopeFromPath('/articles')).toBe('home')
    expect(resolveSidebarScopeFromPath('/articles/neptun')).toBe('home')
    expect(resolveSidebarScopeFromPath('/settings')).toBe('home')
  })

  it('renders compact summary content', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.24, lon: 17.2, tz: 'Europe/Bratislava', locationName: 'Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Astronomické podmienky')
    expect(wrapper.text()).toContain('/ 100')
    expect(wrapper.text()).toContain('Výborné podmienky')
    expect(wrapper.text()).toContain('Jasno · 9.4 °C')
    expect(wrapper.text()).not.toContain('Mesiac')
    expect(wrapper.text()).toContain('Práve prebieha')
  })

  it('renders bundled summary payload without extra sky requests', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: {
        lat: 48.24,
        lon: 17.2,
        tz: 'Europe/Bratislava',
        locationName: 'Bratislava',
        initialPayload: {
          weather: weatherPayload().data,
          astronomy: astronomyPayload().data,
        },
        bundlePending: false,
      },
    })

    await wait()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('/ 100')
    expect(wrapper.text()).toContain('Jasno')
    expect(wrapper.text()).not.toContain('Mesiac')
  })

  it('routes to profile location editor from location action', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.34, lon: 17.3, tz: 'Europe/Bratislava', locationName: 'Bratislava' },
    })

    await wait()

    await wrapper.get('.locationBtn').trigger('click')
    expect(pushMock).toHaveBeenCalledWith('/profile/edit#location')
  })

  it('shows missing-location state and avoids API calls', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: null, lon: null, tz: 'Europe/Bratislava', locationName: '' },
    })

    await wait()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Poloha nie je nastavená')
  })

  it('treats out-of-range coordinates as missing location and avoids API calls', async () => {
    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 120.5, lon: 220.1, tz: 'Europe/Bratislava', locationName: 'Ivanka pri Nitre' },
    })

    await wait()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Poloha nie je nastavená')
  })

  it('does not show moon data (handled by separate moon widget)', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/weather') return weatherPayload()
      if (url === '/sky/astronomy') {
        return {
          data: {
            ...astronomyPayload().data,
            moon_illumination_percent: null,
          },
        }
      }
      return { data: {} }
    })

    const wrapper = mount(RightObservingSidebar, {
      props: { lat: 48.14, lon: 17.1, tz: 'Europe/Bratislava', locationName: 'Bratislava' },
    })

    await wait()

    expect(wrapper.text()).not.toContain('Mesiac')
    expect(wrapper.text()).toContain('Jasno')
  })
})
