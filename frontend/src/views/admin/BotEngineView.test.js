import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
import BotEngineView from '@/views/admin/BotEngineView.vue'

const toastErrorMock = vi.fn()
const toastSuccessMock = vi.fn()

const store = {
  sources: ref([
    {
      id: 1,
      key: 'nasa_rss_breaking',
      bot_identity: 'kozmo',
      source_type: 'rss',
      is_enabled: true,
      last_run_at: null,
    },
    {
      id: 2,
      key: 'nasa_apod_daily',
      bot_identity: 'stela',
      source_type: 'api',
      is_enabled: true,
      last_run_at: null,
    },
  ]),
  runsPage: ref({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } }),
  runItemsPage: ref({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } }),
  filters: ref({
    sourceKey: '',
    bot_identity: '',
    status: '',
    date_from: '',
    date_to: '',
    per_page: 20,
  }),
  translationHealth: ref({
    provider: 'libretranslate',
    fallback_provider: 'ollama',
    simulate_outage_provider: 'none',
    degraded: false,
    result: { ok: true, error_type: null },
  }),
  testingTranslation: false,
  fetchSources: vi.fn().mockResolvedValue([]),
  fetchRuns: vi.fn().mockResolvedValue([]),
  runSource: vi.fn().mockResolvedValue({ status: 'success', stats: {} }),
  testTranslation: vi.fn().mockResolvedValue({
    ok: true,
    provider: 'ollama_postedit',
    latency_ms: 55,
    translated_text: 'Prirodzeny slovensky text.',
    mode: 'lt_ollama_postedit',
    provider_chain: ['libretranslate', 'ollama_postedit'],
    quality_flags: ['too_short'],
  }),
  fetchTranslationHealth: vi.fn().mockResolvedValue({
    provider: 'libretranslate',
    fallback_provider: 'ollama',
    simulate_outage_provider: 'none',
    degraded: false,
    result: { ok: true, error_type: null },
  }),
  setTranslationOutageProvider: vi.fn().mockResolvedValue({
    key: 'translation.simulate_outage_provider',
    old_value: 'none',
    new_value: 'none',
  }),
  resetFilters: vi.fn(() => ({
    sourceKey: '',
    bot_identity: '',
    status: '',
    date_from: '',
    date_to: '',
    per_page: 20,
    page: 1,
  })),
}

vi.mock('pinia', () => ({
  storeToRefs: (input) => input,
}))

vi.mock('@/stores/botEngine', () => ({
  useBotEngineStore: () => store,
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: (...args) => toastSuccessMock(...args),
    error: (...args) => toastErrorMock(...args),
  }),
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

function mountView(options = {}) {
  return mount(BotEngineView, {
    ...options,
    global: {
      stubs: {
        AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
        RouterLink: { template: '<a><slot /></a>' },
        routerLink: true,
        teleport: true,
      },
      ...(options.global || {}),
    },
  })
}

describe('BotEngineView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders compact legacy service panel without bot identity pills', async () => {
    const wrapper = mountView()

    await flush()
    await flush()

    const text = normalizeText(wrapper.text())

    expect(text).toContain('run control')
    expect(text).toContain('ai test')
    expect(wrapper.findAll('[data-testid="quick-run-all"]').length).toBeGreaterThan(0)
    expect(text).not.toContain('kozmobot')
    expect(text).not.toContain('stellarbot')
    expect(text).not.toContain('behy')
  })

  it('quick run all executes enabled sources', async () => {
    const wrapper = mountView()

    await flush()
    await flush()

    await wrapper.get('[data-testid="quick-run-all"]').trigger('click')
    await flush()

    expect(store.runSource).toHaveBeenCalledWith('nasa_rss_breaking', {
      mode: 'auto',
      force_manual_override: true,
    })
    expect(store.runSource).toHaveBeenCalledWith('nasa_apod_daily', {
      mode: 'auto',
      force_manual_override: true,
    })
  })

  it('quick run all reports completion with errors and compact summary', async () => {
    store.runSource.mockImplementation(async (sourceKey) => {
      if (sourceKey === 'nasa_rss_breaking') {
        return { status: 'success', stats: {} }
      }

      throw {
        response: {
          data: {
            message: 'Stela run failed.',
          },
        },
      }
    })

    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.get('[data-testid="quick-run-all"]').trigger('click')
    await flush()
    await flush()

    expect(toastErrorMock).toHaveBeenCalled()
    const message = String(toastErrorMock.mock.calls.at(-1)?.[0] || '')
    expect(message).toContain('Spustenie dokoncene s chybami.')
    expect(message).toContain('KozmoBot: 1 zdroj (OK 1).')
    expect(message).toContain('StellarBot: 1 zdroj (chyby 1).')
  })

  it('runs AI translation test from compact action row', async () => {
    const wrapper = mountView()

    await flush()
    await flush()

    await wrapper.get('[data-testid="ai-test-run"]').trigger('click')
    await flush()

    expect(store.testTranslation).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Prirodzeny slovensky text.')
  })
})
