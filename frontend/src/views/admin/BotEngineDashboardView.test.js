import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import BotEngineDashboardView from '@/views/admin/BotEngineDashboardView.vue'
import {
  getBotPostRetentionSettings,
} from '@/services/api/admin/bots'

vi.mock('@/services/api/admin/bots', () => ({
  getBotPostRetentionSettings: vi.fn(),
  updateBotPostRetentionSettings: vi.fn(),
  deleteAllBotPosts: vi.fn(),
  runBotPostRetentionCleanup: vi.fn(),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: vi.fn(async () => true),
  }),
}))

function flush() {
  return Promise.resolve().then(() => nextTick())
}

function makeRouter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/bots', name: 'admin.bots', component: BotEngineDashboardView },
      { path: '/admin/bots/activity', name: 'admin.bots.activity', component: { template: '<div>activity</div>' } },
      { path: '/admin/bots/engine', name: 'admin.bots.engine', component: { template: '<div>engine</div>' } },
    ],
  })

  return router.push('/admin/bots').then(() => router.isReady()).then(() => router)
}

function normalizeText(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
}

const overviewPayload = {
  window_hours: 24,
  generated_at: '2026-03-06T10:00:00Z',
  overall: {
    active_sources: 7,
    failing_sources: 2,
    dead_sources: 1,
    cooldown_skips_24h: 4,
  },
  bots: [
    {
      id: 7,
      name: 'Kozmo',
      username: 'kozmobot',
      role: 'bot',
      is_active: true,
      avatar_path: 'bots/kozmobot/default.png',
      bot_identity: 'kozmo',
      last_activity_at: '2026-03-06T10:00:00Z',
      posts_24h: 5,
      duplicates_24h: 2,
      errors_24h: 1,
    },
  ],
}

describe('BotEngineDashboardView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    getBotPostRetentionSettings.mockResolvedValue({
      data: {
        data: {
          enabled: true,
          auto_delete_after_hours: 48,
          allowed_hours: [24, 48, 72],
          scheduled_frequency: 'hourly',
        },
      },
    })
  })

  it('renders bot overview cards and loads retention settings', async () => {
    const router = await makeRouter()
    const wrapper = mount(BotEngineDashboardView, {
      props: {
        overview: overviewPayload,
      },
      global: {
        plugins: [router],
        stubs: {
          AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
          RouterLink: { template: '<a><slot /></a>' },
          UserAvatar: { template: '<span class="avatar-stub" />' },
          BaseModal: {
            props: ['open'],
            template: '<div v-if="open" class="base-modal-stub"><slot name="description" /><slot /></div>',
          },
          AdminUserDetailView: { template: '<div data-testid="bot-detail-view-stub">detail-view</div>' },
        },
      },
    })

    await flush()
    await flush()

    const text = normalizeText(wrapper.text())

    expect(getBotPostRetentionSettings).toHaveBeenCalledTimes(1)
    expect(text).toContain('bot ucty')
    expect(wrapper.text()).toContain('Metriky bot pipeline')
    expect(wrapper.text()).toContain('kozmobot')
    expect(wrapper.text()).toContain('Kozmo')
    expect(text).toContain('pokrocile nastavenia')
  })

  it('opens bot account detail popup from bot card action', async () => {
    const router = await makeRouter()
    const wrapper = mount(BotEngineDashboardView, {
      props: {
        overview: overviewPayload,
      },
      global: {
        plugins: [router],
        stubs: {
          AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
          RouterLink: { template: '<a><slot /></a>' },
          UserAvatar: { template: '<span class="avatar-stub" />' },
          BaseModal: {
            props: ['open'],
            template: '<div v-if="open" class="base-modal-stub"><slot name="description" /><slot /></div>',
          },
          AdminUserDetailView: { template: '<div data-testid="bot-detail-view-stub">detail-view</div>' },
        },
      },
    })

    await flush()
    await flush()

    const detailButton = wrapper.findAll('button')
      .find((button) => normalizeText(button.text()).includes('detail'))
    expect(detailButton).toBeTruthy()
    await detailButton.trigger('click')
    await flush()
    await flush()

    expect(wrapper.find('[data-testid="bot-detail-view-stub"]').exists()).toBe(true)
  })

  it('updates bot account card after detail view emits user-updated', async () => {
    const router = await makeRouter()
    const wrapper = mount(BotEngineDashboardView, {
      props: {
        overview: overviewPayload,
      },
      global: {
        plugins: [router],
        stubs: {
          AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
          RouterLink: { template: '<a><slot /></a>' },
          UserAvatar: { template: '<span class="avatar-stub" />' },
          BaseModal: {
            props: ['open'],
            template: '<div v-if="open" class="base-modal-stub"><slot name="description" /><slot /></div>',
          },
          AdminUserDetailView: {
            template: `
              <button
                data-testid="emit-user-updated"
                @click="$emit('user-updated', { id: 7, name: 'Kozmo Prime', avatar_url: '/api/media/file/avatars/7/new.png' })"
              >
                emit
              </button>
            `,
          },
        },
      },
    })

    await flush()
    await flush()

    const detailButton = wrapper.findAll('button')
      .find((button) => normalizeText(button.text()).includes('detail'))
    expect(detailButton).toBeTruthy()
    await detailButton.trigger('click')
    await flush()

    await wrapper.get('[data-testid="emit-user-updated"]').trigger('click')
    await flush()

    expect(wrapper.text()).toContain('Kozmo Prime')
  })
})
