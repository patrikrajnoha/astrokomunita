import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import NightSkyWidget from './NightSkyWidget.vue'

const getMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

function astronomyPayload() {
  return {
    data: {
      moon_phase: 'full_moon',
      moon_illumination_percent: 99,
      sample_at: '2026-03-08T20:10:00+01:00',
      sun_altitude_deg: -22.4,
      sunrise_at: '2026-03-08T06:18:00+01:00',
      sunset_at: '2026-03-08T17:46:00+01:00',
      civil_twilight_end_at: '2026-03-08T18:20:00+01:00',
    },
  }
}

function dayAstronomyPayload() {
  return {
    data: {
      moon_phase: 'waxing_crescent',
      moon_illumination_percent: 32,
      sample_at: '2026-03-08T13:10:00+01:00',
      sun_altitude_deg: 28.4,
      sunrise_at: '2026-03-08T06:18:00+01:00',
      sunset_at: '2026-03-08T17:46:00+01:00',
      civil_twilight_end_at: '2026-03-08T18:20:00+01:00',
    },
  }
}

function planetsPayload() {
  return {
    data: {
      planets: [],
      sample_at: '2026-03-08T20:10:00+01:00',
      sun_altitude_deg: -22.4,
      source: 'jpl_horizons',
    },
  }
}

function ephemerisPayload() {
  return {
    data: {
      planets: [],
      comets: [],
      asteroids: [],
      source: null,
      sample_at: '2026-03-08T20:10:00+01:00',
    },
  }
}

async function wait(ms = 400) {
  await vi.advanceTimersByTimeAsync(ms)
}

describe('NightSkyWidget light pollution sources', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-08T20:10:00+01:00'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders VIIRS light pollution payload when backend provides it', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return astronomyPayload()
      if (url === '/sky/light-pollution') {
        return {
          data: {
            bortle_class: 8,
            brightness_value: 0.875,
            confidence: 'med',
            source: 'light_pollution_viirs',
            reason: null,
          },
        }
      }
      if (url === '/sky/visible-planets') return planetsPayload()
      if (url === '/sky/ephemeris') return ephemerisPayload()
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.3064, lon: 18.0764, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Svetelne znecistenie')
    expect(wrapper.text()).toContain('Bortle')
    expect(wrapper.text()).not.toContain('odhad')
    expect(wrapper.text()).not.toContain('realne data docasne nedostupne')
  })

  it('shows unavailable light pollution when backend returns unavailable payload', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return astronomyPayload()
      if (url === '/sky/light-pollution') {
        return {
          data: {
            bortle_class: null,
            brightness_value: null,
            confidence: 'low',
            source: 'light_pollution_provider',
            reason: 'light_pollution_provider_unavailable',
          },
        }
      }
      if (url === '/sky/visible-planets') return planetsPayload()
      if (url === '/sky/ephemeris') return ephemerisPayload()
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.1487, lon: 17.1077, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Svetelne znecistenie')
    expect(wrapper.text()).toContain('realne data docasne nedostupne')
    expect(wrapper.text()).not.toContain('odhad')
  })

  it('shows today planets fallback when no planet is visible right now', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return dayAstronomyPayload()
      if (url === '/sky/light-pollution') {
        return {
          data: {
            bortle_class: 5,
            brightness_value: 0.32,
            confidence: 'med',
            source: 'light_pollution_viirs',
            reason: null,
          },
        }
      }
      if (url === '/sky/visible-planets') {
        return {
          data: {
            planets: [
              { name: 'Mars', elongation_deg: 74.0, best_time_window: '20:15-01:10' },
              { name: 'Jupiter', elongation_deg: 91.2, best_time_window: '19:40-23:10' },
              { name: 'Venusa', elongation_deg: 12.4 },
            ],
            sample_at: '2026-03-08T13:10:00+01:00',
            sun_altitude_deg: 28.4,
            source: 'jpl_horizons',
          },
        }
      }
      if (url === '/sky/ephemeris') return ephemerisPayload()
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.1488, lon: 17.1077, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Viditelne planety')
    expect(wrapper.text()).toContain('dnes:')
    expect(wrapper.text()).toContain('Mars (20:15-01:10)')
    expect(wrapper.text()).toContain('Jupiter (19:40-23:10)')
    expect(wrapper.text()).not.toContain('teraz ziadne')
  })

  it('shows best-time windows for planets that are visible right now', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return astronomyPayload()
      if (url === '/sky/light-pollution') {
        return {
          data: {
            bortle_class: 5,
            brightness_value: 0.32,
            confidence: 'med',
            source: 'light_pollution_viirs',
            reason: null,
          },
        }
      }
      if (url === '/sky/visible-planets') {
        return {
          data: {
            planets: [
              { name: 'Jupiter', altitude_deg: 64.7, elongation_deg: 132.1, best_time_window: '19:40-23:10' },
              { name: 'Mars', altitude_deg: 18.3, elongation_deg: 73.4, best_time_window: '20:15-01:10' },
              { name: 'Saturn', altitude_deg: 8.0, elongation_deg: 48.0, best_time_window: '19:10-20:00' },
            ],
            sample_at: '2026-03-08T20:10:00+01:00',
            sun_altitude_deg: -22.4,
            source: 'jpl_horizons',
          },
        }
      }
      if (url === '/sky/ephemeris') return ephemerisPayload()
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.1489, lon: 17.1077, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Viditelne planety')
    expect(wrapper.text()).toContain('Jupiter (19:40-23:10)')
    expect(wrapper.text()).toContain('Mars (20:15-01:10)')
  })

  it('uses bundled night-sky payload without refetching bundled blocks', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/ephemeris') return ephemerisPayload()
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: {
        lat: 48.1489,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        initialPayload: {
          astronomy: astronomyPayload().data,
          visible_planets: {
            planets: [
              { name: 'Jupiter', altitude_deg: 64.7, elongation_deg: 132.1, best_time_window: '19:40-23:10' },
            ],
            sample_at: '2026-03-08T20:10:00+01:00',
            sun_altitude_deg: -22.4,
            source: 'jpl_horizons',
          },
          light_pollution: {
            bortle_class: 5,
            brightness_value: 0.32,
            confidence: 'med',
            source: 'light_pollution_viirs',
            reason: null,
            sample_at: '2026-03-08T20:10:00+01:00',
          },
        },
        bundlePending: false,
      },
    })

    await wrapper.vm.$nextTick()
    await wait()

    const requestedUrls = getMock.mock.calls.map(([url]) => url)
    expect(requestedUrls).not.toContain('/sky/astronomy')
    expect(requestedUrls).not.toContain('/sky/visible-planets')
    expect(requestedUrls).not.toContain('/sky/light-pollution')
    expect(requestedUrls.every((url) => url === '/sky/ephemeris')).toBe(true)
    expect(wrapper.text()).toContain('Bortle')
    expect(wrapper.text()).toContain('Jupiter (19:40-23:10)')
  })

  it('marks fallback as estimate when only elongation-based candidates are available', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return dayAstronomyPayload()
      if (url === '/sky/light-pollution') {
        return {
          data: {
            bortle_class: 5,
            brightness_value: 0.32,
            confidence: 'med',
            source: 'light_pollution_viirs',
            reason: null,
          },
        }
      }
      if (url === '/sky/visible-planets') {
        return {
          data: {
            planets: [
              { name: 'Mars', elongation_deg: 74.0 },
              { name: 'Jupiter', elongation_deg: 91.2 },
              { name: 'Venusa', elongation_deg: 12.4 },
            ],
            sample_at: '2026-03-08T13:10:00+01:00',
            sun_altitude_deg: 28.4,
            source: 'jpl_horizons',
          },
        }
      }
      if (url === '/sky/ephemeris') return ephemerisPayload()
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Viditelne planety')
    expect(wrapper.text()).toContain('dnes (odhad): Mars, Jupiter')
    expect(wrapper.text()).not.toContain('teraz ziadne')
  })
})
