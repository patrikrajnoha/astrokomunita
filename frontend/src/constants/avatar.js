export const AVATAR_MODES = ['image', 'generated']

export const AVATAR_COLORS = ['#fe8311', '#fed811', '#73df84', '#1185fe', '#ef75ea', '#f55454']

export const AVATAR_ICONS = [
  'planet',
  'star',
  'comet',
  'constellation',
  'moon',
  'sun',
  'galaxy',
  'rocket',
  'satellite',
  'meteor',
]

export const AVATAR_ICON_LABELS = {
  planet: 'Planeta',
  star: 'Hviezda',
  comet: 'Kometa',
  constellation: 'Suhvezdie',
  moon: 'Mesiac',
  sun: 'Slnko',
  galaxy: 'Galaxia',
  rocket: 'Raketa',
  satellite: 'Druzica',
  meteor: 'Meteor',
}

// Keep the original deterministic icon pool stable for users without an explicit symbol choice.
export const LEGACY_AVATAR_ICON_COUNT = 5

export function hashAvatarString(value) {
  const text = String(value || '')
  let hash = 0
  for (let i = 0; i < text.length; i += 1) {
    hash = (hash * 31 + text.charCodeAt(i)) >>> 0
  }
  return hash
}

export function coerceAvatarIndex(value, max) {
  if (!Number.isFinite(Number(max)) || max < 0) return null
  if (value === null || value === undefined || value === '') return null

  if (typeof value === 'number' || typeof value === 'string') {
    const parsed = Number(value)
    if (Number.isInteger(parsed) && parsed >= 0 && parsed <= max) {
      return parsed
    }
  }

  return null
}

export function normalizeAvatarMode(value) {
  const normalized = String(value || '').trim().toLowerCase()
  if (AVATAR_MODES.includes(normalized)) return normalized
  return 'image'
}

export function pickDeterministicAvatarIndex(seed, salt, max) {
  if (!Number.isFinite(Number(max)) || max <= 0) return 0
  const hash = hashAvatarString(`${String(seed || '').trim()}:${salt}`)
  return hash % max
}
