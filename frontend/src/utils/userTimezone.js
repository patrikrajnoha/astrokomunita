import { EVENT_TIMEZONE } from '@/utils/eventTime'

const DEFAULT_LOCATION_LABEL = 'Bratislava, Slovakia'

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

function readLocationData(user) {
  const value = user?.location_data
  return value && typeof value === 'object' ? value : null
}

export function isValidTimezone(value) {
  const timezone = readString(value)
  if (timezone === '') return false

  try {
    new Intl.DateTimeFormat('en-US', { timeZone: timezone })
    return true
  } catch {
    return false
  }
}

export function resolveUserPreferredTimezone(user) {
  const locationData = readLocationData(user)
  const timezoneCandidates = [
    locationData?.timezone,
    user?.timezone,
  ]

  for (const candidate of timezoneCandidates) {
    const timezone = readString(candidate)
    if (isValidTimezone(timezone)) {
      return timezone
    }
  }

  return EVENT_TIMEZONE
}

export function resolveUserLocationLabel(user) {
  const locationData = readLocationData(user)
  const labelCandidates = [
    locationData?.label,
    user?.location_label,
    user?.location,
  ]

  for (const candidate of labelCandidates) {
    const label = readString(candidate)
    if (label !== '') {
      return label
    }
  }

  return DEFAULT_LOCATION_LABEL
}

export function resolveUserCoordinates(user) {
  const locationData = readLocationData(user)

  const latitude = readNumber(locationData?.latitude ?? user?.latitude)
  const longitude = readNumber(locationData?.longitude ?? user?.longitude)

  if (latitude === null || longitude === null) {
    return null
  }

  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
    return null
  }

  return {
    lat: latitude,
    lon: longitude,
  }
}
