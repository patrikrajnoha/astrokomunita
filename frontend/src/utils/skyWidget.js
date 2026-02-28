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

  if (score >= 80) {
    return { label: 'Výborné', emoji: '😄' }
  }

  if (score >= 60) {
    return { label: 'Dobré', emoji: '🙂' }
  }

  if (score >= 40) {
    return { label: 'Priemerné', emoji: '😐' }
  }

  return { label: 'Slabé', emoji: '☹️' }
}

export function getBortlePresentation(bortle) {
  const numeric = toFiniteNumber(bortle)
  if (numeric === null) return null

  const normalized = Math.max(1, Math.min(9, Math.round(numeric)))

  if (normalized <= 2) {
    return { levelText: 'veľmi tmavé', contextText: 'divočina / vysoké hory', bortle: normalized }
  }

  if (normalized <= 4) {
    return { levelText: 'tmavé', contextText: 'vidiek', bortle: normalized }
  }

  if (normalized <= 6) {
    return { levelText: 'stredné', contextText: 'predmestie', bortle: normalized }
  }

  if (normalized === 7) {
    return { levelText: 'vysoké', contextText: 'mesto', bortle: normalized }
  }

  return { levelText: 'veľmi vysoké', contextText: 'centrum veľkého mesta', bortle: normalized }
}

export function getVisiblePlanets(planets, options = {}) {
  if (!Array.isArray(planets)) return []

  const visibleAltMin = Number.isFinite(options.visibleAltMin) ? options.visibleAltMin : VISIBLE_ALT_MIN
  const lowAltMin = Number.isFinite(options.lowAltMin) ? options.lowAltMin : LOW_ALT_MIN

  return planets
    .map((planet) => {
      const altitude = toFiniteNumber(planet?.altitude_deg)
      if (altitude === null || altitude < lowAltMin) return null

      const isVisible = altitude >= visibleAltMin

      return {
        name: sanitizeLabel(planet?.name) || 'Planéta',
        direction: sanitizeLabel(planet?.direction) || 'neznámy smer',
        bestTimeWindow: sanitizeLabel(planet?.best_time_window) || '',
        altitude,
        altitudeLabel: `${Math.round(altitude)}°`,
        visibilityLabel: isVisible ? 'viditeľná' : 'nízko nad obzorom',
        isVisible,
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
