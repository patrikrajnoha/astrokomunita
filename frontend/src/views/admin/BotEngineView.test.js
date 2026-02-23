import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
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
  fetchItemsForRun: vi.fn().mockResolvedValue([]),
  clearRunItems: vi.fn(),
  isSourceRunning: vi.fn(() => false),
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
  }),
}))

function flush() {
  return Promise.resolve().then(() => nextTick())
}

describe('BotEngineView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    store.fetchSources.mockResolvedValue([])
    store.fetchRuns.mockResolvedValue([])
    store.runSource.mockResolvedValue({
      status: 'success',
      stats: {},
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
})
