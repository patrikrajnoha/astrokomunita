import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FeedList from '@/components/FeedList.vue'
import api from '@/services/api'

const STORAGE_KEY = 'astrokomunita.feed.activeTab'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    defaults: { baseURL: 'http://127.0.0.1:8000/api' },
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    isAuthed: false,
    user: null,
    csrf: vi.fn(),
  }),
}))

vi.mock('@/stores/bookmarks', () => ({
  useBookmarksStore: () => ({
    isLoading: () => false,
    setBookmarked: vi.fn(),
    toggleBookmark: vi.fn(),
    hydrateFromPosts: vi.fn(),
  }),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
  await nextTick()
}

function mountFeed(stubs = {}) {
  return mount(FeedList, {
    attachTo: document.body,
    global: {
      stubs: {
        HashtagText: {
          props: ['content'],
          template: '<span class="hashtag-text">{{ content }}</span>',
        },
        DropdownMenu: true,
        PollCard: true,
        PostMediaImage: true,
        ShareModal: true,
        ...stubs,
      },
    },
  })
}

describe('FeedList tabs', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.post.mockReset()
    api.patch.mockReset()
    api.delete.mockReset()
    window.localStorage.removeItem(STORAGE_KEY)

    api.get.mockImplementation((url) => {
      if (String(url).startsWith('/astro-feed')) {
        return Promise.resolve({
          data: {
            data: [
              {
                id: 200,
                content: 'AstroBot content',
                author_kind: 'bot',
                bot_identity: 'kozmo',
                user: { username: 'astro', name: 'Astro Bot' },
              },
            ],
            next_page_url: null,
          },
        })
      }

      return Promise.resolve({
        data: {
          data: [
            { id: 100, content: 'For you content', user: { username: 'user', name: 'User Name' } },
          ],
          next_page_url: null,
        },
      })
    })

    window.scrollTo = vi.fn()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('switching tab changes feed content and aria-selected', async () => {
    const wrapper = mountFeed()
    await flushPromises()

    expect(wrapper.text()).toContain('For you content')
    expect(wrapper.find('#feed-tab-for-you').attributes('aria-selected')).toBe('true')

    await wrapper.get('#feed-tab-astrobot').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('AstroBot content')
    expect(wrapper.find('#feed-tab-astrobot').attributes('aria-selected')).toBe('true')
  })

  it('persists selected home feed tab to localStorage', async () => {
    const wrapper = mountFeed()
    await flushPromises()

    await wrapper.get('#feed-tab-astrobot').trigger('click')
    await flushPromises()

    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('astrobot')
  })

  it('renders bookmark state from post payload', async () => {
    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 101,
              content: 'Saved post',
              is_bookmarked: true,
              user: { username: 'user', name: 'User Name' },
            },
          ],
          next_page_url: null,
        },
      }),
    )

    const wrapper = mountFeed()
    await flushPromises()

    expect(wrapper.find('.action-btn--bookmark').classes()).toContain('action-btn--bookmarked')
  })

  it('renders bot badge, source label and source link', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'astrobot')

    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 301,
              author_kind: 'bot',
              bot_identity: 'kozmo',
              content: 'Wikipedia On This Day item https://en.wikipedia.org/wiki/Moon',
              user: { username: 'kozmo', name: 'Kozmo' },
            },
          ],
          next_page_url: null,
        },
      }),
    )

    const wrapper = mountFeed()
    await flushPromises()

    expect(wrapper.text()).toContain('Kozmo')
    expect(wrapper.text()).toContain('Wikipedia')
    expect(wrapper.text()).toContain('Zdroj: Wikipedia')
    expect(wrapper.find('.source-link').attributes('href')).toBe(
      'https://en.wikipedia.org/wiki/Moon',
    )
  })

  it('collapses long bot content and toggles show-more state', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'astrobot')

    const longContent = `Start ${'x'.repeat(805)} END_MARK`

    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 302,
              author_kind: 'bot',
              bot_identity: 'kozmo',
              content: longContent,
              user: { username: 'kozmo', name: 'Kozmo' },
            },
          ],
          next_page_url: null,
        },
      }),
    )

    const wrapper = mountFeed()
    await flushPromises()

    expect(wrapper.text()).toContain('Zobrazit viac')
    expect(wrapper.text()).not.toContain('END_MARK')

    await wrapper.get('.show-more-btn').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Zobrazit menej')
    expect(wrapper.text()).toContain('END_MARK')
  })

  it('renders stela preview from attachments array', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'astrobot')

    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 401,
              author_kind: 'bot',
              bot_identity: 'stela',
              content: 'NASA APOD',
              attachments: [{ type: 'image', url: 'https://images.nasa.gov/apod.jpg' }],
              user: { username: 'stela', name: 'Stela' },
            },
          ],
          next_page_url: null,
        },
      }),
    )

    const wrapper = mountFeed({
      PostMediaImage: {
        props: ['src'],
        template: '<img class="media-probe" :data-src="src" />',
      },
    })
    await flushPromises()

    expect(wrapper.find('.media-probe').exists()).toBe(true)
    expect(wrapper.find('.media-probe').attributes('data-src')).toBe(
      'https://images.nasa.gov/apod.jpg',
    )
  })
})
