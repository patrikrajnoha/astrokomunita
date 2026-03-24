export const SHARED_SKY_CACHE_TTL_MS = Object.freeze({
  weather: 2 * 60 * 1000,
  astronomy: 2 * 60 * 1000,
  planets: 2 * 60 * 1000,
  iss: 2 * 60 * 1000,
  light: 5 * 60 * 1000,
  ephemeris: 5 * 60 * 1000,
})

export const EMPTY_PLANETS_PAYLOAD = Object.freeze({ planets: [], sample_at: null, sun_altitude_deg: null })
export const EMPTY_ISS_PREVIEW = Object.freeze({ available: false })
export const EMPTY_EPHEMERIS_PAYLOAD = Object.freeze({ planets: [], comets: [], asteroids: [], source: null, sample_at: null })

const sharedSkyPayloadCache = new Map()
const sharedSkyPendingRequests = new Map()

function getFreshSharedSkyPayload(cacheKey, ttlMs) {
  const entry = sharedSkyPayloadCache.get(cacheKey)
  if (!entry) return undefined
  if (Date.now() - entry.fetchedAt > ttlMs) {
    sharedSkyPayloadCache.delete(cacheKey)
    return undefined
  }
  return entry.payload
}

export async function fetchWithSharedSkyCache(cacheKey, ttlMs, fetcher) {
  const cachedPayload = getFreshSharedSkyPayload(cacheKey, ttlMs)
  if (cachedPayload !== undefined) {
    return cachedPayload
  }

  const pending = sharedSkyPendingRequests.get(cacheKey)
  if (pending) {
    return pending
  }

  const requestPromise = Promise.resolve()
    .then(fetcher)
    .then((payload) => {
      sharedSkyPayloadCache.set(cacheKey, {
        payload,
        fetchedAt: Date.now(),
      })
      return payload
    })

  sharedSkyPendingRequests.set(cacheKey, requestPromise)

  try {
    return await requestPromise
  } finally {
    if (sharedSkyPendingRequests.get(cacheKey) === requestPromise) {
      sharedSkyPendingRequests.delete(cacheKey)
    }
  }
}
