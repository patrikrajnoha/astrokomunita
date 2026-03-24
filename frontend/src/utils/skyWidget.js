export const SKY_WIDGET_SECTION_IDS = [
  'weather',
  'moon',
  'light_pollution',
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
    return { label: 'Nedostupne', tone: 'neutral' }
  }

  if (score >= 80) {
    return { label: 'Vyborne', tone: 'excellent' }
  }

  if (score >= 60) {
    return { label: 'Dobré', tone: 'good' }
  }

  if (score >= 40) {
    return { label: 'Priemerné', tone: 'fair' }
  }

  return { label: 'Slabe', tone: 'poor' }
}

export function getBortlePresentation(bortle) {
  const numeric = toFiniteNumber(bortle)
  if (numeric === null) return null

  const normalized = Math.max(1, Math.min(9, Math.round(numeric)))

  if (normalized <= 2) {
    return {
      levelText: 'Velmi tmava obloha',
      contextText: 'divocina a vysoke hory',
      impactText: 'Vhodne aj pre slabé deep-sky objekty.',
      bortle: normalized,
      tone: 'excellent',
    }
  }

  if (normalized <= 4) {
    return {
      levelText: 'Tmava obloha',
      contextText: 'vidiek',
      impactText: 'Dobré podmienky pre väčšinu deep-sky objektov.',
      bortle: normalized,
      tone: 'good',
    }
  }

  if (normalized <= 6) {
    return {
      levelText: 'Stredne svetelne znecistenie',
      contextText: 'predmestie',
      impactText: 'Lepsie pre jasne objekty a planety.',
      bortle: normalized,
      tone: 'fair',
    }
  }

  if (normalized === 7) {
    return {
      levelText: 'Vyssie svetelne znecistenie',
      contextText: 'mesto',
      impactText: 'Deep-sky je limitovane, vhodne skor na planety.',
      bortle: normalized,
      tone: 'poor',
    }
  }

  return {
    levelText: 'Velmi vysoke svetelne znecistenie',
    contextText: 'centrum velkeho mesta',
    impactText: 'Vhodne hlavne na Mesiac a najjasnejsie objekty.',
    bortle: normalized,
    tone: 'poor',
  }
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
      return {
        label: 'Viditeľná',
        toneClass: 'planetBadge planetBadge--visible',
      }
    case 'close_to_sun':
      return {
        label: 'Blizko Slnka',
        toneClass: 'planetBadge planetBadge--warning',
      }
    case 'low':
      return {
        label: 'Nizko nad obzorom',
        toneClass: 'planetBadge planetBadge--warning',
      }
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
      const magnitude = toFiniteNumber(planet?.magnitude)
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
        direction: sanitizeLabel(planet?.direction) || '-',
        bestTimeWindow: sanitizeLabel(planet?.best_time_window) || '',
        altitude,
        elongation,
        magnitude,
        altitudeLabel: altitude === null ? '-' : `${Math.round(altitude)} deg`,
        elongationLabel: elongation === null ? '-' : `${Math.round(elongation)} deg`,
        magnitudeLabel: magnitude === null ? '' : `mag ${magnitude.toFixed(1)}`,
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
