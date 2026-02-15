export function clampYear(year, minYear, maxYear) {
  const y = Number(year)
  if (!Number.isFinite(y)) return minYear
  return Math.max(minYear, Math.min(maxYear, y))
}

export function resolveDefaultYear(meta, now = new Date()) {
  const minYear = Number(meta?.minYear ?? 2021)
  const maxYear = Number(meta?.maxYear ?? 2030)
  const candidate = Number(meta?.defaultYear ?? now.getFullYear())
  return clampYear(candidate, minYear, maxYear)
}

export function buildPeriodQuery({ period, year, month, week }) {
  const query = {
    period: period || 'month',
    year: String(year),
  }

  if (query.period === 'month') {
    query.month = String(month)
    delete query.week
  } else if (query.period === 'week') {
    query.week = String(week)
    delete query.month
  } else {
    delete query.month
    delete query.week
  }

  return query
}
