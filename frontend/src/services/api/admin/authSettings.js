import api from '@/services/api'

export function getAuthSettings() {
  return api.get('/admin/settings/email-verification')
}

export function updateAuthSettings(payload) {
  return api.put('/admin/settings/email-verification', payload)
}
