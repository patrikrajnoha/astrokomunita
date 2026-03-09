import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
import BotEngineView from '@/views/admin/BotEngineView.vue'

const toastErrorMock = vi.fn()
const toastSuccessMock = vi.fn()
const confirmMock = vi.fn(async () => true)

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
  runsPage: ref({
    data: [
      {
        id: 11,
        source_key: 'nasa_rss_breaking',
        started_at: '2026-02-23T10:00:00Z',
        finished_at: '2026-02-23T10:01:00Z',
        status: 'success',
        stats: { published_count: 1 },
        meta: { mode: 'dry', publish_limit: 3 },
        error_text: null,
      },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
  }),
  runItemsPage: ref({
    data: [
      {
        id: 91,
        stable_key: 'stable-91',
        publish_status: 'pending',
        translation_status: 'done',
        post_id: null,
        used_translation: true,
        skip_reason: null,
        fetched_at: '2026-02-23T10:00:10Z',
        title: 'Preview title',
        content: 'Preview body',
        title_original: 'Original title',
        content_original: 'Original body',
        title_translated: 'Translated title',
        content_translated: 'Translated body',
        url: 'https://example.test/item-91',
        source_key: 'nasa_rss_breaking',
        published_manually: false,
      },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
  }),
  filters: ref({
    sourceKey: '',
    bot_identity: '',
    status: '',
    date_from: '',
    date_to: '',
    per_page: 20,
  }),
  loadingSources: ref(false),
  loadingRuns: ref(false),
  loadingRunItems: ref(false),
  translationHealth: ref({
    provider: 'libretranslate',
    simulate_outage_provider: 'none',
    degraded: false,
    result: { ok: true, error_type: null },
  }),
  savingTranslationOutage: ref(false),
  testingTranslation: false,
  fetchSources: vi.fn().mockResolvedValue([]),
  fetchRuns: vi.fn().mockResolvedValue([]),
  runSource: vi.fn().mockResolvedValue({ status: 'success', stats: {} }),
  publishItem: vi.fn(),
  publishRun: vi.fn().mockResolvedValue({
    run_id: 11,
    published_count: 1,
    skipped_count: 0,
    failed_count: 0,
  }),
  retryTranslation: vi.fn(),
  backfillTranslation: vi.fn().mockResolvedValue({
    source_key: 'nasa_rss_breaking',
    scanned: 1,
    updated_posts: 1,
    skipped: 0,
    failed: 0,
  }),
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
    simulate_outage_provider: 'none',
    degraded: false,
    result: { ok: true, error_type: null },
  }),
  setTranslationOutageProvider: vi.fn().mockResolvedValue({
    key: 'translation.simulate_outage_provider',
    old_value: 'none',
    new_value: 'ollama',
  }),
  deleteItemPost: vi.fn(),
  fetchItemsForRun: vi.fn().mockResolvedValue([]),
  clearRunItems: vi.fn(),
  isSourceRunning: vi.fn(() => false),
  isItemPublishing: vi.fn(() => false),
  isItemDeleting: vi.fn(() => false),
  isRunPublishing: vi.fn(() => false),
  isTranslationRetrying: vi.fn(() => false),
  isTranslationBackfilling: vi.fn(() => false),
  deletingAllPosts: false,
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

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: (...args) => confirmMock(...args),
  }),
}))

function flush() {
  return Promise.resolve().then(() => nextTick())
}

function mountView(options = {}) {
  return mount(BotEngineView, {
    ...options,
    global: {
      stubs: {
        AdminPageShell: { template: '<div><slot name="right-actions" /><slot /></div>' },
        RouterLink: true,
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
    confirmMock.mockResolvedValue(true)
  })

  it('applies preset bot identity filter for dedicated bot routes', async () => {
    mountView({
      props: {
        presetBotIdentity: 'kozmo',
        presetLabel: 'Kozmo',
      },
    })

    await flush()
    await flush()

    expect(store.fetchRuns).toHaveBeenCalled()
    expect(store.fetchRuns.mock.calls[0][0]).toMatchObject({ bot_identity: 'kozmo' })
  })

  it('quick run executes enabled sources for selected bot', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.get('[data-testid="quick-run-kozmo"]').trigger('click')
    await flush()

    expect(store.runSource).toHaveBeenCalledWith('nasa_rss_breaking', {
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
    expect(message).not.toContain('OK 0')
    expect(message).not.toContain('ciastocne 0')
  })

  it('quick run all reports skipped sources separately from errors', async () => {
    store.runSource.mockImplementation(async (sourceKey) => {
      if (sourceKey === 'nasa_rss_breaking') {
        return { status: 'success', stats: {} }
      }

      return {
        status: 'skipped',
        ui_message: 'Source "nasa_apod_daily" je v cooldowne do 2026-03-09T13:00:00+00:00.',
        stats: {},
      }
    })

    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.get('[data-testid="quick-run-all"]').trigger('click')
    await flush()
    await flush()

    expect(toastSuccessMock).toHaveBeenCalled()
    const message = String(toastSuccessMock.mock.calls.at(-1)?.[0] || '')
    expect(message).toContain('Spustenie dokoncene s preskocenymi zdrojmi.')
    expect(message).toContain('KozmoBot: 1 zdroj (OK 1).')
    expect(message).toContain('StellarBot: 1 zdroj (preskočené 1).')
    expect(message).not.toContain('chyby 1')
  })

  it('uses single primary CTA for translation test and shows advanced output', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    const testButton = wrapper
      .findAll('button')
      .find((node) => node.text().toLowerCase().includes('otest'))
    expect(testButton).toBeTruthy()
    await testButton.trigger('click')
    await flush()

    expect(store.testTranslation).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('ollama_postedit')
    expect(wrapper.text()).toContain('too_short')
  })

  it('runs translation backfill from run detail advanced actions', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper
      .findAll('button')
      .find((node) => node.text().includes('Detail'))
      .trigger('click')
    await flush()

    await wrapper.get('[data-testid="backfill-translation-btn"]').trigger('click')
    await flush()

    expect(store.backfillTranslation).toHaveBeenCalledWith('nasa_rss_breaking', {
      limit: 10,
      run_id: 11,
    })
    expect(confirmMock).toHaveBeenCalled()
  })
})
