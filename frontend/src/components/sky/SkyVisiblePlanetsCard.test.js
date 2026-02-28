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

  it('renders quality badges and low-horizon warning', async () => {
    getMock.mockResolvedValue({
      data: {
        planets: [
          {
            name: 'Jupiter',
            direction: 'S',
            altitude_deg: 64.7,
            azimuth_deg: 181.3,
            best_time_window: '18:10-03:00',
            quality: 'excellent',
          },
          {
            name: 'Saturn',
            direction: 'W',
            altitude_deg: 12.3,
            azimuth_deg: 253.9,
            best_time_window: '18:10-18:20',
            quality: 'low',
            magnitude: 1.1,
          },
        ],
      },
    })

    const wrapper = mount(SkyVisiblePlanetsCard, {
      props: { lat: 48.1486, lon: 17.1077, tz: 'Europe/Bratislava' },
    })

    await flush()

    expect(wrapper.text()).toContain('Vyborne')
    expect(wrapper.text()).toContain('Nizko nad horizontom')
    expect(wrapper.text()).toContain('Planeta je nizko nad horizontom, viditelnost moze byt obmedzena.')
    expect(wrapper.text()).toContain('Magnituda: 1.1')
  })
})
