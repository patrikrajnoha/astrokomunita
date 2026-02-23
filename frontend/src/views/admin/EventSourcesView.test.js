import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import EventSourcesView from './EventSourcesView.vue'

const getEventSourcesMock = vi.fn()
const getCrawlRunsMock = vi.fn()
const runEventSourceCrawlMock = vi.fn()
const updateEventSourceMock = vi.fn()
const toastSuccessMock = vi.fn()

vi.mock('@/services/api/admin/eventSources', () => ({
  getEventSources: (...args) => getEventSourcesMock(...args),
  getCrawlRuns: (...args) => getCrawlRunsMock(...args),
  runEventSourceCrawl: (...args) => runEventSourceCrawlMock(...args),
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
          },
        ],
      },
    })

    runEventSourceCrawlMock.mockResolvedValue({
      data: { results: [] },
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

    expect(wrapper.text()).toContain('Run panel')
    expect(wrapper.text()).toContain('Sources')
    expect(wrapper.text()).toContain('Recent runs')
    expect(wrapper.text()).toContain('IMO')
  })

  it('disables unsupported source run button with deferred tooltip', async () => {
    const { wrapper } = await mountView()

    const unsupportedRunButton = wrapper.find('[data-testid="run-source-nasa"]')

    expect(unsupportedRunButton.exists()).toBe(true)
    expect(unsupportedRunButton.attributes('disabled')).toBeDefined()
    expect(unsupportedRunButton.attributes('title')).toBe('Deferred in MVP')
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
})
