export const SKY_WIDGET_SECTION_IDS = [
  'hero_score',
  'best_time',
  'weather_inline',
  'moon',
  'bortle',
  'planets',
  'iss',
]

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
    return { label: 'Nedostupné', emoji: '😐' }
  }

  if (score < 40) {
    return { label: 'Zlé', emoji: '🙁' }
  }

  if (score < 65) {
    return { label: 'Priemerné', emoji: '😐' }
  }

  if (score < 85) {
    return { label: 'Dobré', emoji: '🙂' }
  }

  return { label: 'Výborné', emoji: '😄' }
}

export function getVisiblePlanets(planets, minimumAltitude = 5) {
  if (!Array.isArray(planets)) return []

  return planets
    .map((planet) => {
      const altitude = toFiniteNumber(planet?.altitude_deg)
      if (altitude === null || altitude < minimumAltitude) return null

      return {
        name: sanitizeLabel(planet?.name) || 'Planéta',
        direction: sanitizeLabel(planet?.direction) || 'neznámy smer',
        bestTimeWindow: sanitizeLabel(planet?.best_time_window) || '',
        altitude,
        altitudeLabel: `${Math.round(altitude)}°`,
        horizonNote: altitude < 15 ? 'nízko nad horizontom' : '',
      }
    })
    .filter(Boolean)
    .sort((a, b) => b.altitude - a.altitude)
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
