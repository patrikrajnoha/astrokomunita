import api from '@/services/api'

export function getFeaturedEvents() {
  return api.get('/admin/featured-events')
}

export function createFeaturedEvent(payload) {
  return api.post('/admin/featured-events', payload)
}

export function updateFeaturedEvent(id, payload) {
  return api.patch(`/admin/featured-events/${id}`, payload)
}

export function deleteFeaturedEvent(id) {
  return api.delete(`/admin/featured-events/${id}`)
}

export function forceFeaturedEventsPopup() {
  return api.post('/admin/featured-events/force-popup')
}

export function updateFeaturedPopupSettings(payload) {
  return api.patch('/admin/featured-events/popup-settings', payload)
}

