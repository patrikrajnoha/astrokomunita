import { toFiniteNumber } from './skyPhase'

export function formatTime(date, timeZone) {
  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  }
}

export function formatIsoShort(value, timeZone) {
  if (!value) return '-'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  }
}

export function formatTimeOrDash(value, timeZone) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return '-'
  return formatTime(value, timeZone)
}

export function resolvePayloadTimestamp(payload, keys) {
  if (!payload || typeof payload !== 'object') return null
  const fields = Array.isArray(keys) ? keys : []

  for (const key of fields) {
    const raw = typeof payload[key] === 'string' ? payload[key].trim() : ''
    if (!raw) continue
    const parsed = new Date(raw)
    if (!Number.isNaN(parsed.getTime())) return parsed
  }

  return null
}

export function normalizeSourceLabel(value) {
  const normalized = sanitizeLabel(value)
  if (!normalized) return 'neznámy'
  return normalized.replace(/_/g, '-')
}

export function formatPercent(value) {
  const numeric = toFiniteNumber(value)
  return numeric === null ? '-' : `${Math.round(numeric)}%`
}

export function formatTemperature(value) {
  const numeric = toFiniteNumber(value)
  return numeric === null ? '-' : `${numeric.toFixed(1)} \u00B0C`
}

export function formatWind(speed, unit) {
  const numeric = toFiniteNumber(speed)
  const normalizedUnit = String(unit || 'km/h')
  return numeric === null ? '-' : `${numeric.toFixed(1)} ${normalizedUnit}`
}

export function formatDurationMinutes(value) {
  const numeric = toFiniteNumber(value)
  if (numeric === null) return '-'
  const minutes = Math.max(1, Math.round(numeric / 60))
  return `${minutes} min`
}

export function sanitizeLabel(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

export function formatFreshness(value, tick) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return ''
  const minutes = Math.max(0, Math.round((tick - value.getTime()) / 60000))
  if (minutes <= 0) return 'Aktualizované práve teraz'
  return `Aktualizované pred ${minutes} min`
}

export function toFriendlyError(_error, fallback) {
  const fromUserMessage = sanitizeLabel(_error?.userMessage)
  if (fromUserMessage) return fromUserMessage

  const fromBackendMessage = sanitizeLabel(_error?.response?.data?.message)
  if (fromBackendMessage) return fromBackendMessage

  const status = Number(_error?.response?.status || 0)

  if (status === 422) return 'Poloha je neplatna. Skontroluj ju v profile.'
  if (status === 429) return 'Prilis vela poziadaviek. Skus to znova o chvilu.'
  if (status === 401) return 'Prihlas sa a skus to znova.'
  if (status >= 500) return 'Server je dočasne nedostupny. Skus to neskor.'

  const message = sanitizeLabel(_error?.message)
  if (message) return message

  return fallback
}
