export function calculateAstronomyScore(input = {}) {
  const sunAltitudeDeg = toFiniteNumber(input.sunAltitudeDeg)
  const cloudPercent = clamp01Percent(input.cloudPercent)
  const humidityPercent = clamp01Percent(input.humidityPercent)
  const windKmh = toFiniteNumber(input.windKmh)
  const moonIlluminationPercent = clamp01Percent(input.moonIlluminationPercent)
  const moonAltitudeDeg = toFiniteNumber(input.moonAltitudeDeg)
  const bortleClass = toFiniteNumber(input.bortleClass)

  const phase = resolvePhase(sunAltitudeDeg)
  const daylight = phase === 'daylight'
  const twilight = phase === 'twilight'
  const twilightCap = twilight ? 40 : null

  const cloudsScore = 1 - (cloudPercent / 100)
  const humidityScore = 1 - smoothstep(humidityPercent, 60, 95)
  const windScore = 1 - smoothstep(windKmh, 10, 35)
  const moonPenalty = resolveMoonPenalty(moonIlluminationPercent, moonAltitudeDeg)
  const lightPollutionPenalty = resolveBortlePenalty(bortleClass)

  const baseScore = 100 * (
    (0.45 * cloudsScore)
    + (0.20 * humidityScore)
    + (0.20 * windScore)
    + (0.15 * (1 - moonPenalty))
  )
  const adjustedScore = baseScore * (1 - (0.35 * lightPollutionPenalty))
  const gatedScore = daylight
    ? null
    : twilight
      ? Math.min(adjustedScore, twilightCap)
      : adjustedScore
  const score = gatedScore === null ? null : clampInt(Math.round(gatedScore), 0, 100)

  const reasons = buildReasons({
    phase,
    cloudPercent,
    humidityPercent,
    windKmh,
    moonIlluminationPercent,
    moonAltitudeDeg,
    bortleClass,
  })

  return {
    score,
    phase,
    twilightCap,
    reasons,
    components: {
      cloudsScore: round3(cloudsScore),
      humidityScore: round3(humidityScore),
      windScore: round3(windScore),
      moonPenalty: round3(moonPenalty),
      lightPollutionPenalty: round3(lightPollutionPenalty),
      baseScore: round1(baseScore),
      adjustedScore: round1(adjustedScore),
    },
  }
}

function buildReasons(input) {
  const reasons = []

  if (input.phase === 'daylight') {
    reasons.push('Denné svetlo')
  } else if (input.phase === 'twilight') {
    reasons.push('Sumrak')
  }

  reasons.push(`Oblacnost ${Math.round(input.cloudPercent)}%`)
  reasons.push(`Vlhkost ${Math.round(input.humidityPercent)}%`)

  const wind = toFiniteNumber(input.windKmh)
  if (wind !== null) {
    reasons.push(`Vietor ${round1(wind)} km/h`)
  }

  const moonIllumination = toFiniteNumber(input.moonIlluminationPercent)
  const moonAltitude = toFiniteNumber(input.moonAltitudeDeg)
  if (moonIllumination !== null && moonAltitude !== null && moonAltitude > 0) {
    reasons.push(`Mesiac ${Math.round(moonIllumination)}% nad obzorom`)
  } else if (moonAltitude !== null && moonAltitude <= 0) {
    reasons.push('Mesiac pod obzorom')
  }

  const bortle = toFiniteNumber(input.bortleClass)
  if (bortle !== null) {
    reasons.push(`Bortle ${Math.round(clamp(bortle, 1, 9))}`)
  }

  return reasons.slice(0, 4)
}

function resolvePhase(sunAltitudeDeg) {
  const altitude = toFiniteNumber(sunAltitudeDeg)
  if (altitude === null) return 'unknown'
  if (altitude > -6) return 'daylight'
  if (altitude > -18) return 'twilight'
  return 'astronomical_night'
}

function resolveMoonPenalty(moonIlluminationPercent, moonAltitudeDeg) {
  const illumination = toFiniteNumber(moonIlluminationPercent)
  const altitude = toFiniteNumber(moonAltitudeDeg)
  if (illumination === null || altitude === null || altitude <= 0) return 0

  const illuminationFactor = smoothstep(illumination, 20, 100)
  const altitudeFactor = smoothstep(altitude, 5, 60)

  return clamp(illuminationFactor * altitudeFactor, 0, 1)
}

function resolveBortlePenalty(bortleClass) {
  const bortle = toFiniteNumber(bortleClass)
  if (bortle === null) return 0
  return clamp((Math.round(bortle) - 1) / 8, 0, 1)
}

function smoothstep(value, edge0, edge1) {
  const x = toFiniteNumber(value)
  if (x === null) return 0.5
  if (edge0 === edge1) return x < edge0 ? 0 : 1
  const t = clamp((x - edge0) / (edge1 - edge0), 0, 1)
  return t * t * (3 - (2 * t))
}

function clamp01Percent(value) {
  const numeric = toFiniteNumber(value)
  if (numeric === null) return 50
  return clamp(numeric, 0, 100)
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value))
}

function clampInt(value, min, max) {
  return Math.max(min, Math.min(max, value))
}

function round1(value) {
  return Math.round(value * 10) / 10
}

function round3(value) {
  return Math.round(value * 1000) / 1000
}
