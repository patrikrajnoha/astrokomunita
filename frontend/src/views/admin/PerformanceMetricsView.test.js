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

function deferredPromise() {
  let resolve = () => {}
  let reject = () => {}
  const promise = new Promise((resolveFn, rejectFn) => {
    resolve = resolveFn
    reject = rejectFn
  })

  return { promise, resolve, reject }
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

  it('renders Slovak UI labels and table data', async () => {
    const router = makeRouter()
    await router.push('/admin/performance-metrics')
    await router.isReady()

    const wrapper = mount(PerformanceMetricsView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Vykonnostne metriky')
    expect(wrapper.text()).toContain('Spustenie benchmarku')
    expect(wrapper.text()).toContain('Najnovsie vysledky')
    expect(wrapper.text()).toContain('events_list_200')
  })

  it('disables run button and shows loading state while benchmark is running', async () => {
    const pendingRun = deferredPromise()
    runMetricsMock.mockReturnValueOnce(pendingRun.promise)

    const router = makeRouter()
    await router.push('/admin/performance-metrics')
    await router.isReady()

    const wrapper = mount(PerformanceMetricsView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    const button = wrapper.find('[data-testid="run-benchmark-btn"]')
    const confirmCheckbox = wrapper.find('[data-testid="confirm-load-checkbox"]')

    expect(button.attributes('disabled')).toBeDefined()

    await confirmCheckbox.setValue(true)
    expect(button.attributes('disabled')).toBeUndefined()

    await button.trigger('click')
    await flush()

    expect(runMetricsMock).toHaveBeenCalledTimes(1)
    expect(button.attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="run-progress"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Spustam benchmark...')

    pendingRun.resolve({
      status: 'ok',
      log_ids: [10],
      results: { events_list: { avg_ms: 12.5 } },
    })
    await flush()
    await flush()
  })

  it('renders Slovak empty state when no metrics are available', async () => {
    getMetricsMock.mockResolvedValue({
      logs: [],
      trend: [],
      last_run_per_key: [],
    })

    const router = makeRouter()
    await router.push('/admin/performance-metrics')
    await router.isReady()

    const wrapper = mount(PerformanceMetricsView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    const emptyState = wrapper.find('[data-testid="empty-state"]')
    expect(emptyState.exists()).toBe(true)
    expect(emptyState.text()).toContain('Zatial nie su k dispozicii ziadne vysledky benchmarku.')
  })
})
