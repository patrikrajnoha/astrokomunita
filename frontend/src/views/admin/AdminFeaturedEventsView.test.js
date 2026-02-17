import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminFeaturedEventsView from '@/views/admin/AdminFeaturedEventsView.vue'

const getFeaturedEventsMock = vi.hoisted(() => vi.fn())
const createFeaturedEventMock = vi.hoisted(() => vi.fn())
const updateFeaturedEventMock = vi.hoisted(() => vi.fn())
const deleteFeaturedEventMock = vi.hoisted(() => vi.fn())
const forceFeaturedEventsPopupMock = vi.hoisted(() => vi.fn())
const updateFeaturedPopupSettingsMock = vi.hoisted(() => vi.fn())
const getEventsMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api/admin/featuredEvents', () => ({
  getFeaturedEvents: (...args) => getFeaturedEventsMock(...args),
  createFeaturedEvent: (...args) => createFeaturedEventMock(...args),
  updateFeaturedEvent: (...args) => updateFeaturedEventMock(...args),
  deleteFeaturedEvent: (...args) => deleteFeaturedEventMock(...args),
  forceFeaturedEventsPopup: (...args) => forceFeaturedEventsPopupMock(...args),
  updateFeaturedPopupSettings: (...args) => updateFeaturedPopupSettingsMock(...args),
}))

vi.mock('@/services/api/admin/events', () => ({
  getEvents: (...args) => getEventsMock(...args),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('AdminFeaturedEventsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getFeaturedEventsMock.mockResolvedValue({
      data: {
        data: [
          {
            id: 5,
            event_id: 12,
            position: 0,
            is_active: true,
            event: { id: 12, title: 'Lunar Eclipse' },
          },
        ],
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
  })

  it('renders list from API', async () => {
    const wrapper = mount(AdminFeaturedEventsView)
    await flush()
    await flush()

    expect(getFeaturedEventsMock).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Lunar Eclipse')
    expect(wrapper.text()).toContain('1/10')
  })

  it('calls force endpoint once when force button is clicked', async () => {
    const wrapper = mount(AdminFeaturedEventsView)
    await flush()
    await flush()

    const button = wrapper.findAll('button').find((node) => node.text().includes('Show popup to everyone now'))
    expect(button).toBeTruthy()

    await button.trigger('click')
    await flush()

    expect(forceFeaturedEventsPopupMock).toHaveBeenCalledTimes(1)
  })
})

