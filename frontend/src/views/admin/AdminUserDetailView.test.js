import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import AdminUserDetailView from './AdminUserDetailView.vue'

const apiGetMock = vi.fn()
const apiPostMock = vi.fn()
const apiPatchMock = vi.fn()
const authState = {
  user: { id: 1 },
  isAdmin: true,
}

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
    post: (...args) => apiPostMock(...args),
    patch: (...args) => apiPatchMock(...args),
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: vi.fn(async () => true),
    prompt: vi.fn(async () => 'test'),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
}))

vi.mock('@/utils/imageCompression', () => ({
  compressImageFileToMaxBytes: vi.fn(async (file) => file),
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/users/:id',
        name: 'admin.users.detail',
        meta: { adminSection: 'community', adminTab: 'users' },
        component: AdminUserDetailView,
      },
      {
        path: '/admin/community/users',
        name: 'admin.users',
        meta: { adminSection: 'community', adminTab: 'users' },
        component: { template: '<div>users</div>' },
      },
      {
        path: '/admin/community/moderation',
        name: 'admin.moderation',
        meta: { adminSection: 'community', adminTab: 'moderation' },
        component: { template: '<div>moderation</div>' },
      },
    ],
  })
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('AdminUserDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authState.user = { id: 1 }
    authState.isAdmin = true
    apiGetMock.mockImplementation((url) => {
      if (url === '/admin/users/42') {
        return Promise.resolve({
          data: {
            id: 42,
            name: 'Raj User',
            email: 'raj@example.test',
            role: 'user',
            is_active: true,
            is_banned: false,
            created_at: '2026-03-01T12:00:00Z',
            banned_at: null,
            ban_reason: null,
          },
        })
      }

      return Promise.resolve({
        data: { data: [] },
      })
    })
    apiPatchMock.mockResolvedValue({ data: {} })
    apiPostMock.mockResolvedValue({ data: {} })
  })

  it('shows community section context and back link to users tab', async () => {
    const router = makeRouter()
    await router.push('/admin/users/42?page=5&search=raj')
    await router.isReady()

    const wrapper = mount(AdminUserDetailView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Správa komunity')
    expect(wrapper.find('.adminSectionTabs__tab.active').text()).toContain('Používatelia')

    const back = wrapper.get('[data-testid="admin-section-back-link"]')
    expect(back.attributes('href')).toContain('/admin/community/users?page=5&search=raj')

    expect(apiGetMock).toHaveBeenCalledWith('/admin/users/42')
  })

  it('uploads bot avatar through admin upload endpoint', async () => {
    apiGetMock.mockImplementation((url) => {
      if (url === '/admin/users/42') {
        return Promise.resolve({
          data: {
            id: 42,
            name: 'Kozmo',
            email: null,
            role: 'bot',
            is_bot: true,
            is_active: true,
            is_banned: false,
            avatar_path: null,
            cover_path: null,
            avatar_url: null,
            cover_url: null,
          },
        })
      }

      return Promise.resolve({
        data: { data: [] },
      })
    })

    apiPatchMock.mockResolvedValue({
      data: {
        id: 42,
        role: 'bot',
        is_bot: true,
        avatar_path: 'avatars/42/new-avatar.png',
        avatar_url: '/api/media/file/avatars/42/new-avatar.png',
      },
    })

    const router = makeRouter()
    await router.push('/admin/users/42')
    await router.isReady()

    const wrapper = mount(AdminUserDetailView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Upload avatar')
    expect(wrapper.text()).toContain('Upload cover')

    const avatarInput = wrapper.findAll('.botMediaInput')[0]
    const file = new File([new Uint8Array([1, 2, 3])], 'avatar.png', { type: 'image/png' })
    Object.defineProperty(avatarInput.element, 'files', {
      value: [file],
      writable: false,
      configurable: true,
    })
    await avatarInput.trigger('change')

    await flush()
    await flush()

    expect(apiPatchMock).toHaveBeenCalled()
    const [url, payload] = apiPatchMock.mock.calls.find((call) => call[0] === '/admin/users/42/avatar') || []
    expect(url).toBe('/admin/users/42/avatar')
    expect(payload).toBeInstanceOf(FormData)
  })

  it('hides bot upload controls for non-admin and keeps read-only preview', async () => {
    authState.isAdmin = false

    apiGetMock.mockImplementation((url) => {
      if (url === '/admin/users/42') {
        return Promise.resolve({
          data: {
            id: 42,
            name: 'Kozmo',
            email: null,
            role: 'bot',
            is_bot: true,
            is_active: true,
            is_banned: false,
            avatar_path: null,
            cover_path: null,
            avatar_url: null,
            cover_url: null,
          },
        })
      }

      return Promise.resolve({
        data: { data: [] },
      })
    })

    const router = makeRouter()
    await router.push('/admin/users/42')
    await router.isReady()

    const wrapper = mount(AdminUserDetailView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(wrapper.findAll('.botMediaInput').length).toBe(0)
    expect(wrapper.text()).not.toContain('Upload avatar')
    expect(wrapper.text()).not.toContain('Upload cover')
    expect(wrapper.text().toLowerCase()).toContain('read-only preview')
    expect(wrapper.find('.botMediaPreview.avatar').exists()).toBe(true)
    expect(wrapper.find('.botMediaPreview.cover').exists()).toBe(true)
  })
})
