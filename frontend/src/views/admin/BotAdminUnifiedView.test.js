import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { nextTick } from 'vue'
import BotAdminUnifiedView from '@/views/admin/BotAdminUnifiedView.vue'
import { getBotOverview, getBotTranslationHealth } from '@/services/api/admin/bots'

vi.mock('@/services/api/admin/bots', () => ({
  getBotOverview: vi.fn(),
  getBotTranslationHealth: vi.fn(),
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

function makeRouter(initialPath = '/admin/bots') {
  const routes = [
    { path: '/admin/bots', name: 'admin.bots', component: BotAdminUnifiedView },
    { path: '/admin/bots/sources', name: 'admin.bots.sources', component: BotAdminUnifiedView },
    { path: '/admin/bots/schedules', name: 'admin.bots.schedules', component: BotAdminUnifiedView },
    { path: '/admin/bots/activity', name: 'admin.bots.activity', component: BotAdminUnifiedView },
  ]

  const router = createRouter({
    history: createMemoryHistory(),
    routes,
  })

  return router.push(initialPath).then(() => router.isReady()).then(() => router)
}

describe('BotAdminUnifiedView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getBotOverview.mockResolvedValue({
      data: {
        generated_at: '2026-03-06T10:00:00Z',
        overall: {
          active_sources: 7,
          failing_sources: 1,
          dead_sources: 0,
          cooldown_skips_24h: 3,
        },
        bots: [{ id: 1 }, { id: 2 }],
      },
    })

    getBotTranslationHealth.mockResolvedValue({
      data: {
        provider: 'libretranslate',
        fallback_provider: 'ollama',
        degraded: false,
        result: {
          ok: true,
          error_type: null,
        },
        provider_probes: {
          libretranslate: {
            ok: true,
            error_type: null,
          },
          ollama: {
            ok: true,
            error_type: null,
          },
        },
      },
    })
  })

  it('renders schedules tab panel when route points to schedules path', async () => {
    const router = await makeRouter('/admin/bots/schedules')
    const wrapper = mount(BotAdminUnifiedView, {
      global: {
        plugins: [router],
        stubs: {
          AdminPageShell: { template: '<section><slot name="right-actions" /><slot /></section>' },
          BotEngineDashboardView: { template: '<div data-testid="dashboard-panel" />' },
          BotSourcesHealthView: { template: '<div data-testid="sources-panel" />' },
          BotSchedulesView: { template: '<div data-testid="schedules-panel" />' },
          BotActivityView: { template: '<div data-testid="logs-panel" />' },
        },
      },
    })

    await flush()
    await flush()

    expect(normalizeText(wrapper.text())).toContain('plany')
    expect(wrapper.find('[data-testid="schedules-panel"]').exists()).toBe(true)
    expect(getBotOverview).toHaveBeenCalledTimes(1)
    expect(getBotTranslationHealth).toHaveBeenCalledTimes(1)
  })

  it('shows summary metrics and warning badge on dashboard', async () => {
    const router = await makeRouter('/admin/bots')
    const wrapper = mount(BotAdminUnifiedView, {
      global: {
        plugins: [router],
        stubs: {
          AdminPageShell: { template: '<section><slot name="right-actions" /><slot /></section>' },
          BotEngineDashboardView: { template: '<div data-testid="dashboard-panel" />' },
          BotSourcesHealthView: { template: '<div data-testid="sources-panel" />' },
          BotSchedulesView: { template: '<div data-testid="schedules-panel" />' },
          BotActivityView: { template: '<div data-testid="logs-panel" />' },
        },
      },
    })

    await flush()
    await flush()

    const text = normalizeText(wrapper.text())

    expect(wrapper.text()).toContain('Upozornenie')
    expect(wrapper.text()).toContain('7')
    expect(wrapper.text()).toContain('3')
    expect(wrapper.text()).toContain('2')
    expect(text).toContain('preklady')
    expect(text).toContain('libretranslate: aktivny')
    expect(text).toContain('ollama: aktivny')
    expect(wrapper.find('[data-testid="dashboard-panel"]').exists()).toBe(true)
  })
})
