import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useEventPreferencesStore } from '@/stores/eventPreferences'

const getMyPreferencesMock = vi.hoisted(() => vi.fn())
const updateMyPreferencesMock = vi.hoisted(() => vi.fn())
const authStoreMock = vi.hoisted(() => ({
  csrf: vi.fn(async () => {}),
  fetchUser: vi.fn(async () => null),
}))

vi.mock('@/services/events', () => ({
  getMyPreferences: getMyPreferencesMock,
  updateMyPreferences: updateMyPreferencesMock,
  getOnboardingInterests: vi.fn(),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authStoreMock,
}))

describe('eventPreferences sidebar widget getters', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('falls back to home widget selection for scopes without explicit override', () => {
    const store = useEventPreferencesStore()
    store.sidebarWidgetKeys = ['search', 'nasa_apod', 'next_event']
    store.sidebarWidgetOverrides = {
      home: ['search', 'nasa_apod', 'next_event'],
    }

    expect(store.sidebarWidgetKeysForScope('events')).toEqual(['search', 'nasa_apod', 'next_event'])
    expect(store.sidebarWidgetKeysForScope('calendar')).toEqual(['search', 'nasa_apod', 'next_event'])
  })

  it('prefers explicit scope override when present', () => {
    const store = useEventPreferencesStore()
    store.sidebarWidgetKeys = ['search', 'nasa_apod', 'next_event']
    store.sidebarWidgetOverrides = {
      home: ['search', 'nasa_apod', 'next_event'],
      events: ['upcoming_events', 'latest_articles'],
    }

    expect(store.sidebarWidgetKeysForScope('events')).toEqual(['upcoming_events', 'latest_articles'])
    expect(store.sidebarWidgetKeysForScope('home')).toEqual(['search', 'nasa_apod', 'next_event'])
  })

  it('returns the in-flight preferences promise instead of no-op while loading', async () => {
    const store = useEventPreferencesStore()
    let resolveRequest

    getMyPreferencesMock.mockImplementationOnce(() => new Promise((resolve) => {
      resolveRequest = resolve
    }))

    const first = store.fetchPreferences()
    const second = store.fetchPreferences()

    expect(getMyPreferencesMock).toHaveBeenCalledTimes(1)

    resolveRequest({
      data: {
        data: {
          event_types: ['meteors'],
          interests: ['visual'],
          region: 'sk',
          has_preferences: true,
          sidebar_widget_keys: ['neo_watchlist'],
          onboarding_completed_at: '2026-03-15T11:00:00Z',
        },
        meta: {
          supported_event_types: ['meteors'],
          supported_regions: ['sk', 'eu', 'global'],
          supported_interests: ['visual'],
          supported_sidebar_widgets: [{ section_key: 'neo_watchlist', title: 'Asteroidy nablízku' }],
          supported_sidebar_scopes: ['home', 'events'],
        },
      },
    })

    await first
    await second

    expect(store.loaded).toBe(true)
    expect(store.sidebarWidgetKeys).toEqual(['neo_watchlist'])
    expect(store.supportedSidebarScopes).toEqual(['home', 'events'])
  })

  it('refreshes auth user after saving location preferences', async () => {
    const store = useEventPreferencesStore()
    updateMyPreferencesMock.mockResolvedValue({
      data: {
        data: {
          location_label: 'Bratislava',
          location_place_id: 'sk:bratislava',
          location_lat: 48.1486,
          location_lon: 17.1077,
          sidebar_widget_keys: [],
          sidebar_widget_overrides: {},
          has_preferences: true,
        },
        meta: {},
      },
    })

    await store.savePreferences({
      location_label: 'Bratislava',
      location_place_id: 'sk:bratislava',
      location_lat: 48.1486,
      location_lon: 17.1077,
    })

    expect(authStoreMock.csrf).toHaveBeenCalledTimes(1)
    expect(authStoreMock.fetchUser).toHaveBeenCalledWith({
      source: 'preferences-save',
      retry: false,
      markBootstrap: false,
      preserveStateOnError: true,
    })
  })

  it('marks onboarding saves to skip auth redirects and duplicate toasts', async () => {
    const store = useEventPreferencesStore()
    updateMyPreferencesMock.mockResolvedValue({
      data: {
        data: {
          interests: [],
          location_label: '',
          location_place_id: null,
          location_lat: null,
          location_lon: null,
          onboarding_completed_at: '2026-04-10T10:00:00Z',
          sidebar_widget_keys: [],
          sidebar_widget_overrides: {},
          has_preferences: true,
        },
        meta: {},
      },
    })

    await store.saveOnboarding({
      interests: [],
      location_label: '',
      location_place_id: null,
      location_lat: null,
      location_lon: null,
    })

    expect(updateMyPreferencesMock).toHaveBeenCalledWith(
      expect.objectContaining({
        onboarding_completed_at: expect.any(String),
      }),
      expect.objectContaining({
        meta: expect.objectContaining({
          skipAuthRedirect: true,
          skipErrorToast: true,
        }),
      }),
    )
  })
})
