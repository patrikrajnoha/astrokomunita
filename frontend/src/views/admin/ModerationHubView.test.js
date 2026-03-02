import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import ModerationHubView from './ModerationHubView.vue'

const apiGetMock = vi.fn()
const apiPostMock = vi.fn()

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
    post: (...args) => apiPostMock(...args),
  },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warn: vi.fn(),
  }),
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: vi.fn(async () => true),
  }),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/reports',
        name: 'admin.reports',
        redirect: (to) => ({
          name: 'admin.moderation',
          query: {
            ...to.query,
            tab: 'reports',
          },
        }),
      },
      {
        path: '/admin/moderation',
        name: 'admin.moderation',
        component: ModerationHubView,
      },
    ],
  })
}

function mockApi() {
  apiGetMock.mockImplementation((url, config = {}) => {
    if (url === '/admin/moderation/overview') {
      return Promise.resolve({
        data: {
          service: { status: 'running', last_check_at: '2026-03-01T10:00:00Z' },
          counts: {
            queue_pending: 1,
            queue_flagged: 1,
            queue_blocked: 0,
            queue_reviewed: 2,
            reports_open: 2,
            reports_closed: 1,
          },
        },
      })
    }

    if (url === '/admin/moderation/review-feed') {
      if (config?.params?.mode === 'reviewed') {
        return Promise.resolve({
          data: [
            {
              kind: 'queue',
              id: '88',
              created_at: '2026-03-01T09:58:00Z',
              label: 'Prispevok #88',
              reason: 'Skontrolovane administratorom.',
              status: 'reviewed',
              target: { type: 'post', id: '88', author: 'Admin', summary: 'Skontrolovany prispevok' },
            },
          ],
        })
      }

      return Promise.resolve({
        data: [
          {
            kind: 'report',
            id: '11',
            created_at: '2026-03-01T09:59:00Z',
            label: 'Post #44',
            reason: 'spam - hlasenie',
            status: 'open',
            target: { type: 'post', id: '44', author: 'Jana', summary: 'Nahlaseny obsah' },
          },
          {
            kind: 'queue',
            id: '22',
            created_at: '2026-03-01T09:58:00Z',
            label: 'Prispevok #22',
            reason: 'Automaticka moderacia oznacila prispevok.',
            status: 'flagged',
            target: { type: 'post', id: '22', author: 'Miro', summary: 'Obsah vo fronte' },
          },
        ],
      })
    }

    if (url === '/admin/reports') {
      return Promise.resolve({
        data: {
          data: [],
          current_page: 1,
          last_page: 1,
          total: 0,
        },
      })
    }

    if (url === '/admin/moderation/health') {
      return Promise.resolve({
        data: {
          status: 'running',
          checked_at: '2026-03-01T10:00:00Z',
          service: { device: 'cpu' },
        },
      })
    }

    if (url === '/admin/moderation') {
      return Promise.resolve({
        data: {
          data: [
            {
              id: 22,
              moderation_status: 'flagged',
              snippet: 'Obsah vo fronte',
              moderation_summary: {
                text: { toxicity_score: 0.82, hate_score: 0.14 },
                attachment: { nsfw_score: 0.01 },
              },
              created_at: '2026-03-01T09:58:00Z',
            },
          ],
        },
      })
    }

    if (url === '/admin/moderation/22') {
      return Promise.resolve({
        data: {
          post: {
            id: 22,
            moderation_status: 'flagged',
            content: 'Obsah vo fronte',
            attachment_url: null,
          },
          logs: [],
        },
      })
    }

    return Promise.reject(new Error(`Unexpected GET ${url}`))
  })
}

describe('ModerationHubView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockApi()
  })

  it('redirects /admin/reports to /admin/moderation?tab=reports', async () => {
    const router = makeRouter()
    await router.push('/admin/reports')
    await router.isReady()

    expect(router.currentRoute.value.path).toBe('/admin/moderation')
    expect(router.currentRoute.value.query.tab).toBe('reports')
  })

  it('switches tabs from query params', async () => {
    const router = makeRouter()
    await router.push('/admin/moderation?tab=service')
    await router.isReady()

    const wrapper = mount(ModerationHubView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Obnovit stav')

    await router.push('/admin/moderation?tab=reports')
    await flush()
    await flush()

    expect(wrapper.find('#reports-search').exists()).toBe(true)
  })

  it('renders combined review feed items', async () => {
    const router = makeRouter()
    await router.push('/admin/moderation?tab=review')
    await router.isReady()

    const wrapper = mount(ModerationHubView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Post #44')
    expect(wrapper.text()).toContain('Prispevok #22')
    expect(wrapper.text()).toContain('Jana: Nahlaseny obsah')
    expect(wrapper.text()).toContain('Miro: Obsah vo fronte')
  })

  it('navigates to queue tab with deep-link params when inspect is clicked', async () => {
    const router = makeRouter()
    await router.push('/admin/moderation?tab=review')
    await router.isReady()

    const wrapper = mount(ModerationHubView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    const buttons = wrapper.findAll('button').filter((node) => node.text().includes('Skontrolovat'))
    await buttons[1].trigger('click')
    await flush()
    await flush()

    expect(router.currentRoute.value.query.tab).toBe('queue')
    expect(router.currentRoute.value.query.queueId).toBe('22')
    expect(router.currentRoute.value.query.queueStatus).toBe('flagged')
  })
})
