import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import SpaceWeatherWidget from './SpaceWeatherWidget.vue'

const getMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('SpaceWeatherWidget', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-14T21:20:00Z'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('loads NOAA SWPC payload and renders kp plus aurora summary', async () => {
    getMock.mockResolvedValue({
      data: {
        available: true,
        kp_index: 6,
        estimated_kp: 6.33,
        geomagnetic_level: 'Stredna burka',
        noaa_scale: 'G2',
        updated_at: '2026-03-14T21:52:00Z',
        aurora: {
          watch_score: 72,
          watch_label: 'Vysoka sanca',
          forecast_for: '2026-03-14T21:52:00Z',
        },
      },
    })

    const wrapper = mount(SpaceWeatherWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).toHaveBeenCalledWith('/sky/space-weather', {
      params: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
      },
      meta: { skipErrorToast: true },
    })
    expect(wrapper.text()).toContain('Vesmirne pocasie')
    expect(wrapper.text()).toContain('6.0')
    expect(wrapper.text()).toContain('G2')
    expect(wrapper.text()).toContain('Vysoka sanca')
    expect(wrapper.text()).toContain('Zdroj: NOAA SWPC')
  })

  it('uses bundled payload and skips the standalone request', async () => {
    const wrapper = mount(SpaceWeatherWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        initialPayload: {
          available: true,
          kp_index: 5,
          estimated_kp: 5.33,
          geomagnetic_level: 'Mensia burka',
          noaa_scale: 'G1',
          updated_at: '2026-03-14T21:52:00Z',
          aurora: {
            watch_score: 38,
            watch_label: 'Slaba sanca',
            forecast_for: '2026-03-14T21:52:00Z',
          },
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('5.0')
    expect(wrapper.text()).toContain('G1')
  })

  it('shows a missing-location state and skips the request without coordinates', async () => {
    const wrapper = mount(SpaceWeatherWidget, {
      props: {
        lat: null,
        lon: null,
        tz: 'Europe/Bratislava',
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Poloha nie je nastavena')
  })
})
