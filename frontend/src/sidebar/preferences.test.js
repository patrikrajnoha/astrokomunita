import { describe, expect, it, vi } from 'vitest'
import { resolvePreferredSidebarWidgetKeys } from '@/sidebar/preferences'

describe('resolvePreferredSidebarWidgetKeys', () => {
  it('returns null for guests so admin-configured widgets are shown', () => {
    expect(resolvePreferredSidebarWidgetKeys({
      isAuthed: false,
      preferences: null,
      scope: 'home',
    })).toBeNull()
  })

  it('returns null while signed-in preferences are not loaded yet', () => {
    expect(resolvePreferredSidebarWidgetKeys({
      isAuthed: true,
      preferences: {
        loaded: false,
      },
      scope: 'home',
    })).toBeNull()
  })

  it('preserves an explicit empty override when preferences are loaded', () => {
    const preferences = {
      loaded: true,
      sidebarWidgetKeysForScope: vi.fn(() => []),
      hasSidebarWidgetOverrideForScope: vi.fn((scope) => scope === 'home'),
    }

    expect(resolvePreferredSidebarWidgetKeys({
      isAuthed: true,
      preferences,
      scope: 'settings',
    })).toEqual([])
  })

  it('returns a scoped selection when one exists', () => {
    const preferences = {
      loaded: true,
      sidebarWidgetKeysForScope: vi.fn((scope) => (
        scope === 'events' ? ['upcoming_events', 'latest_articles'] : []
      )),
      hasSidebarWidgetOverrideForScope: vi.fn(() => false),
    }

    expect(resolvePreferredSidebarWidgetKeys({
      isAuthed: true,
      preferences,
      scope: 'events',
    })).toEqual(['upcoming_events', 'latest_articles'])
  })
})
