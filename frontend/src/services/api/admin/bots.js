import api from '@/services/api'

export function getBotSources() {
  return api.get('/admin/bots/sources', { meta: { skipErrorToast: true } })
}

export function getBotRuns(params = {}) {
  return api.get('/admin/bots/runs', {
    params,
    meta: { skipErrorToast: true },
  })
}

export function getBotItems(params = {}) {
  return api.get('/admin/bots/items', {
    params,
    meta: { skipErrorToast: true },
  })
}

export function runBotSource(sourceKey) {
  return api.post(`/admin/bots/run/${encodeURIComponent(sourceKey)}`, null, {
    meta: { skipErrorToast: true },
  })
}
