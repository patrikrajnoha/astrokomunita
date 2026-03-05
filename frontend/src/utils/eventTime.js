export const EVENT_TIMEZONE = 'Europe/Bratislava'

export function formatEventDate(value, timeZone = EVENT_TIMEZONE, options = {}) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'

  const formatterOptions = {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    ...options,
    timeZone,
  }

  return new Intl.DateTimeFormat('sk-SK', formatterOptions).format(date)
}

export function formatEventDateKey(value, timeZone = EVENT_TIMEZONE) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  return new Intl.DateTimeFormat('sv-SE', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date)
}
