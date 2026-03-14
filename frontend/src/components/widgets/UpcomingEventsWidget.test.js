import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import UpcomingEventsWidget from './UpcomingEventsWidget.vue'

const mockGetUpcomingEventsWidget = vi.hoisted(() => vi.fn())

vi.mock('@/services/widgets', () => ({
  getUpcomingEventsWidget: mockGetUpcomingEventsWidget,
}))

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('UpcomingEventsWidget', () => {
  beforeEach(() => {
    mockGetUpcomingEventsWidget.mockReset()
    mockGetUpcomingEventsWidget.mockResolvedValue({
      items: [
        { id: 11, title: 'Event A', slug: null, start_at: '2026-02-20T23:30:00Z' },
        { id: 12, title: 'Event B', slug: null, start_at: '2026-02-21T18:00:00Z' },
        { id: 13, title: 'Event C', slug: null, start_at: '2026-02-22T18:00:00Z' },
        { id: 14, title: 'Event D', slug: null, start_at: '2026-02-23T18:00:00Z' },
      ],
      source: {
        label: 'Databaza udalosti',
      },
      generated_at: '2026-02-16T12:00:00Z',
    })
  })

  it('calls API once on mount and renders event links with source metadata', async () => {
    const wrapper = mount(UpcomingEventsWidget, {
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

    expect(mockGetUpcomingEventsWidget).toHaveBeenCalledTimes(1)
    expect(wrapper.findAll('.eventItem')).toHaveLength(4)

    const showMoreLink = wrapper.find('a[href="/events"]')
    expect(showMoreLink.exists()).toBe(true)
    expect(showMoreLink.text()).toContain('Vsetky udalosti')
    expect(wrapper.find('a[href="/events/11"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Zdroj: Databaza udalosti')
    expect(wrapper.text()).toMatch(/21\.\s?2\.\s?2026/)
  })

  it('uses bundled payload and skips standalone widget fetch', async () => {
    const wrapper = mount(UpcomingEventsWidget, {
      props: {
        initialPayload: {
          items: [
            { id: 21, title: 'Bundled event', slug: null, start_at: '2026-02-24T18:00:00Z' },
          ],
          source: {
            label: 'Databaza udalosti',
          },
          generated_at: '2026-02-16T12:00:00Z',
        },
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

    expect(mockGetUpcomingEventsWidget).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Bundled event')
  })
})
