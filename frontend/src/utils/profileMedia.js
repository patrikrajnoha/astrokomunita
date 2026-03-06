import api from '@/services/api'
import { AVATAR_COLORS, AVATAR_ICONS } from '@/constants/avatar'

const BOT_MEDIA_PRESET_TABLE = {
  stellarbot: {
    key: 'stellarbot',
    avatar: {
      icon: 'comet',
      color: '#ef75ea',
    },
    cover: {
      primary: '192 132 252',
      secondary: '168 85 247',
      accent: '244 208 255',
    },
  },
  kozmobot: {
    key: 'kozmobot',
    avatar: {
      icon: 'planet',
      color: '#1185fe',
    },
    cover: {
      primary: '59 130 246',
      secondary: '14 165 233',
      accent: '186 230 253',
    },
  },
}

const GENERIC_BOT_MEDIA_PRESET = {
  key: 'bot-generic',
  avatar: {
    icon: 'star',
    color: '#73df84',
  },
  cover: {
    primary: '56 189 248',
    secondary: '15 23 42',
    accent: '186 230 253',
  },
}

const DEFAULT_COVER_FALLBACK_STYLE = {
  backgroundImage: [
    'radial-gradient(900px 220px at 20% 20%, rgb(var(--color-primary-rgb) / 0.25), transparent 60%)',
    'radial-gradient(700px 220px at 80% 30%, rgb(var(--color-primary-rgb) / 0.12), transparent 60%)',
    'linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.2), rgb(var(--color-bg-rgb) / 0.9))',
  ].join(', '),
}

function mediaOrigin() {
  const base = String(api?.defaults?.baseURL || '').trim()
  const configuredOrigin = base.replace(/\/api\/?$/, '')
  if (configuredOrigin !== '') {
    return configuredOrigin
  }

  if (typeof window !== 'undefined') {
    return String(window.location.origin || '').trim()
  }

  return ''
}

function encodeMediaPath(inputPath) {
  return String(inputPath || '')
    .split('/')
    .filter(Boolean)
    .map((segment) => encodeURIComponent(segment))
    .join('/')
}

function toNormalizedIndex(value, allowlist, fallbackIndex = 0) {
  const list = Array.isArray(allowlist) ? allowlist : []
  if (list.length === 0) {
    return 0
  }

  if (typeof value === 'number' && Number.isInteger(value) && value >= 0 && value < list.length) {
    return value
  }

  const normalized = String(value || '').trim().toLowerCase()
  if (normalized !== '') {
    const foundIndex = list.findIndex((item) => String(item).trim().toLowerCase() === normalized)
    if (foundIndex >= 0) {
      return foundIndex
    }
  }

  return Math.min(Math.max(0, fallbackIndex), list.length - 1)
}

function botPresetKey(username) {
  const normalized = String(username || '').trim().toLowerCase()
  return normalized !== '' ? normalized : 'bot-generic'
}

function botUsername(user) {
  return String(user?.username || '').trim().toLowerCase()
}

function botCoverFallbackStyle(coverPreset) {
  const primary = String(coverPreset?.primary || '56 189 248').trim()
  const secondary = String(coverPreset?.secondary || '15 23 42').trim()
  const accent = String(coverPreset?.accent || '186 230 253').trim()

  return {
    backgroundImage: [
      `radial-gradient(920px 220px at 12% 16%, rgb(${primary} / 0.4), transparent 60%)`,
      `radial-gradient(760px 220px at 88% 25%, rgb(${secondary} / 0.34), transparent 62%)`,
      `linear-gradient(180deg, rgb(${accent} / 0.24), rgb(6 11 24 / 0.92))`,
    ].join(', '),
  }
}

export function isBotAccount(user) {
  const role = String(user?.role || '').trim().toLowerCase()
  return role === 'bot' || Boolean(user?.is_bot)
}

export function normalizeMediaUrl(url) {
  const value = String(url || '').trim()
  if (value === '') return ''

  const origin = mediaOrigin()

  const absoluteStorageMatch = value.match(/^https?:\/\/[^/]+\/storage\/(.+)$/i)
  if (absoluteStorageMatch) {
    if (origin === '') return value
    return `${origin}/api/media/file/${encodeMediaPath(absoluteStorageMatch[1])}`
  }

  const absoluteMediaApiMatch = value.match(/^https?:\/\/[^/]+\/api\/media\/file\/(.+)$/i)
  if (absoluteMediaApiMatch) {
    if (origin === '') return value
    return `${origin}/api/media/file/${encodeMediaPath(absoluteMediaApiMatch[1])}`
  }

  if (value.startsWith('/storage/')) {
    if (origin === '') return value
    return `${origin}/api/media/file/${encodeMediaPath(value.slice('/storage/'.length))}`
  }

  if (value.startsWith('/api/media/file/')) {
    if (origin === '') return value
    return `${origin}/api/media/file/${encodeMediaPath(value.slice('/api/media/file/'.length))}`
  }

  if (/^https?:\/\//i.test(value)) return value
  if (origin === '') return value
  if (value.startsWith('/')) return origin + value

  return origin + '/' + value
}

export function normalizeMediaPath(path) {
  const value = String(path || '').trim()
  if (value === '') return ''

  if (/^https?:\/\//i.test(value)) return normalizeMediaUrl(value)
  if (value.startsWith('/api/media/file/') || value.startsWith('/storage/')) {
    return normalizeMediaUrl(value)
  }

  const normalized = value
    .replace(/\\/g, '/')
    .replace(/^\/+/, '')
    .replace(/\/+$/, '')

  if (normalized === '' || normalized.includes('..')) return ''

  return normalizeMediaUrl(`/api/media/file/${normalized}`)
}

export function resolveBotMediaPreset(user) {
  const username = botUsername(user)
  const preset = BOT_MEDIA_PRESET_TABLE[username] || GENERIC_BOT_MEDIA_PRESET
  const fallbackColorIndex = AVATAR_COLORS.findIndex((value) => value === '#73df84')
  const colorIndex = toNormalizedIndex(
    preset.avatar?.color,
    AVATAR_COLORS,
    fallbackColorIndex >= 0 ? fallbackColorIndex : 0,
  )
  const iconIndex = toNormalizedIndex(preset.avatar?.icon, AVATAR_ICONS, 1)
  const key = preset.key || botPresetKey(username)
  const seedUsername = username !== '' ? username : 'bot'

  return {
    key,
    username,
    colorIndex,
    iconIndex,
    seed: `bot:${seedUsername}:${key}`,
    coverFallbackStyle: botCoverFallbackStyle(preset.cover),
  }
}

export function resolveAvatarDisplayUser(user) {
  if (!user || typeof user !== 'object') {
    return user
  }

  const avatarUrl =
    normalizeMediaUrl(user.avatar_url ?? user.avatarUrl ?? '') ||
    normalizeMediaPath(user.avatar_path ?? user.avatarPath ?? '')

  if (!isBotAccount(user) || avatarUrl !== '') {
    return user
  }

  const preset = resolveBotMediaPreset(user)
  return {
    ...user,
    avatar_mode: 'generated',
    avatar_color: preset.colorIndex,
    avatar_icon: preset.iconIndex,
    avatar_seed: preset.seed,
    avatar_url: '',
    avatar_path: '',
  }
}

export function resolveUserCoverMedia(user) {
  const imageUrl =
    normalizeMediaUrl(user?.cover_url ?? user?.coverUrl ?? '') ||
    normalizeMediaPath(user?.cover_path ?? user?.coverPath ?? '')
  const hasImage = imageUrl !== ''
  const botAccount = isBotAccount(user)
  const preset = botAccount ? resolveBotMediaPreset(user) : null

  return {
    imageUrl,
    hasImage,
    isBot: botAccount,
    isBotFallback: botAccount && !hasImage,
    presetKey: preset?.key || null,
    fallbackStyle: botAccount ? preset.coverFallbackStyle : DEFAULT_COVER_FALLBACK_STYLE,
  }
}

export function resolveUserProfileMedia(user) {
  return {
    avatarUser: resolveAvatarDisplayUser(user),
    cover: resolveUserCoverMedia(user),
  }
}
