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

  if (
    normalized.startsWith('/events') ||
    normalized.startsWith('/observations') ||
    normalized.startsWith('/observing') ||
    normalized.startsWith('/calendar') ||
    normalized.startsWith('/articles') ||
    normalized.startsWith('/clanky') ||
    normalized.startsWith('/learn') ||
    normalized.startsWith('/learning') ||
    normalized.startsWith('/search') ||
    normalized.startsWith('/notifications') ||
    normalized.startsWith('/settings') ||
    normalized.startsWith('/posts/') ||
    normalized.startsWith('/profile') ||
    normalized.startsWith('/u/') ||
    normalized.startsWith('/bookmarks') ||
    normalized.startsWith('/tags/') ||
    normalized.startsWith('/hashtags/')
  ) {
    return DEFAULT_SIDEBAR_SCOPE
  }

  return null
}
