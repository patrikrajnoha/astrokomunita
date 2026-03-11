export const SKY_PHASE = Object.freeze({
  LOCATION_REQUIRED: 'location_required',
  UNKNOWN: 'unknown',
  DAY: 'day',
  CIVIL_TWILIGHT: 'civil_twilight',
  NAUTICAL_TWILIGHT: 'nautical_twilight',
  ASTRONOMICAL_TWILIGHT: 'astronomical_twilight',
  ASTRONOMICAL_NIGHT: 'astronomical_night',
})

export const SKY_PHASE_LABELS = Object.freeze({
  [SKY_PHASE.LOCATION_REQUIRED]: 'Poloha chyba',
  [SKY_PHASE.UNKNOWN]: 'Neznamy stav',
  [SKY_PHASE.DAY]: 'Den',
  [SKY_PHASE.CIVIL_TWILIGHT]: 'Obciansky sumrak',
  [SKY_PHASE.NAUTICAL_TWILIGHT]: 'Nauticky sumrak',
  [SKY_PHASE.ASTRONOMICAL_TWILIGHT]: 'Astronomicky sumrak',
  [SKY_PHASE.ASTRONOMICAL_NIGHT]: 'Astronomicka noc',
})

export function classifySkyPhase({ hasLocationCoords, sunAltitudeDeg }) {
  if (!hasLocationCoords) {
    return SKY_PHASE.LOCATION_REQUIRED
  }

  const altitude = toFiniteNumber(sunAltitudeDeg)
  if (altitude === null) {
    return SKY_PHASE.UNKNOWN
  }

  if (altitude > 0) {
    return SKY_PHASE.DAY
  }

  if (altitude > -6) {
    return SKY_PHASE.CIVIL_TWILIGHT
  }

  if (altitude > -12) {
    return SKY_PHASE.NAUTICAL_TWILIGHT
  }

  if (altitude > -18) {
    return SKY_PHASE.ASTRONOMICAL_TWILIGHT
  }

  return SKY_PHASE.ASTRONOMICAL_NIGHT
}

export function classifySkyPhaseFromTimeline({
  hasLocationCoords,
  nowTs = Date.now(),
  sunriseAt,
  sunsetAt,
  civilTwilightEndAt,
}) {
  if (!hasLocationCoords) {
    return SKY_PHASE.LOCATION_REQUIRED
  }

  const timing = resolveNightTiming({ nowTs, sunriseAt, sunsetAt, civilTwilightEndAt })
  if (!timing) {
    return SKY_PHASE.UNKNOWN
  }

  if (timing.isNightNow) {
    return SKY_PHASE.ASTRONOMICAL_NIGHT
  }

  if (timing.isCivilTwilightNow) {
    return SKY_PHASE.CIVIL_TWILIGHT
  }

  return SKY_PHASE.DAY
}

export function resolveNightTiming({
  nowTs = Date.now(),
  sunriseAt,
  sunsetAt,
  civilTwilightEndAt,
}) {
  const sunriseToday = parseDate(sunriseAt)
  const sunsetToday = parseDate(sunsetAt)
  const twilightEndToday = parseDate(civilTwilightEndAt) || sunsetToday

  if (!(sunriseToday instanceof Date) || Number.isNaN(sunriseToday.getTime())) return null
  if (!(sunsetToday instanceof Date) || Number.isNaN(sunsetToday.getTime())) return null
  if (!(twilightEndToday instanceof Date) || Number.isNaN(twilightEndToday.getTime())) return null

  const DAY_MS = 24 * 60 * 60 * 1000
  const nowMs = Number.isFinite(nowTs) ? nowTs : Date.now()
  const sunriseMs = sunriseToday.getTime()
  const sunsetMs = sunsetToday.getTime()
  const nightStartTodayMs = Math.max(twilightEndToday.getTime(), sunsetMs)
  const sunriseTomorrowMs = sunriseMs + DAY_MS
  const nightStartYesterdayMs = nightStartTodayMs - DAY_MS
  const nightStartTomorrowMs = nightStartTodayMs + DAY_MS

  const isCivilTwilightNow = nowMs >= sunsetMs && nowMs < nightStartTodayMs
  const isNightAfterEvening = nowMs >= nightStartTodayMs && nowMs < sunriseTomorrowMs
  const isNightAfterMidnight = nowMs >= nightStartYesterdayMs && nowMs < sunriseMs
  const isNightNow = isNightAfterEvening || isNightAfterMidnight

  let upcomingNightStartMs = nightStartTodayMs
  if (nowMs >= nightStartTodayMs) {
    upcomingNightStartMs = nightStartTomorrowMs
  }

  return {
    isNightNow,
    isCivilTwilightNow,
    upcomingNightStart: new Date(upcomingNightStartMs),
  }
}

export function isTwilightSkyPhase(phase) {
  return phase === SKY_PHASE.CIVIL_TWILIGHT
    || phase === SKY_PHASE.NAUTICAL_TWILIGHT
    || phase === SKY_PHASE.ASTRONOMICAL_TWILIGHT
}

export function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

export function parseDate(value) {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}
