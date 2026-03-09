import api from '@/services/api'

function withSilentMeta(config = {}) {
  return {
    ...config,
    meta: {
      skipErrorToast: true,
      ...(config?.meta || {}),
    },
  }
}

export function getEventSources() {
  return api.get('/admin/event-sources')
}

export function updateEventSource(id, payload) {
  return api.patch(`/admin/event-sources/${id}`, payload)
}

export function runEventSourceCrawl(payload) {
  return api.post('/admin/event-sources/run', payload)
}

export function purgeEventSources(payload) {
  return api.post('/admin/event-sources/purge', payload)
}

export function getCrawlRuns(params = {}) {
  return api.get('/admin/crawl-runs', { params })
}

export function getCrawlRun(id) {
  return api.get(`/admin/crawl-runs/${id}`)
}

export function getEventTranslationHealth(config = {}) {
  return api.get('/admin/event-translation-health', withSilentMeta({
    timeout: 30000,
    ...config,
  }))
}

export function getTranslationArtifactsReport(params = {}, config = {}) {
  return api.get('/admin/event-sources/translation-artifacts/report', withSilentMeta({
    params,
    timeout: 30000,
    ...config,
  }))
}

export function repairTranslationArtifacts(payload = {}) {
  return api.post('/admin/event-sources/translation-artifacts/repair', payload)
}
