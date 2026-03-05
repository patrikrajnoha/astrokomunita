import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BotEngineDashboardView from '@/views/admin/BotEngineDashboardView.vue'
import { getBotOverview } from '@/services/api/admin/bots'

vi.mock('@/services/api/admin/bots', () => ({
  getBotOverview: vi.fn(),
}))

function flush() {
  return Promise.resolve().then(() => nextTick())
}

describe('BotEngineDashboardView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getBotOverview.mockResolvedValue({
      data: {
        overall: {
          posts_24h_total: 5,
          duplicates_24h: 2,
          failures_24h: 1,
        },
        bots: [
          {
            id: 7,
            username: 'kozmobot',
            role: 'bot',
            bot_identity: 'kozmo',
            last_activity_at: '2026-03-06T10:00:00Z',
            posts_24h: 5,
            duplicates_24h: 2,
            errors_24h: 1,
            rate_limit_state: {
              limited: false,
              remaining_attempts: 10,
              max_attempts: 30,
              retry_after_sec: 0,
            },
          },
        ],
      },
    })
  })

  it('renders summary cards and bot table from overview payload', async () => {
    const wrapper = mount(BotEngineDashboardView, {
      global: {
        stubs: {
          AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
          RouterLink: { template: '<a><slot /></a>' },
        },
      },
    })

    await flush()
    await flush()

    expect(getBotOverview).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('5')
    expect(wrapper.text()).toContain('2')
    expect(wrapper.text()).toContain('1')
    expect(wrapper.text()).toContain('kozmobot')
    expect(wrapper.text()).toContain('View activity')
  })
})

