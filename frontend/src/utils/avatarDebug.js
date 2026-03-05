export function avatarDebug(scope, payload = {}) {
  const enabled = String(import.meta.env?.VITE_DEBUG_AVATAR || '').toLowerCase() === 'true'
  if (!import.meta.env?.DEV || !enabled) return

  console.debug(`[avatar] ${scope}`, payload)
}
