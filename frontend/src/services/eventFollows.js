import api from '@/services/api'

export function getEventFollowState(eventId) {
  return api.get(`/events/${eventId}/follow-state`, {
    meta: { requiresAuth: true },
  })
}

export function followEvent(eventId) {
  return api.post(`/events/${eventId}/follow`, null, {
    meta: { requiresAuth: true },
  })
}

export function unfollowEvent(eventId) {
  return api.delete(`/events/${eventId}/follow`, {
    meta: { requiresAuth: true },
  })
}

export function getFollowedEvents(params = {}) {
  return api.get('/me/followed-events', {
    params,
    meta: { requiresAuth: true },
  })
}
