import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { AVATAR_ICONS } from '@/constants/avatar'
import ProfileView from './ProfileView.vue'

const authMock = vi.hoisted(() => ({
  user: {
    id: 7,
    name: 'Test User',
    username: 'test-user',
    email: 'test@example.com',
    avatar_mode: 'image',
    avatar_color: null,
    avatar_icon: null,
    avatar_seed: null,
    avatar_url: null,
    cover_url: null,
    is_admin: false,
    bio: null,
    location: null,
  },
  initialized: true,
  csrf: vi.fn(async () => {}),
  fetchUser: vi.fn(async () => null),
}))

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  patch: vi.fn(),
  post: vi.fn(),
  delete: vi.fn(),
  defaults: {
    baseURL: '/api',
  },
}))

const toastMock = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warn: vi.fn(),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/stores/eventFollows', () => ({
  useEventFollowsStore: () => ({
    revision: 0,
    hydrateFromEvents: vi.fn(),
  }),
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: vi.fn(async () => true),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => toastMock,
}))

vi.mock('@/services/api', () => ({
  default: apiMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/profile', name: 'profile', component: ProfileView },
      { path: '/profile/edit', name: 'profile.edit', component: { template: '<div>edit-profile</div>' } },
      { path: '/', name: 'home', component: { template: '<div>home</div>' } },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
      { path: '/events/:id', component: { template: '<div>event</div>' } },
      { path: '/posts/:id', component: { template: '<div>post</div>' } },
    ],
  })
}

async function mountProfile() {
  const router = makeRouter()
  await router.push('/profile')
  await router.isReady()

  const wrapper = mount(ProfileView, {
    global: {
      plugins: [router],
      stubs: {
        ProfileEventCard: { template: '<div></div>' },
        ProfileEdit: { template: '<div data-testid="profile-edit-stub"></div>' },
        teleport: true,
      },
    },
  })

  await flush()
  await flush()
  return { wrapper, router }
}

describe('ProfileView avatar panel', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    authMock.user = {
      id: 7,
      name: 'Test User',
      username: 'test-user',
      email: 'test@example.com',
      avatar_mode: 'image',
      avatar_color: null,
      avatar_icon: null,
      avatar_seed: null,
      avatar_url: null,
      cover_url: null,
      is_admin: false,
      bio: null,
      location: null,
    }
    authMock.initialized = true
    authMock.fetchUser.mockImplementation(async () => authMock.user)

    apiMock.get.mockResolvedValue({
      data: {
        data: [],
        total: 0,
        next_page_url: null,
      },
    })

    apiMock.patch.mockImplementation(async (url, payload) => {
      if (url === '/me/avatar') {
        return {
          data: {
            ...authMock.user,
            avatar_mode: payload.avatar_mode,
            avatar_color: payload.avatar_color,
            avatar_icon: payload.avatar_icon,
            avatar_seed: payload.avatar_seed,
          },
        }
      }

      return { data: { ...authMock.user } }
    })
  })

  it('switches mode to generated and renders generated controls', async () => {
    const { wrapper } = await mountProfile()

    const openButton = wrapper.find('.avatarEditTrigger')
    await openButton.trigger('click')
    await flush()

    const modeButtons = wrapper.findAll('.modeBtn')
    await modeButtons[1].trigger('click')
    await flush()

    expect(wrapper.find('.avatarIconGrid').exists()).toBe(true)
    expect(wrapper.find('.avatarColorGrid').exists()).toBe(true)
    expect(wrapper.find('.avatarImageActions').exists()).toBe(false)

    wrapper.unmount()
  })

  it('shows observations tab in profile tabs', async () => {
    const { wrapper } = await mountProfile()

    const tabLabels = wrapper.findAll('.tab').map((tab) => tab.text())
    expect(tabLabels.some((label) => label.includes('Pozorovania'))).toBe(true)
    expect(tabLabels.some((label) => label.includes('Odpovede'))).toBe(false)

    wrapper.unmount()
  })

  it('loads followed-events total during initial profile bootstrap', async () => {
    const { wrapper } = await mountProfile()

    expect(apiMock.get).toHaveBeenCalledWith('/me/followed-events', {
      params: { per_page: 1 },
    })

    wrapper.unmount()
  })

  it('saves avatar preferences via PATCH /me/avatar', async () => {
    const { wrapper } = await mountProfile()

    const openButton = wrapper.find('.avatarEditTrigger')
    await openButton.trigger('click')
    await flush()

    const modeButtons = wrapper.findAll('.modeBtn')
    await modeButtons[1].trigger('click')
    await flush()

    const iconChoices = wrapper.findAll('.avatarIconGrid .avatarChoice')
    const colorChoices = wrapper.findAll('.avatarColorGrid .avatarChoice')

    expect(iconChoices).toHaveLength(AVATAR_ICONS.length)

    await iconChoices[2].trigger('click')
    await colorChoices[4].trigger('click')

    const saveButton = wrapper.findAll('.avatarActionRowSave button')[1]
    await saveButton.trigger('click')
    await flush()
    await flush()

    expect(authMock.csrf).toHaveBeenCalled()
    expect(apiMock.patch).toHaveBeenCalledWith('/me/avatar', {
      avatar_mode: 'generated',
      avatar_color: 4,
      avatar_icon: 2,
      avatar_seed: null,
    })
    expect(toastMock.success).toHaveBeenCalled()

    wrapper.unmount()
  })

  it('opens profile edit modal for profile edit CTA', async () => {
    const { wrapper, router } = await mountProfile()

    expect(wrapper.find('input[maxlength="60"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="profile-edit-modal"]').exists()).toBe(false)

    const editButton = wrapper.findAll('.headActions button')[0]
    expect(editButton.exists()).toBe(true)
    await editButton.trigger('click')
    await flush()

    expect(wrapper.find('[data-testid="profile-edit-modal"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="profile-edit-stub"]').exists()).toBe(true)
    expect(router.currentRoute.value.name).toBe('profile')

    wrapper.unmount()
  })

  it('hides location UI and separate avatar panel in profile header area', async () => {
    const { wrapper } = await mountProfile()

    expect(wrapper.text()).not.toMatch(/lokalita/i)
    expect(wrapper.text()).not.toMatch(/upraviť polohu/i)
    expect(wrapper.text()).not.toMatch(/nastaviť polohu/i)
    expect(wrapper.text()).not.toContain('test@example.com')
    expect(wrapper.find('.avatarCard').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Profilovy avatar')

    wrapper.unmount()
  })

  it('renders BOT badge in profile header for bot accounts', async () => {
    authMock.user = {
      ...authMock.user,
      is_bot: true,
      role: 'bot',
    }

    const { wrapper } = await mountProfile()

    expect(wrapper.text()).toContain('BOT')

    wrapper.unmount()
  })
})
