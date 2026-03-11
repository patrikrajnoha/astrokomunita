export function statusLabel(userRow) {
  if (!userRow?.is_active) return 'deaktivovany'
  if (userRow?.is_banned) return 'zablokovany'
  return 'aktivny'
}

export function statusClass(userRow) {
  if (!userRow?.is_active) return 'is-inactive'
  if (userRow?.is_banned) return 'is-banned'
  return 'is-active'
}

export function roleLabel(userRow) {
  const role = String(userRow?.role || '').trim().toLowerCase()
  if (role === 'admin') return 'admin'
  if (role === 'editor') return 'editor'
  if (role === 'bot') return 'bot'
  return 'user'
}

export function roleClass(userRow) {
  const role = String(userRow?.role || '').trim().toLowerCase()
  if (role === 'admin') return 'is-admin'
  if (role === 'editor') return 'is-editor'
  if (role === 'bot') return 'is-bot'
  return 'is-user'
}

export function subjectLabel(userRow) {
  if (!userRow) return 'pouzivatela'
  if (userRow.email) return userRow.email
  if (userRow.username) return `@${userRow.username}`
  if (userRow.name) return userRow.name
  return `ID ${userRow.id ?? '-'}`
}

export function reportType(report) {
  if (!report?.target_type) return 'prispevok'
  const parts = String(report.target_type).split('\\')
  const value = (parts[parts.length - 1] || 'post').toLowerCase()
  if (value === 'post') return 'prispevok'
  return value
}

export function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString()
}

export function formatIconLabel(iconKey) {
  const map = {
    planet: 'Planeta',
    star: 'Hviezda',
    comet: 'Kometa',
    constellation: 'Suhvezdie',
    moon: 'Mesiac',
  }

  return map[iconKey] || iconKey
}

export function resolveMediaErrorMessage(error, fallback = 'Aktualizacia media zlyhala.') {
  const status = error?.response?.status ?? null
  const data = error?.response?.data

  if (status === 422 && data?.errors) {
    const firstField = Object.keys(data.errors)[0]
    const first = firstField && Array.isArray(data.errors[firstField]) ? data.errors[firstField][0] : ''
    return String(first || data?.message || fallback)
  }

  return String(data?.message || fallback)
}
