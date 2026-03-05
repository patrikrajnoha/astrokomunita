import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BotSourcesHealthView from '@/views/admin/BotSourcesHealthView.vue'
import { getBotSources, updateBotSource } from '@/services/api/admin/bots'

vi.mock('@/services/api/admin/bots', () => ({
  getBotSources: vi.fn(),
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
            status: 'ok',
            is_enabled: true,
            consecutive_failures: 0,
            last_success_at: null,
            last_error_at: null,
            avg_latency_ms: 142,
            url: 'https://example.test/rss.xml',
          },
        ],
      },
    })
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

    const toggleButton = wrapper.find('.toggleBtn')
    await toggleButton.trigger('click')
    await flush()

    expect(updateBotSource).toHaveBeenCalledWith(9, { is_enabled: false })
  })
})

