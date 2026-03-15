import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import AuroraWatchWidget from './AuroraWatchWidget.vue'

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

describe('AuroraWatchWidget', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-14T21:20:00Z'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('loads NOAA aurora payload and renders local watch summary', async () => {
    getMock.mockResolvedValue({
      data: {
        available: true,
        watch_score: 72,
        watch_label: 'Vysoka sanca',
        corridor_peak_score: 72,
        nearest_score: 4,
        forecast_for: '2026-03-14T21:52:00Z',
        updated_at: '2026-03-14T21:12:00Z',
        inference: 'poleward_corridor_peak',
        source: {
          label: 'NOAA SWPC OVATION',
        },
      },
    })

    const wrapper = mount(AuroraWatchWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).toHaveBeenCalledWith('/sky/aurora', {
      params: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
      },
      meta: { skipErrorToast: true },
    })
    expect(wrapper.text()).toContain('Aurora watch')
    expect(wrapper.text()).toContain('Vysoka sanca')
    expect(wrapper.text()).toContain('72/100')
    expect(wrapper.text()).toContain('NOAA SWPC OVATION')
    expect(wrapper.text()).toContain('Koridor severne od teba: 72/100')
  })

  it('uses bundled aurora payload and skips the standalone request', async () => {
    const wrapper = mount(AuroraWatchWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        initialPayload: {
          available: true,
          watch_score: 38,
          watch_label: 'Slaba sanca',
          corridor_peak_score: 38,
          nearest_score: 12,
          forecast_for: '2026-03-14T21:52:00Z',
          updated_at: '2026-03-14T21:12:00Z',
          inference: 'poleward_corridor_peak',
          source: {
            label: 'NOAA SWPC OVATION',
          },
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Slaba sanca')
    expect(wrapper.text()).toContain('38/100')
  })

  it('shows a missing-location state and skips the request without coordinates', async () => {
    const wrapper = mount(AuroraWatchWidget, {
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
