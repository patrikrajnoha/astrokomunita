import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import IssPassWidget from './IssPassWidget.vue'

const getMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

async function wait(ms = 80) {
  await vi.advanceTimersByTimeAsync(ms)
}

describe('IssPassWidget bundle hydration', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-15T20:10:00+01:00'))
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders bundled ISS payload without an extra preview request', async () => {
    const wrapper = mount(IssPassWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        initialPayload: {
          iss_preview: {
            available: true,
            next_pass_at: '2026-03-15T21:18:00+01:00',
            duration_sec: 420,
            max_altitude_deg: 41.3,
            direction_start: 'W',
            direction_end: 'E',
            satellite: {
              source: 'celestrak_gp',
              name: 'ISS (ZARYA)',
            },
            tracker: {
              source: 'iss_tracker',
              lat: 12.34,
              lon: 56.78,
              sample_at: '2026-03-15T20:07:00+01:00',
            },
          },
        },
        bundlePending: false,
      },
      global: {
        stubs: {
          SkyIssTrackerMap: true,
        },
      },
    })

    await wait()

    expect(getMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Najblizsi prelet')
    expect(wrapper.text()).toContain('7 min')
    expect(wrapper.text()).toContain('orbita: CelesTrak GP')
  })
})
