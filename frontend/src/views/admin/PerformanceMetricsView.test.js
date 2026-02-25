import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PerformanceMetricsView from '@/views/admin/PerformanceMetricsView.vue'

const getMetricsMock = vi.fn()
const runMetricsMock = vi.fn()

vi.mock('@/services/performance', () => ({
  getMetrics: (...args) => getMetricsMock(...args),
  runMetrics: (...args) => runMetricsMock(...args),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/admin/performance-metrics', component: PerformanceMetricsView }],
  })
}

describe('PerformanceMetricsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getMetricsMock.mockResolvedValue({
      logs: [
        {
          id: 10,
          key: 'events_list_200',
          created_at: '2026-02-25T18:00:00Z',
          avg_ms: 12.5,
          p95_ms: 20.1,
          db_queries_avg: 5.2,
          payload: { mode: 'normal' },
        },
      ],
      trend: [
        {
          key: 'events_list_200',
          points: [
            { avg_ms: 10 },
            { avg_ms: 12 },
            { avg_ms: 11 },
          ],
        },
      ],
      last_run_per_key: [],
    })
    runMetricsMock.mockResolvedValue({
      status: 'ok',
      log_ids: [10],
      results: { events_list: { avg_ms: 12.5 } },
    })
  })

  it('renders latest results table', async () => {
    const router = makeRouter()
    await router.push('/admin/performance-metrics')
    await router.isReady()

    const wrapper = mount(PerformanceMetricsView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Latest results')
    expect(wrapper.text()).toContain('events_list_200')
  })

  it('run button triggers benchmark API call', async () => {
    const router = makeRouter()
    await router.push('/admin/performance-metrics')
    await router.isReady()

    const wrapper = mount(PerformanceMetricsView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    const button = wrapper.find('[data-testid="run-benchmark-btn"]')
    await button.trigger('click')
    await flush()

    expect(runMetricsMock).toHaveBeenCalledTimes(1)
  })
})

