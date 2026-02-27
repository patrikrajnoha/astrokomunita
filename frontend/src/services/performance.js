import api from '@/services/api'

export async function getMetrics() {
  const { data } = await api.get('/admin/performance-metrics', { meta: { skipErrorToast: true } })
  return data
}

export async function runMetrics(payload) {
  const { data } = await api.post('/admin/performance-metrics/run', payload, { meta: { skipErrorToast: true } })
  return data
}

