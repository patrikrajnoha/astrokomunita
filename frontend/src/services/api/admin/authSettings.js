import api from '@/services/api'

export function getAuthSettings() {
  return api.get('/admin/auth-settings')
}

export function updateAuthSettings(payload) {
  return api.patch('/admin/auth-settings', payload)
}
