import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import AdminUserDetailView from './AdminUserDetailView.vue'

const apiGetMock = vi.fn()
const apiPostMock = vi.fn()
const apiPatchMock = vi.fn()
const apiDeleteMock = vi.fn()
const authState = {
  user: { id: 1 },
  isAdmin: true,
}

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
    post: (...args) => apiPostMock(...args),
    patch: (...args) => apiPatchMock(...args),
    delete: (...args) => apiDeleteMock(...args),
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

function makeBotUser(overrides = {}) {
  return {
    id: 42,
    name: 'Kozmo',
    email: null,
    role: 'bot',
    is_bot: true,
    is_active: true,
    is_banned: false,
    avatar_mode: 'image',
    avatar_color: null,
    avatar_icon: null,
    avatar_seed: null,
    avatar_path: null,
    cover_path: null,
    avatar_url: null,
    cover_url: null,
    ...overrides,
  }
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('AdminUserDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authState.user = { id: 1 }
    authState.isAdmin = true
    URL.createObjectURL = vi.fn(() => 'blob:preview')
    URL.revokeObjectURL = vi.fn()

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
    apiDeleteMock.mockResolvedValue({ data: {} })
  })

  it('shows community section context and back link to users tab', async () => {
    const router = makeRouter()
    await router.push('/admin/users/42?page=5&search=raj')
    await router.isReady()

    const wrapper = mount(AdminUserDetailView, {
      global: {
        plugins: [router],
        stubs: { teleport: true },
      },
    })

    await flush()
    await flush()

    expect(wrapper.text().toLowerCase()).toContain('komunity')
    expect(wrapper.find('.adminSectionTabs__tab.active').text().toLowerCase()).toContain('pou')

    const back = wrapper.get('[data-testid="admin-section-back-link"]')
    expect(back.attributes('href')).toContain('/admin/community/users?page=5&search=raj')
    expect(apiGetMock).toHaveBeenCalledWith('/admin/users/42')
  })

  it('does not expose avatar or cover path editing for regular users', async () => {
    const router = makeRouter()
    await router.push('/admin/users/42')
    await router.isReady()

    const wrapper = mount(AdminUserDetailView, {
      global: {
        plugins: [router],
        stubs: { teleport: true },
      },
    })

    await flush()
    await flush()

    expect(wrapper.find('#profile-avatar').exists()).toBe(false)
    expect(wrapper.find('#profile-cover').exists()).toBe(false)

    const saveButton = wrapper.findAll('button').find((button) => button.text().includes('Save profile'))
    expect(saveButton).toBeTruthy()
    await saveButton.trigger('click')
    await flush()

    const profileCall = apiPatchMock.mock.calls.find(([url]) => url === '/admin/users/42/profile')
    expect(profileCall).toBeTruthy()
    const payload = profileCall[1]
    expect(payload).toEqual({
      name: 'Raj User',
      bio: null,
    })
    expect(payload).not.toHaveProperty('avatar_path')
    expect(payload).not.toHaveProperty('cover_path')
  })

  it('uses profile-style bot media cards and hides raw storage paths', async () => {
    apiGetMock.mockImplementation((url) => {
      if (url === '/admin/users/42') {
        return Promise.resolve({
          data: makeBotUser({
            avatar_path: 'avatars/42/current.png',
            cover_path: 'covers/42/current.png',
            avatar_url: '/api/media/file/avatars/42/current.png',
            cover_url: '/api/media/file/covers/42/current.png',
          }),
        })
      }

      return Promise.resolve({ data: { data: [] } })
    })

    const router = makeRouter()
    await router.push('/admin/users/42')
    await router.isReady()

    const wrapper = mount(AdminUserDetailView, {
      global: {
        plugins: [router],
        stubs: { teleport: true },
      },
    })

    await flush()
    await flush()

    expect(wrapper.text()).toContain('Profilovy avatar')
    expect(wrapper.text()).toContain('Titulna fotka')
    expect(wrapper.find('[data-testid="admin-bot-avatar-edit"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="admin-bot-cover-edit"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Path:')
    expect(wrapper.text()).not.toContain('avatars/42/current.png')
    expect(wrapper.text()).not.toContain('covers/42/current.png')
  })

  it('allows admin to upload/remove/save bot avatar and cover via editor modals', async () => {
    let botState = makeBotUser({
      avatar_path: 'avatars/42/current.png',
      cover_path: 'covers/42/current.png',
      avatar_url: '/api/media/file/avatars/42/current.png',
      cover_url: '/api/media/file/covers/42/current.png',
    })

    apiGetMock.mockImplementation((url) => {
      if (url === '/admin/users/42') {
        return Promise.resolve({ data: { ...botState } })
      }
      return Promise.resolve({ data: { data: [] } })
    })

    apiPatchMock.mockImplementation(async (url, payload) => {
      if (url === '/admin/users/42/avatar') {
        botState = {
          ...botState,
          avatar_mode: 'image',
          avatar_path: 'avatars/42/new-avatar.png',
          avatar_url: '/api/media/file/avatars/42/new-avatar.png',
        }
        return { data: { ...botState } }
      }

      if (url === '/admin/users/42/avatar/preferences') {
        botState = {
          ...botState,
          avatar_mode: payload.avatar_mode,
          avatar_color: payload.avatar_color,
          avatar_icon: payload.avatar_icon,
          avatar_seed: payload.avatar_seed,
        }
        return { data: { ...botState } }
      }

      if (url === '/admin/users/42/cover') {
        botState = {
          ...botState,
          cover_path: 'covers/42/new-cover.png',
          cover_url: '/api/media/file/covers/42/new-cover.png',
        }
        return { data: { ...botState } }
      }

      return { data: { ...botState } }
    })

    apiDeleteMock.mockImplementation(async (url) => {
      if (url === '/admin/users/42/avatar') {
        botState = {
          ...botState,
          avatar_path: null,
          avatar_url: null,
        }
      }
      if (url === '/admin/users/42/cover') {
        botState = {
          ...botState,
          cover_path: null,
          cover_url: null,
        }
      }
      return { data: { ...botState } }
    })

    const router = makeRouter()
    await router.push('/admin/users/42')
    await router.isReady()

    const wrapper = mount(AdminUserDetailView, {
      global: {
        plugins: [router],
        stubs: { teleport: true },
      },
    })

    await flush()
    await flush()

    await wrapper.get('[data-testid="admin-bot-avatar-edit"]').trigger('click')
    await flush()

    const avatarFile = new File([new Uint8Array([1, 2, 3])], 'avatar.png', { type: 'image/png' })
    await wrapper.vm.onBotMediaChange('avatar', {
      target: {
        files: [avatarFile],
        value: '',
      },
    })
    await flush()

    await wrapper.get('[data-testid="admin-bot-avatar-save"]').trigger('click')
    await flush()
    await flush()

    expect(apiPatchMock).toHaveBeenCalledWith('/admin/users/42/avatar', expect.any(FormData))
    expect(apiPatchMock).toHaveBeenCalledWith(
      '/admin/users/42/avatar/preferences',
      expect.objectContaining({ avatar_mode: 'image' }),
    )

    await wrapper.get('[data-testid="admin-bot-avatar-edit"]').trigger('click')
    await flush()
    await wrapper.get('[data-testid="admin-bot-avatar-remove"]').trigger('click')
    await wrapper.get('[data-testid="admin-bot-avatar-save"]').trigger('click')
    await flush()
    await flush()

    expect(apiDeleteMock).toHaveBeenCalledWith('/admin/users/42/avatar')
    expect(apiPatchMock).toHaveBeenCalledWith(
      '/admin/users/42/avatar/preferences',
      expect.objectContaining({ avatar_mode: 'image' }),
    )

    await wrapper.get('[data-testid="admin-bot-cover-edit"]').trigger('click')
    await flush()

    const coverFile = new File([new Uint8Array([4, 5, 6])], 'cover.png', { type: 'image/png' })
    await wrapper.vm.onBotMediaChange('cover', {
      target: {
        files: [coverFile],
        value: '',
      },
    })
    await flush()

    await wrapper.get('[data-testid="admin-bot-cover-save"]').trigger('click')
    await flush()
    await flush()

    expect(apiPatchMock).toHaveBeenCalledWith('/admin/users/42/cover', expect.any(FormData))

    await wrapper.get('[data-testid="admin-bot-cover-edit"]').trigger('click')
    await flush()
    await wrapper.get('[data-testid="admin-bot-cover-remove"]').trigger('click')
    await wrapper.get('[data-testid="admin-bot-cover-save"]').trigger('click')
    await flush()
    await flush()

    expect(apiDeleteMock).toHaveBeenCalledWith('/admin/users/42/cover')
  })

  it('hides bot media edit controls for non-admin and keeps read-only preview', async () => {
    authState.isAdmin = false
    apiGetMock.mockImplementation((url) => {
      if (url === '/admin/users/42') {
        return Promise.resolve({
          data: makeBotUser(),
        })
      }

      return Promise.resolve({ data: { data: [] } })
    })

    const router = makeRouter()
    await router.push('/admin/users/42')
    await router.isReady()

    const wrapper = mount(AdminUserDetailView, {
      global: {
        plugins: [router],
        stubs: { teleport: true },
      },
    })

    await flush()
    await flush()

    expect(wrapper.find('[data-testid="admin-bot-avatar-edit"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="admin-bot-cover-edit"]').exists()).toBe(false)
    expect(wrapper.text().toLowerCase()).toContain('read-only preview')
    expect(wrapper.find('.avatarCardMeta').exists()).toBe(true)
    expect(wrapper.find('.botMediaPreview.cover').exists()).toBe(true)
  })
})
