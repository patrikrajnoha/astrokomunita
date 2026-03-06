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
        PostMediaVideo: {
          props: ['src', 'type'],
          template:
            '<div class="post-video-stub"><video class="post-video"><source :src="src" :type="type" /></video></div>',
        },
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
    const wrapper = mountFeed({
      DropdownMenu: {
        props: ['items'],
        emits: ['select'],
        template:
          '<div class="dropdown-stub"><button v-for="item in items" :key="item.key" :data-key="item.key" @click="$emit(\'select\', item)">{{ item.label }}</button></div>',
      },
    })
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

  it('renders mp4 attachments as inline video players', async () => {
    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 901,
              content: 'Video attachment',
              attachment_url: '/api/media/901',
              attachment_mime: 'video/mp4',
              attachment_original_name: 'mars-video.mp4',
              user: { username: 'stellarbot', name: 'Stela' },
              author_kind: 'bot',
              bot_identity: 'stela',
            },
          ],
          next_page_url: null,
        },
      }),
    )

    const wrapper = mountFeed()
    await flushPromises()

    const video = wrapper.find('video.post-video')
    expect(video.exists()).toBe(true)
    expect(video.find('source').attributes('src')).toContain('/api/media/901')
    expect(video.find('source').attributes('type')).toBe('video/mp4')
    expect(wrapper.find('.file-attachment').exists()).toBe(false)
  })

  it('renders bot source label and source link from post meta only', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'astrobot')

    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 301,
              author_kind: 'bot',
              bot_identity: 'kozmo',
              content: 'Any body text',
              meta: {
                bot_source_label: 'Wikipedia On This Day',
                source_url: 'https://en.wikipedia.org/wiki/Moon',
              },
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
    expect(wrapper.text()).toContain('Wikipedia On This Day')
    expect(wrapper.text()).toContain('Zdroj: Wikipedia On This Day')
    expect(wrapper.find('.source-link').attributes('href')).toBe(
      'https://en.wikipedia.org/wiki/Moon',
    )
  })

  it('renders BOT verification badge in post header for bot posts', async () => {
    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 305,
              author_kind: 'bot',
              bot_identity: 'kozmo',
              content: 'Bot badge content',
              user: { username: 'kozmo', name: 'Kozmo' },
            },
          ],
          next_page_url: null,
        },
      }),
    )

    const wrapper = mountFeed()
    await flushPromises()

    const badge = wrapper.find('.author-bot-badge')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('BOT')
    expect(wrapper.find('.post-avatar .default-avatar').exists()).toBe(true)
  })

  it('falls back to generic Bot label for legacy posts without meta', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'astrobot')

    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 303,
              author_kind: 'bot',
              bot_identity: 'kozmo',
              content: 'Wikipedia https://en.wikipedia.org/wiki/Moon',
              user: { username: 'kozmo', name: 'Kozmo' },
            },
          ],
          next_page_url: null,
        },
      }),
    )

    const wrapper = mountFeed()
    await flushPromises()

    expect(wrapper.find('.bot-source-label').text()).toBe('Bot')
    expect(wrapper.find('.source-attribution').text()).toBe('Zdroj: Bot')
    expect(wrapper.find('.source-link').exists()).toBe(false)
  })

  it('toggles bot content between translated and original text from meta', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'astrobot')

    api.get.mockImplementationOnce(() =>
      Promise.resolve({
        data: {
          data: [
            {
              id: 304,
              author_kind: 'bot',
              bot_identity: 'kozmo',
              content: 'legacy body',
              meta: {
                original_title: 'EN title',
                original_content: 'EN body content',
                translated_title: 'SK titulok',
                translated_content: 'SK obsah',
                used_translation: true,
              },
              user: { username: 'kozmo', name: 'Kozmo' },
            },
          ],
          next_page_url: null,
        },
      }),
    )

    const wrapper = mountFeed()
    await flushPromises()

    expect(wrapper.text()).toContain('SK titulok')
    expect(wrapper.text()).toContain('SK obsah')
    expect(wrapper.text()).not.toContain('EN body content')

    const post = wrapper.vm.currentFeed.items[0]
    wrapper.vm.onMenuAction({ key: 'variant_original' }, post)
    await flushPromises()

    expect(wrapper.text()).toContain('EN title')
    expect(wrapper.text()).toContain('EN body content')
    expect(wrapper.text()).not.toContain('SK obsah')
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
