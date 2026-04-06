import { readFileSync } from 'node:fs'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LearnDetailView from '@/views/LearnDetailView.vue'

const blogPostsMock = vi.hoisted(() => ({
  getPublic: vi.fn(),
  getRelated: vi.fn(),
}))

const blogCommentsMock = vi.hoisted(() => ({
  list: vi.fn(),
  create: vi.fn(),
  remove: vi.fn(),
}))

const authStoreMock = vi.hoisted(() => ({
  isAuthed: false,
  user: null,
}))

vi.mock('@/services/blogPosts', () => ({
  blogPosts: blogPostsMock,
}))

vi.mock('@/services/blogComments', () => ({
  blogComments: blogCommentsMock,
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authStoreMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('LearnDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authStoreMock.isAuthed = false
    authStoreMock.user = null
    blogPostsMock.getRelated.mockResolvedValue([])
    blogCommentsMock.list.mockResolvedValue({
      data: [
        {
          id: 11,
          user: { name: 'Test user' },
          user_id: 2,
          depth: 0,
          created_at: '2026-03-20T12:00:00Z',
          content: 'Komentar',
        },
      ],
      total: 1,
      current_page: 1,
      last_page: 1,
    })
  })

  it('renders long title and rich long-form content without crashing layout structure', async () => {
    const longWord = `astro${'y'.repeat(420)}`
    blogPostsMock.getPublic.mockResolvedValue({
      id: 7,
      slug: 'dlhy-clanok',
      title: `Detail ${longWord}`,
      content: `<p>${longWord}</p><h2>${longWord}</h2><ul><li>${longWord}</li></ul><pre>${longWord}</pre><p><a href="https://example.com/${longWord}">${longWord}</a></p>`,
      published_at: '2026-03-20T12:00:00Z',
      user: { name: 'Redakcia' },
      tags: [{ id: 1, name: 'Astronomia' }],
      views: 42,
      cover_image_url: null,
    })

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/articles', component: { template: '<div>list</div>' } },
        { path: '/articles/:slug', component: LearnDetailView },
      ],
    })
    await router.push('/articles/dlhy-clanok')
    await router.isReady()

    const wrapper = mount(LearnDetailView, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(blogPostsMock.getPublic).toHaveBeenCalledWith('dlhy-clanok')
    expect(wrapper.get('.header h1').text()).toContain('Detail astro')
    expect(wrapper.get('.content').text()).toContain('astro')
    expect(wrapper.find('.comments').exists()).toBe(true)
  })

  it('defines overflow-safe deep typography rules for rendered article HTML', () => {
    const css = readFileSync('src/views/learnDetail/LearnDetailView.css', 'utf8')

    expect(css).toContain('.content :deep(p)')
    expect(css).toContain('.content :deep(pre)')
    expect(css).toContain('.content :deep(code)')
    expect(css).toContain('.content :deep(img)')
    expect(css).toContain('overflow-wrap: anywhere;')
    expect(css).toContain('word-break: break-word;')
  })
})
