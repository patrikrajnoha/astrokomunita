import { DEFAULT_SIDEBAR_SCOPE } from '@/generated/sidebarScopes'

export const DEFAULT_HOME_PREFERRED_SECTION_KEYS = Object.freeze([
  'next_event',
  'nasa_apod',
  'search',
])

export const resolvePreferredSidebarWidgetKeys = ({
  isAuthed = false,
  preferences = null,
  scope = DEFAULT_SIDEBAR_SCOPE,
} = {}) => {
  const normalizedScope = String(scope || DEFAULT_SIDEBAR_SCOPE).trim() || DEFAULT_SIDEBAR_SCOPE

  if (isAuthed && preferences?.loaded) {
    const selected = typeof preferences.sidebarWidgetKeysForScope === 'function'
      ? preferences.sidebarWidgetKeysForScope(normalizedScope)
      : null

    if (Array.isArray(selected)) {
      if (selected.length > 0) {
        return selected
      }

      const hasExplicitScopeOverride = typeof preferences.hasSidebarWidgetOverrideForScope === 'function'
        ? preferences.hasSidebarWidgetOverrideForScope(normalizedScope)
        : false
      const hasExplicitGlobalOverride = typeof preferences.hasSidebarWidgetOverrideForScope === 'function'
        ? preferences.hasSidebarWidgetOverrideForScope(DEFAULT_SIDEBAR_SCOPE)
        : false

      if (hasExplicitScopeOverride || hasExplicitGlobalOverride) {
        return []
      }
    }
  }

  if (normalizedScope === DEFAULT_SIDEBAR_SCOPE) {
    return [...DEFAULT_HOME_PREFERRED_SECTION_KEYS]
  }

  return null
}
