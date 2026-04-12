<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  getCrawlRuns,
  getEventSources,
  purgeEventSources,
  runEventSourceCrawl,
  updateEventSource,
} from '@/services/api/admin/eventSources'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { createDictionaryTranslator } from '@/i18n/dictionary'
import { adminEventSourcesMessages } from '@/i18n/adminEventSources.messages'
import { useEventSourcesTranslationQuality } from './eventSources/useEventSourcesTranslationQuality'
import {
  clampYearValue,
  isSourceSupported,
  normalizeSourceKey,
  runStatusLabel as formatRunStatusLabel,
  runStatusTone,
  runTranslation,
  runTranslationModeLabel as formatRunTranslationModeLabel,
  runTranslationQualityLabel as formatRunTranslationQualityLabel,
  runTranslationQualityTone,
  sourceLabel as formatSourceLabel,
} from './eventSources/eventSourcesView.utils'

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
const progressMode = ref('indeterminate')
const progressLabelOverride = ref('')
const progressCurrent = ref(0)
const progressTotal = ref(0)
const progressDetail = ref('')
const progressFailedCount = ref(0)
const progressCurrentSourceKey = ref('')
const translationProgressStartedAtMs = ref(null)
const translationElapsedNowMs = ref(0)
let translationElapsedTimerId = null
const recentRunsRefreshing = ref(false)
let recentRunsPollId = null
const layoutHost = ref(null)
const isMobileViewport = ref(false)
const mobileViewportBreakpoint = 860
let layoutResizeObserver = null

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
const runnableSourcesCount = computed(() =>
  sources.value.filter((source) => Boolean(source?.manual_run_supported) && Boolean(source?.is_enabled)).length,
)
const selectedSourcesCount = computed(() => selectedKeys.value.length)
const canClearSelection = computed(() => selectedKeys.value.length > 0 && !runningSelected.value)
const allSelectableKeys = computed(() =>
  sources.value
    .filter((source) => !isSourceCheckboxDisabled(source))
    .map((source) => source.key),
)
const isAllSelected = computed(
  () =>
    allSelectableKeys.value.length > 0 &&
    allSelectableKeys.value.every((key) => selectedKeys.value.includes(key)),
)
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
const hasDeterminateProgress = computed(() => progressMode.value === 'determinate' && progressTotal.value > 0)
const progressValue = computed(() => {
  if (!hasDeterminateProgress.value) return 0
  const ratio = progressCurrent.value / progressTotal.value
  return Math.max(0, Math.min(100, Math.round(ratio * 100)))
})
const progressRemaining = computed(() => Math.max(0, progressTotal.value - progressCurrent.value))
const progressPercentLabel = computed(() => (hasDeterminateProgress.value ? `${progressValue.value} %` : 'LIVE'))
const progressDetailLabel = computed(() => {
  const detail = String(progressDetail.value || '').trim()
  if (detail !== '') return detail
  if (!hasDeterminateProgress.value) return ''
  return `${Math.min(progressCurrent.value, progressTotal.value)}/${progressTotal.value}`
})

const progressLabel = computed(() => {
  if (String(progressLabelOverride.value || '').trim() !== '') return String(progressLabelOverride.value)
  if (runningSelected.value) return t('progress.crawlingSelected')
  if (purging.value) return t('progress.purging')
  if (isBusy.value) return t('progress.loading')
  return ''
})
const progressMetaItems = computed(() => {
  if (!isBusy.value) return []

  const stats = []
  if (hasDeterminateProgress.value) {
    stats.push(`Dokončené: ${Math.min(progressCurrent.value, progressTotal.value)}/${progressTotal.value}`)
    if (progressRemaining.value > 0) {
      stats.push(`Zostava: ${progressRemaining.value}`)
    }
  } else {
    stats.push('Cakam na odpoved servera')
  }

  if (progressCurrentSourceKey.value) {
    stats.push(`Prave beží: ${sourceLabel(progressCurrentSourceKey.value)}`)
  }

  if (progressFailedCount.value > 0) {
    stats.push(`Chyby: ${progressFailedCount.value}`)
  }

  return stats
})

const purgeConfirmToken = 'delete_crawled_events'

const purgeTargetKeys = computed(() => {
  const selectedSet = new Set(selectedKeys.value.map((key) => normalizeSourceKey(key)))

  return sources.value
    .filter((source) => selectedSet.size === 0 || selectedSet.has(normalizeSourceKey(source.key)))
    .filter((source) => Boolean(source?.manual_run_supported))
    .map((source) => normalizeSourceKey(source.key))
})

function clampRunYear() {
  year.value = clampYearValue(year.value, runYearMin.value, runYearMax.value)
}

function sourceLabel(sourceKey) {
  return formatSourceLabel(sourceKey, t)
}

function runTranslationModeLabel(run) {
  return formatRunTranslationModeLabel(run, t)
}

function runTranslationQualityLabel(run) {
  return formatRunTranslationQualityLabel(run, t)
}

function runStatusLabel(run) {
  return formatRunStatusLabel(run, t)
}

const latestRun = computed(() => recentRuns.value[0] || null)
const latestRunSummary = computed(() => {
  if (!latestRun.value) return t('runs.empty')
  return `${sourceLabel(latestRun.value.source_name)} - ${runStatusLabel(latestRun.value)} - ${formatDate(latestRun.value.started_at)}`
})

function formatDate(value) {
  if (!value) return t('common.na')
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return t('common.na')
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatDurationFromMs(value) {
  const totalMs = Number(value)
  if (!Number.isFinite(totalMs) || totalMs < 0) return t('common.na')

  const totalSeconds = Math.floor(totalMs / 1000)
  if (totalSeconds < 60) return `${totalSeconds}s`

  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60

  if (hours > 0) {
    return `${hours}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`
  }

  return `${minutes}m ${String(seconds).padStart(2, '0')}s`
}

function runTranslationElapsed(run) {
  const human = String(run?.translation?.elapsed_human || '').trim()
  if (human !== '') return human

  return formatDurationFromMs(run?.translation?.elapsed_ms)
}

const {
  attachVisibilityListener,
  detachVisibilityListener,
  loadTranslationHealth,
  startTranslationPoll,
  stopTranslationPoll,
  translationHealth,
  translationProgressLabel,
  translationProgressPercent,
  translationQueueTotal,
} = useEventSourcesTranslationQuality({
  t,
  toast,
  formatDate,
  beginOperation,
  endOperation,
  setError: (value) => {
    error.value = value
  },
  onRefreshAfterRepair: () => load({ trackProgress: false }),
})

const activeCrawlRunsCount = computed(() =>
  recentRuns.value.filter((run) => {
    const status = String(run?.status || '').toLowerCase()
    return status === 'running' || status === 'processing' || status === 'queued' || status === 'pending'
  }).length,
)
const shouldPollRecentRuns = computed(() =>
  runningSelected.value || translationQueueTotal.value > 0 || activeCrawlRunsCount.value > 0,
)

const translationProgressElapsedLabel = computed(() =>
  t('progress.elapsed', { value: formatDurationFromMs(translationElapsedNowMs.value) }),
)

function resolveTranslationTimerStartMs() {
  const activeRunStartedAt = recentRuns.value
    .filter((run) => runTranslation(run).pending > 0)
    .map((run) => Date.parse(String(run?.started_at || '')))
    .filter((value) => Number.isFinite(value))
    .sort((a, b) => a - b)[0]

  if (Number.isFinite(activeRunStartedAt)) {
    return Number(activeRunStartedAt)
  }

  const lastDoneAt = Date.parse(String(translationHealth.value?.last_done?.at || ''))
  if (Number.isFinite(lastDoneAt)) {
    return Number(lastDoneAt)
  }

  return Date.now()
}

function syncTranslationElapsed() {
  if (translationProgressStartedAtMs.value === null) {
    translationElapsedNowMs.value = 0
    return
  }

  translationElapsedNowMs.value = Math.max(0, Date.now() - translationProgressStartedAtMs.value)
}

function stopTranslationElapsedTimer(resetState = true) {
  if (translationElapsedTimerId !== null) {
    window.clearInterval(translationElapsedTimerId)
    translationElapsedTimerId = null
  }

  if (resetState) {
    translationProgressStartedAtMs.value = null
    translationElapsedNowMs.value = 0
  }
}

function ensureTranslationElapsedTimer() {
  if (translationQueueTotal.value <= 0) {
    stopTranslationElapsedTimer(true)
    return
  }

  if (translationProgressStartedAtMs.value === null) {
    translationProgressStartedAtMs.value = resolveTranslationTimerStartMs()
  }

  syncTranslationElapsed()

  if (translationElapsedTimerId !== null) {
    return
  }

  translationElapsedTimerId = window.setInterval(() => {
    syncTranslationElapsed()
  }, 1000)
}

watch(
  () => translationQueueTotal.value,
  (nextValue, prevValue) => {
    ensureTranslationElapsedTimer()
    if (nextValue <= 0 && prevValue > 0) {
      void refreshRecentRuns()
    }
  },
  { immediate: true },
)

watch(
  () => shouldPollRecentRuns.value,
  (shouldPoll, wasPolling) => {
    if (shouldPoll) {
      startRecentRunsPoll()
      return
    }

    stopRecentRunsPoll()
    if (wasPolling) {
      void refreshRecentRuns()
    }
  },
  { immediate: true },
)

watch(
  () => recentRuns.value,
  () => {
    if (translationQueueTotal.value <= 0) {
      return
    }

    if (translationProgressStartedAtMs.value === null) {
      translationProgressStartedAtMs.value = resolveTranslationTimerStartMs()
    }
    syncTranslationElapsed()
  },
  { deep: true },
)

watch(
  () => translationHealth.value?.last_done?.at ?? null,
  () => {
    if (translationQueueTotal.value > 0 && translationProgressStartedAtMs.value === null) {
      translationProgressStartedAtMs.value = resolveTranslationTimerStartMs()
    }
    syncTranslationElapsed()
  },
)

function findLatestRunForSource(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  return latestRunBySourceKey.value[key] || null
}

function applyRunList(runList, syncYear = false) {
  recentRuns.value = runList

  const latestByKey = {}
  for (const run of runList) {
    const key = normalizeSourceKey(run?.source_name)
    if (key === '' || latestByKey[key]) continue
    latestByKey[key] = run
  }
  latestRunBySourceKey.value = latestByKey

  if (syncYear && !yearTouched.value) {
    const latestYear = Number(runList[0]?.year)
    year.value = Number.isFinite(latestYear) && latestYear >= 2000 ? latestYear : new Date().getFullYear()
  }
}

async function refreshRecentRuns() {
  if (recentRunsRefreshing.value) return
  recentRunsRefreshing.value = true

  try {
    const runsRes = await getCrawlRuns({ per_page: 10 })
    const runList = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
    applyRunList(runList, false)
  } catch {
    // Keep UI stable on transient poll failure; main load handles visible errors.
  } finally {
    recentRunsRefreshing.value = false
  }
}

function startRecentRunsPoll() {
  if (recentRunsPollId !== null) return
  recentRunsPollId = window.setInterval(() => {
    void refreshRecentRuns()
  }, 5000)
}

function stopRecentRunsPoll() {
  if (recentRunsPollId === null) return
  window.clearInterval(recentRunsPollId)
  recentRunsPollId = null
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

function toggleSelectAll() {
  if (runningSelected.value) return
  if (isAllSelected.value) {
    selectedKeys.value = []
  } else {
    selectedKeys.value = [...allSelectableKeys.value]
  }
}

function setProgressState({
  mode = 'indeterminate',
  label = '',
  current = 0,
  total = 0,
  detail = '',
  failedCount = 0,
  currentSourceKey = '',
} = {}) {
  const resolvedCurrent = Math.max(0, Number(current) || 0)
  const resolvedTotal = Math.max(0, Number(total) || 0)
  progressMode.value = mode === 'determinate' && resolvedTotal > 0 ? 'determinate' : 'indeterminate'
  progressLabelOverride.value = String(label || '')
  progressCurrent.value = resolvedCurrent
  progressTotal.value = resolvedTotal
  progressDetail.value = String(detail || '')
  progressFailedCount.value = Math.max(0, Number(failedCount) || 0)
  progressCurrentSourceKey.value = normalizeSourceKey(currentSourceKey)
}

function resetProgressState() {
  setProgressState()
}

function beginOperation(options = {}) {
  activeOps.value += 1
  setProgressState(options)
}

function endOperation() {
  activeOps.value = Math.max(0, activeOps.value - 1)
  if (activeOps.value > 0) return
  resetProgressState()
}

async function load(options = {}) {
  const { trackProgress = true } = options
  if (trackProgress) {
    beginOperation({
      mode: 'determinate',
      label: t('progress.loading'),
      current: 0,
      total: 2,
      detail: 'Kroky: 0/2',
    })
  }

  loading.value = true
  error.value = ''
  let loadStepsDone = 0
  const markLoadStepDone = () => {
    if (!trackProgress) return
    loadStepsDone += 1
    setProgressState({
      mode: 'determinate',
      label: t('progress.loading'),
      current: loadStepsDone,
      total: 2,
      detail: `Kroky: ${Math.min(loadStepsDone, 2)}/2`,
      failedCount: progressFailedCount.value,
      currentSourceKey: progressCurrentSourceKey.value,
    })
  }

  try {
    const sourcesPromise = getEventSources().then((response) => {
      markLoadStepDone()
      return response
    })
    const runsPromise = getCrawlRuns({ per_page: 10 }).then((response) => {
      markLoadStepDone()
      return response
    })

    const [sourcesRes, runsRes] = await Promise.all([sourcesPromise, runsPromise])

    const sourceList = Array.isArray(sourcesRes?.data?.data) ? sourcesRes.data.data : []
    sources.value = sourceList

    const runList = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
    applyRunList(runList, true)
    clampRunYear()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.loadError')
  } finally {
    loading.value = false
    if (trackProgress) {
      endOperation()
    }
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

  const sourceKeys = [...supportedSelectedKeys.value]
  if (sourceKeys.length === 0) return

  runningSelected.value = true
  const totalSteps = sourceKeys.length + 1
  beginOperation({
    mode: 'determinate',
    label: t('progress.crawlingSelected'),
    current: 0,
    total: totalSteps,
    detail: `Zdroje: 0/${sourceKeys.length}`,
  })
  error.value = ''
  let completedSources = 0
  let failedSources = 0
  let firstFailureMessage = ''

  try {
    for (const sourceKey of sourceKeys) {
      runningByKey.value = {
        ...runningByKey.value,
        [sourceKey]: true,
      }

      setProgressState({
        mode: 'determinate',
        label: t('progress.crawlingSelected'),
        current: completedSources,
        total: totalSteps,
        detail: `Zdroje: ${completedSources}/${sourceKeys.length}`,
        failedCount: failedSources,
        currentSourceKey: sourceKey,
      })

      try {
        const response = await runEventSourceCrawl({
          source_keys: [sourceKey],
          year: Number(year.value),
        })

        const result = Array.isArray(response?.data?.results) ? response.data.results[0] : null
        const status = String(result?.status || '').toLowerCase()
        if (status !== 'success' && status !== 'ok') {
          failedSources += 1
          if (!firstFailureMessage) {
            firstFailureMessage = String(result?.message || t('messages.runSingleError'))
          }
        }
      } catch (fetchError) {
        failedSources += 1
        if (!firstFailureMessage) {
          firstFailureMessage = fetchError?.response?.data?.message || fetchError?.userMessage || t('messages.runSingleError')
        }
      } finally {
        runningByKey.value = {
          ...runningByKey.value,
          [sourceKey]: false,
        }
        completedSources += 1
        setProgressState({
          mode: 'determinate',
          label: t('progress.crawlingSelected'),
          current: completedSources,
          total: totalSteps,
          detail: `Zdroje: ${completedSources}/${sourceKeys.length}`,
          failedCount: failedSources,
          currentSourceKey: completedSources < sourceKeys.length ? sourceKeys[completedSources] : '',
        })
      }
    }

    await load({ trackProgress: false })
    setProgressState({
      mode: 'determinate',
      label: t('progress.crawlingSelected'),
      current: totalSteps,
      total: totalSteps,
      detail: 'Data: 1/1',
      failedCount: failedSources,
      currentSourceKey: '',
    })

    if (failedSources === 0) {
      toast.success(t('messages.runSelectedSuccess'))
    } else {
      const successfulSources = Math.max(0, sourceKeys.length - failedSources)
      toast.warn(`Crawling hotový: ${successfulSources}/${sourceKeys.length} zdrojov úspešne, chyby: ${failedSources}.`)
      if (firstFailureMessage) {
        error.value = firstFailureMessage
      }
    }
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
  const totalSteps = 2
  beginOperation({
    mode: 'determinate',
    label: t('progress.purging'),
    current: 0,
    total: totalSteps,
    detail: 'Kroky: 0/2',
  })
  error.value = ''

  try {
    const response = await purgeEventSources({
      source_keys: purgeTargetKeys.value,
      dry_run: Boolean(purgeDryRun.value),
      confirm: purgeConfirmToken,
    })
    setProgressState({
      mode: 'determinate',
      label: t('progress.purging'),
      current: 1,
      total: totalSteps,
      detail: 'Kroky: 1/2',
    })

    const deleted = response?.data?.deleted || {}
    const events = Number(deleted.events || 0)
    const preservedEvents = Number(deleted.events_preserved || 0)
    const candidates = Number(deleted.event_candidates || 0)
    const runs = Number(deleted.crawl_runs || 0)
    const key = purgeDryRun.value ? 'messages.purgeDryRunDone' : 'messages.purgeDone'
    toast.success(t(key, { events, preservedEvents, candidates, runs }))

    await load({ trackProgress: false })
    setProgressState({
      mode: 'determinate',
      label: t('progress.purging'),
      current: totalSteps,
      total: totalSteps,
      detail: 'Kroky: 2/2',
    })
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

  const totalSteps = 2
  beginOperation({
    mode: 'determinate',
    label: t('progress.crawlingSelected'),
    current: 0,
    total: totalSteps,
    detail: 'Kroky: 0/2',
    currentSourceKey: key,
  })
  error.value = ''

  try {
    const response = await runEventSourceCrawl({
      source_keys: [key],
      year: Number(year.value),
    })
    const result = Array.isArray(response?.data?.results) ? response.data.results[0] : null
    const status = String(result?.status || '').toLowerCase()
    const failedCount = status !== 'success' && status !== 'ok' ? 1 : 0
    setProgressState({
      mode: 'determinate',
      label: t('progress.crawlingSelected'),
      current: 1,
      total: totalSteps,
      detail: 'Kroky: 1/2',
      failedCount,
      currentSourceKey: key,
    })

    if (failedCount > 0) {
      error.value = String(result?.message || t('messages.runSingleError'))
      toast.warn(`Crawling pre ${sourceLabel(key)} skoncil s chybou.`)
    } else {
      toast.success(t('messages.runSingleSuccess', { source: sourceLabel(key) }))
    }
    await load({ trackProgress: false })
    setProgressState({
      mode: 'determinate',
      label: t('progress.crawlingSelected'),
      current: totalSteps,
      total: totalSteps,
      detail: 'Kroky: 2/2',
      failedCount,
      currentSourceKey: '',
    })
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

function syncMobileViewport() {
  if (typeof window === 'undefined') return
  const hostWidth = Number(layoutHost.value?.clientWidth || 0)
  const referenceWidth = hostWidth > 0 ? hostWidth : window.innerWidth
  isMobileViewport.value = referenceWidth <= mobileViewportBreakpoint
}

onMounted(async () => {
  await nextTick()
  syncMobileViewport()
  window.addEventListener('resize', syncMobileViewport, { passive: true })
  if (typeof ResizeObserver !== 'undefined') {
    layoutResizeObserver = new ResizeObserver(() => syncMobileViewport())
    if (layoutHost.value) {
      layoutResizeObserver.observe(layoutHost.value)
    }
  }
  await Promise.all([load(), loadTranslationHealth()])
  attachVisibilityListener()
  startTranslationPoll()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncMobileViewport)
  stopRecentRunsPoll()
  if (layoutResizeObserver) {
    layoutResizeObserver.disconnect()
    layoutResizeObserver = null
  }
  stopTranslationPoll()
  detachVisibilityListener()
  stopTranslationElapsedTimer(true)
})
</script>

<template src="./eventSources/EventSourcesView.template.html"></template>

<style scoped src="./eventSources/EventSourcesView.css"></style>
