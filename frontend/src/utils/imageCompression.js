const DEFAULT_MAX_BYTES = 3072 * 1024
const DEFAULT_MAX_DIMENSION = 3000
const DEFAULT_INITIAL_QUALITY = 0.9
const DEFAULT_MIN_QUALITY = 0.5
const DEFAULT_QUALITY_STEP = 0.08
const DEFAULT_SCALE_STEP = 0.88
const DEFAULT_MAX_ATTEMPTS = 20
const DEFAULT_MIN_SIDE = 320

function asPositiveNumber(value, fallback) {
  const numeric = Number(value)
  if (!Number.isFinite(numeric) || numeric <= 0) return fallback
  return numeric
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value))
}

function baseName(name) {
  const raw = String(name || 'upload').trim() || 'upload'
  return raw.replace(/\.[^.]+$/, '') || 'upload'
}

function extensionForMime(mimeType) {
  const normalized = String(mimeType || '').toLowerCase()
  if (normalized === 'image/jpeg' || normalized === 'image/jpg') return 'jpg'
  if (normalized === 'image/png') return 'png'
  if (normalized === 'image/webp') return 'webp'
  return 'jpg'
}

function resolveTargetMimeType(originalMimeType) {
  const normalized = String(originalMimeType || '').toLowerCase()
  if (normalized === 'image/jpeg' || normalized === 'image/jpg') return 'image/jpeg'
  return 'image/webp'
}

function fitIntoMaxDimension(width, height, maxDimension) {
  const longest = Math.max(width, height)
  if (longest <= maxDimension) {
    return { width, height }
  }

  const ratio = maxDimension / longest
  return {
    width: Math.max(1, Math.round(width * ratio)),
    height: Math.max(1, Math.round(height * ratio)),
  }
}

function getImageSize(source) {
  const width = Number(source?.width || source?.naturalWidth || 0)
  const height = Number(source?.height || source?.naturalHeight || 0)
  return { width, height }
}

function closeDecodedSource(source) {
  if (source && typeof source.close === 'function') {
    source.close()
  }
}

function fileToDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result || ''))
    reader.onerror = () => reject(new Error('IMAGE_READ_FAILED'))
    reader.readAsDataURL(file)
  })
}

function loadImageElement(src) {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.onload = () => resolve(image)
    image.onerror = () => reject(new Error('IMAGE_DECODE_FAILED'))
    image.src = src
  })
}

async function decodeImageSource(file) {
  if (typeof createImageBitmap === 'function') {
    try {
      return await createImageBitmap(file)
    } catch {
      // Fallback to HTMLImageElement decoding below.
    }
  }

  const dataUrl = await fileToDataUrl(file)
  return loadImageElement(dataUrl)
}

function canvasToBlob(canvas, mimeType, quality) {
  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob), mimeType, quality)
  })
}

async function renderCompressedBlob(source, width, height, mimeType, quality) {
  if (typeof document === 'undefined') {
    throw new Error('IMAGE_COMPRESSION_UNAVAILABLE')
  }

  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(width))
  canvas.height = Math.max(1, Math.round(height))

  const context = canvas.getContext('2d')
  if (!context) {
    throw new Error('IMAGE_CANVAS_CONTEXT_UNAVAILABLE')
  }

  context.imageSmoothingEnabled = true
  context.imageSmoothingQuality = 'high'
  context.clearRect(0, 0, canvas.width, canvas.height)
  context.drawImage(source, 0, 0, canvas.width, canvas.height)

  let blob = await canvasToBlob(canvas, mimeType, quality)
  let resolvedMime = mimeType

  if (!blob && mimeType !== 'image/jpeg') {
    blob = await canvasToBlob(canvas, 'image/jpeg', quality)
    resolvedMime = 'image/jpeg'
  }

  if (!blob) {
    throw new Error('IMAGE_BLOB_FAILED')
  }

  return {
    blob,
    mimeType: String(blob.type || resolvedMime || 'image/jpeg').toLowerCase(),
  }
}

function buildFileFromBlob(originalFile, blob, mimeType) {
  const extension = extensionForMime(mimeType)
  const nextName = `${baseName(originalFile?.name)}.${extension}`

  return new File([blob], nextName, {
    type: mimeType,
    lastModified: Date.now(),
  })
}

export async function compressImageFileToMaxBytes(file, options = {}) {
  const maxBytes = asPositiveNumber(options.maxBytes, DEFAULT_MAX_BYTES)
  const fileSize = Number(file?.size || 0)
  const fileType = String(file?.type || '').toLowerCase()

  if (!file || typeof file !== 'object') return file
  if (!fileType.startsWith('image/')) return file
  if (fileSize <= maxBytes) return file

  const maxDimension = asPositiveNumber(options.maxDimension, DEFAULT_MAX_DIMENSION)
  const initialQuality = clamp(asPositiveNumber(options.initialQuality, DEFAULT_INITIAL_QUALITY), 0.35, 0.95)
  const minQuality = clamp(asPositiveNumber(options.minQuality, DEFAULT_MIN_QUALITY), 0.2, initialQuality)
  const qualityStep = clamp(asPositiveNumber(options.qualityStep, DEFAULT_QUALITY_STEP), 0.02, 0.3)
  const scaleStep = clamp(asPositiveNumber(options.scaleStep, DEFAULT_SCALE_STEP), 0.5, 0.98)
  const maxAttempts = Math.max(1, Math.floor(asPositiveNumber(options.maxAttempts, DEFAULT_MAX_ATTEMPTS)))
  const minSide = Math.max(64, Math.floor(asPositiveNumber(options.minSide, DEFAULT_MIN_SIDE)))

  const source = await decodeImageSource(file)

  try {
    const { width: sourceWidth, height: sourceHeight } = getImageSize(source)
    if (sourceWidth < 1 || sourceHeight < 1) return file

    let { width, height } = fitIntoMaxDimension(sourceWidth, sourceHeight, maxDimension)
    let quality = initialQuality
    let mimeType = resolveTargetMimeType(fileType)
    let bestCandidate = null

    for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
      const { blob, mimeType: resolvedMimeType } = await renderCompressedBlob(source, width, height, mimeType, quality)

      if (!bestCandidate || blob.size < bestCandidate.blob.size) {
        bestCandidate = { blob, mimeType: resolvedMimeType }
      }

      if (blob.size <= maxBytes) {
        return buildFileFromBlob(file, blob, resolvedMimeType)
      }

      const canLowerQuality = quality - qualityStep >= minQuality
      if (canLowerQuality) {
        quality = Math.max(minQuality, quality - qualityStep)
        continue
      }

      const nextWidth = Math.max(minSide, Math.round(width * scaleStep))
      const nextHeight = Math.max(minSide, Math.round(height * scaleStep))

      if (nextWidth === width && nextHeight === height) break

      width = nextWidth
      height = nextHeight
      quality = initialQuality
      mimeType = resolveTargetMimeType(fileType)
    }

    if (!bestCandidate) return file
    return buildFileFromBlob(file, bestCandidate.blob, bestCandidate.mimeType)
  } finally {
    closeDecodedSource(source)
  }
}
