import api from '@/services/api'
import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  coerceAvatarIndex,
  normalizeAvatarMode,
  pickDeterministicAvatarIndex,
} from '@/constants/avatar'
import { avatarDebug } from '@/utils/avatarDebug'
import {
  normalizeMediaPath,
  normalizeMediaUrl,
} from '@/utils/profileMedia'

export function normalizeAvatarUrl(url) {
  const normalized = normalizeMediaUrl(url)
  const value = String(url || '').trim()
  avatarDebug('normalizeAvatarUrl', {
    input: value,
    output: normalized,
    baseURL: api?.defaults?.baseURL || '',
  })
  return normalized
}

function normalizeAvatarPath(path) {
  return normalizeMediaPath(path)
}

function normalizeAvatarListIndex(value, allowlist) {
  const list = Array.isArray(allowlist) ? allowlist : []
  const max = list.length - 1
  const numericIndex = coerceAvatarIndex(value, max)
  if (numericIndex !== null) return numericIndex

  const normalized = String(value || '').trim().toLowerCase()
  if (!normalized) return null

  for (let index = 0; index < list.length; index += 1) {
    if (String(list[index]).trim().toLowerCase() === normalized) {
      return index
    }
  }

  return null
}

export function avatarSeed(user) {
  return user?.id ?? user?.username ?? user?.email ?? user?.name ?? 'user'
}

export function resolveAvatarSeed(user, fallbackSeed = '') {
  const preferredSeed = String(fallbackSeed || '').trim()
  if (preferredSeed) return preferredSeed

  const explicitSeed = String(user?.avatar_seed || user?.avatarSeed || '').trim()
  if (explicitSeed) return explicitSeed

  return String(avatarSeed(user))
}

export function resolveAvatarState(user, overrides = {}) {
  const imageUrlFromUrl = normalizeAvatarUrl(overrides.avatarUrl ?? user?.avatar_url ?? user?.avatarUrl ?? '')
  const imageUrlFromPath = normalizeAvatarPath(overrides.avatarPath ?? user?.avatar_path ?? user?.avatarPath ?? '')
  const imageUrl = imageUrlFromUrl || imageUrlFromPath
  const hasImage = imageUrl !== ''
  const mode = normalizeAvatarMode(overrides.mode ?? user?.avatar_mode ?? user?.avatarMode)
  const seed = resolveAvatarSeed(user, overrides.seed ?? '')

  const colorIndex =
    normalizeAvatarListIndex(
      overrides.colorIndex ?? user?.avatar_color ?? user?.avatarColor,
      AVATAR_COLORS,
    ) ??
    pickDeterministicAvatarIndex(seed, 'color', AVATAR_COLORS.length)

  const iconIndex =
    normalizeAvatarListIndex(
      overrides.iconIndex ?? user?.avatar_icon ?? user?.avatarIcon,
      AVATAR_ICONS,
    ) ??
    pickDeterministicAvatarIndex(seed, 'icon', AVATAR_ICONS.length)

  const usesImage = hasImage && mode === 'image'
  const state = {
    mode,
    imageUrl,
    hasImage,
    usesImage,
    seed,
    colorIndex,
    iconIndex,
  }

  avatarDebug('resolveAvatarState', {
    userId: user?.id ?? null,
    username: user?.username ?? null,
    input: {
      avatar_mode: user?.avatar_mode ?? user?.avatarMode ?? null,
      avatar_url: user?.avatar_url ?? user?.avatarUrl ?? null,
      avatar_color: user?.avatar_color ?? user?.avatarColor ?? null,
      avatar_icon: user?.avatar_icon ?? user?.avatarIcon ?? null,
      avatar_seed: user?.avatar_seed ?? user?.avatarSeed ?? null,
      is_bot: user?.is_bot ?? null,
      role: user?.role ?? null,
    },
    overrides,
    output: state,
  })

  return state
}

export function resolveUserAvatar(user, overrides = {}) {
  const state = resolveAvatarState(user, overrides)
  return state.usesImage ? state.imageUrl : ''
}

export function hasUserAvatar(user, overrides = {}) {
  return resolveUserAvatar(user, overrides) !== ''
}
