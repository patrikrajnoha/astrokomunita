import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
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

    const openButton = wrapper.find('.avatarOpenBtn')
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

  it('saves avatar preferences via PATCH /me/avatar', async () => {
    const { wrapper } = await mountProfile()

    const openButton = wrapper.find('.avatarOpenBtn')
    await openButton.trigger('click')
    await flush()

    const modeButtons = wrapper.findAll('.modeBtn')
    await modeButtons[1].trigger('click')
    await flush()

    const iconChoices = wrapper.findAll('.avatarIconGrid .avatarChoice')
    const colorChoices = wrapper.findAll('.avatarColorGrid .avatarChoice')
    await iconChoices[2].trigger('click')
    await colorChoices[4].trigger('click')

    const saveButton = wrapper.findAll('.avatarActionRowSave .btn')[1]
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

  it('navigates to profile edit route for profile and location edits', async () => {
    const { wrapper, router } = await mountProfile()

    expect(wrapper.find('input[maxlength="60"]').exists()).toBe(false)

    const editButton = wrapper.findAll('button').find((button) => button.text().trim() === 'Upraviť profil')
    expect(editButton).toBeTruthy()
    await editButton.trigger('click')
    await flush()
    expect(router.currentRoute.value.name).toBe('profile.edit')

    await router.push('/profile')
    await flush()

    const locationButton = wrapper.findAll('button').find((button) => button.text().trim() === 'Nastaviť polohu')
    expect(locationButton).toBeTruthy()
    await locationButton.trigger('click')
    await flush()
    expect(router.currentRoute.value.name).toBe('profile.edit')
    expect(router.currentRoute.value.hash).toBe('#location')

    wrapper.unmount()
  })

  it('shows empty location state with primary CTA when location is missing', async () => {
    const { wrapper } = await mountProfile()

    expect(wrapper.text()).toContain('Lokalita: nenastavená')

    const ctaButton = wrapper.findAll('button').find((button) => button.text().trim() === 'Nastaviť polohu')
    expect(ctaButton).toBeTruthy()
    expect(ctaButton.classes()).toContain('metaSetupBtn')

    wrapper.unmount()
  })
})
