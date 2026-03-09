<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  getCrawlRuns,
  getEventTranslationHealth,
  getEventSources,
  getTranslationArtifactsReport,
  purgeEventSources,
  repairTranslationArtifacts,
  runEventSourceCrawl,
  updateEventSource,
} from '@/services/api/admin/eventSources'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { createDictionaryTranslator } from '@/i18n/dictionary'
import { adminEventSourcesMessages } from '@/i18n/adminEventSources.messages'

const router = useRouter()
const toast = useToast()
const { confirm } = useConfirm()
const { t } = createDictionaryTranslator(adminEventSourcesMessages, 'sk')

const loading = ref(false)
const error = ref('')
const runningSelected = ref(false)
const purging = ref(false)
const runningByKey = ref({})
const purgeDryRun = ref(true)

const sources = ref([])
const sourceFilter = ref('all')
const selectedKeys = ref([])
const recentRuns = ref([])
const latestRunBySourceKey = ref({})

const yearTouched = ref(false)
const year = ref(new Date().getFullYear())
const activeOps = ref(0)
const progressValue = ref(0)
let progressIntervalId = null
const translationHealth = ref(null)
const translationHealthLoading = ref(false)
const translationPollFailureCount = ref(0)
let translationPollId = null
const translationPollBaseDelayMs = 3500
const translationPollMaxDelayMs = 28000
const artifactsSummary = ref({
  suspicious_candidates: 0,
  sample_limit: 20,
  sample_count: 0,
  checked_at: null,
})
const artifactsSamples = ref([])
const artifactsSampleLimit = ref(20)
const artifactsRepairLimit = ref(300)
const artifactsLoading = ref(false)
const artifactsRepairing = ref(false)

const supportedSelectedKeys = computed(() => {
  const selectedSet = new Set(selectedKeys.value.map((key) => normalizeSourceKey(key)))

  return sources.value
    .filter((source) => selectedSet.has(normalizeSourceKey(source.key)))
    .filter((source) => Boolean(source?.manual_run_supported) && Boolean(source?.is_enabled))
    .map((source) => normalizeSourceKey(source.key))
})

const astropixelsSource = computed(() =>
  sources.value.find((source) => normalizeSourceKey(source?.key) === 'astropixels') || null,
)
const astropixelsYearCatalog = computed(() => astropixelsSource.value?.year_catalog || null)
const runYearMin = computed(() => {
  const fromCatalog = Number(astropixelsYearCatalog.value?.min_year)
  return Number.isFinite(fromCatalog) && fromCatalog >= 2000 ? fromCatalog : 2000
})
const runYearMax = computed(() => {
  const fromCatalog = Number(astropixelsYearCatalog.value?.max_year)
  return Number.isFinite(fromCatalog) && fromCatalog >= runYearMin.value ? fromCatalog : 2100
})
const isRunYearValid = computed(() => {
  const y = Number(year.value)
  if (!Number.isFinite(y)) return false
  return y >= runYearMin.value && y <= runYearMax.value
})
const runYearHint = computed(() => {
  const catalog = astropixelsYearCatalog.value
  if (!catalog) {
    return t('runPanel.yearHintFallback', { min: runYearMin.value, max: runYearMax.value })
  }

  const checkedAt = formatDate(catalog?.checked_at)
  if (String(catalog.status || '') === 'ok') {
    return t('runPanel.yearHintCatalog', { min: runYearMin.value, max: runYearMax.value, checkedAt })
  }

  return t('runPanel.yearHintFallback', { min: runYearMin.value, max: runYearMax.value })
})

const selectedIncludesAstropixels = computed(() => supportedSelectedKeys.value.includes('astropixels'))
const isRunYearValidForSelected = computed(() => !selectedIncludesAstropixels.value || isRunYearValid.value)

const canRunSelected = computed(() => !runningSelected.value && supportedSelectedKeys.value.length > 0 && isRunYearValidForSelected.value)

const totalSourcesCount = computed(() => sources.value.length)
const enabledSourcesCount = computed(() => sources.value.filter((source) => Boolean(source?.is_enabled)).length)
const supportedSourcesCount = computed(() => sources.value.filter((source) => Boolean(source?.manual_run_supported)).length)
const selectedSourcesCount = computed(() => selectedKeys.value.length)
const canClearSelection = computed(() => selectedKeys.value.length > 0 && !runningSelected.value)
const filteredSources = computed(() => {
  if (sourceFilter.value === 'selected') {
    const selectedSet = new Set(selectedKeys.value.map((key) => normalizeSourceKey(key)))
    return sources.value.filter((source) => selectedSet.has(normalizeSourceKey(source?.key)))
  }

  if (sourceFilter.value === 'supported') {
    return sources.value.filter((source) => isSourceSupported(source))
  }
  if (sourceFilter.value === 'enabled') {
    return sources.value.filter((source) => Boolean(source?.is_enabled))
  }
  if (sourceFilter.value === 'unsupported') {
    return sources.value.filter((source) => !isSourceSupported(source))
  }
  return sources.value
})

const sourceFilterOptions = computed(() => [
  { value: 'all', label: t('sources.filters.all') },
  { value: 'selected', label: t('sources.filters.selected') },
  { value: 'supported', label: t('sources.filters.supported') },
  { value: 'enabled', label: t('sources.filters.enabled') },
  { value: 'unsupported', label: t('sources.filters.unsupported') },
])

const isBusy = computed(() => activeOps.value > 0)

const progressLabel = computed(() => {
  if (runningSelected.value) return t('progress.crawlingSelected')
  if (purging.value) return t('progress.purging')
  if (isBusy.value) return t('progress.loading')
  return ''
})

const translationPendingCount = computed(() => Number(translationHealth.value?.pending_candidates_total || 0))
const translationQueuedJobs = computed(() => Number(translationHealth.value?.queue?.queued_event_translation_jobs || 0))
const translationQueueTotal = computed(() => translationPendingCount.value + translationQueuedJobs.value)
const translationCounts = computed(() => translationHealth.value?.counts_24h || {})

const translationProgressPercent = computed(() => {
  const done = Number(translationCounts.value?.done || 0)
  const failed = Number(translationCounts.value?.failed || 0)
  const pending = Number(translationCounts.value?.pending || 0)
  const total = done + failed + pending
  if (total <= 0) return 0
  return Math.max(0, Math.min(100, Math.round(((done + failed) / total) * 100)))
})

const translationIsActive = computed(() => {
  return translationPendingCount.value > 0 || translationQueuedJobs.value > 0
})

const translationProgressLabel = computed(() => {
  if (!translationIsActive.value) return t('progress.translateIdle')
  return t('progress.translateRunning', {
    pending: translationPendingCount.value,
    queued: translationQueuedJobs.value,
  })
})

const artifactsSuspiciousCount = computed(() => Number(artifactsSummary.value?.suspicious_candidates || 0))
const artifactsCheckedAtLabel = computed(() => formatDate(artifactsSummary.value?.checked_at))
const artifactsHasFindings = computed(() => artifactsSuspiciousCount.value > 0)
const artifactsReportTone = computed(() => (artifactsHasFindings.value ? 'danger' : 'success'))
const canRepairArtifacts = computed(() => !artifactsRepairing.value && artifactsSuspiciousCount.value > 0)

const purgeConfirmToken = 'delete_crawled_events'

const purgeTargetKeys = computed(() => {
  const selectedSet = new Set(selectedKeys.value.map((key) => normalizeSourceKey(key)))

  return sources.value
    .filter((source) => selectedSet.size === 0 || selectedSet.has(normalizeSourceKey(source.key)))
    .filter((source) => Boolean(source?.manual_run_supported))
    .map((source) => normalizeSourceKey(source.key))
})

function normalizeSourceKey(value) {
  return String(value || '').trim().toLowerCase()
}

function clampRunYear() {
  const numericYear = Number(year.value)
  if (!Number.isFinite(numericYear)) {
    year.value = runYearMin.value
    return
  }

  year.value = Math.max(runYearMin.value, Math.min(runYearMax.value, Math.floor(numericYear)))
}

function sourceLabel(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  if (key === 'astropixels') return 'AstroPixels'
  if (key === 'imo') return 'IMO'
  if (key === 'nasa_watch_the_skies' || key === 'nasa_wts') return 'NASA WTS'
  if (key === 'nasa') return 'NASA'
  return key || t('common.na')
}

function sourceToneClass(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  if (key === 'astropixels') return 'sourceBadge--astropixels'
  if (key === 'imo') return 'sourceBadge--imo'
  if (key === 'nasa' || key === 'nasa_watch_the_skies' || key === 'nasa_wts') return 'sourceBadge--nasa'
  return 'sourceBadge--generic'
}

function isSourceSupported(source) {
  return Boolean(source?.manual_run_supported)
}

function sourceStatusLabel(source) {
  if (!isSourceSupported(source)) return t('statuses.unsupported')
  return source?.is_enabled ? t('statuses.enabled') : t('statuses.disabled')
}

function sourceStatusTone(source) {
  if (!isSourceSupported(source)) return 'muted'
  return source?.is_enabled ? 'success' : 'muted'
}

function runStatusTone(status) {
  const value = String(status || '').toLowerCase()
  if (value === 'success') return 'success'
  if (value === 'running' || value === 'processing') return 'warning'
  if (value === 'failed' || value === 'error') return 'danger'
  if (value === 'never') return 'muted'
  return 'muted'
}

function formatDate(value) {
  if (!value) return t('common.na')
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return t('common.na')
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function toCount(value) {
  const n = Number(value)
  return Number.isFinite(n) && n >= 0 ? n : 0
}

function runCounters(run) {
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

function runTranslation(run) {
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

function runTranslationModeLabel(run) {
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

function isRunTranslationFullyCorrect(run) {
  const details = runTranslation(run)
  if (details.total <= 0) return false
  if (details.failed > 0 || details.pending > 0) return false
  if (details.done !== details.total) return false
  if (details.withoutText > 0) return false
  if (details.titleOnly > 0 || details.descriptionOnly > 0) return false
  return true
}

function isRunTranslationInProgress(run) {
  const details = runTranslation(run)
  if (details.total <= 0) return false

  const status = String(run?.status || '').toLowerCase()
  if (status === 'running' || status === 'processing') return true
  if (details.pending > 0) return true

  return details.done + details.failed < details.total
}

function runTranslationQualityLabel(run) {
  const details = runTranslation(run)
  if (details.total <= 0) return t('runs.translationQuality.notRated')
  if (isRunTranslationInProgress(run)) return t('runs.translationQuality.inProgress')
  return isRunTranslationFullyCorrect(run) ? t('runs.translationQuality.ok') : t('runs.translationQuality.problem')
}

function runTranslationQualityTone(run) {
  const details = runTranslation(run)
  if (details.total <= 0) return 'muted'
  if (isRunTranslationInProgress(run)) return 'warning'
  return isRunTranslationFullyCorrect(run) ? 'success' : 'danger'
}

function findLatestRunForSource(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  return latestRunBySourceKey.value[key] || null
}

function runStatusLabel(run) {
  if (!run) return t('common.never')
  const status = String(run.status || '').trim()
  return status !== '' ? status : t('common.unknown')
}

function isSourceCheckboxDisabled(source) {
  return runningSelected.value || !source?.is_enabled || !isSourceSupported(source)
}

function isRowRunDisabled(source) {
  const key = normalizeSourceKey(source?.key)
  const yearBlocked = key === 'astropixels' && !isRunYearValid.value
  return runningSelected.value
    || Boolean(runningByKey.value[key])
    || !source?.is_enabled
    || !isSourceSupported(source)
    || yearBlocked
}

function rowRunDisabledReason(source) {
  if (!isSourceSupported(source)) {
    return t('statuses.unsupportedMvp')
  }

  if (!source?.is_enabled) {
    return t('statuses.enableFirst')
  }

  if (normalizeSourceKey(source?.key) === 'astropixels' && !isRunYearValid.value) {
    return t('statuses.yearOutOfRange', { min: runYearMin.value, max: runYearMax.value })
  }

  return ''
}

function clearSelectedSources() {
  if (!canClearSelection.value) return
  selectedKeys.value = []
}

function beginOperation() {
  activeOps.value += 1
  if (progressIntervalId !== null) return
  progressValue.value = 8
  progressIntervalId = window.setInterval(() => {
    if (progressValue.value < 92) {
      progressValue.value += Math.max(1, Math.floor((100 - progressValue.value) / 14))
    }
  }, 220)
}

function endOperation() {
  activeOps.value = Math.max(0, activeOps.value - 1)
  if (activeOps.value > 0) return
  if (progressIntervalId !== null) {
    window.clearInterval(progressIntervalId)
    progressIntervalId = null
  }
  progressValue.value = 100
  window.setTimeout(() => {
    if (activeOps.value === 0) {
      progressValue.value = 0
    }
  }, 200)
}

async function load() {
  beginOperation()
  loading.value = true
  error.value = ''

  try {
    const [sourcesRes, runsRes] = await Promise.all([getEventSources(), getCrawlRuns({ per_page: 10 })])

    const sourceList = Array.isArray(sourcesRes?.data?.data) ? sourcesRes.data.data : []
    sources.value = sourceList

    const runList = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
    recentRuns.value = runList

    const latestByKey = {}
    for (const run of runList) {
      const key = normalizeSourceKey(run?.source_name)
      if (key === '' || latestByKey[key]) continue
      latestByKey[key] = run
    }
    latestRunBySourceKey.value = latestByKey

    if (!yearTouched.value) {
      const latestYear = Number(runList[0]?.year)
      year.value = Number.isFinite(latestYear) && latestYear >= 2000 ? latestYear : new Date().getFullYear()
    }
    clampRunYear()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.loadError')
  } finally {
    loading.value = false
    endOperation()
  }
}

async function loadTranslationHealth() {
  translationHealthLoading.value = true
  try {
    const response = await getEventTranslationHealth()
    translationHealth.value = response?.data || null
    translationPollFailureCount.value = 0
    return true
  } catch {
    translationPollFailureCount.value = Math.min(4, translationPollFailureCount.value + 1)
    return false
  } finally {
    translationHealthLoading.value = false
  }
}

function resolveNextTranslationPollDelay() {
  if (translationPollFailureCount.value <= 0) {
    return translationPollBaseDelayMs
  }

  return Math.min(
    translationPollMaxDelayMs,
    translationPollBaseDelayMs * (2 ** translationPollFailureCount.value),
  )
}

function startTranslationPoll() {
  if (translationPollId !== null) return
  translationPollId = window.setTimeout(async () => {
    translationPollId = null
    await loadTranslationHealth()
    startTranslationPoll()
  }, resolveNextTranslationPollDelay())
}

function stopTranslationPoll() {
  if (translationPollId === null) return
  window.clearTimeout(translationPollId)
  translationPollId = null
}

function handleVisibilityChange() {
  if (typeof document === 'undefined') return

  if (document.visibilityState === 'hidden') {
    stopTranslationPoll()
    return
  }

  void loadTranslationHealth()
  startTranslationPoll()
}

function normalizePositiveInt(value, fallback) {
  const n = Number(value)
  if (!Number.isFinite(n)) return fallback
  return Math.max(1, Math.floor(n))
}

async function loadTranslationArtifactsReport(showSuccessToast = false) {
  artifactsLoading.value = true

  try {
    const response = await getTranslationArtifactsReport({
      sample: normalizePositiveInt(artifactsSampleLimit.value, 20),
    })

    const summary = response?.data?.summary || {}
    const samples = Array.isArray(response?.data?.samples) ? response.data.samples : []

    artifactsSummary.value = {
      suspicious_candidates: Number(summary.suspicious_candidates || 0),
      sample_limit: Number(summary.sample_limit || normalizePositiveInt(artifactsSampleLimit.value, 20)),
      sample_count: Number(summary.sample_count || samples.length),
      checked_at: summary.checked_at || null,
    }
    artifactsSamples.value = samples

    if (showSuccessToast) {
      const count = Number(summary.suspicious_candidates || 0)
      if (count > 0) {
        toast.warn(t('messages.qualityFound', { count }))
      } else {
        toast.success(t('messages.qualityClear'))
      }
    }
  } catch (fetchError) {
    if (showSuccessToast) {
      error.value = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.artifactsLoadError')
    }
  } finally {
    artifactsLoading.value = false
  }
}

async function runTranslationArtifactsRepair() {
  if (!canRepairArtifacts.value) return

  artifactsRepairing.value = true
  beginOperation()
  error.value = ''

  try {
    const response = await repairTranslationArtifacts({
      limit: normalizePositiveInt(artifactsRepairLimit.value, 300),
      dry_run: false,
      sample: normalizePositiveInt(artifactsSampleLimit.value, 20),
    })

    const payload = response?.data || {}
    const summary = payload.summary || {}
    const processed = Number(summary.processed || 0)
    const translated = Number(summary.translated || 0)
    const failed = Number(summary.failed || 0)

    toast.success(t('messages.repairDone', { processed, translated, failed }))

    await Promise.all([loadTranslationArtifactsReport(false), load(), loadTranslationHealth()])
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.artifactsRepairError')
  } finally {
    artifactsRepairing.value = false
    endOperation()
  }
}

async function toggleSource(source, checked) {
  try {
    await updateEventSource(source.id, { is_enabled: checked })
    source.is_enabled = checked

    const key = normalizeSourceKey(source.key)
    if (!checked) {
      selectedKeys.value = selectedKeys.value.filter((item) => normalizeSourceKey(item) !== key)
    }
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.sourceUpdateError')
  }
}

async function runSelected() {
  if (!canRunSelected.value) return
  if (selectedIncludesAstropixels.value) {
    clampRunYear()
  }

  runningSelected.value = true
  beginOperation()
  error.value = ''

  try {
    await runEventSourceCrawl({
      source_keys: supportedSelectedKeys.value,
      year: Number(year.value),
    })
    toast.success(t('messages.runSelectedSuccess'))
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.runSelectedError')
  } finally {
    runningSelected.value = false
    endOperation()
  }
}

async function requestPurgeConfirmation() {
  return confirm({
    title: purgeDryRun.value ? t('dialogs.purgeDryRunTitle') : t('dialogs.purgeHardTitle'),
    message: t('dialogs.purgeMessage'),
    confirmText: t('dialogs.purgeConfirm'),
    cancelText: t('dialogs.purgeCancel'),
    variant: 'danger',
  })
}

async function startPurgeFlow() {
  if (purging.value) return
  if (!(await requestPurgeConfirmation())) return
  await purgeCrawledData()
}

async function purgeCrawledData() {
  if (purging.value) return

  purging.value = true
  beginOperation()
  error.value = ''

  try {
    const response = await purgeEventSources({
      source_keys: purgeTargetKeys.value,
      dry_run: Boolean(purgeDryRun.value),
      confirm: purgeConfirmToken,
    })

    const deleted = response?.data?.deleted || {}
    const events = Number(deleted.events || 0)
    const candidates = Number(deleted.event_candidates || 0)
    const runs = Number(deleted.crawl_runs || 0)
    const key = purgeDryRun.value ? 'messages.purgeDryRunDone' : 'messages.purgeDone'
    toast.success(t(key, { events, candidates, runs }))

    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.purgeError')
  } finally {
    purging.value = false
    endOperation()
  }
}

async function runSingleSource(source) {
  const key = normalizeSourceKey(source?.key)
  if (!key || isRowRunDisabled(source)) return
  if (key === 'astropixels') {
    clampRunYear()
  }

  runningByKey.value = {
    ...runningByKey.value,
    [key]: true,
  }

  beginOperation()
  error.value = ''

  try {
    await runEventSourceCrawl({
      source_keys: [key],
      year: Number(year.value),
    })
    toast.success(t('messages.runSingleSuccess', { source: sourceLabel(key) }))
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.runSingleError')
  } finally {
    runningByKey.value = {
      ...runningByKey.value,
      [key]: false,
    }
    endOperation()
  }
}

function viewRunCandidates(run) {
  const sourceKey = normalizeSourceKey(run?.source_name)

  router.push({
    name: 'admin.event-candidates',
    query: {
      run_id: run?.id != null ? String(run.id) : undefined,
      source_key: sourceKey || undefined,
      source: sourceKey || undefined,
      year: run?.year != null ? String(run.year) : undefined,
    },
  })
}

function openRunDetails(run) {
  if (!run?.id) return

  router.push({
    name: 'admin.crawl-run.detail',
    params: { id: String(run.id) },
  })
}

function openCandidateDetail(candidateId) {
  const id = Number(candidateId)
  if (!Number.isFinite(id) || id <= 0) return

  router.push({
    name: 'admin.candidate.detail',
    params: { id: String(id) },
  })
}

onMounted(async () => {
  await Promise.all([load(), loadTranslationHealth(), loadTranslationArtifactsReport(false)])
  if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', handleVisibilityChange)
  }
  startTranslationPoll()
})

onUnmounted(() => {
  stopTranslationPoll()
  if (typeof document !== 'undefined') {
    document.removeEventListener('visibilitychange', handleVisibilityChange)
  }
})
</script>

<template>
  <AdminPageShell :title="t('page.title')" :subtitle="t('page.subtitle')">
    <div v-if="error" class="alert">{{ error }}</div>

    <section class="statsGrid">
      <article class="statCard">
        <span class="statCard__label">{{ t('stats.totalSources') }}</span>
        <strong class="statCard__value">{{ totalSourcesCount }}</strong>
      </article>
      <article class="statCard">
        <span class="statCard__label">{{ t('stats.enabledSources') }}</span>
        <strong class="statCard__value">{{ enabledSourcesCount }}</strong>
      </article>
      <article class="statCard">
        <span class="statCard__label">{{ t('stats.supportedSources') }}</span>
        <strong class="statCard__value">{{ supportedSourcesCount }}</strong>
      </article>
      <article class="statCard">
        <span class="statCard__label">{{ t('stats.selectedSources') }}</span>
        <strong class="statCard__value">{{ selectedSourcesCount }}</strong>
      </article>
      <article class="statCard">
        <span class="statCard__label">{{ t('stats.translationQueue') }}</span>
        <strong class="statCard__value">{{ translationQueueTotal }}</strong>
      </article>
    </section>

    <section v-if="isBusy || progressValue > 0" class="progressPanel" data-testid="crawl-progress-panel">
      <div class="progressPanel__label">{{ progressLabel }}</div>
      <div class="progressBar">
        <div class="progressBar__fill" :style="{ width: `${progressValue}%` }"></div>
      </div>
    </section>

    <section
      v-if="translationHealthLoading || translationIsActive"
      class="progressPanel"
      data-testid="translation-progress-panel"
    >
      <div class="progressPanel__label">{{ translationProgressLabel }}</div>
      <div class="progressBar progressBar--translation">
        <div class="progressBar__fill progressBar__fill--translation" :style="{ width: `${translationProgressPercent}%` }"></div>
      </div>
      <div class="progressPanel__meta">
        <span>{{ t('progress.done') }}: {{ Number(translationCounts.done || 0) }}</span>
        <span>{{ t('progress.failed') }}: {{ Number(translationCounts.failed || 0) }}</span>
        <span>{{ t('progress.pending') }}: {{ Number(translationCounts.pending || 0) }}</span>
      </div>
    </section>

    <section class="topGrid">
      <section class="card runPanel">
        <div class="cardHead">
          <h2>{{ t('runPanel.title') }}</h2>
          <span class="muted">{{ t('runPanel.selectedSupported', { count: supportedSelectedKeys.length }) }}</span>
        </div>

        <div class="runPanel__actions">
          <label class="field" for="run-year">
            <span>{{ t('runPanel.year') }}</span>
            <input
              id="run-year"
              v-model.number="year"
              type="number"
              :min="runYearMin"
              :max="runYearMax"
              :disabled="runningSelected"
              @input="yearTouched = true"
            />
            <span class="muted">{{ runYearHint }}</span>
          </label>

          <button
            type="button"
            class="primaryBtn"
            data-testid="run-selected-btn"
            :disabled="!canRunSelected"
            @click="runSelected"
          >
            {{ runningSelected ? t('runPanel.runSelectedLoading') : t('runPanel.runSelectedIdle') }}
          </button>

          <label class="switchLabel" for="purge-dry-run">
            <input id="purge-dry-run" v-model="purgeDryRun" type="checkbox" :disabled="purging" />
            <span>{{ t('runPanel.purgeDryRun') }}</span>
          </label>

          <button
            type="button"
            class="btn-danger"
            data-testid="purge-crawled-btn"
            :disabled="purging"
            @click="startPurgeFlow"
          >
            {{ purging ? t('runPanel.purgeLoading') : t('runPanel.purgeIdle') }}
          </button>
        </div>

        <p class="muted">{{ t('runPanel.hint') }}</p>
      </section>

      <section class="card qualityPanel" data-testid="translation-quality-panel">
        <div class="cardHead">
          <h2>{{ t('quality.title') }}</h2>
          <span class="muted">{{ t('quality.subtitle') }}</span>
        </div>

        <div class="qualityPanel__summary">
          <span class="pill" :class="`pill--${artifactsReportTone}`" data-testid="translation-artifacts-count">
            {{ t('quality.suspicious', { count: artifactsSuspiciousCount }) }}
          </span>
          <span class="muted">{{ t('quality.checkedAt', { value: artifactsCheckedAtLabel }) }}</span>
        </div>

        <div class="qualityPanel__actions">
          <label class="field" for="artifacts-sample-limit">
            <span>{{ t('quality.sample') }}</span>
            <input
              id="artifacts-sample-limit"
              v-model.number="artifactsSampleLimit"
              type="number"
              min="1"
              max="100"
              :disabled="artifactsLoading || artifactsRepairing"
            />
          </label>

          <label class="field" for="artifacts-repair-limit">
            <span>{{ t('quality.repairLimit') }}</span>
            <input
              id="artifacts-repair-limit"
              v-model.number="artifactsRepairLimit"
              type="number"
              min="1"
              max="1000"
              :disabled="artifactsLoading || artifactsRepairing"
            />
          </label>

          <button
            type="button"
            class="ghostBtn"
            data-testid="translation-artifacts-report-btn"
            :disabled="artifactsLoading || artifactsRepairing"
            @click="loadTranslationArtifactsReport(true)"
          >
            {{ artifactsLoading ? t('quality.checkLoading') : t('quality.checkIdle') }}
          </button>

          <button
            type="button"
            class="btn-danger"
            data-testid="translation-artifacts-repair-btn"
            :disabled="!canRepairArtifacts"
            @click="runTranslationArtifactsRepair"
          >
            {{ artifactsRepairing ? t('quality.repairLoading') : t('quality.repairIdle') }}
          </button>
        </div>

        <p class="muted">{{ t('quality.hint') }}</p>

        <div v-if="artifactsSamples.length > 0" class="tableWrap qualityPanel__tableWrap">
          <table class="table compact">
            <thead>
              <tr>
                <th>{{ t('quality.columns.candidate') }}</th>
                <th>{{ t('quality.columns.event') }}</th>
                <th>{{ t('quality.columns.sourceTitle') }}</th>
                <th>{{ t('quality.columns.translatedTitle') }}</th>
                <th>{{ t('quality.columns.eventTitle') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sample in artifactsSamples" :key="sample.candidate_id">
                <td>
                  <button
                    type="button"
                    class="inlineLinkBtn"
                    :data-testid="`translation-artifacts-candidate-link-${sample.candidate_id}`"
                    @click="openCandidateDetail(sample.candidate_id)"
                  >
                    {{ sample.candidate_id }}
                  </button>
                </td>
                <td>{{ sample.event_id || t('common.na') }}</td>
                <td>{{ sample.source_title || t('common.na') }}</td>
                <td>{{ sample.translated_title || t('common.na') }}</td>
                <td>{{ sample.event_title || t('common.na') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="muted qualityPanel__empty">
          {{ t('quality.empty') }}
        </div>
      </section>
    </section>

    <section class="card">
      <div class="cardHead">
        <h2>{{ t('sources.title') }}</h2>
        <div class="sourceToolbar">
          <label class="field field--inline" for="source-filter">
            <select id="source-filter" v-model="sourceFilter" data-testid="source-filter">
              <option v-for="option in sourceFilterOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>
          <button
            type="button"
            class="ghostBtn"
            data-testid="source-clear-selection-btn"
            :disabled="!canClearSelection"
            @click="clearSelectedSources"
          >
            {{ t('sources.clearSelection') }}
          </button>
          <span class="muted">{{ t('sources.shown', { shown: filteredSources.length, total: sources.length }) }}</span>
        </div>
      </div>

      <div v-if="loading" class="muted">{{ t('sources.loading') }}</div>
      <div v-else-if="filteredSources.length === 0" class="muted">{{ t('sources.emptyFiltered') }}</div>
      <div v-else>
        <div class="tableWrap sourcesTableWrap">
          <table class="table compact">
            <thead>
              <tr>
                <th aria-label="Vyber zdroja">{{ t('sources.columns.select') }}</th>
                <th>{{ t('sources.columns.source') }}</th>
                <th>{{ t('sources.columns.status') }}</th>
                <th>{{ t('sources.columns.lastRun') }}</th>
                <th>{{ t('sources.columns.counters') }}</th>
                <th>{{ t('sources.columns.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="source in filteredSources"
                :key="source.id"
                :data-testid="`source-row-${normalizeSourceKey(source.key)}`"
              >
                <td class="tight">
                  <input
                    :id="`source-select-${source.id}`"
                    v-model="selectedKeys"
                    :value="source.key"
                    type="checkbox"
                    :data-testid="`source-select-${normalizeSourceKey(source.key)}`"
                    :disabled="isSourceCheckboxDisabled(source)"
                  />
                </td>

                <td>
                  <span class="sourceBadge" :class="sourceToneClass(source.key)">{{ sourceLabel(source.key) }}</span>
                </td>

                <td>
                  <span class="pill" :class="`pill--${sourceStatusTone(source)}`">{{ sourceStatusLabel(source) }}</span>
                </td>

                <td>
                  <div class="stackTiny">
                    <span>{{ formatDate(findLatestRunForSource(source.key)?.started_at) }}</span>
                    <span class="pill" :class="`pill--${runStatusTone(runStatusLabel(findLatestRunForSource(source.key)))}`">
                      {{ runStatusLabel(findLatestRunForSource(source.key)) }}
                    </span>
                  </div>
                </td>

                <td>
                  <div class="counterRow">
                    <span>F {{ runCounters(findLatestRunForSource(source.key)).fetched }}</span>
                    <span>C {{ runCounters(findLatestRunForSource(source.key)).created }}</span>
                    <span>U {{ runCounters(findLatestRunForSource(source.key)).updated }}</span>
                    <span>S {{ runCounters(findLatestRunForSource(source.key)).skipped }}</span>
                  </div>
                </td>

                <td>
                  <div class="actionRow">
                    <label :for="`source-enabled-${source.id}`" class="switchLabel">
                      <input
                        :id="`source-enabled-${source.id}`"
                        :checked="source.is_enabled"
                        type="checkbox"
                        :disabled="runningSelected"
                        @change="toggleSource(source, $event.target.checked)"
                      />
                      <span>{{ source.is_enabled ? t('sources.switchOn') : t('sources.switchOff') }}</span>
                    </label>

                    <button
                      type="button"
                      class="ghostBtn"
                      :data-testid="`run-source-${normalizeSourceKey(source.key)}`"
                      :disabled="isRowRunDisabled(source)"
                      :title="rowRunDisabledReason(source)"
                      @click="runSingleSource(source)"
                    >
                      {{ runningByKey[normalizeSourceKey(source.key)] ? t('sources.runLoading') : t('sources.runIdle') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="sourcesMobileList">
          <article
            v-for="source in filteredSources"
            :key="`mobile-${source.id}`"
            class="sourceMobileCard"
          >
            <div class="sourceMobileCard__head">
              <label class="sourceMobileCard__select" :for="`source-select-mobile-${source.id}`">
                <input
                  :id="`source-select-mobile-${source.id}`"
                  v-model="selectedKeys"
                  :value="source.key"
                  type="checkbox"
                  :disabled="isSourceCheckboxDisabled(source)"
                />
                <span class="sourceBadge" :class="sourceToneClass(source.key)">{{ sourceLabel(source.key) }}</span>
              </label>
              <span class="pill" :class="`pill--${sourceStatusTone(source)}`">{{ sourceStatusLabel(source) }}</span>
            </div>

            <div class="sourceMobileCard__meta">
              <span>{{ t('sources.columns.lastRun') }}: {{ formatDate(findLatestRunForSource(source.key)?.started_at) }}</span>
              <span class="pill" :class="`pill--${runStatusTone(runStatusLabel(findLatestRunForSource(source.key)))}`">
                {{ runStatusLabel(findLatestRunForSource(source.key)) }}
              </span>
            </div>

            <div class="counterRow">
              <span>F {{ runCounters(findLatestRunForSource(source.key)).fetched }}</span>
              <span>C {{ runCounters(findLatestRunForSource(source.key)).created }}</span>
              <span>U {{ runCounters(findLatestRunForSource(source.key)).updated }}</span>
              <span>S {{ runCounters(findLatestRunForSource(source.key)).skipped }}</span>
            </div>

            <div class="sourceMobileCard__actions">
              <label :for="`source-enabled-mobile-${source.id}`" class="switchLabel">
                <input
                  :id="`source-enabled-mobile-${source.id}`"
                  :checked="source.is_enabled"
                  type="checkbox"
                  :disabled="runningSelected"
                  @change="toggleSource(source, $event.target.checked)"
                />
                <span>{{ source.is_enabled ? t('sources.switchOn') : t('sources.switchOff') }}</span>
              </label>

              <button
                type="button"
                class="ghostBtn"
                :disabled="isRowRunDisabled(source)"
                :title="rowRunDisabledReason(source)"
                @click="runSingleSource(source)"
              >
                {{ runningByKey[normalizeSourceKey(source.key)] ? t('sources.runLoading') : t('sources.runIdle') }}
              </button>
            </div>

            <p v-if="rowRunDisabledReason(source)" class="muted sourceMobileCard__hint">
              {{ rowRunDisabledReason(source) }}
            </p>
          </article>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="cardHead">
        <h2>{{ t('runs.title') }}</h2>
        <span class="muted">{{ t('runs.subtitle') }}</span>
      </div>

      <div v-if="recentRuns.length === 0" class="muted">{{ t('runs.empty') }}</div>
      <div v-else>
        <div class="tableWrap runsTableWrap">
          <table class="table compact">
            <thead>
              <tr>
                <th>{{ t('runs.columns.time') }}</th>
                <th>{{ t('runs.columns.source') }}</th>
                <th>{{ t('runs.columns.year') }}</th>
                <th>{{ t('runs.columns.status') }}</th>
                <th>{{ t('runs.columns.counters') }}</th>
                <th>{{ t('runs.columns.translation') }}</th>
                <th>{{ t('runs.columns.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="run in recentRuns" :key="run.id">
                <td>{{ formatDate(run.started_at) }}</td>
                <td>
                  <span class="sourceBadge" :class="sourceToneClass(run.source_name)">{{ sourceLabel(run.source_name) }}</span>
                </td>
                <td>{{ run.year || t('common.na') }}</td>
                <td>
                  <span class="pill" :class="`pill--${runStatusTone(run.status)}`">{{ run.status || t('common.unknown') }}</span>
                </td>
                <td>
                  <div class="counterRow">
                    <span>F {{ runCounters(run).fetched }}</span>
                    <span>C {{ runCounters(run).created }}</span>
                    <span>U {{ runCounters(run).updated }}</span>
                    <span>S {{ runCounters(run).skipped }}</span>
                  </div>
                </td>
                <td>
                  <div v-if="runTranslation(run).total > 0" class="stackTiny">
                    <span class="pill" :class="`pill--${runTranslationQualityTone(run)}`">
                      {{ runTranslationQualityLabel(run) }}
                    </span>
                    <div class="counterRow">
                      <span>D {{ runTranslation(run).done }}</span>
                      <span>F {{ runTranslation(run).failed }}</span>
                      <span>P {{ runTranslation(run).pending }}</span>
                    </div>
                    <span class="muted">{{ t('runs.translationForm', { mode: runTranslationModeLabel(run) }) }}</span>
                  </div>
                  <span v-else class="muted">{{ t('common.na') }}</span>
                </td>
                <td>
                  <div class="actionRow">
                    <button type="button" class="ghostBtn" @click="viewRunCandidates(run)">{{ t('runs.candidates') }}</button>
                    <button type="button" class="ghostBtn" @click="openRunDetails(run)">{{ t('runs.detail') }}</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="runsMobileList">
          <article v-for="run in recentRuns" :key="`run-mobile-${run.id}`" class="runMobileCard">
            <div class="runMobileCard__head">
              <span class="sourceBadge" :class="sourceToneClass(run.source_name)">{{ sourceLabel(run.source_name) }}</span>
              <span class="pill" :class="`pill--${runStatusTone(run.status)}`">{{ run.status || t('common.unknown') }}</span>
            </div>

            <div class="runMobileCard__meta">
              <span>{{ formatDate(run.started_at) }}</span>
              <span>{{ t('runs.columns.year') }}: {{ run.year || t('common.na') }}</span>
            </div>

            <div class="counterRow">
              <span>F {{ runCounters(run).fetched }}</span>
              <span>C {{ runCounters(run).created }}</span>
              <span>U {{ runCounters(run).updated }}</span>
              <span>S {{ runCounters(run).skipped }}</span>
            </div>

            <div v-if="runTranslation(run).total > 0" class="runMobileCard__translation">
              <span class="pill" :class="`pill--${runTranslationQualityTone(run)}`">
                {{ runTranslationQualityLabel(run) }}
              </span>
              <div class="counterRow">
                <span>D {{ runTranslation(run).done }}</span>
                <span>F {{ runTranslation(run).failed }}</span>
                <span>P {{ runTranslation(run).pending }}</span>
              </div>
              <span class="muted">{{ t('runs.translationForm', { mode: runTranslationModeLabel(run) }) }}</span>
            </div>
            <span v-else class="muted">{{ t('common.na') }}</span>

            <div class="actionRow runMobileCard__actions">
              <button type="button" class="ghostBtn" @click="viewRunCandidates(run)">{{ t('runs.candidates') }}</button>
              <button type="button" class="ghostBtn" @click="openRunDetails(run)">{{ t('runs.detail') }}</button>
            </div>
          </article>
        </div>
      </div>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  padding: 10px;
  background: rgb(var(--color-bg-rgb) / 0.92);
}

.cardHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}

.cardHead h2 {
  margin: 0;
  font-size: 15px;
}

.statsGrid {
  display: grid;
  gap: 6px;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
}

.statCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.08);
  border-radius: 10px;
  padding: 7px 9px;
  background: rgb(var(--color-bg-rgb) / 0.98);
  display: grid;
  gap: 2px;
}

.statCard__label {
  font-size: 11px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.statCard__value {
  font-size: 19px;
  line-height: 1.1;
}

.topGrid {
  display: grid;
  gap: 8px;
  grid-template-columns: 1.1fr 1fr;
}

.runPanel,
.qualityPanel {
  display: grid;
  gap: 10px;
}

.runPanel__actions,
.qualityPanel__actions {
  display: flex;
  align-items: end;
  gap: 8px;
  flex-wrap: wrap;
}

.field {
  display: grid;
  gap: 4px;
  font-size: 12px;
}

.field input,
.field select {
  width: 120px;
}

.field input,
.field select,
.ghostBtn,
.primaryBtn,
.btn-danger {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 8px;
  padding: 6px 9px;
  background: transparent;
  color: inherit;
}

.primaryBtn {
  border-color: rgb(var(--color-primary-rgb) / 0.22);
  background: rgb(var(--color-primary-rgb) / 0.08);
}

.btn-danger {
  border-color: rgb(220 38 38 / 0.22);
  background: rgb(220 38 38 / 0.06);
}

.ghostBtn:disabled,
.primaryBtn:disabled,
.btn-danger:disabled {
  opacity: 0.58;
  cursor: not-allowed;
}

.field--inline {
  gap: 0;
}

.sourceToolbar {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.qualityPanel__summary {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.qualityPanel__tableWrap {
  max-height: 260px;
}

.qualityPanel__empty {
  padding: 6px 0 2px;
}

.inlineLinkBtn {
  border: none;
  background: transparent;
  padding: 0;
  color: rgb(var(--color-primary-rgb) / 0.95);
  font: inherit;
  text-decoration: underline;
  text-underline-offset: 2px;
  cursor: pointer;
}

.progressPanel {
  margin-bottom: 2px;
  display: grid;
  gap: 6px;
}

.progressPanel__label {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.progressPanel__meta {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.progressBar {
  width: 100%;
  height: 6px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.12);
  overflow: hidden;
}

.progressBar__fill {
  height: 100%;
  background: linear-gradient(90deg, rgb(14 116 144 / 0.9), rgb(59 130 246 / 0.9));
  transition: width 180ms linear;
}

.progressBar--translation {
  background: rgb(16 185 129 / 0.18);
}

.progressBar__fill--translation {
  background: linear-gradient(90deg, rgb(5 150 105 / 0.9), rgb(22 163 74 / 0.9));
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
}

.sourcesMobileList,
.runsMobileList {
  display: none;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  text-align: left;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.08);
  padding: 6px 7px;
  vertical-align: middle;
}

.table th {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.table .tight {
  width: 1%;
  white-space: nowrap;
}

.sourceBadge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: transparent;
  padding: 1px 7px;
  font-size: 12px;
}

.sourceBadge--astropixels {
  border-color: rgb(30 64 175 / 0.25);
  background: rgb(30 64 175 / 0.04);
}

.sourceBadge--imo {
  border-color: rgb(6 95 70 / 0.25);
  background: rgb(6 95 70 / 0.04);
}

.sourceBadge--nasa {
  border-color: rgb(107 33 168 / 0.25);
  background: rgb(107 33 168 / 0.04);
}

.pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  padding: 1px 7px;
  font-size: 12px;
  background: transparent;
}

.pill--success {
  border-color: rgb(22 163 74 / 0.24);
  background: rgb(22 163 74 / 0.05);
}

.pill--warning {
  border-color: rgb(202 138 4 / 0.24);
  background: rgb(202 138 4 / 0.05);
}

.pill--danger {
  border-color: rgb(220 38 38 / 0.24);
  background: rgb(220 38 38 / 0.05);
}

.stackTiny {
  display: grid;
  gap: 4px;
}

.counterRow {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 12px;
}

.actionRow {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.sourceMobileCard,
.runMobileCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.98);
  padding: 9px;
  display: grid;
  gap: 8px;
}

.sourceMobileCard__head,
.runMobileCard__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.sourceMobileCard__select {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.sourceMobileCard__meta,
.runMobileCard__meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 12px;
}

.sourceMobileCard__actions,
.runMobileCard__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.sourceMobileCard__hint {
  margin: 0;
}

.runMobileCard__translation {
  display: grid;
  gap: 6px;
}

.switchLabel {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.muted {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.alert {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.1);
  color: rgb(185 28 28);
}

@media (max-width: 1100px) {
  .topGrid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 900px) {
  .card {
    padding: 10px;
  }

  .runPanel__actions,
  .qualityPanel__actions {
    align-items: stretch;
  }

  .field input {
    width: 100%;
  }

  .field select {
    width: 100%;
  }

  .sourcesTableWrap,
  .runsTableWrap {
    display: none;
  }

  .sourcesMobileList,
  .runsMobileList {
    display: grid;
    gap: 8px;
  }

  .sourceMobileCard__actions,
  .runMobileCard__actions {
    width: 100%;
  }

  .sourceMobileCard__actions .ghostBtn,
  .runMobileCard__actions .ghostBtn,
  .sourceMobileCard__actions .switchLabel {
    flex: 1 1 auto;
  }

  .runMobileCard__actions .ghostBtn {
    text-align: center;
  }
}
</style>
