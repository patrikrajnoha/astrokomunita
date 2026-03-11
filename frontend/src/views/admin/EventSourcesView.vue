<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
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
  runCounters,
  runStatusLabel as formatRunStatusLabel,
  runStatusTone,
  runTranslation,
  runTranslationModeLabel as formatRunTranslationModeLabel,
  runTranslationQualityLabel as formatRunTranslationQualityLabel,
  runTranslationQualityTone,
  sourceLabel as formatSourceLabel,
  sourceStatusLabel as formatSourceStatusLabel,
  sourceStatusTone,
  sourceToneClass,
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
const progressValue = ref(0)
let progressIntervalId = null

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

const sourceLabel = (sourceKey) => formatSourceLabel(sourceKey, t)
const sourceStatusLabel = (source) => formatSourceStatusLabel(source, t)
const runTranslationModeLabel = (run) => formatRunTranslationModeLabel(run, t)
const runTranslationQualityLabel = (run) => formatRunTranslationQualityLabel(run, t)
const runStatusLabel = (run) => formatRunStatusLabel(run, t)

function formatDate(value) {
  if (!value) return t('common.na')
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return t('common.na')
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

const {
  artifactsCheckedAtLabel,
  artifactsLoading,
  artifactsRepairLimit,
  artifactsRepairing,
  artifactsReportTone,
  artifactsSampleLimit,
  artifactsSamples,
  artifactsSuspiciousCount,
  attachVisibilityListener,
  canRepairArtifacts,
  detachVisibilityListener,
  loadTranslationArtifactsReport,
  loadTranslationHealth,
  runTranslationArtifactsRepair,
  startTranslationPoll,
  stopTranslationPoll,
  translationCounts,
  translationHealthLoading,
  translationIsActive,
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
  onRefreshAfterRepair: () => load(),
})

function findLatestRunForSource(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  return latestRunBySourceKey.value[key] || null
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
  attachVisibilityListener()
  startTranslationPoll()
})

onUnmounted(() => {
  stopTranslationPoll()
  detachVisibilityListener()
})
</script>

<template src="./eventSources/EventSourcesView.template.html"></template>

<style scoped src="./eventSources/EventSourcesView.css"></style>
