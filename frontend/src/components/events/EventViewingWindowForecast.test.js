import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EventViewingWindowForecast from './EventViewingWindowForecast.vue'

const apiGetMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
  },
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('EventViewingWindowForecast', () => {
  beforeEach(() => {
    apiGetMock.mockReset()
  })

  it('renders viewing window and rating when location is available', async () => {
    apiGetMock.mockResolvedValue({
      data: {
        viewing_window: {
          start_at: '2026-03-14T20:10:00+01:00',
          end_at: '2026-03-14T23:30:00+01:00',
        },
        summary: {
          clouds_pct: 15,
          wind_ms: 4,
          temp_c: 6,
          humidity_pct: 60,
          precip_pct: 10,
          rating: 'good',
          label_sk: 'Dobre',
        },
      },
    })

    const wrapper = mount(EventViewingWindowForecast, {
      props: {
        event: { id: 12 },
        userLocation: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava' },
      },
    })

    await flush()
    await flush()

    expect(apiGetMock).toHaveBeenCalledWith('/events/12/viewing-forecast', expect.objectContaining({
      params: expect.objectContaining({
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
      }),
    }))
    expect(wrapper.text()).toContain('Okno pozorovania')
    expect(wrapper.text()).toContain('20:10 - 23:30')
    expect(wrapper.text()).toContain('Dobre')
    expect(wrapper.text()).toContain('15%')
  })

  it('renders missing-location helper when coordinates are unavailable', async () => {
    const wrapper = mount(EventViewingWindowForecast, {
      props: {
        event: { id: 12 },
        userLocation: null,
      },
    })

    await flush()

    expect(apiGetMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Predpoved zobrazime po ulozeni polohy.')

    const states = wrapper.emitted('state') || []
    expect(states.at(-1)?.[0]).toMatchObject({
      missingLocation: true,
      viewingWindow: null,
      summary: null,
    })
  })
})
