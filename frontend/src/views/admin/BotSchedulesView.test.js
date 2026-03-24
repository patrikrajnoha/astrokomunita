import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BotSchedulesView from '@/views/admin/BotSchedulesView.vue'
import {
  createBotSchedule,
  deleteBotSchedule,
  getBotOverview,
  getBotSchedules,
  getBotSources,
  updateBotSchedule,
} from '@/services/api/admin/bots'

vi.mock('@/services/api/admin/bots', () => ({
  getBotOverview: vi.fn(),
  getBotSources: vi.fn(),
  getBotSchedules: vi.fn(),
  createBotSchedule: vi.fn(),
  updateBotSchedule: vi.fn(),
  deleteBotSchedule: vi.fn(),
}))

function flush() {
  return Promise.resolve().then(() => nextTick())
}

function normalizeText(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
}

describe('BotSchedulesView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    getBotOverview.mockResolvedValue({
      data: {
        bots: [
          { id: 3, username: 'kozmobot', bot_identity: 'kozmo' },
        ],
      },
    })

    getBotSources.mockResolvedValue({
      data: {
        data: [
          { id: 12, key: 'nasa_rss_breaking', bot_identity: 'kozmo' },
        ],
      },
    })

    getBotSchedules.mockResolvedValue({
      data: {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 30,
        total: 0,
      },
    })

    createBotSchedule.mockResolvedValue({ data: { data: { id: 99 } } })
    updateBotSchedule.mockResolvedValue({ data: { data: {} } })
    deleteBotSchedule.mockResolvedValue({ data: { deleted: true } })
  })

  it('creates a schedule from form values', async () => {
    const wrapper = mount(BotSchedulesView, {
      global: {
        stubs: {
          AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
          RouterLink: { template: '<a><slot /></a>' },
          BaseModal: true,
        },
      },
    })

    await flush()
    await flush()

    const selects = wrapper.findAll('select')
    await selects[0].setValue('3')
    await selects[1].setValue('12')
    await wrapper.find('input[type="number"]').setValue('45')

    const createButton = wrapper
      .findAll('button')
      .find((node) => normalizeText(node.text()).includes('vytvorit'))
    await createButton.trigger('click')
    await flush()

    expect(createBotSchedule).toHaveBeenCalledWith({
      bot_user_id: 3,
      source_id: 12,
      interval_minutes: 45,
      jitter_seconds: 0,
      enabled: true,
    })
  })
})

