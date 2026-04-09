import { compressImageFileToMaxBytes } from '@/utils/imageCompression'

export const IMAGE_UPLOAD_LIMITS = Object.freeze({
  postAttachmentBytes: 32 * 1024 * 1024,
  replyAttachmentBytes: 32 * 1024 * 1024,
  observationImageBytes: 32 * 1024 * 1024,
  pollOptionImageBytes: 20 * 1024 * 1024,
})

export const IMAGE_PREPROCESS_MAX_DIMENSION = 2048
export const IMAGE_PREPROCESS_QUALITY = 0.8

const SUPPORTED_IMAGE_MIME_TYPES = new Set([
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
])

const EXTENSION_TO_MIME_TYPE = Object.freeze({
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  png: 'image/png',
  webp: 'image/webp',
  gif: 'image/gif',
})

export class ImageUploadPreparationError extends Error {
  constructor(code, userMessage) {
    super(userMessage)
    this.name = 'ImageUploadPreparationError'
    this.code = code
    this.userMessage = userMessage
  }
}

function asPositiveNumber(value, fallback) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric <= 0) return fallback
  return numeric
}

function prettySize(bytes) {
  const value = Number(bytes || 0)
  if (value < 1024) return `${value} B`
  const kb = value / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  return `${(kb / 1024).toFixed(1)} MB`
}

function normalizeContext(context) {
  const normalized = String(context || '').trim().toLowerCase()
  if (normalized === 'poll') return 'poll'
  if (normalized === 'observation') return 'observation'
  if (normalized === 'post-image') return 'post-image'
  if (normalized === 'reply-image') return 'reply-image'
  if (normalized === 'attachment') return 'attachment'
  return 'attachment'
}

function resolveContextMaxBytes(context) {
  switch (normalizeContext(context)) {
    case 'poll':
      return IMAGE_UPLOAD_LIMITS.pollOptionImageBytes
    case 'observation':
      return IMAGE_UPLOAD_LIMITS.observationImageBytes
    case 'reply-image':
      return IMAGE_UPLOAD_LIMITS.replyAttachmentBytes
    case 'post-image':
      return IMAGE_UPLOAD_LIMITS.postAttachmentBytes
    default:
      return IMAGE_UPLOAD_LIMITS.postAttachmentBytes
  }
}

function resolveFileMimeType(file) {
  const explicitMime = String(file?.type || '').trim().toLowerCase()
  if (explicitMime) return explicitMime

  const name = String(file?.name || '').trim().toLowerCase()
  const match = name.match(/\.([a-z0-9]+)$/i)
  if (!match) return ''

  return EXTENSION_TO_MIME_TYPE[match[1]] || ''
}

function isGifImage(file) {
  return resolveFileMimeType(file) === 'image/gif'
}

function parseLimitBytesFromMessage(message) {
  const match = String(message || '').match(/(\d+(?:[.,]\d+)?)\s*mb/i)
  if (!match) return null

  const parsed = Number(String(match[1]).replace(',', '.'))
  if (!Number.isFinite(parsed) || parsed <= 0) return null

  return Math.round(parsed * 1024 * 1024)
}

function isImageTooLargeMessage(message) {
  const normalized = String(message || '').trim().toLowerCase()
  return (
    normalized.includes('may not be greater than') ||
    normalized.includes('may be up to') ||
    normalized.includes('payload too large') ||
    normalized.includes('too large for server limits') ||
    normalized.includes('file is too large')
  )
}

function isUnsupportedImageMessage(message) {
  const normalized = String(message || '').trim().toLowerCase()
  return (
    normalized.includes('unsupported image format') ||
    normalized.includes('allowed image formats') ||
    normalized.includes('must be an image') ||
    normalized.includes('must be a valid image file')
  )
}

function isUploadInvalidMessage(message) {
  const normalized = String(message || '').trim().toLowerCase()
  return (
    normalized.includes('partially uploaded') ||
    normalized.includes('uploaded file is invalid') ||
    normalized.includes('no file was uploaded')
  )
}

function isImageDimensionMessage(message) {
  return String(message || '').trim().toLowerCase().includes('image dimensions cannot exceed')
}

export function inferUploadContextFromField(fieldName, fallbackContext = 'attachment') {
  const normalizedField = String(fieldName || '').trim().toLowerCase()
  if (normalizedField.startsWith('poll.') || normalizedField.includes('.image')) {
    return 'poll'
  }
  if (normalizedField.startsWith('images')) {
    return 'observation'
  }
  if (normalizedField === 'attachment') {
    return fallbackContext
  }
  return fallbackContext
}

export function createUploadTooLargeMessage({ context = 'attachment', maxBytes } = {}) {
  const normalizedContext = normalizeContext(context)
  const resolvedMaxBytes = asPositiveNumber(maxBytes, resolveContextMaxBytes(normalizedContext))
  const formattedLimit = prettySize(resolvedMaxBytes)

  if (normalizedContext === 'poll') {
    return `Obrazok je prilis velky. Maximalna velkost pre anketu je ${formattedLimit}.`
  }

  if (normalizedContext === 'observation') {
    return `Obrazok je prilis velky. Maximalna velkost jednej fotografie je ${formattedLimit}.`
  }

  if (normalizedContext === 'post-image' || normalizedContext === 'reply-image') {
    return `Obrazok je prilis velky. Maximalna povolena velkost je ${formattedLimit}.`
  }

  return `Subor je prilis velky. Maximalna povolena velkost je ${formattedLimit}.`
}

export function createUnsupportedImageMessage() {
  return 'Nepodporovany format obrazka. Povolene su JPG, PNG, WebP a GIF.'
}

export function createImageOptimizationFailedMessage() {
  return 'Obrazok sa nepodarilo optimalizovat pred odoslanim. Skus mensi JPG, PNG alebo WebP obrazok.'
}

export function createInvalidUploadMessage(context = 'attachment') {
  const normalizedContext = normalizeContext(context)
  if (normalizedContext === 'attachment') {
    return 'Nahravanie suboru zlyhalo. Skus vybrat subor znova.'
  }

  return 'Nahravanie obrazka zlyhalo. Skus vybrat obrazok znova.'
}

export function createImageDimensionMessage() {
  return 'Obrazok ma prilis velke rozmery. Skus ho zmensit a nahraj znova.'
}

export function normalizeUploadErrorText(message, { context = 'attachment' } = {}) {
  const rawMessage = String(message || '').trim()
  if (!rawMessage) return ''

  if (isUnsupportedImageMessage(rawMessage)) {
    return createUnsupportedImageMessage()
  }

  if (isImageDimensionMessage(rawMessage)) {
    return createImageDimensionMessage()
  }

  if (isUploadInvalidMessage(rawMessage)) {
    return createInvalidUploadMessage(context)
  }

  if (isImageTooLargeMessage(rawMessage)) {
    return createUploadTooLargeMessage({
      context,
      maxBytes: parseLimitBytesFromMessage(rawMessage) || resolveContextMaxBytes(context),
    })
  }

  return rawMessage
}

export function extractFirstUploadValidationMessage(
  validationErrors,
  fallbackMessage,
  { context = 'attachment' } = {},
) {
  if (validationErrors && typeof validationErrors === 'object') {
    for (const [field, value] of Object.entries(validationErrors)) {
      const firstValue = Array.isArray(value) ? value[0] : value
      const normalized = normalizeUploadErrorText(firstValue, {
        context: inferUploadContextFromField(field, context),
      })
      if (normalized) return normalized
    }
  }

  return normalizeUploadErrorText(fallbackMessage, { context }) || fallbackMessage
}

export function resolveUploadRequestMessage(error, fallbackMessage, { context = 'attachment' } = {}) {
  const validationMessage = extractFirstUploadValidationMessage(error?.response?.data?.errors, '', { context })
  if (validationMessage) return validationMessage

  const responseMessage = normalizeUploadErrorText(error?.response?.data?.message, { context })
  if (responseMessage) return responseMessage

  const userMessage = normalizeUploadErrorText(error?.userMessage, { context })
  if (userMessage) return userMessage

  return normalizeUploadErrorText(fallbackMessage, { context }) || fallbackMessage
}

export async function prepareImageFileForUpload(file, options = {}) {
  const context = normalizeContext(options.context)
  const maxBytes = asPositiveNumber(options.maxBytes, resolveContextMaxBytes(context))
  const maxDimension = asPositiveNumber(options.maxDimension, IMAGE_PREPROCESS_MAX_DIMENSION)
  const initialQuality = asPositiveNumber(options.initialQuality, IMAGE_PREPROCESS_QUALITY)
  const mimeType = resolveFileMimeType(file)

  if (!file || typeof file !== 'object' || !mimeType.startsWith('image/')) {
    throw new ImageUploadPreparationError('UNSUPPORTED_IMAGE_TYPE', createUnsupportedImageMessage())
  }

  if (!SUPPORTED_IMAGE_MIME_TYPES.has(mimeType)) {
    throw new ImageUploadPreparationError('UNSUPPORTED_IMAGE_TYPE', createUnsupportedImageMessage())
  }

  if (Number(file?.size || 0) <= maxBytes) {
    return { file, wasOptimized: false }
  }

  if (isGifImage(file)) {
    throw new ImageUploadPreparationError('IMAGE_TOO_LARGE', createUploadTooLargeMessage({
      context,
      maxBytes,
    }))
  }

  let nextFile = file

  try {
    nextFile = await compressImageFileToMaxBytes(file, {
      maxBytes,
      maxDimension,
      initialQuality,
    })
  } catch {
    throw new ImageUploadPreparationError('IMAGE_COMPRESSION_FAILED', createImageOptimizationFailedMessage())
  }

  if (!nextFile || typeof nextFile !== 'object') {
    throw new ImageUploadPreparationError('IMAGE_COMPRESSION_FAILED', createImageOptimizationFailedMessage())
  }

  if (Number(nextFile?.size || 0) > maxBytes) {
    throw new ImageUploadPreparationError('IMAGE_TOO_LARGE', createUploadTooLargeMessage({
      context,
      maxBytes,
    }))
  }

  const wasOptimized =
    nextFile !== file ||
    Number(nextFile?.size || 0) !== Number(file?.size || 0) ||
    String(nextFile?.type || '') !== String(file?.type || '') ||
    String(nextFile?.name || '') !== String(file?.name || '')

  return {
    file: nextFile,
    wasOptimized,
  }
}

export async function prepareImageFilesForUpload(files, options = {}) {
  const selectedFiles = Array.isArray(files) ? files : []
  const preparedFiles = []

  for (const file of selectedFiles) {
    const prepared = await prepareImageFileForUpload(file, options)
    preparedFiles.push(prepared.file)
  }

  return preparedFiles
}
