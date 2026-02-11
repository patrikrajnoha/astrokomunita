import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FeedList from '@/components/FeedList.vue'
import api from '@/services/api'

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

describe('FeedList tabs', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.post.mockReset()
    api.patch.mockReset()
    api.delete.mockReset()

    api.get.mockImplementation((url) => {
      if (String(url).startsWith('/feed/astrobot')) {
        return Promise.resolve({
          data: {
            data: [{ id: 200, content: 'AstroBot content', user: { username: 'astro', name: 'Astro Bot' } }],
            next_page_url: null,
          },
        })
      }

      return Promise.resolve({
        data: {
          data: [{ id: 100, content: 'For you content', user: { username: 'user', name: 'User Name' } }],
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
    const wrapper = mount(FeedList, {
      attachTo: document.body,
      global: {
        stubs: {
          HashtagText: {
            props: ['content'],
            template: '<span>{{ content }}</span>',
          },
          DropdownMenu: true,
          PostMediaImage: true,
          ShareModal: true,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('For you content')
    expect(wrapper.find('#feed-tab-for-you').attributes('aria-selected')).toBe('true')

    await wrapper.get('#feed-tab-astrobot').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('AstroBot content')
    expect(wrapper.find('#feed-tab-astrobot').attributes('aria-selected')).toBe('true')
  })
})
