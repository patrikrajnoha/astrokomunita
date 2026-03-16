import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import EventsView from './EventsView.vue'

const getEventsMock = vi.hoisted(() => vi.fn())
const getEventYearsMock = vi.hoisted(() => vi.fn())
const lookupEventsByIdsMock = vi.hoisted(() => vi.fn())
const getEventLiveHighlightsMock = vi.hoisted(() => vi.fn())
const initEchoMock = vi.hoisted(() => vi.fn(async () => null))
const getEchoMock = vi.hoisted(() => vi.fn(() => null))

const authMock = vi.hoisted(() => ({
  user: null,
  isAuthed: false,
}))

const preferencesMock = vi.hoisted(() => ({
  locationLabel: '',
  locationLat: null,
  locationLon: null,
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => preferencesMock,
}))

vi.mock('@/services/events', () => ({
  getEvents: getEventsMock,
  getEventYears: getEventYearsMock,
  lookupEventsByIds: lookupEventsByIdsMock,
  getEventLiveHighlights: getEventLiveHighlightsMock,
}))

vi.mock('@/realtime/echo', () => ({
  initEcho: initEchoMock,
  getEcho: getEchoMock,
}))

const flush = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

async function mountView(path = '/events') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/events',
        name: 'events',
        component: EventsView,
      },
    ],
  })

  await router.push(path)
  await router.isReady()

  const wrapper = mount(EventsView, {
    global: {
      plugins: [router],
    },
  })

  await flush()
  await flush()

  return { wrapper }
}

describe('EventsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    window.matchMedia = vi.fn().mockReturnValue({
      matches: true,
      media: '(min-width: 960px)',
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })

    authMock.user = null
    authMock.isAuthed = false
    preferencesMock.locationLabel = ''
    preferencesMock.locationLat = null
    preferencesMock.locationLon = null

    getEventYearsMock.mockResolvedValue({
      data: {
        years: [2026],
        defaultYear: 2026,
        currentYearBounded: 2026,
      },
    })
    getEventsMock.mockResolvedValue({
      data: {
        data: [],
        meta: {
          total: 0,
          current_page: 1,
          last_page: 1,
        },
      },
    })
    lookupEventsByIdsMock.mockResolvedValue({ data: { data: [] } })
    getEventLiveHighlightsMock.mockResolvedValue({
      data: {
        data: [],
        meta: {
          location_required: true,
        },
      },
    })
  })

  it('renders live aurora highlight when an observing location is available', async () => {
    authMock.user = {
      timezone: 'Europe/Bratislava',
    }
    authMock.isAuthed = true
    preferencesMock.locationLat = 48.1486
    preferencesMock.locationLon = 17.1077
    preferencesMock.locationLabel = 'Bratislava'

    getEventLiveHighlightsMock.mockResolvedValue({
      data: {
        data: [
          {
            key: 'aurora_watch',
            title: 'Aurora watch',
            badge: 'Živé teraz',
            status_label: 'Slabá šanca',
            status_score: 23,
            tone: 'low',
            summary: 'Signál je zvýšený smerom na sever.',
            detail: 'Koridor severne: 23/100',
            forecast_for: '2026-03-14T22:37:00+01:00',
            updated_at: '2026-03-14T22:57:00+01:00',
            source: {
              label: 'NOAA SWPC OVATION',
              url: 'https://www.swpc.noaa.gov/products/aurora-30-minute-forecast',
            },
          },
        ],
        meta: {
          location_required: false,
        },
      },
    })

    const { wrapper } = await mountView()

    expect(getEventLiveHighlightsMock).toHaveBeenCalledWith({
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    })
    expect(wrapper.text()).toContain('Aurora watch')
    expect(wrapper.text()).toContain('Slabá šanca')
    expect(wrapper.text()).toContain('NOAA SWPC OVATION')
    expect(wrapper.text()).toContain('23/100')

    wrapper.unmount()
  })

  it('skips live highlight loading when no observing location is available', async () => {
    const { wrapper } = await mountView()

    expect(getEventLiveHighlightsMock).not.toHaveBeenCalled()
    expect(wrapper.text()).not.toContain('Aurora watch')

    wrapper.unmount()
  })
})
