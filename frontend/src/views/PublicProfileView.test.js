import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import PublicProfileView from './PublicProfileView.vue'

const authStoreMock = vi.hoisted(() => ({
  user: null,
}))
const apiGetMock = vi.fn()
const listObservationsMock = vi.fn()

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
    defaults: { baseURL: '/api' },
  },
}))

vi.mock('@/services/observations', () => ({
  listObservations: (...args) => listObservationsMock(...args),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authStoreMock,
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/u/:username',
        name: 'public-profile',
        component: PublicProfileView,
      },
      {
        path: '/',
        name: 'home',
        component: { template: '<div>home</div>' },
      },
      {
        path: '/posts/:id',
        name: 'post-detail',
        component: { template: '<div>post</div>' },
      },
      {
        path: '/observations/:id',
        name: 'observation-detail',
        component: { template: '<div>observation</div>' },
      },
    ],
  })
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makePostsResponse(rows = [], total = rows.length) {
  return {
    data: rows,
    total,
    current_page: 1,
    last_page: 1,
    next_page_url: null,
  }
}

describe('PublicProfileView media fallback', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authStoreMock.user = null

    listObservationsMock.mockResolvedValue({
      data: {
        data: [],
        total: 0,
        current_page: 1,
        last_page: 1,
      },
    })
  })

  it('renders a usable avatar fallback and bot cover fallback when bot media is missing', async () => {
    apiGetMock.mockImplementation((url, config = {}) => {
      if (url === '/users/kozmobot') {
        return Promise.resolve({
          data: {
            id: 8,
            username: 'kozmobot',
            name: 'Kozmo Bot',
            role: 'bot',
            is_bot: true,
            avatar_mode: 'image',
            avatar_url: null,
            avatar_path: null,
            cover_url: null,
            cover_path: null,
            bio: null,
          },
        })
      }

      if (url === '/users/kozmobot/posts') {
        const kind = String(config?.params?.kind || 'roots')
        if (kind === 'roots') {
          return Promise.resolve({ data: makePostsResponse([{ id: 501, content: 'Bot post', created_at: '2026-03-05T12:00:00Z' }], 1) })
        }
        return Promise.resolve({ data: makePostsResponse([], 0) })
      }

      throw new Error(`Unexpected GET ${url}`)
    })

    const router = makeRouter()
    await router.push('/u/kozmobot')
    await router.isReady()

    const wrapper = mount(PublicProfileView, {
      global: {
        plugins: [router],
        stubs: {
          ObservationCard: { template: '<div class="obs-stub"></div>' },
          HashtagText: { props: ['content'], template: '<p>{{ content }}</p>' },
        },
      },
    })

    await flush()
    await flush()

    const cover = wrapper.get('[data-testid="public-profile-cover"]')
    expect(cover.classes()).toContain('cover--bot-fallback')
    expect(wrapper.find('.coverImg').exists()).toBe(false)

    const headerAvatar = wrapper.get('.profileHead .avatar .user-avatar')
    expect(
      headerAvatar.find('.default-avatar').exists() || headerAvatar.find('.user-avatar-media').exists(),
    ).toBe(true)
  })

  it('prefers uploaded avatar and cover over fallback', async () => {
    apiGetMock.mockImplementation((url, config = {}) => {
      if (url === '/users/stellarbot') {
        return Promise.resolve({
          data: {
            id: 9,
            username: 'stellarbot',
            name: 'Stellar Bot',
            role: 'bot',
            is_bot: true,
            avatar_mode: 'image',
            avatar_url: '/api/media/file/avatars/9/custom.png',
            avatar_path: 'avatars/9/custom.png',
            cover_url: '/api/media/file/covers/9/custom-cover.png',
            cover_path: 'covers/9/custom-cover.png',
            bio: null,
          },
        })
      }

      if (url === '/users/stellarbot/posts') {
        const kind = String(config?.params?.kind || 'roots')
        if (kind === 'roots') {
          return Promise.resolve({ data: makePostsResponse([{ id: 601, content: 'Stela post', created_at: '2026-03-05T12:00:00Z' }], 1) })
        }
        return Promise.resolve({ data: makePostsResponse([], 0) })
      }

      throw new Error(`Unexpected GET ${url}`)
    })

    const router = makeRouter()
    await router.push('/u/stellarbot')
    await router.isReady()

    const wrapper = mount(PublicProfileView, {
      global: {
        plugins: [router],
        stubs: {
          ObservationCard: { template: '<div class="obs-stub"></div>' },
          HashtagText: { props: ['content'], template: '<p>{{ content }}</p>' },
        },
      },
    })

    await flush()
    await flush()

    const cover = wrapper.get('[data-testid="public-profile-cover"]')
    expect(cover.classes()).not.toContain('cover--bot-fallback')

    const coverImg = wrapper.get('.coverImg')
    expect(coverImg.attributes('src')).toContain('/api/media/file/covers/9/custom-cover.png')

    const headerAvatarImg = wrapper.get('.profileHead .avatar img.user-avatar-media')
    expect(headerAvatarImg.attributes('src')).toContain('/api/media/file/avatars/9/custom.png')
  })

  it('never renders email-looking name in public profile header', async () => {
    apiGetMock.mockImplementation((url, config = {}) => {
      if (url === '/users/astro') {
        return Promise.resolve({
          data: {
            id: 10,
            username: 'astro',
            name: 'astro@example.com',
            bio: null,
          },
        })
      }

      if (url === '/users/astro/posts') {
        const kind = String(config?.params?.kind || 'roots')
        if (kind === 'roots') {
          return Promise.resolve({ data: makePostsResponse([], 0) })
        }
        return Promise.resolve({ data: makePostsResponse([], 0) })
      }

      throw new Error(`Unexpected GET ${url}`)
    })

    const router = makeRouter()
    await router.push('/u/astro')
    await router.isReady()

    const wrapper = mount(PublicProfileView, {
      global: {
        plugins: [router],
        stubs: {
          ObservationCard: { template: '<div class="obs-stub"></div>' },
          HashtagText: { props: ['content'], template: '<p>{{ content }}</p>' },
        },
      },
    })

    await flush()
    await flush()

    expect(wrapper.text()).not.toContain('astro@example.com')
    expect(wrapper.text()).toContain('@astro')
  })

  it('hides location on foreign public profile', async () => {
    authStoreMock.user = {
      id: 777,
      username: 'viewer',
    }

    apiGetMock.mockImplementation((url, config = {}) => {
      if (url === '/users/astro') {
        return Promise.resolve({
          data: {
            id: 10,
            username: 'astro',
            name: 'Astro User',
            location: 'Bratislava',
            bio: null,
          },
        })
      }

      if (url === '/users/astro/posts') {
        const kind = String(config?.params?.kind || 'roots')
        if (kind === 'roots') return Promise.resolve({ data: makePostsResponse([], 0) })
        return Promise.resolve({ data: makePostsResponse([], 0) })
      }

      throw new Error(`Unexpected GET ${url}`)
    })

    const router = makeRouter()
    await router.push('/u/astro')
    await router.isReady()

    const wrapper = mount(PublicProfileView, {
      global: {
        plugins: [router],
        stubs: {
          ObservationCard: { template: '<div class="obs-stub"></div>' },
          HashtagText: { props: ['content'], template: '<p>{{ content }}</p>' },
        },
      },
    })

    await flush()
    await flush()

    expect(wrapper.find('.meta .metaItem').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Lokalita:')
  })

  it('shows location on own public profile', async () => {
    authStoreMock.user = {
      id: 10,
      username: 'astro',
    }

    apiGetMock.mockImplementation((url, config = {}) => {
      if (url === '/users/astro') {
        return Promise.resolve({
          data: {
            id: 10,
            username: 'astro',
            name: 'Astro User',
            location: 'Bratislava',
            bio: null,
          },
        })
      }

      if (url === '/users/astro/posts') {
        const kind = String(config?.params?.kind || 'roots')
        if (kind === 'roots') return Promise.resolve({ data: makePostsResponse([], 0) })
        return Promise.resolve({ data: makePostsResponse([], 0) })
      }

      throw new Error(`Unexpected GET ${url}`)
    })

    const router = makeRouter()
    await router.push('/u/astro')
    await router.isReady()

    const wrapper = mount(PublicProfileView, {
      global: {
        plugins: [router],
        stubs: {
          ObservationCard: { template: '<div class="obs-stub"></div>' },
          HashtagText: { props: ['content'], template: '<p>{{ content }}</p>' },
        },
      },
    })

    await flush()
    await flush()

    expect(wrapper.find('.meta .metaItem').exists()).toBe(true)
    expect(wrapper.text()).toContain('Lokalita: Bratislava')
  })

  it('renders share action label and icon in profile header', async () => {
    apiGetMock.mockImplementation((url, config = {}) => {
      if (url === '/users/astro') {
        return Promise.resolve({
          data: {
            id: 10,
            username: 'astro',
            name: 'Astro User',
            bio: null,
          },
        })
      }

      if (url === '/users/astro/posts') {
        const kind = String(config?.params?.kind || 'roots')
        if (kind === 'roots') return Promise.resolve({ data: makePostsResponse([], 0) })
        return Promise.resolve({ data: makePostsResponse([], 0) })
      }

      throw new Error(`Unexpected GET ${url}`)
    })

    const router = makeRouter()
    await router.push('/u/astro')
    await router.isReady()

    const wrapper = mount(PublicProfileView, {
      global: {
        plugins: [router],
        stubs: {
          ObservationCard: { template: '<div class="obs-stub"></div>' },
          HashtagText: { props: ['content'], template: '<p>{{ content }}</p>' },
        },
      },
    })

    await flush()
    await flush()

    const shareButton = wrapper.find('.shareProfileBtn')
    expect(shareButton.exists()).toBe(true)
    expect(shareButton.text()).toContain('Zdieľať')
    expect(shareButton.find('.shareProfileBtn__icon').exists()).toBe(true)
  })
  it('requests public-only observations on own public profile', async () => {
    authStoreMock.user = {
      id: 10,
      username: 'astro',
    }

    apiGetMock.mockImplementation((url, config = {}) => {
      if (url === '/users/astro') {
        return Promise.resolve({
          data: {
            id: 10,
            username: 'astro',
            name: 'Astro User',
            bio: null,
          },
        })
      }

      if (url === '/users/astro/posts') {
        const kind = String(config?.params?.kind || 'roots')
        if (kind === 'roots') return Promise.resolve({ data: makePostsResponse([], 0) })
        return Promise.resolve({ data: makePostsResponse([], 0) })
      }

      throw new Error(`Unexpected GET ${url}`)
    })

    const router = makeRouter()
    await router.push('/u/astro')
    await router.isReady()

    mount(PublicProfileView, {
      global: {
        plugins: [router],
        stubs: {
          ObservationCard: { template: '<div class="obs-stub"></div>' },
          HashtagText: { props: ['content'], template: '<p>{{ content }}</p>' },
        },
      },
    })

    await flush()
    await flush()

    expect(listObservationsMock).toHaveBeenCalledWith(expect.objectContaining({
      user_id: 10,
      public_only: true,
      page: 1,
      per_page: 1,
    }))
  })

  it('renders polls and attached observations in the public profile post list', async () => {
    apiGetMock.mockImplementation((url, config = {}) => {
      if (url === '/users/astro') {
        return Promise.resolve({
          data: {
            id: 10,
            username: 'astro',
            name: 'Astro User',
            bio: null,
          },
        })
      }

      if (url === '/users/astro/posts') {
        const kind = String(config?.params?.kind || 'roots')
        if (kind === 'roots') {
          return Promise.resolve({
            data: makePostsResponse([
              {
                id: 701,
                content: 'Ktory objekt dnes?',
                created_at: '2026-03-05T12:00:00Z',
                poll: {
                  id: 91,
                  is_closed: false,
                  total_votes: 4,
                  ends_in_seconds: 3600,
                  my_vote_option_id: null,
                  chosen_option_id: null,
                  user_has_voted: false,
                  options: [
                    { id: 1, text: 'M42', percent: 50, votes_count: 2, is_winner: false },
                    { id: 2, text: 'M31', percent: 50, votes_count: 2, is_winner: false },
                  ],
                },
              },
              {
                id: 702,
                content: 'Pozorovanie: First Light',
                created_at: '2026-03-05T13:00:00Z',
                attached_observation: {
                  id: 55,
                  title: 'First Light',
                  media: [],
                  is_public: true,
                  user: { username: 'astro' },
                },
              },
            ], 2),
          })
        }

        return Promise.resolve({ data: makePostsResponse([], 0) })
      }

      throw new Error(`Unexpected GET ${url}`)
    })

    const router = makeRouter()
    await router.push('/u/astro')
    await router.isReady()

    const wrapper = mount(PublicProfileView, {
      global: {
        plugins: [router],
        stubs: {
          ObservationCard: {
            props: ['observation'],
            template: '<div data-testid="public-observation-stub">{{ observation.title }}</div>',
          },
          PollCard: {
            props: ['poll'],
            template: '<div data-testid="public-poll-stub">{{ poll.id }}</div>',
          },
          HashtagText: { props: ['content'], template: '<p>{{ content }}</p>' },
        },
      },
    })

    await flush()
    await flush()

    expect(wrapper.find('[data-testid="public-poll-stub"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="public-observation-stub"]').text()).toContain('First Light')

    const openButtons = wrapper.findAll('.postActions .ui-btn')
    expect(openButtons).toHaveLength(2)

    await openButtons[1].trigger('click')
    await flush()

    expect(router.currentRoute.value.fullPath).toBe('/observations/55')
  })
})
