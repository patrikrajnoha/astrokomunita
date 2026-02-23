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
    status: '',
    date_from: '',
    date_to: '',
    per_page: 20,
  }),
  loadingSources: ref(false),
  loadingRuns: ref(false),
  loadingRunItems: ref(false),
  fetchSources: vi.fn().mockResolvedValue([]),
  fetchRuns: vi.fn().mockResolvedValue([]),
  runSource: vi.fn(),
  publishItem: vi.fn(),
  publishRun: vi.fn(),
  fetchItemsForRun: vi.fn().mockResolvedValue([]),
  clearRunItems: vi.fn(),
  isSourceRunning: vi.fn(() => false),
  isItemPublishing: vi.fn(() => false),
  isRunPublishing: vi.fn(() => false),
  resetFilters: vi.fn(() => ({
    sourceKey: '',
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

function mountView() {
  return mount(BotEngineView, {
    global: {
      stubs: {
        AdminPageShell: { template: '<div><slot /></div>' },
        routerLink: true,
        teleport: true,
      },
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
          status: 'success',
          stats: { published_count: 0 },
          meta: { mode: 'auto' },
          error_text: null,
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
})
