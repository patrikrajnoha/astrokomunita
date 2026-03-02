export const EVENT_TIMEZONE = 'Europe/Bratislava'
export const EVENT_TIMEZONE_SHORT_LABEL = 'SK'

const UNKNOWN_TIME_FALLBACK_SOURCES = ['imo']

export function parseEventDate(value) {
  if (!value) return null

  const parsed = value instanceof Date ? new Date(value.getTime()) : new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function formatEventDate(value, timeZone = EVENT_TIMEZONE, options = {}) {
  const date = parseEventDate(value)
  if (!date) return '-'

  return new Intl.DateTimeFormat('sk-SK', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
    timeZone,
    ...options,
  }).format(date)
}

export function formatEventDateKey(value, timeZone = EVENT_TIMEZONE) {
  const date = parseEventDate(value)
  if (!date) return ''

  const parts = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone,
  }).formatToParts(date)

  const resolved = Object.fromEntries(parts.map((part) => [part.type, part.value]))
  if (!resolved.year || !resolved.month || !resolved.day) return ''

  return `${resolved.year}-${resolved.month}-${resolved.day}`
}

export function formatEventTime(value, timeZone = EVENT_TIMEZONE, options = {}) {
  const labels = resolveTimezoneLabels(timeZone)
  const timezoneLabelStyle = options.timezoneLabelStyle === 'short' ? 'short' : 'long'
  const date = parseEventDate(value)
  if (!date) {
    return {
      timeString: '',
      timezoneLabel: timezoneLabelStyle === 'short' ? labels.short : labels.long,
      timezoneLabelShort: labels.short,
      timezoneLabelLong: labels.long,
    }
  }

  return {
    timeString: new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone,
    }).format(date),
    timezoneLabel: timezoneLabelStyle === 'short' ? labels.short : labels.long,
    timezoneLabelShort: labels.short,
    timezoneLabelLong: labels.long,
  }
}

export function getHourInTimezone(value, timeZone = EVENT_TIMEZONE) {
  const date = parseEventDate(value)
  if (!date) return null

  const formatted = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    hour12: false,
    timeZone,
  }).format(date)
  const parsed = Number(formatted)

  return Number.isFinite(parsed) ? parsed : null
}

export function getEventNowPeriodDefaults(timeZone = EVENT_TIMEZONE) {
  const now = new Date()
  const parts = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone,
  }).formatToParts(now)
  const resolved = Object.fromEntries(parts.map((part) => [part.type, part.value]))

  const year = Number(resolved.year || now.getUTCFullYear())
  const month = Number(resolved.month || now.getUTCMonth() + 1)
  const day = Number(resolved.day || now.getUTCDate())

  return {
    year,
    month,
    day,
    week: getIsoWeekFromParts(year, month, day),
  }
}

export function resolveEventTimeContext(event, timeZone = EVENT_TIMEZONE) {
  const timeType = normalizeTimeType(event)
  const timePrecision = normalizeTimePrecision(event)

  if (timePrecision === 'unknown') {
    const labels = resolveTimezoneLabels(timeZone)
    return {
      timeType,
      timePrecision,
      timeString: '',
      timezoneLabel: labels.long,
      timezoneLabelShort: labels.short,
      timezoneLabelLong: labels.long,
      showTimezoneLabel: false,
      message: 'Cas bude upresneny',
    }
  }

  const rawValue =
    timeType === 'peak'
      ? event?.max_at || event?.start_at || event?.starts_at
      : event?.start_at || event?.starts_at || event?.max_at
  const { timeString, timezoneLabel, timezoneLabelShort, timezoneLabelLong } = formatEventTime(
    rawValue,
    timeZone,
  )

  if (!timeString) {
    return {
      timeType: 'unknown',
      timePrecision: 'unknown',
      timeString: '',
      timezoneLabel,
      timezoneLabelShort,
      timezoneLabelLong,
      showTimezoneLabel: false,
      message: 'Cas bude upresneny',
    }
  }

  const approximate = timePrecision === 'approximate' ? ' priblizne' : ''
  const prefix = resolveTimePrefix(timeType)

  return {
    timeType,
    timePrecision,
    timeString,
    timezoneLabel,
    timezoneLabelShort,
    timezoneLabelLong,
    showTimezoneLabel: true,
    message: `${prefix}${approximate} o ${timeString}`,
  }
}

function normalizeTimeType(event) {
  const raw = String(event?.time_type || '').trim()
  if (['start', 'peak', 'window', 'unknown'].includes(raw)) {
    return raw
  }

  const start = parseEventDate(event?.start_at || event?.starts_at)
  const max = parseEventDate(event?.max_at)

  if (!start && !max) return 'unknown'
  if (max && (!start || start.getTime() !== max.getTime())) return 'peak'

  return 'start'
}

function normalizeTimePrecision(event) {
  const raw = String(event?.time_precision || '').trim()
  if (['exact', 'approximate', 'unknown'].includes(raw)) {
    return raw
  }

  const start = parseEventDate(event?.start_at || event?.starts_at)
  const max = parseEventDate(event?.max_at)

  if (!start && !max) {
    return 'unknown'
  }

  if (hasUnknownMidnightFallback(event)) {
    return 'unknown'
  }

  return 'exact'
}

function hasUnknownMidnightFallback(event) {
  if (!usesUnknownTimeFallback(event)) return false

  const values = [event?.max_at, event?.start_at, event?.starts_at]
    .filter((value) => typeof value === 'string')
    .map((value) => value.trim())
    .filter(Boolean)

  if (values.length === 0) return false

  return values.every((value) => isMidnightValue(value))
}

function resolveTimePrefix(timeType) {
  if (timeType === 'peak') return 'Maximum'
  if (timeType === 'window') return 'Okno'
  if (timeType === 'unknown') return 'Cas'

  return 'Zaciatok'
}

function resolveTimezoneLabels(timeZone) {
  if (timeZone === EVENT_TIMEZONE) {
    return {
      short: EVENT_TIMEZONE_SHORT_LABEL,
      long: EVENT_TIMEZONE,
    }
  }

  return {
    short: timeZone,
    long: timeZone,
  }
}

function usesUnknownTimeFallback(event) {
  const sourceName = resolveSourceName(event)
  return UNKNOWN_TIME_FALLBACK_SOURCES.includes(sourceName)
}

function resolveSourceName(event) {
  return String(event?.source_name || event?.source?.name || '')
    .trim()
    .toLowerCase()
}

function isMidnightValue(value) {
  return /T00:00(?::00(?:\.000)?)?(?:Z|[+-]\d{2}:\d{2})$/.test(value) || / 00:00:00$/.test(value)
}

function getIsoWeekFromParts(year, month, day) {
  const dt = new Date(Date.UTC(year, month - 1, day))
  const dayNum = dt.getUTCDay() || 7
  dt.setUTCDate(dt.getUTCDate() + 4 - dayNum)
  const yearStart = new Date(Date.UTC(dt.getUTCFullYear(), 0, 1))

  return Math.ceil(((dt - yearStart) / 86400000 + 1) / 7)
}
