import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import LatestArticlesWidget from './LatestArticlesWidget.vue'

const mockBlogPosts = vi.hoisted(() => ({
  widget: vi.fn(),
}))

vi.mock('@/services/blogPosts', () => ({
  blogPosts: mockBlogPosts,
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('LatestArticlesWidget', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    mockBlogPosts.widget.mockReset()
    mockBlogPosts.widget.mockResolvedValue({
      most_read: [
        { id: 1, title: 'Most read A', slug: 'most-read-a', thumbnail_url: null, views: 50, created_at: '2026-02-16T10:00:00Z' },
        { id: 2, title: 'Most read B', slug: 'most-read-b', thumbnail_url: null, views: 40, created_at: '2026-02-16T09:00:00Z' },
        { id: 3, title: 'Most read C', slug: 'most-read-c', thumbnail_url: null, views: 30, created_at: '2026-02-16T08:00:00Z' },
      ],
      latest: [
        { id: 4, title: 'Latest A', slug: 'latest-a', thumbnail_url: null, views: 5, created_at: '2026-02-16T11:00:00Z' },
        { id: 5, title: 'Latest B', slug: 'latest-b', thumbnail_url: null, views: 4, created_at: '2026-02-16T10:30:00Z' },
        { id: 6, title: 'Latest C', slug: 'latest-c', thumbnail_url: null, views: 3, created_at: '2026-02-16T10:10:00Z' },
      ],
      generated_at: '2026-02-16T11:30:00Z',
    })
  })

  afterEach(() => {
    vi.runOnlyPendingTimers()
    vi.useRealTimers()
  })

  it('calls widget API once on mount', async () => {
    mount(LatestArticlesWidget, {
      props: {
        switchIntervalMs: 1000,
        refetchIntervalMs: 300000,
      },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="String(to)"><slot /></a>',
          },
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(mockBlogPosts.widget).toHaveBeenCalledTimes(1)
  })

  it('switches mode after interval without duplicate API calls', async () => {
    const wrapper = mount(LatestArticlesWidget, {
      props: {
        switchIntervalMs: 1000,
        refetchIntervalMs: 300000,
      },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="String(to)"><slot /></a>',
          },
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(wrapper.vm.mode).toBe('most_read')
    expect(wrapper.text()).toContain('Most read A')
    expect(mockBlogPosts.widget).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(1000)
    await nextTick()

    expect(wrapper.vm.mode).toBe('latest')
    expect(wrapper.text()).toContain('Latest A')
    expect(mockBlogPosts.widget).toHaveBeenCalledTimes(1)
  })

  it('uses bundled payload and skips initial widget fetch', async () => {
    const wrapper = mount(LatestArticlesWidget, {
      props: {
        initialPayload: {
          most_read: [
            { id: 9, title: 'Bundled most read', slug: 'bundled-most-read', thumbnail_url: null, views: 20, created_at: '2026-02-16T10:00:00Z' },
          ],
          latest: [
            { id: 10, title: 'Bundled latest', slug: 'bundled-latest', thumbnail_url: null, views: 2, created_at: '2026-02-16T11:00:00Z' },
          ],
        },
        switchIntervalMs: 1000,
        refetchIntervalMs: 300000,
      },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="String(to)"><slot /></a>',
          },
        },
      },
    })

    await flushPromises()
    await nextTick()

    expect(mockBlogPosts.widget).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Bundled most read')
  })
})
