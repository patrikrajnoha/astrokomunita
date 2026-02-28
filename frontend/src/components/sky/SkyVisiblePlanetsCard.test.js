import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SkyVisiblePlanetsCard from './SkyVisiblePlanetsCard.vue'

const getMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('SkyVisiblePlanetsCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders Planety 1.5 visibility labels from the shared util rules', async () => {
    getMock.mockResolvedValue({
      data: {
        sample_at: '2026-02-27T21:00:00+01:00',
        sun_altitude_deg: -18.4,
        planets: [
          {
            name: 'Jupiter',
            direction: 'S',
            altitude_deg: 64.7,
            azimuth_deg: 181.3,
            elongation_deg: 132.1,
            best_time_window: '18:10-03:00',
            quality: 'excellent',
          },
          {
            name: 'Saturn',
            direction: 'W',
            altitude_deg: 8.3,
            azimuth_deg: 253.9,
            elongation_deg: 48.0,
            best_time_window: '18:10-18:20',
            quality: 'low',
          },
          {
            name: 'Mercury',
            direction: 'W',
            altitude_deg: 13.2,
            azimuth_deg: 241.2,
            elongation_deg: 14.9,
            best_time_window: '18:10-18:40',
            quality: 'good',
          },
        ],
      },
    })

    const wrapper = mount(SkyVisiblePlanetsCard, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava' },
    })

    await flush()

    expect(wrapper.text()).toContain('Viditeľná')
    expect(wrapper.text()).toContain('Nízko nad obzorom')
    expect(wrapper.text()).toContain('Blízko Slnka')
    expect(wrapper.text()).toContain('Elong:')
  })
})
