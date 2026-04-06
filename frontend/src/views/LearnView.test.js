import { readFileSync } from 'node:fs'
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
          cover_image_url: '/images/card-cover.jpg',
        },
        {
          id: 3,
          slug: 'long-card-no-thumb',
          title: `No thumb ${longWord}`,
          content: `<p>${longWord}</p>`,
          published_at: '2026-03-20T10:00:00Z',
          user: { name: 'Redakcia' },
          cover_image_url: null,
        },
      ],
      total: 3,
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
    expect(wrapper.findAll('.postItem')).toHaveLength(2)
    expect(wrapper.find('.postItem .postItem__thumb').exists()).toBe(true)
    expect(wrapper.find('.postItem .postItem__content').exists()).toBe(true)
    expect(wrapper.find('.postItem .postItem__footer .postItem__open').exists()).toBe(true)
    expect(wrapper.find('.postItem--noThumb').exists()).toBe(true)
    expect(wrapper.get('.postItem h3').text()).toContain('Card astro')
    expect(wrapper.get('.postItem__excerpt').text()).toContain('...')
  })

  it('defines overflow-safe typography guards for article cards', () => {
    const css = readFileSync('src/views/learn/LearnView.css', 'utf8')

    expect(css).toContain('overflow-wrap: anywhere;')
    expect(css).toContain('grid-template-columns: minmax(0, 84px) minmax(0, 1fr);')
    expect(css).toContain('.postItem__content')
    expect(css).toContain('.postItem__footer')
    expect(css).toContain('.postItem--noThumb')
    expect(css).toContain('min-width: 0;')
    expect(css).toContain('.postItem__thumb')
    expect(css).toContain('display: block;')
  })
})
