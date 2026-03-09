export function clampYear(year, minYear, maxYear) {
  const y = Number(year)
  if (!Number.isFinite(y)) return minYear
  return Math.max(minYear, Math.min(maxYear, y))
}

export function resolveDefaultYear(meta, now = new Date()) {
  const minYear = Number(meta?.minYear ?? 2021)
  const maxYear = Number(meta?.maxYear ?? 2100)
  const candidate = Number(meta?.defaultYear ?? now.getFullYear())
  return clampYear(candidate, minYear, maxYear)
}

export function normalizePeriod(period) {
  return ['month', 'week', 'year'].includes(period) ? period : 'month'
}

export function normalizeScope(scope) {
  return ['future', 'past', 'all'].includes(scope) ? scope : 'future'
}

export function parsePositiveInt(value, fallback = 1) {
  const parsed = Number.parseInt(String(value ?? ''), 10)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
}

export function buildPeriodQuery({ period, year, month, week }) {
  const normalizedPeriod = normalizePeriod(period)
  const query = {
    period: normalizedPeriod,
    year: String(year),
  }

  if (normalizedPeriod === 'month') {
    query.month = String(month)
    delete query.week
  } else if (normalizedPeriod === 'week') {
    query.week = String(week)
    delete query.month
  } else {
    delete query.month
    delete query.week
  }

  return query
}

export function resolvePeriodSelectionFromQuery(query, defaults = {}) {
  const fallbackDate = defaults.now instanceof Date ? defaults.now : new Date()
  const fallbackYear = Number(defaults.year ?? fallbackDate.getFullYear())
  const fallbackMonth = Number(defaults.month ?? fallbackDate.getMonth() + 1)
  const fallbackWeek = Number(defaults.week ?? 1)

  const year = Number(query?.year)
  const month = Number(query?.month)
  const week = Number(query?.week)

  return {
    period: normalizePeriod(query?.period),
    year: Number.isFinite(year) ? year : fallbackYear,
    month: Number.isFinite(month) && month >= 1 && month <= 12 ? month : fallbackMonth,
    week: Number.isFinite(week) && week >= 1 && week <= 53 ? week : fallbackWeek,
  }
}
