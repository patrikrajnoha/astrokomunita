const DEFAULT_POLL_MIN_OPTIONS = 2
const DEFAULT_POLL_MAX_OPTIONS = 4
const DEFAULT_POLL_MIN_SECONDS = 300
const DEFAULT_POLL_MAX_SECONDS = 604800

export function isImageFile(targetFile) {
  return typeof targetFile?.type === 'string' && targetFile.type.startsWith('image/')
}

export function createObjectUrl(targetFile) {
  return typeof URL?.createObjectURL === 'function' ? URL.createObjectURL(targetFile) : ''
}

export function revokeObjectUrl(url) {
  if (url && typeof URL?.revokeObjectURL === 'function') URL.revokeObjectURL(url)
}

export function prettySize(bytes) {
  const numberValue = Number(bytes || 0)
  if (numberValue < 1024) return `${numberValue} B`
  const kb = numberValue / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  return `${(kb / 1024).toFixed(1)} MB`
}

export function createInitialPollOptions() {
  return [createEmptyPollOption(), createEmptyPollOption()]
}

export function createEmptyPollOption() {
  return { text: '', imageFile: null, imagePreviewUrl: '' }
}

export function normalizePollOptions(
  nextOptions,
  previousOptions = [],
  {
    minOptions = DEFAULT_POLL_MIN_OPTIONS,
    maxOptions = DEFAULT_POLL_MAX_OPTIONS,
  } = {},
) {
  const fallbackOptions = Array.isArray(previousOptions) ? previousOptions : []
  const sanitized = Array.isArray(nextOptions) ? nextOptions : []
  const normalized = sanitized.slice(0, maxOptions).map((option, index) => {
    const previousOption = fallbackOptions[index] || createEmptyPollOption()
    const nextImageFile = option?.imageFile || null
    let imagePreviewUrl = ''

    if (nextImageFile && nextImageFile === previousOption.imageFile && previousOption.imagePreviewUrl) {
      imagePreviewUrl = previousOption.imagePreviewUrl
    } else if (nextImageFile && isImageFile(nextImageFile)) {
      imagePreviewUrl = createObjectUrl(nextImageFile)
    } else if (typeof option?.imagePreviewUrl === 'string') {
      imagePreviewUrl = option.imagePreviewUrl
    }

    if (previousOption.imagePreviewUrl && previousOption.imagePreviewUrl !== imagePreviewUrl) {
      revokeObjectUrl(previousOption.imagePreviewUrl)
    }

    return {
      text: String(option?.text || '').slice(0, 25),
      imageFile: nextImageFile,
      imagePreviewUrl,
    }
  })

  while (normalized.length < minOptions) {
    normalized.push(createEmptyPollOption())
  }

  if (fallbackOptions.length > normalized.length) {
    fallbackOptions.slice(normalized.length).forEach((option) => {
      if (option?.imagePreviewUrl) {
        revokeObjectUrl(option.imagePreviewUrl)
      }
    })
  }

  return normalized
}

export function revokeAllPollOptionPreviews(options) {
  for (const option of options || []) {
    if (option?.imagePreviewUrl) {
      revokeObjectUrl(option.imagePreviewUrl)
      option.imagePreviewUrl = ''
    }
  }
}

export function clampPollDuration(
  value,
  {
    min = DEFAULT_POLL_MIN_SECONDS,
    max = DEFAULT_POLL_MAX_SECONDS,
  } = {},
) {
  const numericValue = Number(value)
  if (!Number.isFinite(numericValue)) return min
  return Math.max(min, Math.min(max, Math.round(numericValue)))
}

export function formatEventRange(startAt, endAt) {
  const start = parseEventDate(startAt)
  const end = parseEventDate(endAt)

  if (!start && !end) return 'Datum upresnime'
  if (start && !end) return start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short', year: 'numeric' })
  if (!start && end) return end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short', year: 'numeric' })

  const startLabel = start.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  const endLabel = end.toLocaleDateString('sk-SK', { day: '2-digit', month: 'short' })
  return startLabel === endLabel ? startLabel : `${startLabel} - ${endLabel}`
}

export function parseEventDate(value) {
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function firstValidationError(error, fallbackMessage) {
  const validationErrors = error?.response?.data?.errors
  if (validationErrors && typeof validationErrors === 'object') {
    for (const value of Object.values(validationErrors)) {
      if (Array.isArray(value) && value[0]) {
        return String(value[0])
      }
      if (value) {
        return String(value)
      }
    }
  }

  return (
    error?.response?.data?.message ||
    error?.userMessage ||
    fallbackMessage
  )
}
