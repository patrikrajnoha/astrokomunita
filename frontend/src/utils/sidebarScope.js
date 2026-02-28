import {
  DEFAULT_SIDEBAR_SCOPE,
  SIDEBAR_SCOPE,
  SIDEBAR_SCOPES,
  hasExplicitSidebarScope,
  isSidebarScope,
  normalizeSidebarScope,
} from '@/generated/sidebarScopes'

export {
  DEFAULT_SIDEBAR_SCOPE,
  SIDEBAR_SCOPE,
  SIDEBAR_SCOPES,
  hasExplicitSidebarScope,
  isSidebarScope,
  normalizeSidebarScope,
}

export function resolveSidebarScopeFromPath(path) {
  const normalized = typeof path === 'string' ? path : ''

  if (normalized === '/' || normalized === '') {
    return DEFAULT_SIDEBAR_SCOPE
  }

  if (normalized.startsWith('/events')) {
    return SIDEBAR_SCOPE.EVENTS
  }

  if (normalized.startsWith('/observations') || normalized.startsWith('/observing')) {
    return SIDEBAR_SCOPE.OBSERVING
  }

  if (normalized.startsWith('/calendar')) {
    return SIDEBAR_SCOPE.CALENDAR
  }

  if (normalized.startsWith('/clanky') || normalized.startsWith('/learn') || normalized.startsWith('/learning')) {
    return SIDEBAR_SCOPE.LEARNING
  }

  if (normalized.startsWith('/search')) {
    return SIDEBAR_SCOPE.SEARCH
  }

  if (normalized.startsWith('/notifications')) {
    return SIDEBAR_SCOPE.NOTIFICATIONS
  }

  if (normalized.startsWith('/sky')) {
    return SIDEBAR_SCOPE.SKY
  }

  if (normalized.startsWith('/posts/')) {
    return SIDEBAR_SCOPE.POST_DETAIL
  }

  if (normalized.startsWith('/profile')) {
    return SIDEBAR_SCOPE.PROFILE
  }

  return null
}
