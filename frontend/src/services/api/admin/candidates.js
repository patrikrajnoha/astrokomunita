import api from '@/services/api'

export function getCandidates(params = {}) {
  const normalized = { ...params }

  if (normalized.source_name == null && normalized.source != null) {
    normalized.source_name = normalized.source
  }
  delete normalized.source

  return api.get('/admin/event-candidates', { params: normalized })
}

export function getCandidate(id) {
  return api.get(`/admin/event-candidates/${id}`)
}

export function createCandidate(data) {
  return api.post('/admin/manual-events', data)
}

export function updateCandidate(id, data) {
  return api.put(`/admin/manual-events/${id}`, data)
}

export function deleteCandidate(id) {
  return api.delete(`/admin/manual-events/${id}`)
}

export function approveCandidate(id, data = {}) {
  return api.post(`/admin/event-candidates/${id}/approve`, data)
}

export function rejectCandidate(id, data = {}) {
  return api.post(`/admin/event-candidates/${id}/reject`, data)
}

export function publishCandidate(id, data = {}) {
  return approveCandidate(id, data)
}

export function importCandidates(data) {
  return createCandidate(data)
}

export function getCandidatesMeta(params = {}) {
  return api.get('/admin/event-candidates-meta', { params })
}
