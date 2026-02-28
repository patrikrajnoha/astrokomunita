export const SKY_WIDGET_SECTION_IDS = [
  'hero_score',
  'best_time',
  'weather_inline',
  'moon',
  'bortle',
  'planets',
  'iss',
]

export const VISIBLE_ALT_MIN = 10
export const LOW_ALT_MIN = 5
export const MIN_VISIBLE_ELONGATION = 20
export const CLOSE_TO_SUN_ELONGATION = 15
export const NIGHT_SUN_ALTITUDE_MAX = -12

export function moveSection(sectionIds, sectionId, direction) {
  const list = Array.isArray(sectionIds) ? [...sectionIds] : [...SKY_WIDGET_SECTION_IDS]
  const index = list.indexOf(sectionId)
  if (index === -1) return list

  const targetIndex = direction === 'up' ? index - 1 : index + 1
  if (targetIndex < 0 || targetIndex >= list.length) return list

  const [item] = list.splice(index, 1)
  list.splice(targetIndex, 0, item)
  return list
}

export function getScorePresentation(score) {
  if (!Number.isFinite(score)) {
    return { label: 'Nedostupne', emoji: '😐' }
  }

  if (score >= 80) {
    return { label: 'Vyborne', emoji: '😄' }
  }

  if (score >= 60) {
    return { label: 'Dobre', emoji: '🙂' }
  }

  if (score >= 40) {
    return { label: 'Priemerne', emoji: '😐' }
  }

  return { label: 'Slabe', emoji: '☁️' }
}

export function getBortlePresentation(bortle) {
  const numeric = toFiniteNumber(bortle)
  if (numeric === null) return null

  const normalized = Math.max(1, Math.min(9, Math.round(numeric)))

  if (normalized <= 2) {
    return { levelText: 'velmi tmave', contextText: 'divocina / vysoke hory', bortle: normalized }
  }

  if (normalized <= 4) {
    return { levelText: 'tmave', contextText: 'vidiek', bortle: normalized }
  }

  if (normalized <= 6) {
    return { levelText: 'stredne', contextText: 'predmestie', bortle: normalized }
  }

  if (normalized === 7) {
    return { levelText: 'vysoke', contextText: 'mesto', bortle: normalized }
  }

  return { levelText: 'velmi vysoke', contextText: 'centrum velkeho mesta', bortle: normalized }
}

export function isPlanetNight(sunAltitudeDeg) {
  const sunAltitude = toFiniteNumber(sunAltitudeDeg)
  return sunAltitude !== null && sunAltitude < NIGHT_SUN_ALTITUDE_MAX
}

export function getPlanetVisibilityTag({ sunAltitudeDeg, altitudeDeg, elongationDeg }) {
  const altitude = toFiniteNumber(altitudeDeg)
  const elongation = toFiniteNumber(elongationDeg)

  if (altitude === null || elongation === null || !isPlanetNight(sunAltitudeDeg)) {
    return 'hidden'
  }

  if (altitude < LOW_ALT_MIN) {
    return 'hidden'
  }

  if (altitude < VISIBLE_ALT_MIN) {
    return 'low'
  }

  if (elongation < CLOSE_TO_SUN_ELONGATION) {
    return 'close_to_sun'
  }

  if (elongation >= MIN_VISIBLE_ELONGATION) {
    return 'visible'
  }

  return 'hidden'
}

export function getPlanetVisibilityPresentation(visibilityTag) {
  switch (String(visibilityTag || '').toLowerCase()) {
    case 'visible':
      return { label: 'Viditeľná', toneClass: 'text-emerald-200 border-emerald-400/20 bg-emerald-400/10' }
    case 'close_to_sun':
      return { label: 'Blízko Slnka', toneClass: 'text-amber-200 border-amber-400/20 bg-amber-400/10' }
    case 'low':
      return { label: 'Nízko nad obzorom', toneClass: 'text-amber-200 border-amber-400/20 bg-amber-400/10' }
    default:
      return null
  }
}

export function getVisiblePlanets(planetsPayload) {
  const planets = Array.isArray(planetsPayload?.planets)
    ? planetsPayload.planets
    : Array.isArray(planetsPayload)
      ? planetsPayload
      : []
  const sunAltitude = toFiniteNumber(planetsPayload?.sun_altitude_deg)

  if (!isPlanetNight(sunAltitude)) {
    return []
  }

  return planets
    .map((planet) => {
      const altitude = toFiniteNumber(planet?.altitude_deg)
      const elongation = toFiniteNumber(planet?.elongation_deg)
      const visibilityTag = getPlanetVisibilityTag({
        sunAltitudeDeg: sunAltitude,
        altitudeDeg: altitude,
        elongationDeg: elongation,
      })
      const presentation = getPlanetVisibilityPresentation(visibilityTag)

      if (!presentation) {
        return null
      }

      return {
        name: sanitizeLabel(planet?.name) || 'Planeta',
        direction: sanitizeLabel(planet?.direction) || 'neznamy smer',
        bestTimeWindow: sanitizeLabel(planet?.best_time_window) || '',
        altitude,
        elongation,
        altitudeLabel: altitude === null ? '-' : `${Math.round(altitude)}°`,
        elongationLabel: elongation === null ? '-' : `${Math.round(elongation)}°`,
        visibilityTag,
        visibilityLabel: presentation.label,
        visibilityToneClass: presentation.toneClass,
        isVisible: visibilityTag === 'visible',
      }
    })
    .filter(Boolean)
    .sort((a, b) => {
      if (a.visibilityTag !== b.visibilityTag) {
        return Number(b.isVisible) - Number(a.isVisible)
      }

      return (b.altitude ?? -Infinity) - (a.altitude ?? -Infinity)
    })
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function sanitizeLabel(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}
