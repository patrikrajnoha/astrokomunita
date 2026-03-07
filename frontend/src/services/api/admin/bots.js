import api from '@/services/api'

export function getBotOverview() {
  return api.get('/admin/bots/overview', { meta: { skipErrorToast: true } })
}

export function getBotSources(params = {}) {
  return api.get('/admin/bots/sources', {
    params,
    meta: { skipErrorToast: true },
  })
}

export function updateBotSource(sourceId, payload = {}) {
  return api.patch(`/admin/bots/sources/${encodeURIComponent(sourceId)}`, payload, {
    meta: { skipErrorToast: true },
  })
}

export function resetBotSourceHealth(sourceId) {
  return api.post(`/admin/bots/sources/${encodeURIComponent(sourceId)}/reset-health`, null, {
    meta: { skipErrorToast: true },
  })
}

export function clearBotSourceCooldown(sourceId) {
  return api.post(`/admin/bots/sources/${encodeURIComponent(sourceId)}/clear-cooldown`, null, {
    meta: { skipErrorToast: true },
  })
}

export function reviveBotSource(sourceId) {
  return api.post(`/admin/bots/sources/${encodeURIComponent(sourceId)}/revive`, null, {
    meta: { skipErrorToast: true },
  })
}

export function getBotRuns(params = {}) {
  return api.get('/admin/bots/runs', {
    params,
    meta: { skipErrorToast: true },
  })
}

export function getBotActivity(params = {}) {
  return api.get('/admin/bots/activity', {
    params,
    meta: { skipErrorToast: true },
  })
}

export function getBotSchedules(params = {}) {
  return api.get('/admin/bots/schedules', {
    params,
    meta: { skipErrorToast: true },
  })
}

export function createBotSchedule(payload = {}) {
  return api.post('/admin/bots/schedules', payload, {
    meta: { skipErrorToast: true },
  })
}

export function updateBotSchedule(scheduleId, payload = {}) {
  return api.patch(`/admin/bots/schedules/${encodeURIComponent(scheduleId)}`, payload, {
    meta: { skipErrorToast: true },
  })
}

export function deleteBotSchedule(scheduleId) {
  return api.delete(`/admin/bots/schedules/${encodeURIComponent(scheduleId)}`, {
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

export function deleteAllBotPosts(params = {}) {
  const query = {}
  const sourceKey = String(params?.source_key || '').trim().toLowerCase()
  if (sourceKey) {
    query.source_key = sourceKey
  }

  const botIdentity = String(params?.bot_identity || '').trim().toLowerCase()
  if (['kozmo', 'stela'].includes(botIdentity)) {
    query.bot_identity = botIdentity
  }

  return api.delete('/admin/bots/posts', {
    params: query,
    meta: { skipErrorToast: true },
  })
}

export function getBotPostRetentionSettings() {
  return api.get('/admin/bots/post-retention', {
    meta: { skipErrorToast: true },
  })
}

export function updateBotPostRetentionSettings(payload = {}) {
  return api.patch('/admin/bots/post-retention', payload, {
    meta: { skipErrorToast: true },
  })
}

export function runBotPostRetentionCleanup(payload = {}) {
  return api.post('/admin/bots/post-retention/cleanup', payload, {
    meta: { skipErrorToast: true },
  })
}

export function publishBotRun(runId, payload = {}) {
  return api.post(`/admin/bots/runs/${encodeURIComponent(runId)}/publish`, payload, {
    meta: { skipErrorToast: true },
  })
}
