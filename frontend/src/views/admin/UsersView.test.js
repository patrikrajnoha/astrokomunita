import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import UsersView from './UsersView.vue'

const apiGetMock = vi.fn()

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
    patch: vi.fn(),
    post: vi.fn(),
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { id: 1 },
    isAdmin: true,
  }),
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: vi.fn(async () => true),
    prompt: vi.fn(async () => 'reason'),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/community/users',
        name: 'admin.users',
        component: UsersView,
      },
      {
        path: '/admin/users/:id',
        name: 'admin.users.detail',
        component: { template: '<div>detail</div>' },
      },
    ],
  })
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('UsersView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    apiGetMock.mockImplementation(async (url) => {
      if (url === '/admin/users') {
        return {
          data: {
            current_page: 1,
            data: [
              {
                id: 4,
                name: 'Kozmo',
                username: 'kozmobot',
                role: 'bot',
                email: 'legacy-bot@example.test',
                is_bot: true,
                is_active: true,
                is_banned: false,
              },
              {
                id: 9,
                name: 'Regular',
                username: 'regular',
                role: 'user',
                email: 'regular@example.test',
                is_bot: false,
                is_active: true,
                is_banned: false,
              },
            ],
            total: 2,
            per_page: 20,
            last_page: 1,
          },
        }
      }

      if (url === '/_health') {
        return {
          data: {
            ok: true,
            env: 'local',
            git_sha: 'abc123',
            build_id: null,
            time: '2026-03-05T12:00:00Z',
          },
        }
      }

      throw new Error(`Unexpected GET ${url}`)
    })
  })

  it('renders bot role badge and hides bot email behind bot-account hint', async () => {
    const router = makeRouter()
    await router.push('/admin/community/users')
    await router.isReady()

    const wrapper = mount(UsersView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    const botRow = wrapper.get('[data-row-id="4"]')
    expect(botRow.find('.roleBadge').text()).toBe('BOT')
    expect(botRow.find('.col-email .truncateText').text()).toBe('—')

    const hint = botRow.get('.botAccountHint')
    expect(hint.text()).toBe('(bot účet)')
    expect(hint.attributes('title')).toContain('Automatizovaný účet')

    const regularRow = wrapper.get('[data-row-id="9"]')
    expect(regularRow.find('.col-email .truncateText').text()).toBe('regular@example.test')

    const debugBanner = wrapper.get('.devConnectivityBanner')
    expect(debugBanner.text()).toContain('DEV API Connectivity')
    expect(debugBanner.text()).toContain('api.defaults.baseURL')
    expect(debugBanner.text()).toContain('/api/_health')
    expect(debugBanner.text()).toContain('env=local; rev=abc123')
  })
})
