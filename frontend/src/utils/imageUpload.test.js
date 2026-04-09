import { beforeEach, describe, expect, it, vi } from 'vitest'
import { compressImageFileToMaxBytes } from '@/utils/imageCompression'
import {
  IMAGE_PREPROCESS_MAX_DIMENSION,
  IMAGE_PREPROCESS_QUALITY,
  IMAGE_UPLOAD_LIMITS,
  createImageOptimizationFailedMessage,
  createUnsupportedImageMessage,
  prepareImageFileForUpload,
  resolveUploadRequestMessage,
} from '@/utils/imageUpload'

vi.mock('@/utils/imageCompression', () => ({
  compressImageFileToMaxBytes: vi.fn(),
}))

function createSizedFile(name, type, sizeBytes) {
  const file = new File(['x'], name, { type })
  Object.defineProperty(file, 'size', {
    configurable: true,
    value: sizeBytes,
  })
  return file
}

describe('imageUpload', () => {
  beforeEach(() => {
    vi.mocked(compressImageFileToMaxBytes).mockReset()
  })

  it('keeps small supported images unchanged', async () => {
    const inputFile = createSizedFile('small.png', 'image/png', 128 * 1024)

    const result = await prepareImageFileForUpload(inputFile, {
      context: 'post-image',
      maxBytes: IMAGE_UPLOAD_LIMITS.postAttachmentBytes,
    })

    expect(result.file).toBe(inputFile)
    expect(result.wasOptimized).toBe(false)
    expect(compressImageFileToMaxBytes).not.toHaveBeenCalled()
  })

  it('compresses oversized images with the configured frontend limits', async () => {
    const inputFile = createSizedFile('large.png', 'image/png', IMAGE_UPLOAD_LIMITS.postAttachmentBytes + 4096)
    const optimizedFile = createSizedFile('large.webp', 'image/webp', 2 * 1024 * 1024)

    vi.mocked(compressImageFileToMaxBytes).mockResolvedValueOnce(optimizedFile)

    const result = await prepareImageFileForUpload(inputFile, {
      context: 'post-image',
      maxBytes: IMAGE_UPLOAD_LIMITS.postAttachmentBytes,
    })

    expect(result.file).toBe(optimizedFile)
    expect(result.wasOptimized).toBe(true)
    expect(compressImageFileToMaxBytes).toHaveBeenCalledWith(inputFile, {
      maxBytes: IMAGE_UPLOAD_LIMITS.postAttachmentBytes,
      maxDimension: IMAGE_PREPROCESS_MAX_DIMENSION,
      initialQuality: IMAGE_PREPROCESS_QUALITY,
    })
  })

  it('shows a clear limit message when a poll image is still too large after compression', async () => {
    const inputFile = createSizedFile('poll.png', 'image/png', IMAGE_UPLOAD_LIMITS.pollOptionImageBytes + 2048)
    const optimizedFile = createSizedFile('poll.webp', 'image/webp', IMAGE_UPLOAD_LIMITS.pollOptionImageBytes + 1024)

    vi.mocked(compressImageFileToMaxBytes).mockResolvedValueOnce(optimizedFile)

    await expect(prepareImageFileForUpload(inputFile, {
      context: 'poll',
      maxBytes: IMAGE_UPLOAD_LIMITS.pollOptionImageBytes,
    })).rejects.toMatchObject({
      code: 'IMAGE_TOO_LARGE',
      userMessage: 'Obrazok je prilis velky. Maximalna velkost pre anketu je 20.0 MB.',
    })
  })

  it('surfaces a dedicated compression failure message', async () => {
    const inputFile = createSizedFile('broken.png', 'image/png', IMAGE_UPLOAD_LIMITS.postAttachmentBytes + 1024)

    vi.mocked(compressImageFileToMaxBytes).mockRejectedValueOnce(new Error('canvas failed'))

    await expect(prepareImageFileForUpload(inputFile, {
      context: 'post-image',
      maxBytes: IMAGE_UPLOAD_LIMITS.postAttachmentBytes,
    })).rejects.toMatchObject({
      code: 'IMAGE_COMPRESSION_FAILED',
      userMessage: createImageOptimizationFailedMessage(),
    })
  })

  it('rejects unsupported image types before upload', async () => {
    const inputFile = createSizedFile('astro.heic', 'image/heic', 512 * 1024)

    await expect(prepareImageFileForUpload(inputFile, {
      context: 'observation',
      maxBytes: IMAGE_UPLOAD_LIMITS.observationImageBytes,
    })).rejects.toMatchObject({
      code: 'UNSUPPORTED_IMAGE_TYPE',
      userMessage: createUnsupportedImageMessage(),
    })
  })

  it('localizes backend upload validation messages', () => {
    const message = resolveUploadRequestMessage({
      response: {
        data: {
          errors: {
            'poll.options.0.image': ['Poll option image may not be greater than 20 MB.'],
          },
        },
      },
    }, 'fallback', { context: 'attachment' })

    expect(message).toBe('Obrazok je prilis velky. Maximalna velkost pre anketu je 20.0 MB.')
  })
})
