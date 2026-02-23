export const SIDEBAR_SCOPES = ['home', 'events', 'calendar', 'learning', 'notifications', 'post_detail', 'profile']

export function resolveSidebarScopeFromPath(path) {
  const normalized = typeof path === 'string' ? path : ''

  if (normalized === '/' || normalized === '') {
    return 'home'
  }

  if (normalized.startsWith('/events')) {
    return 'events'
  }

  if (normalized.startsWith('/calendar')) {
    return 'calendar'
  }

  if (normalized.startsWith('/clanky') || normalized.startsWith('/learn') || normalized.startsWith('/learning')) {
    return 'learning'
  }

  if (normalized.startsWith('/notifications')) {
    return 'notifications'
  }

  if (normalized.startsWith('/posts/')) {
    return 'post_detail'
  }

  if (normalized.startsWith('/profile')) {
    return 'profile'
  }

  return null
}
