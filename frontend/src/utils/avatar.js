import api from '@/services/api'
import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  coerceAvatarIndex,
  normalizeAvatarMode,
  pickDeterministicAvatarIndex,
} from '@/constants/avatar'
import { avatarDebug } from '@/utils/avatarDebug'

export function normalizeAvatarUrl(url) {
  const value = String(url || '').trim()
  if (!value) return ''

  const base = api?.defaults?.baseURL || ''
  const origin = base.replace(/\/api\/?$/, '')
  const appOrigin = origin || (typeof window !== 'undefined' ? window.location.origin : '')

  const encodeMediaPath = (inputPath) =>
    String(inputPath || '')
      .split('/')
      .filter(Boolean)
      .map((segment) => encodeURIComponent(segment))
      .join('/')

  const debugAndReturn = (result, reason) => {
    avatarDebug('normalizeAvatarUrl', {
      reason,
      input: value,
      output: result,
      baseURL: base,
      appOrigin,
    })
    return result
  }

  const absoluteStorageMatch = value.match(/^https?:\/\/[^/]+\/storage\/(.+)$/i)
  if (absoluteStorageMatch) {
    if (!appOrigin) return debugAndReturn(value, 'absolute-storage-no-origin')
    return debugAndReturn(
      `${appOrigin}/api/media/file/${encodeMediaPath(absoluteStorageMatch[1])}`,
      'absolute-storage',
    )
  }

  const absoluteMediaApiMatch = value.match(/^https?:\/\/[^/]+\/api\/media\/file\/(.+)$/i)
  if (absoluteMediaApiMatch) {
    if (!appOrigin) return debugAndReturn(value, 'absolute-media-api-no-origin')
    return debugAndReturn(
      `${appOrigin}/api/media/file/${encodeMediaPath(absoluteMediaApiMatch[1])}`,
      'absolute-media-api',
    )
  }

  if (value.startsWith('/storage/')) {
    if (!appOrigin) return debugAndReturn(value, 'relative-storage-no-origin')
    return debugAndReturn(
      `${appOrigin}/api/media/file/${encodeMediaPath(value.slice('/storage/'.length))}`,
      'relative-storage',
    )
  }

  if (value.startsWith('/api/media/file/')) {
    if (!appOrigin) return debugAndReturn(value, 'relative-media-api-no-origin')
    return debugAndReturn(
      `${appOrigin}/api/media/file/${encodeMediaPath(value.slice('/api/media/file/'.length))}`,
      'relative-media-api',
    )
  }

  if (/^https?:\/\//i.test(value)) return debugAndReturn(value, 'absolute-http')
  if (!appOrigin) return debugAndReturn(value, 'no-origin')

  if (value.startsWith('/')) return debugAndReturn(appOrigin + value, 'relative-rooted')
  return debugAndReturn(appOrigin + '/' + value, 'relative-path')
}

function normalizeAvatarPath(path) {
  const value = String(path || '').trim()
  if (!value) return ''

  if (/^https?:\/\//i.test(value)) return normalizeAvatarUrl(value)
  if (value.startsWith('/api/media/file/') || value.startsWith('/storage/')) {
    return normalizeAvatarUrl(value)
  }

  const normalized = value
    .replace(/\\/g, '/')
    .replace(/^\/+/, '')
    .replace(/\/+$/, '')
  if (!normalized || normalized.includes('..')) return ''

  return normalizeAvatarUrl(`/api/media/file/${normalized}`)
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
  const mode = normalizeAvatarMode(overrides.mode ?? user?.avatar_mode ?? user?.avatarMode)
  const imageUrlFromUrl = normalizeAvatarUrl(overrides.avatarUrl ?? user?.avatar_url ?? user?.avatarUrl ?? '')
  const imageUrlFromPath = normalizeAvatarPath(overrides.avatarPath ?? user?.avatar_path ?? user?.avatarPath ?? '')
  const imageUrl = imageUrlFromUrl || imageUrlFromPath
  const seed = resolveAvatarSeed(user, overrides.seed ?? '')

  const colorIndex =
    normalizeAvatarListIndex(overrides.colorIndex ?? user?.avatar_color ?? user?.avatarColor, AVATAR_COLORS) ??
    pickDeterministicAvatarIndex(seed, 'color', AVATAR_COLORS.length)

  const iconIndex =
    normalizeAvatarListIndex(overrides.iconIndex ?? user?.avatar_icon ?? user?.avatarIcon, AVATAR_ICONS) ??
    pickDeterministicAvatarIndex(seed, 'icon', AVATAR_ICONS.length)

  const hasImage = imageUrl !== ''
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
