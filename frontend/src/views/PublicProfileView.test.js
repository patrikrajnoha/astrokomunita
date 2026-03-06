import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import PublicProfileView from './PublicProfileView.vue'

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

    listObservationsMock.mockResolvedValue({
      data: {
        data: [],
        total: 0,
        current_page: 1,
        last_page: 1,
      },
    })
  })

  it('renders default avatar fallback and bot cover fallback when bot media is missing', async () => {
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
    expect(headerAvatar.find('.default-avatar').exists()).toBe(true)
    expect(headerAvatar.find('img.user-avatar-media').exists()).toBe(false)
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
})
