import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminFeaturedEventsView from '@/views/admin/AdminFeaturedEventsView.vue'

const getFeaturedEventsMock = vi.hoisted(() => vi.fn())
const createFeaturedEventMock = vi.hoisted(() => vi.fn())
const updateFeaturedEventMock = vi.hoisted(() => vi.fn())
const deleteFeaturedEventMock = vi.hoisted(() => vi.fn())
const forceFeaturedEventsPopupMock = vi.hoisted(() => vi.fn())
const updateFeaturedPopupSettingsMock = vi.hoisted(() => vi.fn())
const applyFallbackAsFeaturedMock = vi.hoisted(() => vi.fn())
const getEventsMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api/admin/featuredEvents', () => ({
  getFeaturedEvents: (...args) => getFeaturedEventsMock(...args),
  createFeaturedEvent: (...args) => createFeaturedEventMock(...args),
  updateFeaturedEvent: (...args) => updateFeaturedEventMock(...args),
  deleteFeaturedEvent: (...args) => deleteFeaturedEventMock(...args),
  forceFeaturedEventsPopup: (...args) => forceFeaturedEventsPopupMock(...args),
  updateFeaturedPopupSettings: (...args) => updateFeaturedPopupSettingsMock(...args),
  applyFallbackAsFeatured: (...args) => applyFallbackAsFeaturedMock(...args),
}))

vi.mock('@/services/api/admin/events', () => ({
  getEvents: (...args) => getEventsMock(...args),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function normalizeText(value = '') {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

describe('AdminFeaturedEventsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    getFeaturedEventsMock.mockResolvedValue({
      data: {
        month: '2026-02',
        selection_mode: 'fallback',
        data: [
          {
            id: 5,
            event_id: 12,
            position: 0,
            is_active: true,
            event: { id: 12, title: 'Lunar Eclipse' },
          },
        ],
        fallback_preview: [
          {
            id: 18,
            title: 'Meteor Shower',
            start_at: '2026-02-20T20:00:00+00:00',
            fallback_score: 76,
          },
        ],
        resolved_events: [
          {
            id: 18,
            title: 'Meteor Shower',
            start_at: '2026-02-20T20:00:00+00:00',
            google_calendar_url: 'https://calendar.google.com/calendar/render?action=TEMPLATE',
            ics_url: 'http://localhost/api/events/18/calendar.ics',
          },
        ],
        calendar: { bundle_ics_url: 'http://localhost/api/featured-events/2026-02/calendar.ics' },
        settings: { enabled: true, force_version: 2, force_at: null },
        meta: { max_items: 10 },
      },
    })

    getEventsMock.mockResolvedValue({
      data: {
        data: [{ id: 12, title: 'Lunar Eclipse' }],
      },
    })

    forceFeaturedEventsPopupMock.mockResolvedValue({
      data: { force_version: 3, force_at: '2026-02-17T12:00:00+00:00' },
    })

    applyFallbackAsFeaturedMock.mockResolvedValue({
      data: {
        data: {
          month: '2026-02',
          applied_count: 4,
        },
      },
    })

    updateFeaturedPopupSettingsMock.mockResolvedValue({
      data: {
        data: {
          enabled: false,
          force_version: 2,
          force_at: null,
        },
      },
    })
  })

  it('renders mode badge from selection_mode', async () => {
    const wrapper = mount(AdminFeaturedEventsView)
    await flush()
    await flush()

    expect(getFeaturedEventsMock).toHaveBeenCalledTimes(1)
    expect(normalizeText(wrapper.text())).toContain('auto')
    expect(wrapper.text()).toContain('Meteor Shower')
  })

  it('calls apply fallback endpoint and refreshes view', async () => {
    const wrapper = mount(AdminFeaturedEventsView)
    await flush()
    await flush()

    const button = wrapper
      .findAll('button')
      .find((node) => normalizeText(node.text()).startsWith('pouzit'))
    expect(button).toBeTruthy()
    if (!button) {
      throw new Error('Fallback apply button not found')
    }

    await button.trigger('click')
    await flush()
    await flush()

    expect(applyFallbackAsFeaturedMock).toHaveBeenCalledTimes(1)
    const payload = applyFallbackAsFeaturedMock.mock.calls[0]?.[0] || {}
    expect(payload.month).toMatch(/^\d{4}-\d{2}$/)
    expect(getFeaturedEventsMock).toHaveBeenCalledTimes(2)
  })

  it('updates global popup enabled setting from the toggle', async () => {
    const wrapper = mount(AdminFeaturedEventsView)
    await flush()
    await flush()

    const checkbox = wrapper.get('#toggle-popup')
    await checkbox.setValue(false)
    await flush()

    expect(updateFeaturedPopupSettingsMock).toHaveBeenCalledWith({ enabled: false })
    expect(normalizeText(wrapper.text())).toContain('popup vyp')
  })
})
