import api from '@/services/api'

export function getNewsletterPreview() {
  return api.get('/admin/newsletter/preview')
}

export function sendNewsletterPreview(payload = {}) {
  return api.post('/admin/newsletter/preview', payload)
}

export function updateNewsletterFeaturedEvents(payload) {
  return api.post('/admin/newsletter/feature-events', payload)
}

export function sendNewsletter(payload = {}) {
  return api.post('/admin/newsletter/send', payload)
}

export function getNewsletterRuns(params = {}) {
  return api.get('/admin/newsletter/runs', { params })
}
