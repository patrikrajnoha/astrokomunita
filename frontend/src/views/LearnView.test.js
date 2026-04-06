import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LearnView from '@/views/LearnView.vue'

const blogPostsMock = vi.hoisted(() => ({
  listPublic: vi.fn(),
  listTagsPublic: vi.fn(),
}))

vi.mock('@/services/blogPosts', () => ({
  blogPosts: blogPostsMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('LearnView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    blogPostsMock.listTagsPublic.mockResolvedValue([])
  })

  it('renders article cards with very long title and excerpt text', async () => {
    const longWord = `astro${'x'.repeat(320)}`
    blogPostsMock.listPublic.mockResolvedValue({
      data: [
        {
          id: 1,
          slug: 'featured',
          title: `Featured ${longWord}`,
          content: `<p>${longWord}</p>`,
          published_at: '2026-03-20T10:00:00Z',
          user: { name: 'Redakcia' },
          cover_image_url: null,
        },
        {
          id: 2,
          slug: 'long-card',
          title: `Card ${longWord}`,
          content: `<p>${longWord}</p>`,
          published_at: '2026-03-20T10:00:00Z',
          user: { name: 'Redakcia' },
          cover_image_url: null,
        },
      ],
      total: 2,
      current_page: 1,
      last_page: 1,
    })

    const wrapper = mount(LearnView, {
      global: {
        stubs: {
          PageHeader: { template: '<div class="page-header-stub" />' },
          RouterLink: { template: '<a><slot /></a>' },
        },
        mocks: {
          $router: {
            push: vi.fn(),
          },
        },
      },
    })

    await flush()
    await flush()

    expect(wrapper.find('.featured').exists()).toBe(true)
    expect(wrapper.find('.postItem').exists()).toBe(true)
    expect(wrapper.get('.postItem h3').text()).toContain('Card astro')
    expect(wrapper.get('.postItem__excerpt').text()).toContain('...')
  })

  it('defines overflow-safe typography guards for article cards', () => {
    const css = readFileSync(resolve(process.cwd(), 'src/views/learn/LearnView.css'), 'utf8')

    expect(css).toContain('overflow-wrap: anywhere;')
    expect(css).toContain('.postItem__body')
    expect(css).toContain('min-width: 0;')
    expect(css).toContain('.featured h2')
    expect(css).toContain('word-break: break-word;')
  })
})
