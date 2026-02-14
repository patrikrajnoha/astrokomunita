import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminDashboardView from '@/views/admin/AdminDashboardView.vue'

const pushMock = vi.fn()
const apiGetMock = vi.fn()
const apiPostMock = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warn: vi.fn(),
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
    post: (...args) => apiPostMock(...args),
  },
}))

const dashboardPayload = {
  totals: {
    total_users: 100,
    total_posts: 220,
    total_events: 8,
    total_event_candidates: 14,
    total_reports: 17,
    total_blog_posts: 5,
  },
  range_metrics: {
    new_users: 6,
    new_posts: 11,
    new_events_published: 2,
    new_event_candidates: 3,
    likes_count: 10,
    replies_count: 4,
  },
  activity: {
    latest_users: [{ id: 1, name: 'Alice', created_at: '2026-02-13T10:00:00Z' }],
    latest_posts: [],
    latest_event_candidates: [],
    latest_events: [],
  },
  chart_series: {
    users_series: [
      { date: '2026-02-12', count: 2 },
      { date: '2026-02-13', count: 6 },
    ],
    posts_series: [
      { date: '2026-02-12', count: 4 },
      { date: '2026-02-13', count: 10 },
    ],
    candidates_series: [
      { date: '2026-02-12', count: 1 },
      { date: '2026-02-13', count: 3 },
    ],
  },
}

const reportsPaginated = {
  data: [
    {
      id: 44,
      reason: 'spam',
      status: 'open',
      created_at: '2026-02-13T09:00:00Z',
      target: { user: { name: 'Bob' }, content: 'Spam post' },
    },
  ],
  current_page: 1,
  last_page: 1,
  total: 1,
}

const flush = async () => {
  await Promise.resolve()
  await new Promise((resolve) => setTimeout(resolve, 0))
  await Promise.resolve()
}

function setupApiWithData({ empty = false } = {}) {
  apiGetMock.mockImplementation((url, config = {}) => {
    const params = config.params || {}

    if (url === '/admin/dashboard') {
      return Promise.resolve({ data: dashboardPayload })
    }

    if (url === '/admin/astrobot/nasa/status') {
      return Promise.resolve({
        data: {
          last_run: {
            new_items: 2,
            published_items: 1,
            finished_at: '2026-02-13T10:20:00Z',
            error_message: '',
          },
        },
      })
    }

    if (url === '/admin/reports') {
      if (params.per_page === 1) {
        return Promise.resolve({ data: { total: empty ? 0 : 4, data: [] } })
      }
      return Promise.resolve({ data: empty ? { ...reportsPaginated, data: [] } : reportsPaginated })
    }

    if (url === '/admin/event-candidates') {
      if (params.per_page === 1) {
        return Promise.resolve({ data: { total: empty ? 0 : 3, data: [] } })
      }

      return Promise.resolve({
        data: {
          data: empty
            ? []
            : [{ id: 77, title: 'Meteor shower', source_name: 'crawler', created_at: '2026-02-13T08:00:00Z' }],
        },
      })
    }

    if (url === '/admin/moderation') {
      const totalByStatus = {
        pending: empty ? 0 : 2,
        flagged: empty ? 0 : 1,
        blocked: empty ? 0 : 1,
      }

      if (params.per_page === 1) {
        return Promise.resolve({ data: { total: totalByStatus[params.status] || 0, data: [] } })
      }

      return Promise.resolve({
        data: {
          data: empty
            ? []
            : [{ id: 88, snippet: 'Toxic text', moderation_status: 'pending', created_at: '2026-02-13T07:00:00Z' }],
        },
      })
    }

    return Promise.resolve({ data: {} })
  })
}

describe('AdminDashboardView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setupApiWithData()
  })

  it('renders KPI and queue blocks with loaded data', async () => {
    const wrapper = mount(AdminDashboardView)
    await flush()

    expect(wrapper.text()).toContain('Admin Dashboard')
    expect(wrapper.text()).toContain('New users')
    expect(wrapper.text()).toContain('Reports open')
    expect(wrapper.text()).toContain('#44')
  })

  it('shows empty states when queue endpoints return no rows', async () => {
    setupApiWithData({ empty: true })
    const wrapper = mount(AdminDashboardView)
    await flush()

    expect(wrapper.text()).toContain('No pending reports.')
    expect(wrapper.text()).toContain('No pending candidates.')
    expect(wrapper.text()).toContain('No moderation items.')
  })
})
