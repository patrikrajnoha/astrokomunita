import api from '@/services/api'

export function createEventInvite(eventId, payload, config = {}) {
  return api.post(`/events/${eventId}/invites`, payload, config)
}

export function listMyInvites(params = {}, config = {}) {
  return api.get('/me/invites', {
    ...config,
    params,
  })
}

export function acceptInvite(inviteId, config = {}) {
  return api.post(`/invites/${inviteId}/accept`, {}, config)
}

export function declineInvite(inviteId, config = {}) {
  return api.post(`/invites/${inviteId}/decline`, {}, config)
}

export function fetchPublicInviteByToken(token, config = {}) {
  return api.get(`/invites/public/${token}`, config)
}
