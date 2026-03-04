import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import CrawlRunDetailView from './CrawlRunDetailView.vue'

const getCrawlRunMock = vi.fn()

vi.mock('@/services/api/admin/eventSources', () => ({
  getCrawlRun: (...args) => getCrawlRunMock(...args),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/crawl-runs/:id',
        name: 'admin.crawl-run.detail',
        meta: { adminSection: 'events', adminTab: 'crawling' },
        component: CrawlRunDetailView,
      },
      {
        path: '/admin/events/candidates',
        name: 'admin.event-candidates',
        meta: { adminSection: 'events', adminTab: 'candidates' },
        component: { template: '<div>candidates</div>' },
      },
      {
        path: '/admin/events/crawling',
        name: 'admin.event-sources',
        meta: { adminSection: 'events', adminTab: 'crawling' },
        component: { template: '<div>sources</div>' },
      },
      {
        path: '/admin/events/published',
        name: 'admin.events',
        meta: { adminSection: 'events', adminTab: 'published' },
        component: { template: '<div>events</div>' },
      },
    ],
  })
}

describe('CrawlRunDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    getCrawlRunMock.mockResolvedValue({
      data: {
        id: 123,
        source_name: 'imo',
        year: 2026,
        status: 'success',
        started_at: '2026-02-23T10:00:00Z',
        finished_at: '2026-02-23T10:01:00Z',
        fetched_count: 30,
        created_candidates_count: 12,
        updated_candidates_count: 3,
        skipped_duplicates_count: 15,
      },
    })
  })

  it('shows CTA to open candidates from this run', async () => {
    const router = makeRouter()
    await router.push('/admin/crawl-runs/123')
    await router.isReady()

    const wrapper = mount(CrawlRunDetailView, {
      global: { plugins: [router] },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Crawl run detail')
    expect(wrapper.text()).toContain('Event Pipeline')
    expect(wrapper.find('.adminSectionTabs__tab.active').text()).toContain('Crawling')

    const back = wrapper.get('[data-testid="admin-section-back-link"]')
    expect(back.attributes('href')).toContain('/admin/events/crawling')

    const cta = wrapper.find('[data-testid="view-candidates-btn"]')
    expect(cta.exists()).toBe(true)

    await cta.trigger('click')
    await flush()

    expect(router.currentRoute.value.name).toBe('admin.event-candidates')
    expect(router.currentRoute.value.query.run_id).toBe('123')
    expect(router.currentRoute.value.query.source_key).toBe('imo')
    expect(router.currentRoute.value.query.year).toBe('2026')
  })
})
