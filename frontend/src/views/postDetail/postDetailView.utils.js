export function isImage(p) {
  const mime = p?.attachment_mime || ''
  if (typeof mime === 'string' && mime.startsWith('image/')) return true

  const name = (p?.attachment_original_name || p?.attachment_url || '').toLowerCase()
  return (
    name.endsWith('.jpg') ||
    name.endsWith('.jpeg') ||
    name.endsWith('.png') ||
    name.endsWith('.gif') ||
    name.endsWith('.webp')
  )
}

export function isAttachmentPending(item) {
  return item?.attachment_moderation_status === 'pending' || item?.attachment_is_blurred === true
}

export function isAttachmentBlocked(item) {
  return (
    item?.attachment_moderation_status === 'blocked'
    || item?.attachment_moderation_status === 'flagged'
    || !!item?.attachment_hidden_at
  )
}

export function attachmentSrc(item, apiBaseUrl) {
  return buildAbsoluteAssetUrl(item?.attachment_url, apiBaseUrl)
}

export function attachmentDownloadSrc(item, apiBaseUrl) {
  return buildAbsoluteAssetUrl(item?.attachment_download_url, apiBaseUrl)
}

export function postGifUrl(post, apiBaseUrl) {
  const gif = post?.meta?.gif
  if (!gif || typeof gif !== 'object') return ''

  const original = buildAbsoluteAssetUrl(gif.original_url, apiBaseUrl)
  if (original) return original

  return buildAbsoluteAssetUrl(gif.preview_url, apiBaseUrl)
}

export function postGifTitle(post) {
  const title = String(post?.meta?.gif?.title || '').trim()
  return title || 'GIF'
}

export function attachedEventForPost(post) {
  const event = post?.attached_event
  if (event && typeof event === 'object') {
    return event
  }

  const fallbackId = Number(post?.meta?.event?.event_id || 0)
  if (!Number.isInteger(fallbackId) || fallbackId <= 0) {
    return null
  }

  return {
    id: fallbackId,
    title: `Udalosť #${fallbackId}`,
    start_at: null,
    end_at: null,
  }
}

export function parseEventDate(value) {
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function formatEventRange(startAt, endAt) {
  const start = parseEventDate(startAt)
  const end = parseEventDate(endAt)

  if (!start && !end) return 'Dátum upresníme'
  if (start && !end) return start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short', year: 'numeric' })
  if (!start && end) return end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short', year: 'numeric' })

  const startLabel = start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  const endLabel = end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  return startLabel === endLabel ? startLabel : `${startLabel} - ${endLabel}`
}

export function normalizeToken(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
}

export function isBotPost(post) {
  if (!post || typeof post !== 'object') return false

  const authorKind = normalizeToken(post?.author_kind)
  if (authorKind === 'bot') return true

  if (normalizeToken(post?.source_name) === 'astrobot') return true

  if (post?.user?.is_bot === true) return true
  if (normalizeToken(post?.user?.role) === 'bot') return true

  return false
}

export function canAdminEditBotPost(post, currentUser) {
  const isAdmin = Boolean(currentUser?.is_admin || currentUser?.role === 'admin')
  if (!isAdmin) return false

  if (!isBotPost(post)) return false

  const identity = normalizeToken(post?.bot_identity)
  return identity === 'kozmo' || identity === 'stela'
}

function buildAbsoluteAssetUrl(url, apiBaseUrl) {
  const value = String(url || '').trim()
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value

  const origin = String(apiBaseUrl || '').replace(/\/api\/?$/, '')
  if (!origin) return value

  if (value.startsWith('/')) return origin + value
  return origin + '/' + value
}
