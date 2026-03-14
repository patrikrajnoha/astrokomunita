import api from '@/services/api'

export function getAdminAiConfig(params = {}) {
  return api.get('/admin/ai/config', { params })
}

export function generateAdminEventDescription(eventId, payload = {}) {
  return api.post(`/admin/events/${encodeURIComponent(eventId)}/ai/generate-description`, payload)
}

export function primeNewsletterInsights(payload = {}) {
  return api.post('/admin/newsletter/ai/prime-insights', payload)
}

export function draftNewsletterCopy(payload = {}) {
  return api.post('/admin/newsletter/ai/draft-copy', payload)
}
