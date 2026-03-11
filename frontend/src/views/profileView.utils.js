export function toNonEmptyText(value) {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

export function safeHandle(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
}

export function looksLikeEmail(value) {
  if (typeof value !== 'string') return false
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

export function shorten(text) {
  if (!text) return ''
  const clean = String(text).trim()
  return clean.length > 80 ? clean.slice(0, 77) + '...' : clean
}

export function isImage(post) {
  const mime = post?.attachment_mime || ''
  return mime.startsWith('image/')
}

export function absoluteUrl(url, baseApiUrl = '') {
  const value = String(url || '').trim()
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value

  const origin = String(baseApiUrl || '').replace(/\/api\/?$/, '')
  if (!origin) return value

  if (value.startsWith('/')) return origin + value
  return origin + '/' + value
}

export function postGifUrl(post, baseApiUrl = '') {
  const gif = post?.meta?.gif
  if (!gif || typeof gif !== 'object') return ''

  const original = absoluteUrl(gif.original_url, baseApiUrl)
  if (original) return original

  return absoluteUrl(gif.preview_url, baseApiUrl)
}

export function postGifTitle(post) {
  const title = String(post?.meta?.gif?.title || '').trim()
  return title || 'GIF'
}

export function attachedEventForPost(post) {
  const event = post?.attached_event
  if (event && typeof event === 'object') return event

  const fallbackId = Number(post?.meta?.event?.event_id || 0)
  if (!Number.isInteger(fallbackId) || fallbackId <= 0) return null

  return {
    id: fallbackId,
    title: `Udalost #${fallbackId}`,
    start_at: null,
    end_at: null,
  }
}

export function mergeUniqueById(existingItems, incomingItems) {
  const seen = new Set()
  const merged = []

  const append = (item) => {
    const id = Number(item?.id || 0)
    if (!Number.isInteger(id) || id <= 0) {
      merged.push(item)
      return
    }
    if (seen.has(id)) return
    seen.add(id)
    merged.push(item)
  }

  ;(Array.isArray(existingItems) ? existingItems : []).forEach(append)
  ;(Array.isArray(incomingItems) ? incomingItems : []).forEach(append)

  return merged
}

export function hasEventPlanData(item) {
  const plan = item?.plan && typeof item.plan === 'object' ? item.plan : null
  if (!plan) return false
  if (plan.has_data === true) return true

  return [
    toNonEmptyText(plan.personal_note),
    toNonEmptyText(plan.reminder_at),
    toNonEmptyText(plan.planned_time),
    toNonEmptyText(plan.planned_location_label),
  ].some((value) => value !== null)
}

export function parentHandle(post) {
  const parentUser = post?.parent?.user
  const username = toNonEmptyText(parentUser?.username)
  if (username) return safeHandle(username)

  const name = toNonEmptyText(parentUser?.name)
  if (name && !looksLikeEmail(name)) return safeHandle(name)

  return 'user'
}

export function extractFirstError(errorsObj, field) {
  const v = errorsObj?.[field]
  return Array.isArray(v) && v.length ? String(v[0]) : ''
}
