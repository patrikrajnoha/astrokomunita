import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import PostActionBar from '@/components/PostActionBar.vue'
import PostDetailView from './PostDetailView.vue'

const authMock = vi.hoisted(() => ({
  user: {
    id: 7,
    name: 'Admin',
    username: 'admin',
    is_admin: true,
    role: 'admin',
  },
  isAuthed: true,
  csrf: vi.fn(async () => {}),
  fetchUser: vi.fn(async () => null),
}))

const bookmarksMock = vi.hoisted(() => ({
  hydrateFromPosts: vi.fn(),
  isLoading: vi.fn(() => false),
  setBookmarked: vi.fn(),
  toggleBookmark: vi.fn(async () => false),
}))

const apiMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  defaults: {
    baseURL: '/api',
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/stores/bookmarks', () => ({
  useBookmarksStore: () => bookmarksMock,
}))

vi.mock('@/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: vi.fn(async () => true),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  }),
}))

vi.mock('@/services/api', () => ({
  default: apiMock,
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/posts/:id', name: 'post-detail', component: PostDetailView },
      { path: '/', name: 'home', component: { template: '<div>home</div>' } },
      { path: '/u/:username', name: 'public-profile', component: { template: '<div>profile</div>' } },
      { path: '/events/:id', name: 'event-detail', component: { template: '<div>event</div>' } },
    ],
  })
}

function makePost(overrides = {}) {
  return {
    id: 70,
    user_id: 7,
    parent_id: null,
    content: 'Root post',
    created_at: '2026-03-20T18:00:00Z',
    likes_count: 0,
    liked_by_me: false,
    is_bookmarked: false,
    attachment_url: '/uploads/root.jpg',
    attachment_download_url: '/uploads/root-original.jpg',
    attachment_mime: 'image/jpeg',
    user: {
      id: 7,
      name: 'Admin',
      username: 'admin',
    },
    ...overrides,
  }
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

async function mountView(postPayload) {
  apiMock.get.mockImplementation(async (url) => {
    if (url === '/posts/70') {
      return {
        data: {
          post: postPayload,
          root: postPayload,
          thread: [],
          replies: [],
        },
      }
    }

    throw new Error(`Unexpected GET ${url}`)
  })

  const router = makeRouter()
  await router.push('/posts/70')
  await router.isReady()

  const wrapper = mount(PostDetailView, {
    global: {
      plugins: [router],
      stubs: {
        ReplyComposer: { template: '<div class="reply-composer-stub"></div>' },
        ShareModal: { template: '<div class="share-modal-stub"></div>' },
        PollCard: { template: '<div class="poll-stub"></div>' },
        HashtagText: { props: ['content'], template: '<div class="hashtag-stub">{{ content }}</div>' },
        UserAvatar: { template: '<div class="avatar-stub"></div>' },
        DropdownMenu: {
          props: ['items'],
          template: '<div class="dropdown-stub">{{ items.length }}</div>',
        },
        PostMediaImage: {
          props: ['src'],
          template: '<img class="media-image-stub" :src="src" alt="stub" />',
        },
        AsyncState: { template: '<div class="async-state-stub"></div>' },
        InlineStatus: { template: '<div class="inline-status-stub"></div>' },
      },
    },
  })

  await flush()
  await flush()
  return wrapper
}

describe('PostDetailView post menu parity', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    authMock.user = {
      id: 7,
      name: 'Admin',
      username: 'admin',
      is_admin: true,
      role: 'admin',
    }
    authMock.isAuthed = true
  })

  it('shows delete, pin and download for own admin post and hides report', async () => {
    const wrapper = await mountView(makePost())
    const actionBar = wrapper.getComponent(PostActionBar)
    const menuKeys = actionBar.props('menuItems').map((item) => item.key)

    expect(menuKeys).toEqual(expect.arrayContaining(['download_original', 'delete', 'pin']))
    expect(menuKeys).not.toContain('report')

    wrapper.unmount()
  })

  it('shows report but not delete or pin for a non-owner regular viewer', async () => {
    authMock.user = {
      id: 9,
      name: 'Viewer',
      username: 'viewer',
      is_admin: false,
      role: 'user',
    }

    const wrapper = await mountView(makePost({
      user_id: 12,
      user: {
        id: 12,
        name: 'Author',
        username: 'author',
      },
    }))
    const actionBar = wrapper.getComponent(PostActionBar)
    const menuKeys = actionBar.props('menuItems').map((item) => item.key)

    expect(menuKeys).toContain('report')
    expect(menuKeys).not.toContain('delete')
    expect(menuKeys).not.toContain('pin')

    wrapper.unmount()
  })
})
