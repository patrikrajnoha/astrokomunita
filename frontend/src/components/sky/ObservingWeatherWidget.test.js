import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ObservingWeatherWidget from './ObservingWeatherWidget.vue'

const getMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

async function wait(ms = 80) {
  await vi.advanceTimersByTimeAsync(ms)
}

describe('ObservingWeatherWidget bundle hydration', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-15T20:10:00+01:00'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders bundled weather payload without an extra weather request', async () => {
    const wrapper = mount(ObservingWeatherWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        initialPayload: {
          weather: {
            cloud_percent: 32,
            wind_speed: 13,
            wind_unit: 'km/h',
            humidity_percent: 56,
            temperature_c: 9.8,
            weather_label: 'Prevazne jasno',
            updated_at: '2026-03-15T20:00:00+01:00',
            as_of: '2026-03-15T20:00:00+01:00',
            source: 'open_meteo',
          },
        },
        bundlePending: false,
      },
    })

    await wait()

    expect(getMock).not.toHaveBeenCalled()
    // Cloud-based verdict: 32% → "good" → Väčšinou jasno
    expect(wrapper.text()).toContain('Väčšinou jasno')
    // Compact data line includes cloud %
    expect(wrapper.text()).toContain('32%')
    // Wind in compact line
    expect(wrapper.text()).toContain('13.0 km/h')
    // No source attribution
    expect(wrapper.text()).not.toContain('Zdroj:')
  })

  it('shows no-location state when coords are missing', async () => {
    const wrapper = mount(ObservingWeatherWidget, {
      props: {
        lat: null,
        lon: null,
      },
    })

    await wait()

    expect(wrapper.text()).toContain('Poloha nie je nastavená')
  })
})
