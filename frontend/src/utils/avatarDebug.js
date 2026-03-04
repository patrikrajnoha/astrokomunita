function parseBooleanLike(value) {
  if (value === true || value === false) return value
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
  if (normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on') return true
  if (normalized === '0' || normalized === 'false' || normalized === 'no' || normalized === 'off') return false
  return null
}

export function isAvatarDebugEnabled() {
  if (typeof window === 'undefined') return false

  const runtimeFlag = parseBooleanLike(window.__AVATAR_DEBUG__)
  if (runtimeFlag !== null) return runtimeFlag

  const storageFlag = parseBooleanLike(window.localStorage?.getItem('avatar_debug'))
  if (storageFlag !== null) return storageFlag

  const paramFlag = parseBooleanLike(new URLSearchParams(window.location.search).get('avatarDebug'))
  if (paramFlag !== null) return paramFlag

  return false
}

export function avatarDebug(scope, payload = undefined) {
  if (!isAvatarDebugEnabled() || typeof console === 'undefined') return

  const label = `[avatar-debug] ${String(scope || 'event')}`
  if (payload === undefined) {
    console.log(label)
    return
  }

  console.groupCollapsed(label)
  console.log(payload)
  console.groupEnd()
}
