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

  if (payload?.force_manual_override === true) {
    body.force_manual_override = true
  }

  return api.post(
    `/admin/bots/run/${encodeURIComponent(sourceKey)}`,
    Object.keys(body).length > 0 ? body : null,
    {
      meta: { skipErrorToast: true },
    },
  )
}

export function testBotTranslation(payload = {}) {
  return api.post('/admin/bots/translation/test', payload, {
    meta: { skipErrorToast: true },
  })
}

export function getBotTranslationHealth() {
  return api.get('/admin/bots/translation/health', {
    meta: { skipErrorToast: true },
  })
}

export function setBotTranslationSimulateOutage(provider = 'none') {
  return api.post('/admin/bots/translation/simulate-outage', { provider }, {
    meta: { skipErrorToast: true },
  })
}

export function retryBotTranslation(sourceKey, payload = {}) {
  const params = {}
  const limit = Number(payload?.limit)
  if (Number.isInteger(limit) && limit > 0) {
    params.limit = limit
  }

  const runId = Number(payload?.run_id)
  if (Number.isInteger(runId) && runId > 0) {
    params.run_id = runId
  }

  return api.post(`/admin/bots/translation/retry/${encodeURIComponent(sourceKey)}`, null, {
    params,
    meta: { skipErrorToast: true },
  })
}

export function backfillBotTranslation(sourceKey, payload = {}) {
  const params = {}
  const limit = Number(payload?.limit)
  if (Number.isInteger(limit) && limit > 0) {
    params.limit = limit
  }

  const runId = Number(payload?.run_id)
  if (Number.isInteger(runId) && runId > 0) {
    params.run_id = runId
  }

  return api.post(`/admin/bots/translation/backfill/${encodeURIComponent(sourceKey)}`, null, {
    params,
    meta: { skipErrorToast: true },
  })
}

export function publishBotItem(botItemId, payload = {}) {
  return api.post(`/admin/bots/items/${encodeURIComponent(botItemId)}/publish`, payload, {
    meta: { skipErrorToast: true },
  })
}

export function deleteBotItemPost(botItemId) {
  return api.delete(`/admin/bots/items/${encodeURIComponent(botItemId)}/post`, {
    meta: { skipErrorToast: true },
  })
}

export function publishBotRun(runId, payload = {}) {
  return api.post(`/admin/bots/runs/${encodeURIComponent(runId)}/publish`, payload, {
    meta: { skipErrorToast: true },
  })
}
