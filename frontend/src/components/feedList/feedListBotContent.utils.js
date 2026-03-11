export function normalizeToken(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
}

export function isBotPost(post) {
  const authorKind = normalizeToken(post?.author_kind)
  if (authorKind) return authorKind === 'bot'
  return normalizeToken(post?.source_name) === 'astrobot'
}

export function canAdminEditBotPost(post, user) {
  const isAdmin = Boolean(user?.is_admin || user?.role === 'admin')
  if (!isAdmin || !isBotPost(post)) return false

  const identity = normalizeToken(post?.bot_identity)
  return identity === 'kozmo' || identity === 'stela'
}

export function botIdentity(post) {
  return normalizeToken(post?.bot_identity)
}

export function botSourceLabel(post) {
  if (!isBotPost(post)) return ''
  const sourceKey = normalizeToken(post?.meta?.bot_source_key)
  if (sourceKey === 'nasa_apod_daily') {
    return 'APOD dňa'
  }

  const sourceLabel = String(post?.meta?.bot_source_label || '').trim()
  return sourceLabel || 'Bot'
}

export function sourceAttributionLabel(post) {
  const attribution = String(
    post?.meta?.bot_source_attribution || post?.meta?.source_attribution || '',
  ).trim()
  return attribution || botSourceLabel(post)
}

export function showBotTranslationToggle(post) {
  if (!isBotPost(post)) return false
  const hasOriginal =
    String(post?.meta?.original_title || '').trim() !== '' ||
    String(post?.meta?.original_content || '').trim() !== ''
  const hasTranslated =
    String(post?.meta?.translated_title || '').trim() !== '' ||
    String(post?.meta?.translated_content || '').trim() !== ''
  return hasOriginal && hasTranslated
}

export function normalizeBool(value) {
  if (value === true || value === false) return value
  if (typeof value === 'number') return value === 1
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
  if (normalized === '1' || normalized === 'true' || normalized === 'yes') return true
  if (normalized === '0' || normalized === 'false' || normalized === 'no') return false
  return false
}

export function defaultBotVariant(post) {
  return normalizeBool(post?.meta?.used_translation) ? 'translated' : 'original'
}

export function resolvedBotVariant(post, variantMap = {}) {
  if (!showBotTranslationToggle(post)) return null
  const id = Number(post?.id || 0)
  if (!id) return defaultBotVariant(post)
  const current = variantMap[id]
  if (current === 'translated' || current === 'original') {
    return current
  }
  return defaultBotVariant(post)
}

export function setBotContentVariant(post, variant, variantMap = {}) {
  if (!showBotTranslationToggle(post)) return variantMap
  if (variant !== 'translated' && variant !== 'original') return variantMap
  const id = Number(post?.id || 0)
  if (!id) return variantMap
  return {
    ...variantMap,
    [id]: variant,
  }
}

export function isBotVariantActive(post, variant, variantMap = {}) {
  return resolvedBotVariant(post, variantMap) === variant
}

export function variantText(post, variant) {
  const title = String(post?.meta?.[`${variant}_title`] || '').trim()
  const content = String(post?.meta?.[`${variant}_content`] || '').trim()
  return [title, content].filter(Boolean).join('\n\n')
}

export function normalizeBotDisplayText(value) {
  return String(value || '')
    .replace(/\r\n?/g, '\n')
    .replace(/\u00a0/g, ' ')
    .replace(/[\t\f\v]+/g, ' ')
    .replace(/ {2,}/g, ' ')
    .replace(/ *\n */g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

export function resolvedDisplayText(post, variantMap = {}) {
  if (showBotTranslationToggle(post)) {
    const variant = resolvedBotVariant(post, variantMap) || defaultBotVariant(post)
    const text = variantText(post, variant)
    if (text !== '') {
      return normalizeBotDisplayText(text)
    }
  }

  const fallback = String(post?.content || '')
  if (!isBotPost(post)) return fallback
  return normalizeBotDisplayText(fallback)
}

export function isBotContentCollapsible(post, variantMap = {}, previewLimit = 800) {
  const content = resolvedDisplayText(post, variantMap)
  return isBotPost(post) && content.length > previewLimit
}

export function displayPostContent(post, variantMap = {}, expandedIds = new Set(), previewLimit = 800) {
  const content = resolvedDisplayText(post, variantMap)
  if (!isBotContentCollapsible(post, variantMap, previewLimit) || expandedIds.has(post?.id)) {
    return content
  }
  return content.slice(0, previewLimit).trimEnd() + '...'
}
