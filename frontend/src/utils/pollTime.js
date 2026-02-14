export function formatPollRemainingSk(remainingSeconds) {
  const seconds = Number(remainingSeconds ?? 0)
  if (!Number.isFinite(seconds) || seconds <= 0) {
    return '0 min.'
  }

  const totalMinutes = Math.max(1, Math.floor(seconds / 60))
  const days = Math.floor(totalMinutes / (60 * 24))
  const hours = Math.floor((totalMinutes % (60 * 24)) / 60)
  const minutes = totalMinutes % 60

  if (days > 0) {
    const dayWord = pluralizeSkDays(days)
    if (hours > 0) {
      return `${days} ${dayWord} ${hours} hod.`
    }

    return `${days} ${dayWord}`
  }

  if (hours > 0) {
    if (minutes > 0) {
      return `${hours} hod. ${minutes} min.`
    }

    return `${hours} hod.`
  }

  return `${Math.max(1, minutes)} min.`
}

function pluralizeSkDays(days) {
  const abs = Math.abs(Number(days || 0))
  if (abs === 1) return 'den'
  if (abs >= 2 && abs <= 4) return 'dni'
  return 'dni'
}
