import { BOT_FAILURE_REASONS, BOT_FAILURE_REASON_MESSAGES } from '@/constants/botFailureReasons'

export const DEFAULT_PUBLISH_ALL_LIMIT = 3
export const VALID_BOT_IDENTITIES = ['kozmo', 'stela']
export const BOT_LABELS = Object.freeze({
  kozmo: 'KozmoBot',
  stela: 'StellarBot',
})

export function normalizeBotIdentity(value) {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
  return VALID_BOT_IDENTITIES.includes(normalized) ? normalized : ''
}

export function toErrorMessage(error, fallbackMessage) {
  const status = Number(error?.response?.status || 0)
  const code = String(error?.code || '')
    .trim()
    .toUpperCase()
  const messageText = String(error?.message || '')
    .trim()
    .toLowerCase()
  const retryAfter = Number(error?.response?.data?.retry_after || 0)
  const failureReason = String(
    error?.response?.data?.failure_reason || error?.response?.data?.meta?.failure_reason || '',
  )
    .trim()
    .toLowerCase()
  const baseMessage = error?.response?.data?.message || error?.userMessage || error?.message || fallbackMessage
  const isTimeoutOrNetwork =
    code === 'ECONNABORTED' || code === 'ERR_NETWORK' || messageText.includes('timeout')

  if (isTimeoutOrNetwork) {
    return 'Run trva dlhsie. Skus to o chvilu, alebo otvor detail runu.'
  }

  if (
    [
      BOT_FAILURE_REASONS.RATE_LIMITED,
      BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED,
      BOT_FAILURE_REASONS.NEEDS_API_KEY,
    ].includes(failureReason)
  ) {
    return (
      error?.response?.data?.ui_message ||
      error?.response?.data?.meta?.ui_message ||
      BOT_FAILURE_REASON_MESSAGES[failureReason] ||
      baseMessage
    )
  }

  if (BOT_FAILURE_REASON_MESSAGES[failureReason]) {
    return BOT_FAILURE_REASON_MESSAGES[failureReason]
  }

  if (status === 429 && retryAfter > 0) {
    return `${baseMessage} Skús znova o ${retryAfter} s.`
  }

  return baseMessage
}

export function toStatNumber(value) {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

export function statsSummary(stats) {
  const source = stats && typeof stats === 'object' ? stats : {}

  return [
    `načítané ${toStatNumber(source.fetched_count)}`,
    `nové ${toStatNumber(source.new_count)}`,
    `duplikáty ${toStatNumber(source.dupes_count)}`,
    `publikované ${toStatNumber(source.published_count)}`,
    `preskočené ${toStatNumber(source.skipped_count)}`,
    `chyby ${toStatNumber(source.failed_count)}`,
  ].join(' | ')
}

export function formatDateTime(value) {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return parsed.toLocaleString('sk-SK', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

export function formatStatsJson(stats) {
  if (!stats || typeof stats !== 'object') {
    return '{}'
  }

  return JSON.stringify(stats, null, 2)
}

export function formatBool(value) {
  if (value === true) return 'áno'
  if (value === false) return 'nie'
  return '-'
}

export function formatStableKey(value) {
  const stableKey = String(value || '').trim()
  if (stableKey.length <= 44) {
    return stableKey || '-'
  }

  return `${stableKey.slice(0, 22)}...${stableKey.slice(-18)}`
}

export function itemStatusClass(status) {
  const normalized = String(status || '').toLowerCase()
  if (normalized === 'published' || normalized === 'done') return 'statusBadge statusBadge--success'
  if (normalized === 'skipped') return 'statusBadge statusBadge--partial'
  if (normalized === 'failed') return 'statusBadge statusBadge--failed'
  return 'statusBadge statusBadge--muted'
}

export function translationProviderLabel(provider) {
  const normalized = String(provider || '')
    .trim()
    .toLowerCase()
  if (!normalized) return '-'
  if (normalized === 'libretranslate') return 'LibreTranslate'
  if (normalized === 'ollama') return 'Ollama'
  if (normalized === 'ollama_postedit') return 'Ollama post-edit'
  if (normalized === 'mixed') return 'Mix'
  return normalized
}

export function translationProviderClass(provider) {
  const normalized = String(provider || '')
    .trim()
    .toLowerCase()
  if (normalized === 'libretranslate') return 'providerBadge providerBadge--lt'
  if (normalized === 'ollama') return 'providerBadge providerBadge--ollama'
  if (normalized === 'ollama_postedit') return 'providerBadge providerBadge--ollama'
  if (normalized === 'mixed') return 'providerBadge providerBadge--mixed'
  return 'providerBadge providerBadge--muted'
}

export function translationModeLabel(mode) {
  const normalized = String(mode || '')
    .trim()
    .toLowerCase()
  if (normalized === 'lt_ollama_postedit') return 'LT + Ollama úprava'
  if (normalized === 'ollama_direct') return 'Ollama priamo'
  if (normalized === 'lt_only') return 'Len LT'
  return normalized || '-'
}

export function toPositiveIntOrNull(value) {
  const parsed = Number(value)
  if (Number.isInteger(parsed) && parsed > 0) {
    return parsed
  }

  return null
}

export function normalizeRunMode(value) {
  return String(value || '')
    .trim()
    .toLowerCase() === 'dry'
    ? 'dry'
    : 'auto'
}

export function runModeLabel(run) {
  return normalizeRunMode(run?.meta?.mode) === 'dry' ? 'TEST' : 'AUTO'
}

export function runModeClass(run) {
  return runModeLabel(run) === 'TEST' ? 'modeBadge modeBadge--dry' : 'modeBadge modeBadge--auto'
}

export function runPublishLimit(run) {
  return toPositiveIntOrNull(run?.meta?.publish_limit)
}

export function resolvePublishAllLimitDefault(run) {
  return runPublishLimit(run) ?? DEFAULT_PUBLISH_ALL_LIMIT
}

export function normalizeMaybeBool(value) {
  if (value === true) return true
  if (value === false) return false

  if (typeof value === 'number') {
    if (value === 1) return true
    if (value === 0) return false
    return null
  }

  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase()
    if (['1', 'true', 'yes'].includes(normalized)) return true
    if (['0', 'false', 'no'].includes(normalized)) return false
  }

  return null
}

export function isPublishedItem(item) {
  const status = String(item?.publish_status || '').toLowerCase()
  if (status === 'published') return true
  return Number(item?.post_id || 0) > 0
}

export function isManualPublishedItem(item) {
  if (!isPublishedItem(item)) {
    return false
  }

  const candidates = [
    item?.published_manually,
    item?.meta?.published_manually,
    item?.post_meta?.published_manually,
    item?.post?.meta?.published_manually,
  ]

  return candidates.some((value) => normalizeMaybeBool(value) === true)
}

export function resolveItemSourceKey(item, fallbackSourceKey = '') {
  const candidates = [
    item?.source_key,
    item?.bot_source_key,
    item?.meta?.bot_source_key,
    item?.meta?.source_key,
    item?.post_meta?.bot_source_key,
    item?.post?.bot_source_key,
    item?.post?.meta?.bot_source_key,
    fallbackSourceKey,
  ]

  for (const candidate of candidates) {
    const normalized = String(candidate || '')
      .trim()
      .toLowerCase()
    if (normalized !== '') {
      return normalized
    }
  }

  return ''
}

export function requiresPublishConfirm(item, fallbackSourceKey = '') {
  return resolveItemSourceKey(item, fallbackSourceKey) === 'wiki_onthisday_astronomy'
}

export function botIdentityLabel(identity) {
  return BOT_LABELS[identity] || identity
}

export function sourceTypeLabel(sourceType) {
  const normalized = String(sourceType || '')
    .trim()
    .toLowerCase()
  if (!normalized) {
    return '-'
  }

  if (normalized === 'rss') {
    return 'RSS'
  }

  if (normalized === 'api') {
    return 'API'
  }

  return normalized.toUpperCase()
}

export function sourceStateLabel(isEnabled) {
  return isEnabled ? 'Aktívny' : 'Vypnutý'
}

export function sourceCountLabel(count) {
  const normalized = Number(count) || 0

  if (normalized === 1) {
    return '1 zdroj'
  }

  if (normalized >= 2 && normalized <= 4) {
    return `${normalized} zdroje`
  }

  return `${normalized} zdrojov`
}

export function quickRunResultChips(result) {
  const chips = []

  if (Number(result?.successCount || 0) > 0) {
    chips.push(`OK ${Number(result.successCount)}`)
  }

  if (Number(result?.partialCount || 0) > 0) {
    chips.push(`ciastocne ${Number(result.partialCount)}`)
  }

  if (Number(result?.skippedCount || 0) > 0) {
    chips.push(`preskočené ${Number(result.skippedCount)}`)
  }

  if (Number(result?.failedCount || 0) > 0) {
    chips.push(`chyby ${Number(result.failedCount)}`)
  }

  if (chips.length === 0) {
    chips.push('bez vysledku')
  }

  return chips.join(', ')
}

export function itemStatusLabel(status) {
  const normalized = String(status || '')
    .trim()
    .toLowerCase()

  if (normalized === 'published') return 'Publikované'
  if (normalized === 'done' || normalized === 'success') return 'Hotovo'
  if (normalized === 'pending') return 'Čaká'
  if (normalized === 'skipped') return 'Preskočené'
  if (normalized === 'failed') return 'Chyba'

  return normalized || 'Neznáme'
}

export function runStatCount(run, key) {
  return toStatNumber(run?.stats?.[key])
}

export function normalizeOutageProvider(value) {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
  return ['none', 'ollama', 'libretranslate'].includes(normalized) ? normalized : 'none'
}

export function runStatusHint(run) {
  return run?.ui_message || run?.meta?.ui_message || run?.error_text || ''
}

export function runStatusLabel(run) {
  const reason = String(run?.failure_reason || run?.meta?.failure_reason || '').toLowerCase()
  if (
    reason === BOT_FAILURE_REASONS.RATE_LIMITED ||
    reason === BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED
  ) {
    return 'Limit'
  }
  if (reason === BOT_FAILURE_REASONS.NEEDS_API_KEY) {
    return 'Chýba API kľúč'
  }

  const normalized = String(run?.status || '')
    .trim()
    .toLowerCase()
  if (normalized === 'success') return 'Hotovo'
  if (normalized === 'partial') return 'Čiastočne'
  if (normalized === 'failed') return 'Chyba'
  if (normalized === 'skipped') return 'Preskočené'
  return 'Neznáme'
}

export function statusClass(status) {
  const normalizedReason = String(
    status?.failure_reason || status?.meta?.failure_reason || '',
  ).toLowerCase()
  if (
    [
      BOT_FAILURE_REASONS.RATE_LIMITED,
      BOT_FAILURE_REASONS.COOLDOWN_RATE_LIMITED,
      BOT_FAILURE_REASONS.NEEDS_API_KEY,
    ].includes(normalizedReason)
  ) {
    return 'statusBadge statusBadge--partial'
  }

  const normalized = String(status?.status || status || '').toLowerCase()
  if (normalized === 'success') return 'statusBadge statusBadge--success'
  if (normalized === 'partial') return 'statusBadge statusBadge--partial'
  if (normalized === 'failed') return 'statusBadge statusBadge--failed'
  return 'statusBadge statusBadge--muted'
}

export function canPublishItem(item) {
  const status = String(item?.publish_status || '').toLowerCase()
  if (status === 'published' || status === 'skipped') return false
  return !item?.post_id
}

export function canDeleteItemPost(item) {
  const postId = Number(item?.post_id || 0)
  return Number.isInteger(postId) && postId > 0
}
