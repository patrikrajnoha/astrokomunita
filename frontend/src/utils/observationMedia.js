import { normalizeMediaPath, normalizeMediaUrl } from '@/utils/profileMedia'

export function resolveObservationMediaSrc(media) {
  if (!media || typeof media !== 'object') return ''

  const explicitUrl = normalizeMediaUrl(
    media?.url ?? media?.original_url ?? media?.originalUrl ?? '',
  )
  if (explicitUrl) return explicitUrl

  return normalizeMediaPath(media?.path ?? '')
}

export function normalizeObservationMediaItems(mediaItems) {
  const source = Array.isArray(mediaItems) ? mediaItems : []

  return source
    .map((item) => ({
      ...item,
      id: Number(item?.id || 0),
      alt: String(item?.alt || '').trim(),
      resolvedUrl: resolveObservationMediaSrc(item),
    }))
    .filter((item) => item.resolvedUrl !== '')
}
