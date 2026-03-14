import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useEventPreferencesStore } from '@/stores/eventPreferences'

describe('eventPreferences sidebar widget getters', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
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
})
