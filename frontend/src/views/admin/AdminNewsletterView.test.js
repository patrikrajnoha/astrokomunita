import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminNewsletterView from '@/views/admin/AdminNewsletterView.vue'

const getNewsletterPreviewMock = vi.hoisted(() => vi.fn())
const getNewsletterRunsMock = vi.hoisted(() => vi.fn())
const sendNewsletterMock = vi.hoisted(() => vi.fn())
const updateNewsletterFeaturedEventsMock = vi.hoisted(() => vi.fn())
const getEventsMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api/admin/newsletter', () => ({
  getNewsletterPreview: (...args) => getNewsletterPreviewMock(...args),
  getNewsletterRuns: (...args) => getNewsletterRunsMock(...args),
  sendNewsletter: (...args) => sendNewsletterMock(...args),
  updateNewsletterFeaturedEvents: (...args) => updateNewsletterFeaturedEventsMock(...args),
}))

vi.mock('@/services/api/admin/events', () => ({
  getEvents: (...args) => getEventsMock(...args),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('AdminNewsletterView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    getNewsletterPreviewMock.mockResolvedValue({
      data: {
        data: {
          week: { start: '2026-02-23', end: '2026-03-01' },
          top_events: [{ id: 11, title: 'Lunar eclipse' }],
          top_articles: [{ id: 22, title: 'Sky guide', views: 120 }],
          astronomical_tip: 'Use darker skies.',
        },
        meta: { max_featured_events: 10 },
      },
    })

    getNewsletterRunsMock.mockResolvedValue({
      data: {
        data: [],
      },
    })

    getEventsMock.mockResolvedValue({
      data: {
        data: [{ id: 11, title: 'Lunar eclipse', start_at: '2026-02-24T19:00:00Z' }],
      },
    })
  })

  it('loads and renders preview payload from API', async () => {
    const wrapper = mount(AdminNewsletterView)
    await flush()
    await flush()

    expect(getNewsletterPreviewMock).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Lunar eclipse')
    expect(wrapper.text()).toContain('Sky guide')
  })
})
