import {
  EVENT_TIMEZONE,
  formatEventDate,
  formatEventDateKey,
  formatEventTime,
  parseEventDate,
} from '@/utils/eventTime'

export function resolvePhenomenonDate(item) {
  return parseDate(
    item?.max_at ||
      item?.start_at ||
      item?.starts_at ||
      item?.end_at ||
      item?.ends_at,
  )
}

export function syncPlanFormFromEvent(planForm, item) {
  const plan = item?.plan && typeof item.plan === 'object' ? item.plan : null

  planForm.personal_note = normalizeFieldText(plan?.personal_note)
  planForm.planned_location_label = normalizeFieldText(plan?.planned_location_label)
  planForm.planned_time = toDateTimeLocal(plan?.planned_time)

  const reminder = toDateTimeLocal(plan?.reminder_at)
  if (reminder) {
    planForm.reminder_mode = 'custom'
    planForm.reminder_custom_at = reminder
    return
  }

  planForm.reminder_mode = 'none'
  planForm.reminder_custom_at = ''
}

export function resolveReminderPresetDate(mode, item) {
  const anchor = resolveEventAnchorDate(item)
  if (!anchor) return null

  if (mode === 'one_hour_before') {
    return new Date(anchor.getTime() - 60 * 60 * 1000)
  }

  if (mode === 'day_before') {
    return new Date(anchor.getTime() - 24 * 60 * 60 * 1000)
  }

  if (mode === 'same_day_morning') {
    const dateKey = formatDateKey(anchor, EVENT_TIMEZONE)
    if (!dateKey) return null
    return parseDateTimeLocal(`${dateKey}T08:00`)
  }

  return null
}

export function resolveEventAnchorDate(item) {
  return parseDate(
    item?.start_at ||
      item?.starts_at ||
      item?.max_at ||
      item?.end_at ||
      item?.ends_at,
  )
}

export function toDateTimeLocal(value) {
  const parsed = parseDate(value)
  if (!parsed) return ''

  const local = new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60 * 1000)
  return local.toISOString().slice(0, 16)
}

export function parseDateTimeLocal(value) {
  if (typeof value !== 'string' || value.trim() === '') return null

  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function normalizeFieldText(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

export function toNullableString(value) {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

export function formatEventMetaDate(item, timeZone) {
  if (!item) return 'Datum upresnime'

  const startAt = parseDate(item.start_at || item.starts_at || item.max_at || item.end_at || item.ends_at)
  const endAt = parseDate(item.end_at || item.ends_at)

  if (!startAt) return 'Datum upresnime'
  if (!endAt || formatDateKey(startAt, timeZone) === formatDateKey(endAt, timeZone)) {
    return formatDateLabel(startAt, timeZone)
  }

  return `${formatDateLabel(startAt, timeZone)} - ${formatDateLabel(endAt, timeZone)}`
}

export function formatDateLabel(value, timeZone) {
  return formatEventDate(value, timeZone, {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}

export function formatDateKey(value, timeZone) {
  return formatEventDateKey(value, timeZone)
}

export function formatTime(value, timeZone) {
  return formatEventTime(value, timeZone).timeString
}

export function parseDate(value) {
  return parseEventDate(value)
}

export function mapType(type) {
  const types = {
    meteors: 'Meteory',
    meteor_shower: 'Meteoricky roj',
    eclipse_lunar: 'Zatmenie Mesiaca',
    eclipse_solar: 'Zatmenie Slnka',
    planetary_event: 'Planetarny jav',
    conjunction: 'Konjunkcia',
    comet: 'Kometa',
    asteroid: 'Asteroid',
    mission: 'Misia',
    other: 'Udalost',
  }

  return types[type] || 'Udalost'
}

export function mapStatus(item) {
  const startRaw = item?.start_at || item?.starts_at || item?.max_at
  if (!startRaw) return 'Termin caka'

  const start = parseDate(startRaw)
  if (!start) return 'Termin caka'

  const eventDayKey = formatDateKey(start, EVENT_TIMEZONE)
  const todayKey = formatDateKey(new Date(), EVENT_TIMEZONE)
  if (!eventDayKey || !todayKey) return 'Termin caka'

  if (eventDayKey < todayKey) return 'Prebehlo'
  if (eventDayKey === todayKey) return 'Dnes'
  return 'Planovane'
}

export function mapVisibility(value) {
  if (value === 1 || value === '1') return 'Viditelne zo Slovenska'
  if (value === 0 || value === '0') return 'Mimo Slovenska'
  return 'Viditelnost sa upresni'
}

export function mapConfidence(level) {
  if (level === 'verified') return 'Overene'
  if (level === 'partial') return 'Ciastocne overene'
  if (level === 'low') return 'Nizsia dovera'
  return ''
}

export function resolveUserLocation(user) {
  if (!user || typeof user !== 'object') return null

  const locationData = user.location_data && typeof user.location_data === 'object'
    ? user.location_data
    : null
  const locationMeta = user.location_meta && typeof user.location_meta === 'object'
    ? user.location_meta
    : null

  const lat = toFiniteNumber(locationData?.latitude ?? locationMeta?.lat)
  const lon = toFiniteNumber(locationData?.longitude ?? locationMeta?.lon)
  const tz = sanitizeLocationText(locationData?.timezone ?? locationMeta?.tz) || EVENT_TIMEZONE
  const label = sanitizeLocationText(
    locationData?.label ?? locationMeta?.label ?? user.location_label ?? user.location,
  )

  if (lat === null || lon === null) {
    return null
  }

  return {
    lat,
    lon,
    tz,
    label,
  }
}

export function sanitizeLocationText(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

export function normalizeEventsList(response) {
  const payload = response?.data
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  return []
}

export function resolveAdjacentIds(items, currentId) {
  const byId = new Map()

  for (const item of items) {
    const normalizedId = Number(item?.id)
    if (!Number.isInteger(normalizedId) || byId.has(normalizedId)) continue
    byId.set(normalizedId, item)
  }

  const ordered = Array.from(byId.values()).sort((a, b) => {
    const aDate = resolveSortableEventDate(a)
    const bDate = resolveSortableEventDate(b)
    const aTime = aDate ? aDate.getTime() : Number.POSITIVE_INFINITY
    const bTime = bDate ? bDate.getTime() : Number.POSITIVE_INFINITY
    if (aTime !== bTime) return aTime - bTime
    return Number(a?.id || 0) - Number(b?.id || 0)
  })

  const index = ordered.findIndex((item) => Number(item?.id) === currentId)
  if (index < 0) {
    return { prev: null, next: null }
  }

  const prev = Number(ordered[index - 1]?.id)
  const next = Number(ordered[index + 1]?.id)

  return {
    prev: Number.isInteger(prev) ? prev : null,
    next: Number.isInteger(next) ? next : null,
  }
}

export function resolveSortableEventDate(item) {
  return parseDate(
    item?.event_date ||
      item?.start_at ||
      item?.starts_at ||
      item?.max_at ||
      item?.end_at ||
      item?.ends_at,
  )
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}
