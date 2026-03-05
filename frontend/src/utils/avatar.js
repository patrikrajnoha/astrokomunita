import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  coerceAvatarIndex,
  normalizeAvatarMode,
  pickDeterministicAvatarIndex,
} from '@/constants/avatar'

function resolveOriginFromBaseUrl(baseUrl) {
  const raw = String(baseUrl || '').trim()
  if (!raw) return ''
  if (/^https?:\/\//i.test(raw)) {
    return raw.replace(/\/api\/?$/i, '').replace(/\/+$/, '')
  }

  return ''
}

export function normalizeAvatarUrl(value, options = {}) {
  const raw = String(value || '').trim()
  if (!raw) return ''
  if (/^https?:\/\//i.test(raw) || raw.startsWith('data:')) return raw

  if (!raw.startsWith('/')) {
    return raw
  }

  const explicitOrigin = resolveOriginFromBaseUrl(options.baseUrl)
  if (explicitOrigin) {
    return `${explicitOrigin}${raw}`
  }

  if (typeof window !== 'undefined' && window.location?.origin) {
    return `${window.location.origin}${raw}`
  }

  return raw
}

export function resolveAvatarState(user, overrides = {}) {
  const source = user && typeof user === 'object' ? user : {}
  const imageUrl = normalizeAvatarUrl(
    overrides.avatarUrl ?? source.avatar_url ?? source.avatarUrl ?? '',
    { baseUrl: overrides.baseUrl },
  )

  const mode = normalizeAvatarMode(overrides.mode ?? source.avatar_mode ?? source.avatarMode)
  const iconMaxIndex = AVATAR_ICONS.length - 1
  const colorMaxIndex = AVATAR_COLORS.length - 1

  const rawSeed = String(overrides.seed ?? source.avatar_seed ?? source.avatarSeed ?? '').trim()
  const seed = rawSeed !== '' ? rawSeed : String(source.username || source.name || source.id || 'avatar')

  const colorIndex = coerceAvatarIndex(overrides.colorIndex ?? source.avatar_color ?? source.avatarColor, colorMaxIndex)
  const iconIndex = coerceAvatarIndex(overrides.iconIndex ?? source.avatar_icon ?? source.avatarIcon, iconMaxIndex)

  return {
    mode,
    imageUrl,
    seed,
    colorIndex: colorIndex ?? pickDeterministicAvatarIndex(seed, 'color', AVATAR_COLORS.length),
    iconIndex: iconIndex ?? pickDeterministicAvatarIndex(seed, 'icon', AVATAR_ICONS.length),
  }
}
