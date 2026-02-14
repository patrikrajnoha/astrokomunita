export function formatPollRemainingSk(remainingSeconds) {
  const seconds = Number(remainingSeconds ?? 0)
  if (!Number.isFinite(seconds) || seconds <= 0) {
    return '0 min.'
  }

  const totalMinutes = Math.floor(seconds / 60)
  const days = Math.floor(totalMinutes / (60 * 24))
  const hours = Math.floor((totalMinutes % (60 * 24)) / 60)
  const minutes = totalMinutes % 60

  if (days > 0) {
    if (hours > 0) return `${days} d ${hours} hod.`
    return `${days} d`
  }

  if (hours > 0) {
    return `${hours} hod. ${minutes} min.`
  }

  return `${Math.max(1, minutes)} min.`
}
