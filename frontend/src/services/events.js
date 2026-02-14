import api from './api'

export function getEvents(params = {}) {
  const query = {}

  if (params.feed) query.feed = params.feed
  if (Array.isArray(params.types) && params.types.length > 0) {
    query.types = params.types.join(',')
  }
  if (params.region) query.region = params.region
  if (params.type) query.type = params.type
  if (params.q) query.q = params.q
  if (params.from) query.from = params.from
  if (params.to) query.to = params.to

  return api.get('/events', { params: query })
}

export function getMyPreferences() {
  return api.get('/me/preferences', {
    meta: { requiresAuth: true },
  })
}

export function updateMyPreferences(payload) {
  return api.put('/me/preferences', payload, {
    meta: { requiresAuth: true },
  })
}
