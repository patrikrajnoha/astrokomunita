import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
import BotEngineView from '@/views/admin/BotEngineView.vue'

const toastErrorMock = vi.fn()
const toastSuccessMock = vi.fn()
const toastInfoMock = vi.fn()

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
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
  }),
  runItemsPage: ref({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
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
  testingTranslation: false,
  fetchSources: vi.fn().mockResolvedValue([]),
  fetchRuns: vi.fn().mockResolvedValue([]),
  runSource: vi.fn(),
  publishItem: vi.fn(),
  publishRun: vi.fn(),
  retryTranslation: vi.fn(),
  backfillTranslation: vi.fn(),
  testTranslation: vi.fn(),
  deleteItemPost: vi.fn(),
  fetchItemsForRun: vi.fn().mockResolvedValue([]),
  clearRunItems: vi.fn(),
  isSourceRunning: vi.fn(() => false),
  isItemPublishing: vi.fn(() => false),
  isItemDeleting: vi.fn(() => false),
  isRunPublishing: vi.fn(() => false),
  isTranslationRetrying: vi.fn(() => false),
  isTranslationBackfilling: vi.fn(() => false),
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
    info: (...args) => toastInfoMock(...args),
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
        AdminPageShell: { template: '<div><slot /></div>' },
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
    store.runsPage.value = {
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
        {
          id: 12,
          source_key: 'nasa_apod_daily',
          started_at: '2026-02-23T10:05:00Z',
          finished_at: '2026-02-23T10:06:00Z',
          status: 'skipped',
          failure_reason: 'rate_limited',
          ui_message: 'NASA APOD API rate limit (HTTP 429). Nastav NASA_API_KEY alebo skusit neskor.',
          stats: { published_count: 0 },
          meta: {
            mode: 'auto',
            failure_reason: 'rate_limited',
            ui_message: 'NASA APOD API rate limit (HTTP 429). Nastav NASA_API_KEY alebo skusit neskor.',
          },
          error_text: 'NASA APOD API rate limit (HTTP 429). Nastav NASA_API_KEY alebo skusit neskor.',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
    }
    store.runItemsPage.value = {
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
    }
    store.fetchSources.mockResolvedValue([])
    store.fetchRuns.mockResolvedValue([])
    store.fetchItemsForRun.mockResolvedValue([])
    store.runSource.mockResolvedValue({
      status: 'success',
      stats: {},
    })
    store.publishItem.mockResolvedValue({ item: { id: 91, publish_status: 'published' } })
    store.publishRun.mockResolvedValue({
      run_id: 11,
      published_count: 1,
      skipped_count: 0,
      failed_count: 0,
    })
    store.retryTranslation.mockResolvedValue({
      source_key: 'nasa_rss_breaking',
      retried_count: 1,
      done_count: 1,
      skipped_count: 0,
      failed_count: 0,
    })
    store.backfillTranslation.mockResolvedValue({
      source_key: 'nasa_rss_breaking',
      scanned: 1,
      updated_posts: 1,
      skipped: 0,
      failed: 0,
    })
    store.testTranslation.mockResolvedValue({
      ok: true,
      provider: 'libretranslate',
      latency_ms: 40,
      translated_text: 'SK test translation',
      mode: 'lt_only',
      provider_chain: ['libretranslate'],
      quality_flags: [],
    })
    store.deleteItemPost.mockResolvedValue({
      message: 'Published post deleted.',
      item: { id: 91, post_id: null, publish_status: 'pending' },
    })
  })

  it('applies preset bot identity filter for dedicated bot menu routes', async () => {
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

  it('quick run executes enabled sources for selected bot identity', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    const quickRunKozmoButton = wrapper.find('[data-testid="quick-run-kozmo"]')
    await quickRunKozmoButton.trigger('click')
    await flush()

    expect(store.runSource).toHaveBeenCalledWith('nasa_rss_breaking', { mode: 'auto', force_manual_override: true })
    expect(store.runSource).not.toHaveBeenCalledWith('nasa_apod_daily', { mode: 'auto', force_manual_override: true })
  })

  it('shows retry_after detail when manual run is throttled', async () => {
    store.runSource.mockRejectedValueOnce({
      response: {
        status: 429,
        data: {
          message: 'Manual run is temporarily throttled.',
          retry_after: 120,
        },
      },
    })

    const wrapper = mount(BotEngineView, {
      global: {
        stubs: {
          AdminPageShell: {
            template: '<div><slot /></div>',
          },
          routerLink: true,
          teleport: true,
        },
      },
    })

    await flush()
    await flush()

    const runButton = wrapper.findAll('button').find((node) => node.text().includes('Run now'))
    expect(runButton).toBeTruthy()

    await runButton.trigger('click')
    await flush()

    expect(toastErrorMock).toHaveBeenCalledTimes(1)
    expect(String(toastErrorMock.mock.calls[0][0])).toContain('Retry in 120s')
  })

  it('shows structured rate-limited message instead of generic timeout text', async () => {
    store.runSource.mockRejectedValueOnce({
      response: {
        status: 429,
        data: {
          failure_reason: 'rate_limited',
          ui_message: 'NASA APOD API rate limit (HTTP 429). Nastav NASA_API_KEY alebo skusit neskor.',
        },
      },
    })

    const wrapper = mountView()
    await flush()
    await flush()

    const runButton = wrapper.findAll('button').find((node) => node.text().includes('Run now'))
    await runButton.trigger('click')
    await flush()

    expect(toastErrorMock).toHaveBeenCalledTimes(1)
    expect(String(toastErrorMock.mock.calls[0][0])).toContain('NASA APOD API rate limit')
    expect(String(toastErrorMock.mock.calls[0][0])).not.toContain('Server neodpoveda')
  })

  it('shows dedicated long-running run message on Axios timeout', async () => {
    store.runSource.mockRejectedValueOnce({
      code: 'ECONNABORTED',
      message: 'timeout of 60000ms exceeded',
    })

    const wrapper = mountView()
    await flush()
    await flush()

    const runButton = wrapper.findAll('button').find((node) => node.text().includes('Run now'))
    await runButton.trigger('click')
    await flush()

    expect(toastErrorMock).toHaveBeenCalledTimes(1)
    expect(String(toastErrorMock.mock.calls[0][0])).toContain('Run trva dlhsie')
  })

  it('shows friendly translation provider message for structured provider_unavailable error', async () => {
    store.runSource.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          failure_reason: 'provider_unavailable',
          message: 'Translation provider unavailable.',
        },
      },
    })

    const wrapper = mountView()
    await flush()
    await flush()

    const runButton = wrapper.findAll('button').find((node) => node.text().includes('Run now'))
    await runButton.trigger('click')
    await flush()

    expect(toastErrorMock).toHaveBeenCalledTimes(1)
    expect(String(toastErrorMock.mock.calls[0][0])).toContain('Prekladovy provider je nedostupny')
  })

  it('shows friendly translation timeout message for structured translation_timeout error', async () => {
    store.runSource.mockRejectedValueOnce({
      response: {
        status: 504,
        data: {
          failure_reason: 'translation_timeout',
          message: 'Translation timed out.',
        },
      },
    })

    const wrapper = mountView()
    await flush()
    await flush()

    const runButton = wrapper.findAll('button').find((node) => node.text().includes('Run now'))
    await runButton.trigger('click')
    await flush()

    expect(toastErrorMock).toHaveBeenCalledTimes(1)
    expect(String(toastErrorMock.mock.calls[0][0])).toContain('Preklad timeoutol')
  })

  it('renders DRY/AUTO badges with publish limit text from run meta', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    const modeBadges = wrapper.findAll('[data-testid="run-mode-badge"]')
    expect(modeBadges).toHaveLength(2)
    expect(modeBadges[0].text()).toBe('DRY')
    expect(modeBadges[1].text()).toBe('AUTO')

    const modeLimits = wrapper.findAll('[data-testid="run-mode-limit"]')
    expect(modeLimits).toHaveLength(1)
    expect(modeLimits[0].text()).toContain('limit: 3')
  })

  it('renders Rate limited badge for structured run reason', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    const statusBadges = wrapper.findAll('[data-testid="run-status-badge"]')
    expect(statusBadges.length).toBeGreaterThanOrEqual(2)
    expect(statusBadges[1].text()).toContain('Rate limited')
  })

  it('shows APOD env hint in sources table', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    expect(wrapper.text()).toContain('Set NASA_API_KEY in .env to enable APOD.')
  })

  it('asks for confirm before publishing wiki onthisday item', async () => {
    store.runItemsPage.value = {
      data: [
        {
          ...store.runItemsPage.value.data[0],
          id: 92,
          stable_key: 'stable-92',
          source_key: 'wiki_onthisday_astronomy',
        },
      ],
      meta: { ...store.runItemsPage.value.meta },
    }

    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.findAll('button').find((node) => node.text().includes('Detail')).trigger('click')
    await flush()

    const publishButton = wrapper.findAll('button').find((node) => node.text() === 'Publish')
    await publishButton.trigger('click')
    await flush()

    expect(confirmSpy).toHaveBeenCalledWith('Publikovať do AstroFeed?')
    expect(store.publishItem).toHaveBeenCalledWith(92, { force: false })

    confirmSpy.mockRestore()
  })

  it('uses default publish all limit 3 in run detail', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    const detailButtons = wrapper.findAll('button').filter((node) => node.text().includes('Detail'))
    await detailButtons[1].trigger('click')
    await flush()

    const limitInput = wrapper.find('[data-testid="publish-all-limit"]')
    expect(limitInput.element.value).toBe('3')
  })

  it('publish all action calls run publish endpoint with selected limit', async () => {
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.findAll('button').find((node) => node.text().includes('Detail')).trigger('click')
    await flush()

    const limitInput = wrapper.find('[data-testid="publish-all-limit"]')
    await limitInput.setValue('3')

    const publishAllButton = wrapper.findAll('button').find((node) => node.text() === 'Publish all')
    await publishAllButton.trigger('click')
    await flush()

    expect(store.publishRun).toHaveBeenCalledWith(11, { publish_limit: 3 })
  })

  it('deletes published bot post from item row after confirm', async () => {
    store.runItemsPage.value = {
      data: [
        {
          ...store.runItemsPage.value.data[0],
          id: 93,
          post_id: 1234,
          publish_status: 'published',
        },
      ],
      meta: { ...store.runItemsPage.value.meta },
    }

    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.findAll('button').find((node) => node.text().includes('Detail')).trigger('click')
    await flush()

    const deleteButton = wrapper.findAll('button').find((node) => node.text() === 'Delete post')
    await deleteButton.trigger('click')
    await flush()

    expect(confirmSpy).toHaveBeenCalledWith('Delete published bot post from feed?')
    expect(store.deleteItemPost).toHaveBeenCalledWith(93)

    confirmSpy.mockRestore()
  })

  it('does not delete published post when confirm is cancelled', async () => {
    store.runItemsPage.value = {
      data: [
        {
          ...store.runItemsPage.value.data[0],
          id: 94,
          post_id: 4321,
          publish_status: 'published',
        },
      ],
      meta: { ...store.runItemsPage.value.meta },
    }

    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false)
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.findAll('button').find((node) => node.text().includes('Detail')).trigger('click')
    await flush()

    const deleteButton = wrapper.findAll('button').find((node) => node.text() === 'Delete post')
    await deleteButton.trigger('click')
    await flush()

    expect(store.deleteItemPost).not.toHaveBeenCalledWith(94)

    confirmSpy.mockRestore()
  })

  it('runs translation backfill after confirm from run detail', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mountView()
    await flush()
    await flush()

    await wrapper.findAll('button').find((node) => node.text().includes('Detail')).trigger('click')
    await flush()

    const backfillButton = wrapper.findAll('button').find((node) => node.text() === 'Backfill translation (update posts)')
    await backfillButton.trigger('click')
    await flush()

    expect(confirmSpy).toHaveBeenCalledWith('Backfill translation for already published posts?')
    expect(store.backfillTranslation).toHaveBeenCalledWith('nasa_rss_breaking', { limit: 10, run_id: 11 })

    confirmSpy.mockRestore()
  })

  it('shows translation mode, provider chain and quality flags in test summary', async () => {
    store.testTranslation.mockResolvedValueOnce({
      ok: true,
      provider: 'ollama_postedit',
      latency_ms: 55,
      translated_text: 'Prirodzeny slovensky text.',
      mode: 'lt_ollama_postedit',
      provider_chain: ['libretranslate', 'ollama_postedit'],
      quality_flags: ['too_short'],
    })

    const wrapper = mountView()
    await flush()
    await flush()

    const button = wrapper.findAll('button').find((node) => node.text() === 'Test translation')
    await button.trigger('click')
    await flush()

    const text = wrapper.text()
    expect(text).toContain('LT+Ollama post-edit')
    expect(text).toContain('libretranslate -> ollama_postedit')
    expect(text).toContain('too_short')
  })
})
