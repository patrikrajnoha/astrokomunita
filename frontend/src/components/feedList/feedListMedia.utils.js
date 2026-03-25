export function buildAbsoluteUrl(url, baseURL = '') {
  const value = String(url || '').trim()
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value

  const origin = String(baseURL || '').replace(/\/api\/?$/, '')
  if (!origin) return value

  if (value.startsWith('/')) return origin + value
  return origin + '/' + value
}

export function sourceLink(post, baseURL = '') {
  return buildAbsoluteUrl(post?.meta?.source_url, baseURL)
}

export function isAttachmentEntryImage(entry) {
  const type = String(entry?.type || '')
    .trim()
    .toLowerCase()
  const mime = String(entry?.mime || '')
    .trim()
    .toLowerCase()
  const url = String(entry?.url || entry?.src || entry?.href || '')

  if (type === 'image') return true
  if (mime.startsWith('image/')) return true
  return /\.(png|jpe?g|gif|webp|avif)$/i.test(url)
}

export function attachmentEntryUrl(entry, baseURL = '') {
  const raw = entry?.url || entry?.src || entry?.href
  return buildAbsoluteUrl(raw, baseURL)
}

export function postGifUrl(post, baseURL = '') {
  const gif = post?.meta?.gif
  if (!gif || typeof gif !== 'object') return ''

  const original = buildAbsoluteUrl(gif.original_url, baseURL)
  if (original) return original

  return buildAbsoluteUrl(gif.preview_url, baseURL)
}

export function postGifTitle(post) {
  const title = String(post?.meta?.gif?.title || '').trim()
  return title || 'GIF'
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

export function attachmentSrc(post, baseURL = '') {
  return buildAbsoluteUrl(post?.attachment_url, baseURL)
}

export function attachmentDownloadSrc(post, baseURL = '') {
  return buildAbsoluteUrl(post?.attachment_download_url, baseURL)
}

export function isImage(post) {
  const mime = post?.attachment_mime || ''
  if (typeof mime === 'string' && mime.startsWith('image/')) return true

  const name = (post?.attachment_original_name || post?.attachment_url || '').toLowerCase()
  return (
    name.endsWith('.jpg') ||
    name.endsWith('.jpeg') ||
    name.endsWith('.png') ||
    name.endsWith('.gif') ||
    name.endsWith('.webp')
  )
}

export function attachmentMime(post) {
  const mime = String(post?.attachment_mime || '')
    .trim()
    .toLowerCase()
  if (mime !== '') return mime

  const name = (post?.attachment_original_name || post?.attachment_url || '').toLowerCase()
  if (name.endsWith('.mp4') || name.endsWith('.m4v')) return 'video/mp4'
  if (name.endsWith('.webm')) return 'video/webm'
  if (name.endsWith('.mov')) return 'video/quicktime'
  return ''
}

export function isVideo(post) {
  const mime = attachmentMime(post)
  if (mime.startsWith('video/')) return true

  const name = (post?.attachment_original_name || post?.attachment_url || '').toLowerCase()
  return (
    name.endsWith('.mp4') ||
    name.endsWith('.m4v') ||
    name.endsWith('.webm') ||
    name.endsWith('.mov')
  )
}

export function isAttachmentPending(post) {
  return post?.attachment_moderation_status === 'pending' || post?.attachment_is_blurred === true
}

export function isAttachmentBlocked(post) {
  return (
    post?.attachment_moderation_status === 'blocked'
    || post?.attachment_moderation_status === 'flagged'
    || !!post?.attachment_hidden_at
  )
}

export function hasOriginalDownload(post) {
  if (!isImage(post)) return false
  if (isAttachmentPending(post) || isAttachmentBlocked(post)) return false
  return Boolean(post?.attachment_download_url)
}

export function normalizeFeedError(error) {
  const status = Number(error?.response?.status || 0)
  const code = String(error?.code || '')
  const message = String(error?.message || '')

  if (status === 401) return 'Prihlás sa pre túto akciu.'
  if (code === 'ECONNABORTED' || message.toLowerCase().includes('timeout')) {
    return 'Server neodpovedá. Skús to znova neskôr.'
  }
  if (!status && (code === 'ERR_NETWORK' || message.toLowerCase().includes('network'))) {
    return 'Backend je nedostupný. Skontroluj, či beží API server.'
  }

  return error?.response?.data?.message || message || 'Načítanie feedu zlyhalo.'
}
