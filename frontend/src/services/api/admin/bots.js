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

export function runBotSource(sourceKey, payload = {}) {
  const body = {}
  const mode = String(payload?.mode || '')
    .trim()
    .toLowerCase()
  if (mode === 'auto' || mode === 'dry') {
    body.mode = mode
  }

  const publishLimit = Number(payload?.publish_limit)
  if (Number.isInteger(publishLimit) && publishLimit > 0) {
    body.publish_limit = publishLimit
  }

  return api.post(
    `/admin/bots/run/${encodeURIComponent(sourceKey)}`,
    Object.keys(body).length > 0 ? body : null,
    {
      meta: { skipErrorToast: true },
    },
  )
}

export function publishBotItem(botItemId, payload = {}) {
  return api.post(`/admin/bots/items/${encodeURIComponent(botItemId)}/publish`, payload, {
    meta: { skipErrorToast: true },
  })
}

export function publishBotRun(runId, payload = {}) {
  return api.post(`/admin/bots/runs/${encodeURIComponent(runId)}/publish`, payload, {
    meta: { skipErrorToast: true },
  })
}
