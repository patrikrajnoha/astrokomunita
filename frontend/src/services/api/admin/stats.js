import api from '@/services/api'

const DEFAULT_CACHE_TTL_MS = 60_000

let statsCache = null
let statsCachedAt = 0

function isCacheFresh(ttlMs) {
  return !!statsCache && Date.now() - statsCachedAt < ttlMs
}

export async function getStats(options = {}) {
  const force = options.force === true
  const ttlMs = Number.isFinite(Number(options.ttlMs)) ? Number(options.ttlMs) : DEFAULT_CACHE_TTL_MS

  if (!force && isCacheFresh(ttlMs)) {
    return statsCache
  }

  const { data } = await api.get('/admin/stats', {
    meta: { skipErrorToast: true, skipAuthRedirect: true },
  })
  statsCache = data
  statsCachedAt = Date.now()

  return data
}

export async function downloadStatsCsv() {
  const response = await api.get('/admin/stats/export', {
    params: { format: 'csv' },
    responseType: 'blob',
    meta: { skipErrorToast: true },
  })

  const contentDisposition = String(response?.headers?.['content-disposition'] || '')
  const filenameMatch = contentDisposition.match(/filename="?([^";]+)"?/i)
  const filename = filenameMatch?.[1] || `admin_stats_${new Date().toISOString().slice(0, 10)}.csv`

  return {
    blob: response.data,
    filename,
  }
}

export function clearStatsCache() {
  statsCache = null
  statsCachedAt = 0
}
