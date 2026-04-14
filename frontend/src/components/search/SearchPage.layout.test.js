import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchPage from '@/components/search/SearchPage.vue'
import api from '@/services/api'

const routerReplace = vi.fn()

vi.mock('vue-router', () => ({
  RouterLink: {
    props: ['to'],
    template: '<a><slot /></a>',
  },
  useRoute: () => ({
    query: {},
  }),
  useRouter: () => ({
    replace: routerReplace,
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
  },
}))

const discoveryResponse = {
  data: {
    data: {
      trending: { events: [], posts: [] },
      news: { posts: [], articles: [] },
      events: { events: [], posts: [] },
      keywords: [],
    },
  },
}

const globalResponse = {
  data: {
    data: {
      users: [],
      posts: [],
      events: [],
      articles: [],
      hashtags: [],
      keywords: [],
    },
  },
}

async function flush() {
  await Promise.resolve()
  await Promise.resolve()
}

describe('SearchPage layout shell', () => {
  beforeEach(() => {
    routerReplace.mockReset()
    api.get.mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/search/discovery') return Promise.resolve(discoveryResponse)
      if (url === '/search/global') return Promise.resolve(globalResponse)
      return Promise.resolve({ data: { data: {} } })
    })
  })

  it('keeps route-level structure without reintroducing a competing page shell', async () => {
    const wrapper = mount(SearchPage, {
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    })

    await flush()

    const root = wrapper.get('[data-testid="search-page-root"]')
    const shell = wrapper.get('[data-testid="search-page-shell"]')
    const toolbar = wrapper.get('[data-testid="search-page-toolbar"]')

    expect(root.exists()).toBe(true)
    expect(shell.exists()).toBe(true)
    expect(toolbar.exists()).toBe(true)

    expect(root.classes()).not.toContain('min-h-screen')
    expect(root.classes()).not.toContain('bg-[var(--bg-app)]')
    expect(root.classes()).not.toContain('overflow-x-hidden')

    expect(shell.classes()).toContain('w-full')
    expect(shell.classes()).toContain('min-w-0')
    expect(shell.classes()).toContain('px-3')
    expect(shell.classes()).toContain('sm:px-4')
    expect(shell.classes()).toContain('sm:py-5')
    expect(shell.classes()).not.toContain('max-w-[920px]')
  })

  it('repairs mojibake in top events titles', async () => {
    const mojibakeTitle = `Meteorick${String.fromCharCode(0x00c3, 0x0192, 0x00c2, 0x00bd)} roj Lyrid`

    api.get.mockImplementation((url) => {
      if (url === '/search/discovery') {
        return Promise.resolve({
          data: {
            data: {
              trending: {
                events: [
                  {
                    id: 101,
                    title: mojibakeTitle,
                    summary: 'Vrchol aktivity koncom apríla.',
                    start_at: '2026-04-22T19:00:00Z',
                  },
                ],
                posts: [],
              },
              news: { posts: [], articles: [] },
              events: { events: [], posts: [] },
              keywords: [],
            },
          },
        })
      }
      if (url === '/search/global') return Promise.resolve(globalResponse)
      return Promise.resolve({ data: { data: {} } })
    })

    const wrapper = mount(SearchPage, {
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    })

    await flush()

    expect(wrapper.text()).toContain('Meteorický roj Lyrid')
    expect(wrapper.text()).not.toContain(mojibakeTitle)
  })
})
