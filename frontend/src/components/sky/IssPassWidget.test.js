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

  it('renders Slovak direction headline, distance, context, visibility hint and pass time without extra API request', async () => {
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

    // Slovak locative direction headline (Bratislava → 12.34°N 56.78°E ≈ 124° = SE → "na juhovýchode")
    expect(wrapper.text()).toContain('ISS na juhovýchode')

    // Distance — distance > 500km so qualitative context label is shown
    expect(wrapper.text()).toContain('Ďaleko')

    // Duration label
    expect(wrapper.text()).toContain('7 min')

    // Pass row with correct Slovak diacritics
    expect(wrapper.text()).toContain('Viditeľný prelet')

    // Pass time in Europe/Bratislava timezone
    expect(wrapper.text()).toContain('21:18')

    // Visibility hint (available: true) — refers to the upcoming pass, not current position
    expect(wrapper.text()).toContain('Prelet viditeľný voľným okom')

    // Source attribution removed
    expect(wrapper.text()).not.toContain('Zdroj:')
    expect(wrapper.text()).not.toContain('orbita')
    expect(wrapper.text()).not.toContain('CelesTrak')
  })

  it('shows "blízko tvojej polohy" headline and skips context label when ISS is within 500 km', async () => {
    const wrapper = mount(IssPassWidget, {
      props: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        initialPayload: {
          iss_preview: {
            available: false,
            tracker: {
              lat: 48.5,
              lon: 17.5,
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

    expect(wrapper.text()).toContain('ISS blízko tvojej polohy')
    expect(wrapper.text()).not.toContain('Ďaleko')
    expect(wrapper.text()).not.toContain('Blízko')
    expect(wrapper.text()).toContain('Dnes bez viditeľného preletu')
  })
})
