import api from '@/services/api'

export function getAdminAiConfig(params = {}) {
  return api.get('/admin/ai/config', { params })
}

export function generateAdminEventDescription(eventId, payload = {}) {
  return api.post(`/admin/events/${encodeURIComponent(eventId)}/ai/generate-description`, payload)
}

export function getAdminAiPolicy() {
  return api.get('/admin/ai/policy')
}

export function updateAdminAiPolicy(payload = {}) {
  return api.patch('/admin/ai/policy', payload)
}
