import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FeedList from '@/components/FeedList.vue'
import api from '@/services/api'

const STORAGE_KEY = 'astrokomunita.feed.activeTab'

const toastInfoMock = vi.fn()
const toastErrorMock = vi.fn()

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

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    info: toastInfoMock,
    error: toastErrorMock,
    success: vi.fn(),
    warn: vi.fn(),
    show: vi.fn(),
  }),
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
  await nextTick()
}

const DropdownMenuStub = {
  name: 'DropdownMenu',
  props: ['items'],
  emits: ['select'],
  template: `
    <div class="dropdownMenuStub">
      <button
        v-for="item in items"
        :key="item.key"
        class="menu-item"
        type="button"
        @click="$emit('select', item)"
      >
        {{ item.label }}
      </button>
    </div>
  `,
}

function mountFeed() {
  return mount(FeedList, {
    attachTo: document.body,
    global: {
      stubs: {
        HashtagText: {
          props: ['content'],
          template: '<span>{{ content }}</span>',
        },
        DropdownMenu: DropdownMenuStub,
        PollCard: true,
        PostMediaImage: true,
        ShareModal: true,
      },
    },
  })
}

describe('FeedList full-quality download menu', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.post.mockReset()
    api.patch.mockReset()
    api.delete.mockReset()
    toastInfoMock.mockReset()
    toastErrorMock.mockReset()
    window.localStorage.removeItem(STORAGE_KEY)
    window.scrollTo = vi.fn()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('shows "Stiahnut v plnej kvalite" when post has image download url', async () => {
    api.get.mockResolvedValueOnce({
      data: {
        data: [
          {
            id: 1,
            content: 'Photo post',
            user: { id: 2, username: 'astro', name: 'Astro' },
            attachment_mime: 'image/jpeg',
            attachment_url: '/storage/posts/1/images/1/web.webp',
            attachment_download_url: '/api/media/1/download',
          },
        ],
        next_page_url: null,
      },
    })

    const wrapper = mountFeed()
    await flushPromises()

    expect(wrapper.text()).toContain('Stiahnut v plnej kvalite')
  })

  it('clicking the menu action opens download_url', async () => {
    api.get.mockResolvedValueOnce({
      data: {
        data: [
          {
            id: 1,
            content: 'Photo post',
            user: { id: 2, username: 'astro', name: 'Astro' },
            attachment_mime: 'image/jpeg',
            attachment_url: '/storage/posts/1/images/1/web.webp',
            attachment_download_url: '/api/media/1/download',
          },
        ],
        next_page_url: null,
      },
    })

    const openSpy = vi.fn()
    window.open = openSpy

    const wrapper = mountFeed()
    await flushPromises()

    const downloadButton = wrapper
      .findAll('button.menu-item')
      .find((button) => button.text() === 'Stiahnut v plnej kvalite')

    expect(downloadButton).toBeTruthy()
    await downloadButton.trigger('click')

    expect(toastInfoMock).toHaveBeenCalledWith('Stahujem...')
    expect(openSpy).toHaveBeenCalledTimes(1)
    expect(String(openSpy.mock.calls[0][0])).toContain('/api/media/1/download')
  })

  it('hides the menu action when post has no image', async () => {
    api.get.mockResolvedValueOnce({
      data: {
        data: [
          {
            id: 2,
            content: 'Text attachment',
            user: { id: 3, username: 'user', name: 'User' },
            attachment_mime: 'text/plain',
            attachment_url: '/storage/posts/2/file.txt',
            attachment_download_url: null,
          },
        ],
        next_page_url: null,
      },
    })

    const wrapper = mountFeed()
    await flushPromises()

    expect(wrapper.text()).not.toContain('Stiahnut v plnej kvalite')
  })
})
