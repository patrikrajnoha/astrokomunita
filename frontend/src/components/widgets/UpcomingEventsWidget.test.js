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
        { id: 11, title: 'Event A', type: 'meteor_shower', slug: null, start_at: '2026-02-21T18:00:00Z' },
        { id: 12, title: 'Event B', type: 'eclipse_solar', slug: null, start_at: '2026-02-22T18:00:00Z' },
        { id: 13, title: 'Event C', type: 'aurora', slug: null, start_at: '2026-02-23T18:00:00Z' },
        { id: 14, title: 'Event D', type: null, slug: null, start_at: '2026-02-24T18:00:00Z' },
      ],
      generated_at: '2026-02-16T12:00:00Z',
    })
  })

  it('calls API once on mount and renders event rows with date, icon and title', async () => {
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

    // Event links
    expect(wrapper.find('a[href="/events/11"]').exists()).toBe(true)

    // Compact date format (sk-SK — test env may render numeric month)
    expect(wrapper.text()).toMatch(/21\. (feb|2\.)/)

    // Type icon for meteor_shower
    expect(wrapper.text()).toContain('☄️')

    // Show all footer link
    const showMoreLink = wrapper.find('a[href="/events"]')
    expect(showMoreLink.exists()).toBe(true)
    expect(showMoreLink.text()).toContain('Zobraziť všetko')

    // No source metadata
    expect(wrapper.text()).not.toContain('Databáza udalostí')
    expect(wrapper.text()).not.toContain('Zdroj')
  })

  it('uses bundled payload and skips standalone widget fetch', async () => {
    const wrapper = mount(UpcomingEventsWidget, {
      props: {
        initialPayload: {
          items: [
            { id: 21, title: 'Bundled event', type: 'aurora', slug: null, start_at: '2026-02-24T18:00:00Z' },
          ],
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
