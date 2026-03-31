import { afterEach, describe, expect, it } from 'vitest'
import api from '@/services/api'
import { normalizeObservationMediaItems, resolveObservationMediaSrc } from './observationMedia'

describe('observationMedia', () => {
  const originalBaseUrl = api.defaults.baseURL

  afterEach(() => {
    api.defaults.baseURL = originalBaseUrl
  })

  it('builds an absolute observation media URL from relative payload url', () => {
    api.defaults.baseURL = 'https://api.astrokomunita.test/api'

    expect(resolveObservationMediaSrc({
      path: 'observations/55/images/orion.jpg',
      url: '/api/media/file/observations/55/images/orion.jpg',
      mime_type: 'image/jpeg',
    })).toBe('https://api.astrokomunita.test/api/media/file/observations/55/images/orion.jpg')
  })

  it('falls back to media.path when payload url is missing', () => {
    api.defaults.baseURL = 'https://api.astrokomunita.test/api'

    expect(resolveObservationMediaSrc({
      path: 'observations/55/images/orion.jpg',
      url: '',
      mime_type: 'image/jpeg',
    })).toBe('https://api.astrokomunita.test/api/media/file/observations/55/images/orion.jpg')
  })

  it('keeps observations without media source out of the renderable collection', () => {
    expect(normalizeObservationMediaItems([
      {
        id: 1,
        path: '',
        url: '',
        mime_type: 'image/jpeg',
        alt: 'Ignored alt',
      },
    ])).toEqual([])
  })
})
