import api from '@/services/api'

export function getEventSources() {
  return api.get('/admin/event-sources')
}

export function updateEventSource(id, payload) {
  return api.patch(`/admin/event-sources/${id}`, payload)
}

export function runEventSourceCrawl(payload) {
  return api.post('/admin/event-sources/run', payload)
}

export function getCrawlRuns(params = {}) {
  return api.get('/admin/crawl-runs', { params })
}

export function getCrawlRun(id) {
  return api.get(`/admin/crawl-runs/${id}`)
}
