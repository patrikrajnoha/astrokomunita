export function normalizeSourceKey(value) {
  return String(value || '').trim().toLowerCase()
}

export function clampYearValue(value, minYear, maxYear) {
  const numericYear = Number(value)
  if (!Number.isFinite(numericYear)) {
    return minYear
  }
  return Math.max(minYear, Math.min(maxYear, Math.floor(numericYear)))
}

export function sourceLabel(sourceKey, t) {
  const key = normalizeSourceKey(sourceKey)
  if (key === 'astropixels') return 'AstroPixels'
  if (key === 'imo') return 'IMO'
  if (key === 'nasa_watch_the_skies' || key === 'nasa_wts') return 'NASA WTS'
  if (key === 'nasa') return 'NASA'
  return key || t('common.na')
}

export function sourceToneClass(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  if (key === 'astropixels') return 'sourceBadge--astropixels'
  if (key === 'imo') return 'sourceBadge--imo'
  if (key === 'nasa' || key === 'nasa_watch_the_skies' || key === 'nasa_wts') return 'sourceBadge--nasa'
  return 'sourceBadge--generic'
}

export function isSourceSupported(source) {
  return Boolean(source?.manual_run_supported)
}

export function sourceStatusLabel(source, t) {
  if (!isSourceSupported(source)) return t('statuses.unsupported')
  return source?.is_enabled ? t('statuses.enabled') : t('statuses.disabled')
}

export function sourceStatusTone(source) {
  if (!isSourceSupported(source)) return 'muted'
  return source?.is_enabled ? 'success' : 'muted'
}

export function runStatusTone(status) {
  const value = String(status || '').toLowerCase()
  if (value === 'success') return 'success'
  if (value === 'running' || value === 'processing') return 'warning'
  if (value === 'failed' || value === 'error') return 'danger'
  if (value === 'never') return 'muted'
  return 'muted'
}

export function toCount(value) {
  const n = Number(value)
  return Number.isFinite(n) && n >= 0 ? n : 0
}

export function runCounters(run) {
  if (!run) {
    return {
      fetched: 0,
      created: 0,
      updated: 0,
      skipped: 0,
    }
  }

  return {
    fetched: toCount(run.fetched_count),
    created: toCount(run.created_candidates_count),
    updated: toCount(run.updated_candidates_count),
    skipped: toCount(run.skipped_duplicates_count),
  }
}

export function runTranslation(run) {
  const summary = run?.translation || {}
  const breakdown = summary?.done_breakdown || {}

  return {
    total: toCount(summary.total),
    done: toCount(summary.done),
    failed: toCount(summary.failed),
    pending: toCount(summary.pending),
    both: toCount(breakdown.both),
    titleOnly: toCount(breakdown.title_only),
    descriptionOnly: toCount(breakdown.description_only),
    withoutText: toCount(breakdown.without_text),
  }
}

export function runTranslationModeLabel(run, t) {
  const details = runTranslation(run)

  if (details.done <= 0) {
    return details.pending > 0 ? t('runs.translationMode.pending') : t('runs.translationMode.none')
  }

  if (details.both > 0 && details.titleOnly === 0 && details.descriptionOnly === 0 && details.withoutText === 0) {
    return t('runs.translationMode.both')
  }

  if (details.titleOnly > 0 && details.both === 0 && details.descriptionOnly === 0) {
    return t('runs.translationMode.titleOnly')
  }

  if (details.descriptionOnly > 0 && details.both === 0 && details.titleOnly === 0) {
    return t('runs.translationMode.descriptionOnly')
  }

  return t('runs.translationMode.mix')
}

export function isRunTranslationFullyCorrect(run) {
  const details = runTranslation(run)
  if (details.total <= 0) return false
  if (details.failed > 0 || details.pending > 0) return false
  if (details.done !== details.total) return false
  if (details.withoutText > 0) return false
  if (details.titleOnly > 0 || details.descriptionOnly > 0) return false
  return true
}

export function isRunTranslationInProgress(run) {
  const details = runTranslation(run)
  if (details.total <= 0) return false

  const status = String(run?.status || '').toLowerCase()
  if (status === 'running' || status === 'processing') return true
  if (details.pending > 0) return true

  return details.done + details.failed < details.total
}

export function runTranslationQualityLabel(run, t) {
  const details = runTranslation(run)
  if (details.total <= 0) return t('runs.translationQuality.notRated')
  if (isRunTranslationInProgress(run)) return t('runs.translationQuality.inProgress')
  return isRunTranslationFullyCorrect(run) ? t('runs.translationQuality.ok') : t('runs.translationQuality.problem')
}

export function runTranslationQualityTone(run) {
  const details = runTranslation(run)
  if (details.total <= 0) return 'muted'
  if (isRunTranslationInProgress(run)) return 'warning'
  return isRunTranslationFullyCorrect(run) ? 'success' : 'danger'
}

export function runStatusLabel(run, t) {
  if (!run) return t('common.never')
  const status = String(run.status || '').trim()
  return status !== '' ? status : t('common.unknown')
}
