export function formatPollRemainingSk(remainingSeconds) {
  const seconds = Number(remainingSeconds ?? 0)
  if (!Number.isFinite(seconds) || seconds <= 0) {
    return '0 min.'
  }

  const totalMinutes = Math.max(1, Math.floor(seconds / 60))
  const days = Math.floor(totalMinutes / (60 * 24))
  const hours = Math.floor((totalMinutes % (60 * 24)) / 60)
  const minutes = totalMinutes % 60

  const parts = []
  if (days > 0) parts.push(`${days} d.`)
  if (hours > 0) parts.push(`${hours} hod.`)
  if (minutes > 0 && days === 0) parts.push(`${minutes} min.`)

  if (parts.length === 0) {
    return '1 min.'
  }

  return parts.join(' ')
}
