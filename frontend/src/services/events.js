import api from './api'

export function getEvents(params = {}) {
  const query = {}

  if (params.feed) query.feed = params.feed
  if (params.scope) query.scope = params.scope
  if (Array.isArray(params.types) && params.types.length > 0) {
    query.types = params.types.join(',')
  }
  if (params.region) query.region = params.region
  if (params.type) query.type = params.type
  if (params.q) query.q = params.q
  if (params.from) query.from = params.from
  if (params.to) query.to = params.to
  if (params.year) query.year = params.year
  if (params.month) query.month = params.month
  if (params.week) query.week = params.week
  if (params.page) query.page = params.page
  if (params.per_page) query.per_page = params.per_page

  return api.get('/events', { params: query })
}

export function getEventYears() {
  return api.get('/events/years')
}

export function lookupEventsByIds(ids = []) {
  const normalized = Array.from(
    new Set(
      ids
        .map((id) => Number(id))
        .filter((id) => Number.isInteger(id) && id > 0),
    ),
  )

  if (normalized.length === 0) {
    return Promise.resolve({ data: { data: [] } })
  }

  return api.get('/events/lookup', {
    params: { ids: normalized.join(',') },
  })
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

export function getOnboardingInterests() {
  return api.get('/meta/interests')
}

export function searchOnboardingLocations(query, limit = 8) {
  return api.get('/meta/locations', {
    params: { q: query, limit },
  })
}
