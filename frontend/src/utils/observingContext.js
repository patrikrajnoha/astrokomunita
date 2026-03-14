function readString(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

function readNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function readObject(value) {
  return value && typeof value === 'object' ? value : null
}

function parseDateQuery(value) {
  const source = readString(Array.isArray(value) ? value[0] : value)
  return /^\d{4}-\d{2}-\d{2}$/.test(source) ? source : null
}

function localIsoDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function isValidTimezone(value) {
  const timezone = readString(value)
  if (!timezone) return false

  try {
    new Intl.DateTimeFormat('en-US', { timeZone: timezone }).format(new Date())
    return true
  } catch {
    return false
  }
}

export function resolveObservingContext({ user = null, preferences = null, dateQuery = null, now = new Date() } = {}) {
  const locationData = readObject(user?.location_data)
  const locationMeta = readObject(user?.location_meta)

  const latitudeCandidates = [
    locationData?.latitude,
    locationMeta?.lat,
    locationMeta?.latitude,
    preferences?.locationLat,
    user?.latitude,
  ]
  const longitudeCandidates = [
    locationData?.longitude,
    locationMeta?.lon,
    locationMeta?.longitude,
    preferences?.locationLon,
    user?.longitude,
  ]
  const labelCandidates = [
    locationData?.label,
    locationMeta?.label,
    locationMeta?.name,
    preferences?.locationLabel,
    user?.location_label,
    user?.location,
  ]
  const timezoneCandidates = [
    locationData?.timezone,
    locationMeta?.tz,
    locationMeta?.timezone,
    user?.timezone,
  ]

  let lat = null
  for (const candidate of latitudeCandidates) {
    const normalized = readNumber(candidate)
    if (normalized !== null && normalized >= -90 && normalized <= 90) {
      lat = normalized
      break
    }
  }

  let lon = null
  for (const candidate of longitudeCandidates) {
    const normalized = readNumber(candidate)
    if (normalized !== null && normalized >= -180 && normalized <= 180) {
      lon = normalized
      break
    }
  }

  let locationName = ''
  for (const candidate of labelCandidates) {
    const normalized = readString(candidate)
    if (normalized) {
      locationName = normalized
      break
    }
  }

  let tz = ''
  for (const candidate of timezoneCandidates) {
    if (isValidTimezone(candidate)) {
      tz = readString(candidate)
      break
    }
  }

  if (!tz) {
    tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Bratislava'
  }

  return {
    lat,
    lon,
    tz,
    locationName,
    date: parseDateQuery(dateQuery) ?? localIsoDate(now),
  }
}
