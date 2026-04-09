const PRELOAD_RECOVERY_STORAGE_KEY = 'astrokomunita:preload-recovery-attempted'
const PRELOAD_RECOVERY_WINDOW_KEY = '__astrokomunitaPreloadRecoveryAttempted'
const PRELOAD_RECOVERY_CLEANUP_KEY = '__astrokomunitaPreloadRecoveryCleanup'

function getSessionStorage(targetWindow) {
  try {
    return targetWindow?.sessionStorage ?? null
  } catch {
    return null
  }
}

function hasAttemptedRecovery(targetWindow) {
  if (!targetWindow) return false
  if (targetWindow[PRELOAD_RECOVERY_WINDOW_KEY] === true) return true

  const storage = getSessionStorage(targetWindow)

  try {
    return storage?.getItem(PRELOAD_RECOVERY_STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

function markRecoveryAttempted(targetWindow) {
  if (!targetWindow) return

  targetWindow[PRELOAD_RECOVERY_WINDOW_KEY] = true

  const storage = getSessionStorage(targetWindow)

  try {
    storage?.setItem(PRELOAD_RECOVERY_STORAGE_KEY, '1')
  } catch {
    // Ignore storage access issues and still attempt a one-off reload in memory.
  }
}

export function clearPreloadRecoveryState(targetWindow = typeof window !== 'undefined' ? window : null) {
  if (!targetWindow) return

  targetWindow[PRELOAD_RECOVERY_WINDOW_KEY] = false

  const storage = getSessionStorage(targetWindow)

  try {
    storage?.removeItem(PRELOAD_RECOVERY_STORAGE_KEY)
  } catch {
    // Ignore storage access issues.
  }
}

export function installPreloadRecovery(targetWindow = typeof window !== 'undefined' ? window : null) {
  if (!targetWindow || typeof targetWindow.addEventListener !== 'function') {
    return () => {}
  }

  if (typeof targetWindow[PRELOAD_RECOVERY_CLEANUP_KEY] === 'function') {
    return targetWindow[PRELOAD_RECOVERY_CLEANUP_KEY]
  }

  const onPreloadError = (event) => {
    if (hasAttemptedRecovery(targetWindow)) {
      return
    }

    markRecoveryAttempted(targetWindow)
    event?.preventDefault?.()

    if (typeof console !== 'undefined') {
      console.warn('[APP INIT] stale Vite asset detected, reloading page', event?.payload || event)
    }

    targetWindow.location?.reload?.()
  }

  const cleanup = () => {
    targetWindow.removeEventListener('vite:preloadError', onPreloadError)
    delete targetWindow[PRELOAD_RECOVERY_CLEANUP_KEY]
  }

  targetWindow.addEventListener('vite:preloadError', onPreloadError)
  targetWindow[PRELOAD_RECOVERY_CLEANUP_KEY] = cleanup

  return cleanup
}
