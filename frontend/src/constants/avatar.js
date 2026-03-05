export const AVATAR_COLORS = [
  '#2563eb',
  '#0891b2',
  '#7c3aed',
  '#0f766e',
  '#db2777',
  '#ea580c',
  '#16a34a',
  '#334155',
]

export const AVATAR_ICONS = ['planet', 'star', 'comet', 'constellation', 'moon']

export function normalizeAvatarMode(value) {
  const normalized = String(value || '').trim().toLowerCase()
  if (normalized === 'generated' || normalized === 'avatar') return 'generated'
  return 'image'
}

export function coerceAvatarIndex(value, maxIndex) {
  const parsed = Number(value)
  if (!Number.isInteger(parsed)) return null
  if (!Number.isInteger(maxIndex) || maxIndex < 0) return null
  if (parsed < 0 || parsed > maxIndex) return null
  return parsed
}

export function hashAvatarString(value) {
  const input = String(value || '')
  let hash = 0
  for (let index = 0; index < input.length; index += 1) {
    hash = ((hash << 5) - hash) + input.charCodeAt(index)
    hash |= 0
  }

  return Math.abs(hash)
}

export function pickDeterministicAvatarIndex(seed, namespace, size) {
  const total = Number(size)
  if (!Number.isInteger(total) || total <= 0) return 0

  const key = `${namespace || 'avatar'}:${String(seed || '')}`
  const hash = hashAvatarString(key)
  return hash % total
}
