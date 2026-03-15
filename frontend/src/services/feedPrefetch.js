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

function clearHomeFeedPayload() {
  state.homeFeedPayload = null
  state.homeFeedFetchedAt = 0
}

function consumeFreshHomeFeedPayload() {
  if (!state.homeFeedPayload) {
    return null
  }

  if (!isFresh(state.homeFeedFetchedAt)) {
    clearHomeFeedPayload()
    return null
  }

  const payload = state.homeFeedPayload
  clearHomeFeedPayload()
  return payload
}

export function clearHomeFeedPrefetch() {
  clearHomeFeedPayload()
  state.homeFeedPromise = null
  state.homeFeedEpoch += 1
}

export function consumeHomeFeedPrefetch() {
  return consumeFreshHomeFeedPayload()
}

export async function consumePendingHomeFeedPrefetch() {
  if (!state.homeFeedPromise) {
    return null
  }

  const requestEpoch = state.homeFeedEpoch

  try {
    await state.homeFeedPromise
  } catch {
    return null
  }

  if (requestEpoch !== state.homeFeedEpoch) {
    return null
  }

  return consumeFreshHomeFeedPayload()
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
