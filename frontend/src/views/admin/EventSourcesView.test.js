import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import EventSourcesView from './EventSourcesView.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'

const getEventSourcesMock = vi.fn()
const getCrawlRunsMock = vi.fn()
const getEventTranslationHealthMock = vi.fn()
const runEventSourceCrawlMock = vi.fn()
const purgeEventSourcesMock = vi.fn()
const updateEventSourceMock = vi.fn()
const toastSuccessMock = vi.fn()
const toastWarnMock = vi.fn()

vi.mock('@/services/api/admin/eventSources', () => ({
  getEventSources: (...args) => getEventSourcesMock(...args),
  getCrawlRuns: (...args) => getCrawlRunsMock(...args),
  getEventTranslationHealth: (...args) => getEventTranslationHealthMock(...args),
  runEventSourceCrawl: (...args) => runEventSourceCrawlMock(...args),
  purgeEventSources: (...args) => purgeEventSourcesMock(...args),
  updateEventSource: (...args) => updateEventSourceMock(...args),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: (...args) => toastSuccessMock(...args),
    warn: (...args) => toastWarnMock(...args),
  }),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/event-sources', component: EventSourcesView },
      {
        path: '/admin/event-candidates',
        name: 'admin.event-candidates',
        component: { template: '<div>candidates</div>' },
      },
      {
        path: '/admin/candidates/:id',
        name: 'admin.candidate.detail',
        component: { template: '<div>candidate-detail</div>' },
      },
    ],
  })
}

describe('EventSourcesView', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    vi.clearAllMocks()

    getEventSourcesMock.mockResolvedValue({
      data: {
        data: [
          {
            id: 1,
            key: 'imo',
            name: 'IMO',
            is_enabled: true,
            manual_run_supported: true,
          },
          {
            id: 2,
            key: 'nasa',
            name: 'NASA',
            is_enabled: true,
            manual_run_supported: false,
          },
        ],
      },
    })

    getCrawlRunsMock.mockResolvedValue({
      data: {
        data: [
          {
            id: 11,
            source_name: 'imo',
            year: 2026,
            status: 'success',
            started_at: '2026-02-23T10:00:00Z',
            fetched_count: 5,
            created_candidates_count: 2,
            updated_candidates_count: 1,
            skipped_duplicates_count: 2,
            translation: {
              total: 3,
              done: 2,
              failed: 1,
              pending: 0,
              done_breakdown: {
                both: 2,
                title_only: 0,
                description_only: 0,
                without_text: 0,
              },
            },
          },
        ],
      },
    })

    runEventSourceCrawlMock.mockResolvedValue({
      data: { results: [] },
    })
    getEventTranslationHealthMock.mockResolvedValue({
      data: {
        counts_24h: { done: 5, failed: 1, pending: 2 },
        pending_candidates_total: 2,
        queue: { queued_event_translation_jobs: 1 },
      },
    })
    purgeEventSourcesMock.mockResolvedValue({
      data: { status: 'ok', deleted: { events: 1, event_candidates: 2, crawl_runs: 3 } },
    })
    updateEventSourceMock.mockResolvedValue({ data: { ok: true } })
  })

  async function mountView() {
    const router = makeRouter()
    await router.push('/admin/event-sources')
    await router.isReady()

    const wrapper = mount(
      {
        components: { EventSourcesView, ConfirmModal },
        template: '<EventSourcesView /><ConfirmModal />',
      },
      {
        attachTo: document.body,
        global: { plugins: [router] },
      },
    )

    await flush()
    await flush()

    return { wrapper, router }
  }

  function queryBody(selector) {
    return document.body.querySelector(selector)
  }

  it('renders run panel, sources table and recent runs', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Spustiť crawling')
    expect(wrapper.text()).toContain('Zdroje')
    expect(wrapper.text()).toContain('Posledn')
    expect(wrapper.text()).toContain('IMO')
    expect(wrapper.text()).toContain('Preklad')
    expect(wrapper.text()).toContain('2/3')
  })
  it('shows translation elapsed duration for recent runs when available', async () => {
    getCrawlRunsMock.mockResolvedValueOnce({
      data: {
        data: [
          {
            id: 22,
            source_name: 'imo',
            year: 2026,
            status: 'success',
            started_at: '2026-03-02T20:00:00Z',
            fetched_count: 6,
            created_candidates_count: 3,
            updated_candidates_count: 0,
            skipped_duplicates_count: 3,
            translation: {
              total: 6,
              done: 6,
              failed: 0,
              pending: 0,
              elapsed_ms: 90500,
              elapsed_human: '1m 30s',
              done_breakdown: {
                both: 6,
                title_only: 0,
                description_only: 0,
                without_text: 0,
              },
            },
          },
        ],
      },
    })

    const { wrapper } = await mountView()
    expect(wrapper.text()).toContain('1m 30s')
  })
  it('polls translation health and shows translation progress panel', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.find('[data-testid="translation-progress-panel"]').exists()).toBe(true)
    const elapsed = wrapper.find('[data-testid="translation-progress-elapsed"]')
    expect(elapsed.exists()).toBe(true)
    expect(elapsed.text()).toContain('Trvanie')
    expect(getEventTranslationHealthMock).toHaveBeenCalledTimes(1)

    await new Promise((resolve) => setTimeout(resolve, 3800))
    await flush()

    expect(getEventTranslationHealthMock.mock.calls.length).toBeGreaterThanOrEqual(2)
  })

  it('disables unsupported source run button with deferred tooltip', async () => {
    const { wrapper } = await mountView()

    const unsupportedRunButton = wrapper.find('[data-testid="run-source-nasa"]')

    expect(unsupportedRunButton.exists()).toBe(true)
    expect(unsupportedRunButton.attributes('disabled')).toBeDefined()
    expect(unsupportedRunButton.attributes('title')).toContain('Nepodporovan')
    expect(unsupportedRunButton.attributes('title')).toContain('MVP')
  })

  it('filters sources table by selected quick filter', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.find('[data-testid="source-row-imo"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="source-row-nasa"]').exists()).toBe(true)

    const sourceFilter = wrapper.find('[data-testid="source-filter"]')
    expect(sourceFilter.exists()).toBe(true)

    await sourceFilter.setValue('unsupported')
    await flush()

    expect(wrapper.find('[data-testid="source-row-imo"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="source-row-nasa"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Zobrazen')
  })

  it('filters sources table to selected rows only', async () => {
    const { wrapper } = await mountView()

    const supportedCheckbox = wrapper.find('[data-testid="source-select-imo"]')
    expect(supportedCheckbox.exists()).toBe(true)
    await supportedCheckbox.setValue(true)

    const sourceFilter = wrapper.find('[data-testid="source-filter"]')
    expect(sourceFilter.exists()).toBe(true)
    await sourceFilter.setValue('selected')
    await flush()

    expect(wrapper.find('[data-testid="source-row-imo"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="source-row-nasa"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Zobrazen')
  })

  it('clears selected sources from toolbar action', async () => {
    const { wrapper } = await mountView()

    const supportedCheckbox = wrapper.find('[data-testid="source-select-imo"]')
    expect(supportedCheckbox.exists()).toBe(true)
    await supportedCheckbox.setValue(true)

    expect(wrapper.find('[data-testid="run-selected-btn"]').attributes('disabled')).toBeUndefined()

    const clearButton = wrapper.find('[data-testid="source-clear-selection-btn"]')
    expect(clearButton.exists()).toBe(true)
    expect(clearButton.attributes('disabled')).toBeUndefined()

    await clearButton.trigger('click')
    await flush()

    expect(wrapper.find('[data-testid="run-selected-btn"]').attributes('disabled')).toBeDefined()
  })

  it('enables run selected only after selecting a supported source', async () => {
    const { wrapper } = await mountView()

    const runSelectedButton = wrapper.find('[data-testid="run-selected-btn"]')
    expect(runSelectedButton.exists()).toBe(true)
    expect(runSelectedButton.attributes('disabled')).toBeDefined()

    const supportedCheckbox = wrapper.find('[data-testid="source-select-imo"]')
    expect(supportedCheckbox.exists()).toBe(true)

    await supportedCheckbox.setValue(true)

    expect(wrapper.find('[data-testid="run-selected-btn"]').attributes('disabled')).toBeUndefined()

    await wrapper.find('[data-testid="run-selected-btn"]').trigger('click')
    await flush()

    expect(runEventSourceCrawlMock).toHaveBeenCalledWith({
      source_keys: ['imo'],
      year: 2026,
    })
  })

  it('triggers purge from shared confirm modal without typed token', async () => {
    const { wrapper } = await mountView()

    const purgeButton = wrapper.find('[data-testid="purge-crawled-btn"]')
    expect(purgeButton.exists()).toBe(true)

    await purgeButton.trigger('click')
    await flush()

    const confirmInput = queryBody('[data-testid="confirm-modal-input"]')
    expect(confirmInput).toBeNull()

    const confirmButton = queryBody('[data-testid="confirm-modal-confirm"]')
    expect(confirmButton).not.toBeNull()
    expect(confirmButton.textContent).toContain('Vymaza')
    expect(confirmButton.disabled).toBe(false)
    confirmButton.click()
    await flush()

    expect(purgeEventSourcesMock).toHaveBeenCalledWith({
      source_keys: ['imo'],
      dry_run: true,
      confirm: 'delete_crawled_events',
    })
  })

  it('shows translating status when run translation is still pending', async () => {
    getCrawlRunsMock.mockResolvedValueOnce({
      data: {
        data: [
          {
            id: 19,
            source_name: 'imo',
            year: 2026,
            status: 'success',
            started_at: '2026-03-02T20:00:00Z',
            fetched_count: 6,
            created_candidates_count: 3,
            updated_candidates_count: 0,
            skipped_duplicates_count: 3,
            translation: {
              total: 6,
              done: 4,
              failed: 0,
              pending: 2,
              done_breakdown: {
                both: 4,
                title_only: 0,
                description_only: 0,
                without_text: 0,
              },
            },
          },
        ],
      },
    })

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Prekladaj')
  })
})

