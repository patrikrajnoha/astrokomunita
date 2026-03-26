import { AVATAR_COLORS } from '@/constants/avatar'
import { normalizeMediaPath, normalizeMediaUrl } from '@/utils/profileMedia'

const BOT_AVATAR_CONFIG = {
  stellarbot: {
    prefix: 'sb',
    defaultFile: 'sb_blue.png',
    files: ['sb_blue.png', 'sb_green.png', 'sb_orange.png', 'sb_pink.png', 'sb_red.png', 'sb_yellow.png'],
  },
  kozmobot: {
    prefix: 'kb',
    defaultFile: 'kb_blue.png',
    files: ['kb_blue.png', 'kb_green.png', 'kb_orange.png', 'kb_red.png', 'kb_yellow.png'],
  },
}

const COLOR_NAMES_BY_INDEX = ['orange', 'yellow', 'green', 'blue', 'pink', 'red']

const COLOR_NAME_ALLOWLIST = ['blue', 'green', 'orange', 'pink', 'red', 'yellow']

const COLOR_NAME_BY_HEX = AVATAR_COLORS.reduce((acc, hex, index) => {
  const normalizedHex = String(hex || '').trim().toLowerCase()
  if (!normalizedHex) return acc
  const colorName = COLOR_NAMES_BY_INDEX[index] || ''
  if (!colorName) return acc
  acc[normalizedHex] = colorName
  return acc
}, {})

function normalizeBotUsername(value) {
  return String(value || '').trim().toLowerCase()
}

export function isBotUser(user) {
  if (!user || typeof user !== 'object') return false
  return Boolean(user.is_bot) || normalizeBotUsername(user.role) === 'bot'
}

function getBotConfig(username) {
  const normalized = normalizeBotUsername(username)
  if (!normalized) return null
  return BOT_AVATAR_CONFIG[normalized] || null
}

function normalizeColorName(color) {
  if (typeof color === 'number' && Number.isInteger(color)) {
    return COLOR_NAMES_BY_INDEX[color] || ''
  }

  const value = String(color ?? '').trim().toLowerCase()
  if (!value) return ''

  if (/^\d+$/.test(value)) {
    return COLOR_NAMES_BY_INDEX[Number.parseInt(value, 10)] || ''
  }

  if (COLOR_NAME_ALLOWLIST.includes(value)) {
    return value
  }

  return COLOR_NAME_BY_HEX[value] || ''
}

export function mapColorToFile(color, botName = '') {
  const config = getBotConfig(botName)
  if (!config) return ''

  const colorName = normalizeColorName(color)
  if (!colorName) return config.defaultFile

  const candidate = `${config.prefix}_${colorName}.png`
  return config.files.includes(candidate) ? candidate : config.defaultFile
}

function botAvatarPath(username, file) {
  const normalizedUsername = normalizeBotUsername(username)
  const normalizedFile = String(file || '').trim()
  if (!normalizedUsername || !normalizedFile) return ''
  return `bots/${normalizedUsername}/${normalizedFile}`
}

function botAvatarLegacyUrlFromPath(path) {
  const normalizedPath = String(path || '').trim().replace(/^\/+/, '')
  if (!normalizedPath) return ''

  const parts = normalizedPath.split('/').filter(Boolean)
  if (parts.length !== 3 || parts[0] !== 'bots') return ''

  const [, username, file] = parts
  return `/assets/bots/${encodeURIComponent(username)}/${encodeURIComponent(file)}`
}

function resolveBotAssetPathFromUrl(url, username, config) {
  const value = String(url || '').trim()
  if (!value) return { matchesBotAsset: false, path: '' }

  const normalizedUrl = value.replace(/^https?:\/\/[^/]+/i, '').replace(/^\/+/, '')
  const parts = normalizedUrl.split('/').filter(Boolean)
  if (parts.length !== 4) {
    return { matchesBotAsset: false, path: '' }
  }

  const isApiBotAvatar = parts[0] === 'api' && parts[1] === 'bot-avatars'
  const isStaticBotAvatar = parts[0] === 'assets' && parts[1] === 'bots'
  if (!isApiBotAvatar && !isStaticBotAvatar) {
    return { matchesBotAsset: false, path: '' }
  }

  if (parts[2] !== username) {
    return { matchesBotAsset: false, path: '' }
  }

  const file = decodeURIComponent(parts[3] || '')
  return {
    matchesBotAsset: true,
    path: config.files.includes(file) ? botAvatarPath(username, file) : '',
  }
}

function resolveSelectedBotFile({ username, config, avatarPath }) {
  const normalizedPath = String(avatarPath || '').trim().replace(/\\/g, '/').replace(/^\/+/, '')
  const pathParts = normalizedPath.split('/').filter(Boolean)
  const fromPath =
    pathParts.length === 3 && pathParts[0] === 'bots' && pathParts[1] === username
      ? pathParts[2]
      : ''

  if (fromPath && config.files.includes(fromPath)) {
    return fromPath
  }

  return config.defaultFile
}

function normalizeAvatarPath(value) {
  return String(value || '')
    .trim()
    .replace(/\\/g, '/')
    .replace(/^\/+/, '')
}

function isBotAssetPath(path, username) {
  const normalizedPath = normalizeAvatarPath(path)
  const parts = normalizedPath.split('/').filter(Boolean)
  return parts.length === 3 && parts[0] === 'bots' && parts[1] === username
}

function isCustomUploadedAvatarUrl(url) {
  const value = String(url || '').trim().toLowerCase()
  if (!value) return false
  return value.includes('/api/media/file/') || value.includes('/storage/')
}

export function getBotAvatar(user, overrides = {}) {
  if (!isBotUser(user)) return null

  const username = normalizeBotUsername(overrides.username ?? user?.username)
  const config = getBotConfig(username)
  if (!config) return null
  const normalizedPath = normalizeAvatarPath(overrides.avatarPath ?? user?.avatar_path ?? user?.avatarPath ?? '')
  const rawExplicitUrl = overrides.avatarUrl ?? user?.avatar_url ?? user?.avatarUrl ?? ''
  const explicitUrl = normalizeMediaUrl(rawExplicitUrl)
  const explicitBotAsset = resolveBotAssetPathFromUrl(rawExplicitUrl, username, config)

  const isCustomUpload = normalizedPath !== '' && !isBotAssetPath(normalizedPath, username)
  if (isCustomUpload) {
    return {
      username,
      file: '',
      path: normalizedPath,
      url: explicitUrl || normalizeMediaPath(normalizedPath) || '',
      files: [...config.files],
      defaultFile: config.defaultFile,
      isCustomUpload: true,
    }
  }

  if (normalizedPath === '' && isCustomUploadedAvatarUrl(explicitUrl)) {
    return {
      username,
      file: '',
      path: '',
      url: explicitUrl,
      files: [...config.files],
      defaultFile: config.defaultFile,
      isCustomUpload: true,
    }
  }

  const selectedFile = resolveSelectedBotFile({
    username,
    config,
    avatarPath: normalizedPath || explicitBotAsset.path,
  })

  const path = botAvatarPath(username, selectedFile)
  const fallbackPath = botAvatarPath(username, config.defaultFile)
  const url =
    (explicitBotAsset.path ? botAvatarLegacyUrlFromPath(explicitBotAsset.path) : '') ||
    botAvatarLegacyUrlFromPath(path) ||
    (explicitBotAsset.matchesBotAsset ? '' : explicitUrl) ||
    botAvatarLegacyUrlFromPath(fallbackPath)

  return {
    username,
    file: selectedFile || config.defaultFile,
    path: path || fallbackPath,
    url,
    files: [...config.files],
    defaultFile: config.defaultFile,
    isCustomUpload: false,
  }
}
