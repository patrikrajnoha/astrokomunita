import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import EventSourcesView from './EventSourcesView.vue'

const getEventSourcesMock = vi.fn()
const getCrawlRunsMock = vi.fn()
const getEventTranslationHealthMock = vi.fn()
const runEventSourceCrawlMock = vi.fn()
const purgeEventSourcesMock = vi.fn()
const updateEventSourceMock = vi.fn()
const toastSuccessMock = vi.fn()

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
    ],
  })
}

describe('EventSourcesView', () => {
  beforeEach(() => {
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

    const wrapper = mount(EventSourcesView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    return { wrapper, router }
  }

  it('renders run panel, sources table and recent runs', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Panel spustenia')
    expect(wrapper.text()).toContain('Zdroje')
    expect(wrapper.text()).toContain('Posledne runy')
    expect(wrapper.text()).toContain('IMO')
    expect(wrapper.text()).toContain('Preklad')
    expect(wrapper.text()).toContain('Problem')
    expect(wrapper.text()).toContain('D 2')
    expect(wrapper.text()).toContain('Forma: title+popis')
  })

  it('disables unsupported source run button with deferred tooltip', async () => {
    const { wrapper } = await mountView()

    const unsupportedRunButton = wrapper.find('[data-testid="run-source-nasa"]')

    expect(unsupportedRunButton.exists()).toBe(true)
    expect(unsupportedRunButton.attributes('disabled')).toBeDefined()
    expect(unsupportedRunButton.attributes('title')).toBe('Nepodporovane v MVP')
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

  it('triggers purge from modal with confirm token', async () => {
    const { wrapper } = await mountView()

    const purgeButton = wrapper.find('[data-testid="purge-crawled-btn"]')
    expect(purgeButton.exists()).toBe(true)

    await purgeButton.trigger('click')
    await flush()

    const confirmInput = wrapper.find('[data-testid="purge-confirm-input"]')
    expect(confirmInput.exists()).toBe(true)
    await confirmInput.setValue('delete_crawled_events')

    const confirmButton = wrapper.find('[data-testid="purge-confirm-btn"]')
    expect(confirmButton.attributes('disabled')).toBeUndefined()
    await confirmButton.trigger('click')
    await flush()

    expect(purgeEventSourcesMock).toHaveBeenCalledWith({
      source_keys: ['imo'],
      dry_run: true,
      confirm: 'delete_crawled_events',
    })
  })
})
