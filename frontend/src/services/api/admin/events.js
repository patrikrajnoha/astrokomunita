import api from '@/services/api'

export function getEvents(params = {}) {
  return api.get('/admin/events', { params })
}

export function getEvent(id) {
  return api.get(`/admin/events/${id}`)
}

export function createEvent(data) {
  return api.post('/admin/events', data)
}

export function updateEvent(id, data) {
  return api.put(`/admin/events/${id}`, data)
}
