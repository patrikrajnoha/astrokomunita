export function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value || ''), 10)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
}

export function formatRelativeTime(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  const diffMs = Date.now() - date.getTime()
  const absDiffMs = Math.abs(diffMs)
  const minuteMs = 60 * 1000
  const hourMs = 60 * minuteMs
  const dayMs = 24 * hourMs

  if (absDiffMs < minuteMs) return 'teraz'
  if (absDiffMs < hourMs) return `${Math.floor(absDiffMs / minuteMs)}m`
  if (absDiffMs < dayMs) return `${Math.floor(absDiffMs / hourMs)}h`
  return `${Math.floor(absDiffMs / dayMs)}d`
}

export function formatDateTime(value) {
  if (!value) return '-'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString()
}

export function scrollElementIntoView(element) {
  if (!(element instanceof HTMLElement)) return
  if (typeof element.scrollIntoView !== 'function') return
  element.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' })
}
