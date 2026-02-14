import api from '@/services/api'

export function getDashboard(params = {}) {
  return api.get('/admin/dashboard', { params })
}
