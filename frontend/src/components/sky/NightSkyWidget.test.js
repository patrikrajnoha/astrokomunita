import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import NightSkyWidget from './NightSkyWidget.vue'

const getMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

function astronomyPayload(overrides = {}) {
  return {
    data: {
      moon_phase: 'full_moon',
      moon_illumination_percent: 99,
      sample_at: '2026-03-08T20:10:00+01:00',
      sun_altitude_deg: -22.4,
      sunrise_at: '2026-03-08T06:18:00+01:00',
      sunset_at: '2026-03-08T17:46:00+01:00',
      civil_twilight_end_at: '2026-03-08T18:20:00+01:00',
      ...overrides,
    },
  }
}

function planetsPayload(planets = []) {
  return {
    data: {
      planets,
      sample_at: '2026-03-08T20:10:00+01:00',
      sun_altitude_deg: -22.4,
      source: 'jpl_horizons',
    },
  }
}

function lightPollutionPayload(bortleClass = 5) {
  return {
    data: {
      bortle_class: bortleClass,
      brightness_value: 0.32,
      confidence: 'med',
      source: 'light_pollution_viirs',
      reason: null,
    },
  }
}

async function wait(ms = 400) {
  await vi.advanceTimersByTimeAsync(ms)
}

describe('NightSkyWidget', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-08T20:10:00+01:00'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders planet rows with time window and compact moon + conditions', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return astronomyPayload()
      if (url === '/sky/light-pollution') return lightPollutionPayload(5)
      if (url === '/sky/visible-planets') return planetsPayload([
        { name: 'Jupiter', altitude_deg: 64.7, elongation_deg: 132.1, best_time_window: '19:40-23:10' },
        { name: 'Mars', altitude_deg: 18.3, elongation_deg: 73.4, best_time_window: '20:15-01:10' },
      ])
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.1481, lon: 17.11, tz: 'Europe/Bratislava' },
    })

    await wait()

    // Planets displayed
    expect(wrapper.text()).toContain('Jupiter')
    expect(wrapper.text()).toContain('19:40-23:10')
    expect(wrapper.text()).toContain('Mars')
    expect(wrapper.text()).toContain('20:15-01:10')

    // Moon: emoji + illumination + phase
    expect(wrapper.text()).toContain('🌕')
    expect(wrapper.text()).toContain('99%')
    expect(wrapper.text()).toContain('Spln')

    // Conditions: Bortle 5 → Stredné podmienky
    expect(wrapper.text()).toContain('Stredné podmienky')

    // No old table labels
    expect(wrapper.text()).not.toContain('Svetelne znecistenie')
    expect(wrapper.text()).not.toContain('Bortle')
    expect(wrapper.text()).not.toContain('Viditeľné planety')
  })

  it('shows Výborné podmienky for dark sky (Bortle ≤ 2)', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return astronomyPayload()
      if (url === '/sky/light-pollution') return lightPollutionPayload(2)
      if (url === '/sky/visible-planets') return planetsPayload()
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.1482, lon: 17.11, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Výborné podmienky')
  })

  it('shows Silné znečistenie for high Bortle (≥ 8)', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return astronomyPayload()
      if (url === '/sky/light-pollution') return lightPollutionPayload(8)
      if (url === '/sky/visible-planets') return planetsPayload()
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.1483, lon: 17.11, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Silné znečistenie')
  })

  it('shows "žiadne planéty" when no planet data is available', async () => {
    getMock.mockImplementation(async (url) => {
      if (url === '/sky/astronomy') return astronomyPayload()
      if (url === '/sky/light-pollution') return lightPollutionPayload(6)
      if (url === '/sky/visible-planets') return planetsPayload([])
      return { data: {} }
    })

    const wrapper = mount(NightSkyWidget, {
      props: { lat: 48.1484, lon: 17.11, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('žiadne planéty')
  })

  it('shows no-location state when coords are missing', async () => {
    const wrapper = mount(NightSkyWidget, {
      props: { lat: null, lon: null, tz: 'Europe/Bratislava' },
    })

    await wait()

    expect(wrapper.text()).toContain('Poloha nie je nastavená')
  })

  it('uses bundled payload without re-fetching bundled blocks', async () => {
    getMock.mockImplementation(async () => ({ data: {} }))

    const wrapper = mount(NightSkyWidget, {
      props: {
        lat: 48.15,
        lon: 17.11,
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
            bortle_class: 4,
            brightness_value: 0.2,
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

    expect(wrapper.text()).toContain('Jupiter')
    expect(wrapper.text()).toContain('19:40-23:10')
    // Bortle 4 → Dobré podmienky
    expect(wrapper.text()).toContain('Dobré podmienky')
  })
})
