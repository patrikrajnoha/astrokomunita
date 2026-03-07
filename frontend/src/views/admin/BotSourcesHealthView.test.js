import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BotSourcesHealthView from '@/views/admin/BotSourcesHealthView.vue'
import {
  clearBotSourceCooldown,
  getBotSources,
  resetBotSourceHealth,
  reviveBotSource,
  updateBotSource,
} from '@/services/api/admin/bots'

vi.mock('@/services/api/admin/bots', () => ({
  clearBotSourceCooldown: vi.fn(),
  getBotSources: vi.fn(),
  resetBotSourceHealth: vi.fn(),
  reviveBotSource: vi.fn(),
  updateBotSource: vi.fn(),
}))

function flush() {
  return Promise.resolve().then(() => nextTick())
}

describe('BotSourcesHealthView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getBotSources.mockResolvedValue({
      data: {
        data: [
          {
            id: 9,
            key: 'nasa_rss_breaking',
            name: 'NASA RSS',
            source_type: 'rss',
            status: 'dead',
            is_dead: true,
            is_enabled: true,
            consecutive_failures: 0,
            cooldown_until: '2026-03-06T12:00:00Z',
            last_success_at: null,
            last_error_at: null,
            avg_latency_ms: 142,
            metrics_24h: {
              success_rate: 0.75,
              failure_rate: 0.25,
            },
            url: 'https://example.test/rss.xml',
          },
          {
            id: 10,
            key: 'wiki_feed',
            name: 'Wikipedia Feed',
            source_type: 'wikipedia',
            status: 'fail',
            is_dead: false,
            is_enabled: true,
            consecutive_failures: 6,
            cooldown_until: '2026-03-06T14:00:00Z',
            last_success_at: null,
            last_error_at: null,
            avg_latency_ms: 320,
            metrics_24h: {
              success_rate: 0.2,
              failure_rate: 0.8,
            },
            url: 'https://example.test/wiki-feed',
          },
        ],
      },
    })
    clearBotSourceCooldown.mockResolvedValue({ data: { data: {} } })
    resetBotSourceHealth.mockResolvedValue({ data: { data: {} } })
    reviveBotSource.mockResolvedValue({ data: { data: {} } })
    updateBotSource.mockResolvedValue({ data: { data: {} } })
  })

  it('renders source rows and toggles enabled state', async () => {
    const wrapper = mount(BotSourcesHealthView, {
      global: {
        stubs: {
          AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
        },
      },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('NASA RSS')
    expect(wrapper.text()).toContain('nasa_rss_breaking')
    expect(wrapper.text()).toContain('DEAD')
    expect(wrapper.text()).toContain('FAIL')
    expect(wrapper.text()).toContain('Cooldown')
    expect(wrapper.text()).toContain('S 75.0%')
    expect(wrapper.text()).toContain('F 25.0%')

    const toggleButton = wrapper.find('.toggleBtn')
    await toggleButton.trigger('click')
    await flush()

    expect(updateBotSource).toHaveBeenCalledWith(9, { is_enabled: false })
  })

  it('shows admin recovery actions and calls reset/revive endpoints', async () => {
    const wrapper = mount(BotSourcesHealthView, {
      global: {
        stubs: {
          AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
        },
      },
    })

    await flush()
    await flush()

    const findRowActionButton = (label) => {
      const firstRow = wrapper.findAll('tbody tr')[0]
      return firstRow?.findAll('button').find((btn) => btn.text() === label)
    }
    const resetBtn = findRowActionButton('Reset zdravia')
    const reviveBtn = findRowActionButton('Obnovit')
    const clearBtn = findRowActionButton('Vycistit cooldown')

    if (!resetBtn || !reviveBtn || !clearBtn) {
      throw new Error('Expected reset/revive/clear action buttons to be rendered.')
    }

    await resetBtn.trigger('click')
    await flush()
    expect(resetBotSourceHealth).toHaveBeenCalledWith(9)

    await clearBtn.trigger('click')
    await flush()
    expect(clearBotSourceCooldown).toHaveBeenCalledWith(9)

    const reviveBtnAfterRefresh = findRowActionButton('Obnovit')
    if (!reviveBtnAfterRefresh) {
      throw new Error('Expected revive action button to be rendered after refresh.')
    }

    await reviveBtnAfterRefresh.trigger('click')
    await flush()
    expect(reviveBotSource).toHaveBeenCalledWith(9)
  })
})
