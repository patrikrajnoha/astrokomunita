const HOME_FEED_TTL_MS = 15000

const state = {
  homeFeedPayload: null,
  homeFeedFetchedAt: 0,
  homeFeedPromise: null,
  homeFeedEpoch: 0,
}

function isFresh(ts) {
  return Number(ts) > 0 && Date.now() - Number(ts) <= HOME_FEED_TTL_MS
}

function normalizeHomeFeedPayload(payload) {
  if (!payload || typeof payload !== 'object') return null
  if (!Array.isArray(payload.data)) return null
  return payload
}

function setHomeFeedPayload(payload) {
  const normalized = normalizeHomeFeedPayload(payload)
  if (!normalized) return null

  state.homeFeedPayload = normalized
  state.homeFeedFetchedAt = Date.now()
  return normalized
}

export function clearHomeFeedPrefetch() {
  state.homeFeedPayload = null
  state.homeFeedFetchedAt = 0
  state.homeFeedPromise = null
  state.homeFeedEpoch += 1
}

export function consumeHomeFeedPrefetch() {
  if (!state.homeFeedPayload || !isFresh(state.homeFeedFetchedAt)) {
    clearHomeFeedPrefetch()
    return null
  }

  const payload = state.homeFeedPayload
  clearHomeFeedPrefetch()
  return payload
}

export function prefetchHomeFeed(api, options = {}) {
  if (!api?.get || typeof api.get !== 'function') {
    return Promise.resolve(null)
  }

  const force = options.force === true
  if (!force && state.homeFeedPayload && isFresh(state.homeFeedFetchedAt)) {
    return Promise.resolve(state.homeFeedPayload)
  }

  if (!force && state.homeFeedPromise) {
    return state.homeFeedPromise
  }

  const requestEpoch = state.homeFeedEpoch
  const request = api
    .get('/feed?with=counts', {
      meta: { skipErrorToast: true },
    })
    .then((res) => {
      if (requestEpoch !== state.homeFeedEpoch) {
        return null
      }

      return setHomeFeedPayload(res?.data)
    })
    .catch(() => null)
    .finally(() => {
      if (state.homeFeedPromise === request) {
        state.homeFeedPromise = null
      }
    })

  state.homeFeedPromise = request
  return request
}
