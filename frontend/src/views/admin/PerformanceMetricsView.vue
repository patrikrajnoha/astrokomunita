<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import { getMetrics, runMetrics } from '@/services/performance'
import { useToast } from '@/composables/useToast'
import { createDictionaryTranslator } from '@/i18n/dictionary'
import { performanceMetricsMessages } from '@/i18n/adminPerformanceMetrics.messages'

const toast = useToast()
const { t, locale } = createDictionaryTranslator(performanceMetricsMessages, 'sk')

const SAMPLE_MIN = 1
const SAMPLE_MAX = 500
const DEFAULT_SAMPLE_SIZE = 200

const loading = ref(false)
const running = ref(false)
const errorMessage = ref('')
const metrics = ref({ logs: [], last_run_per_key: [], trend: [] })
const selectedLog = ref(null)
const lastRunResult = ref(null)
const fieldErrors = ref({})
const confirmLoadImpact = ref(false)
const tableLimit = ref(10)
const sortBy = ref('created_at')
const sortDirection = ref('desc')

const form = ref({
  run: 'all',
  sample_size: DEFAULT_SAMPLE_SIZE,
  bot_source: 'nasa_rss_breaking',
  mode: 'normal',
})

const localeTag = computed(() => {
  if (!locale) return 'sk-SK'
  return String(locale).toLowerCase() === 'sk' ? 'sk-SK' : locale
})

const botSources = [
  { value: 'nasa_rss_breaking', label: 'nasa_rss_breaking' },
]

const runOptions = computed(() => [
  { value: 'all', label: t('form.runType.options.all') },
  { value: 'events_list', label: t('form.runType.options.events_list') },
  { value: 'canonical', label: t('form.runType.options.canonical') },
  { value: 'bot', label: t('form.runType.options.bot') },
])

const modeOptions = computed(() => [
  { value: 'normal', label: t('form.mode.options.normal') },
  { value: 'no_cache', label: t('form.mode.options.no_cache') },
])

const sortByOptions = computed(() => [
  { value: 'created_at', label: t('form.sortBy.options.created_at') },
  { value: 'avg_ms', label: t('form.sortBy.options.avg_ms') },
  { value: 'p95_ms', label: t('form.sortBy.options.p95_ms') },
])

const sortDirectionOptions = computed(() => [
  { value: 'desc', label: t('form.sortDirection.desc') },
  { value: 'asc', label: t('form.sortDirection.asc') },
])

const limitOptions = [10, 25, 50]

const trendPointsByKey = computed(() => {
  const map = new Map()
  const trendList = Array.isArray(metrics.value?.trend) ? metrics.value.trend : []
  for (const item of trendList) {
    map.set(item.key, item.points || [])
  }
  return map
})

const allLogs = computed(() => (Array.isArray(metrics.value?.logs) ? metrics.value.logs : []))

const sortedLogs = computed(() => {
  const rows = [...allLogs.value]
  const field = sortBy.value
  const direction = sortDirection.value === 'asc' ? 1 : -1

  return rows.sort((a, b) => {
    const left = normalizeSortValue(a, field)
    const right = normalizeSortValue(b, field)

    if (left === right) return 0
    return left > right ? direction : -direction
  })
})

const visibleLogs = computed(() => {
  const limit = Number(tableLimit.value)
  if (!Number.isFinite(limit) || limit <= 0) return sortedLogs.value
  return sortedLogs.value.slice(0, limit)
})

const resultsSummary = computed(() => {
  const total = allLogs.value.length
  if (total <= 0) return t('cards.resultsMetaEmpty')

  const newest = sortedLogs.value[0]
  const latest = newest?.created_at ? formatDate(newest.created_at) : t('common.na')
  return t('cards.resultsMeta', { count: total, latest })
})

const showBotSource = computed(() => form.value.run === 'bot' || form.value.run === 'all')

const safeSampleSize = computed(() => {
  const parsed = Number(form.value.sample_size)
  if (!Number.isFinite(parsed)) return DEFAULT_SAMPLE_SIZE
  return Math.max(SAMPLE_MIN, Math.round(parsed))
})

const runButtonLabel = computed(() => {
  if (running.value) return t('actions.running')
  return t('actions.runBenchmark', {
    count: safeSampleSize.value,
  })
})

const runButtonDisabled = computed(() => running.value || !confirmLoadImpact.value)

const selectedLogDetail = computed(() => {
  if (!selectedLog.value) return null
  const row = selectedLog.value
  const payload = isObjectRecord(row.payload) ? row.payload : {}

  return {
    id: row.id,
    key: row.key || t('common.na'),
    createdAt: formatDate(row.created_at),
    runType: labelForRunType(detectRunType(row)),
    sampleSize: row.sample_size ?? t('common.na'),
    mode: labelForMode(payload.mode),
    botSource: detectBotSource(row),
    avgMs: formatMilliseconds(row.avg_ms),
    p95Ms: formatMilliseconds(row.p95_ms),
    dbQueriesAvg: formatDecimal(row.db_queries_avg),
    payload,
  }
})

const selectedLogPayload = computed(() => {
  if (!selectedLogDetail.value) return '{}'
  return JSON.stringify(selectedLogDetail.value.payload, null, 2)
})

const trendByRowId = computed(() => {
  const map = new Map()
  for (const row of allLogs.value) {
    map.set(row.id, buildTrendState(row))
  }
  return map
})

function formatDate(value) {
  if (!value) return t('common.na')
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return t('common.na')
  return date.toLocaleString(localeTag.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

function formatDecimal(value) {
  if (value === null || value === undefined || value === '') return t('common.na')
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) return t('common.na')
  return new Intl.NumberFormat(localeTag.value, { maximumFractionDigits: 2 }).format(numeric)
}

function formatMilliseconds(value) {
  const formatted = formatDecimal(value)
  if (formatted === t('common.na')) return formatted
  return `${formatted} ${t('units.ms')}`
}

function normalizeSortValue(row, field) {
  if (field === 'created_at') {
    const timestamp = new Date(row?.created_at || '').getTime()
    return Number.isFinite(timestamp) ? timestamp : 0
  }

  const numeric = Number(row?.[field])
  return Number.isFinite(numeric) ? numeric : 0
}

function validateForm() {
  const errors = {}
  const sample = Number(form.value.sample_size)

  if (!Number.isFinite(sample) || sample < SAMPLE_MIN || sample > SAMPLE_MAX) {
    errors.sample_size = t('validation.sampleRange', {
      min: SAMPLE_MIN,
      max: SAMPLE_MAX,
    })
  }

  if (showBotSource.value && !String(form.value.bot_source || '').trim()) {
    errors.bot_source = t('validation.botSourceRequired')
  }

  fieldErrors.value = errors
  return Object.keys(errors).length === 0
}

function applyValidationErrorsFromApi(fetchError) {
  const status = fetchError?.response?.status
  if (status !== 422) return false

  const errors = fetchError?.response?.data?.errors
  if (!errors || typeof errors !== 'object') return false

  const merged = { ...fieldErrors.value }
  for (const [field] of Object.entries(errors)) {
    if (field === 'sample_size') {
      merged.sample_size = t('validation.sampleRange', {
        min: SAMPLE_MIN,
        max: SAMPLE_MAX,
      })
    } else if (field === 'bot_source') {
      merged.bot_source = t('validation.botSourceRequired')
    } else {
      merged[field] = t('validation.invalidValue')
    }
  }

  fieldErrors.value = merged
  return true
}

function isTimeoutError(fetchError) {
  if (!fetchError) return false
  if (fetchError.code === 'ECONNABORTED') return true
  const joinedMessage = `${fetchError.message || ''} ${fetchError?.response?.data?.message || ''}`
  return /timeout/i.test(joinedMessage)
}

function resolveRunErrorMessage(fetchError) {
  if (isTimeoutError(fetchError)) return t('messages.timeout')
  if (fetchError?.response?.status === 409) return t('messages.alreadyRunning')
  if (fetchError?.response?.status === 422) return t('messages.validationFailed')
  return t('messages.runFailed')
}

function detectRunType(row) {
  const key = String(row?.key || '')
  if (key.startsWith('events_list_')) return 'events_list'
  if (key.startsWith('canonical_publish_')) return 'canonical'
  if (key.startsWith('bot_run_')) return 'bot'
  return 'all'
}

function detectBotSource(row) {
  const payload = isObjectRecord(row?.payload) ? row.payload : {}
  const payloadSource = String(payload.sourceKey || payload.bot_source || '').trim()
  if (payloadSource) return payloadSource

  const key = String(row?.key || '')
  if (key.startsWith('bot_run_')) {
    const fromKey = key.slice('bot_run_'.length)
    return fromKey || t('common.na')
  }

  return t('common.na')
}

function labelForRunType(value) {
  const key = `form.runType.options.${value}`
  const resolved = t(key)
  return resolved === key ? t('detail.values.unknown') : resolved
}

function labelForMode(value) {
  const modeKey = String(value || '').trim()
  if (!modeKey) return t('detail.values.unknown')
  const lookup = `form.mode.options.${modeKey}`
  const resolved = t(lookup)
  return resolved === lookup ? modeKey : resolved
}

function isObjectRecord(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

function buildTrendState(row) {
  const points = trendPointsByKey.value.get(row?.key) || []
  if (!Array.isArray(points) || points.length < 2) {
    return {
      symbol: '-',
      tone: 'neutral',
      text: t('trend.noData'),
    }
  }

  const rowId = Number(row?.id)
  const rowAvg = Number(row?.avg_ms)
  const currentAvg = Number.isFinite(rowAvg) ? rowAvg : null
  if (currentAvg === null) {
    return {
      symbol: '-',
      tone: 'neutral',
      text: t('trend.noData'),
    }
  }

  let previousAvg = null
  const rowIndex = points.findIndex((point) => Number(point?.id) === rowId)
  if (rowIndex > 0) {
    const previous = Number(points[rowIndex - 1]?.avg_ms)
    if (Number.isFinite(previous)) previousAvg = previous
  } else {
    const latest = points[points.length - 1]
    const penultimate = points[points.length - 2]
    if (Number(latest?.id) === rowId) {
      const previous = Number(penultimate?.avg_ms)
      if (Number.isFinite(previous)) previousAvg = previous
    }
  }

  if (previousAvg === null) {
    return {
      symbol: '-',
      tone: 'neutral',
      text: t('trend.noData'),
    }
  }

  const delta = Number((currentAvg - previousAvg).toFixed(2))
  if (Math.abs(delta) < 0.01) {
    return {
      symbol: '=',
      tone: 'neutral',
      text: t('trend.stable'),
    }
  }

  if (delta < 0) {
    return {
      symbol: '\u2193',
      tone: 'down',
      text: t('trend.faster', {
        value: Math.abs(delta).toFixed(2),
      }),
    }
  }

  return {
    symbol: '\u2191',
    tone: 'up',
    text: t('trend.slower', {
      value: Math.abs(delta).toFixed(2),
    }),
  }
}

function trendForRow(row) {
  return trendByRowId.value.get(row?.id) || {
    symbol: '-',
    tone: 'neutral',
    text: t('trend.noData'),
  }
}

function openDetail(row) {
  selectedLog.value = row
}

function closeDetail(isOpen) {
  if (isOpen) return
  selectedLog.value = null
}

async function loadMetrics() {
  loading.value = true
  errorMessage.value = ''

  try {
    metrics.value = await getMetrics()
  } catch (e) {
    errorMessage.value = isTimeoutError(e) ? t('messages.timeout') : t('messages.loadFailed')
  } finally {
    loading.value = false
  }
}

async function runBenchmark() {
  if (running.value) return
  if (!validateForm()) {
    toast.error(t('messages.validationFailed'))
    return
  }

  running.value = true

  try {
    const payload = {
      run: form.value.run,
      sample_size: Number(form.value.sample_size || DEFAULT_SAMPLE_SIZE),
      mode: form.value.mode,
      bot_source: form.value.bot_source,
    }

    lastRunResult.value = await runMetrics(payload)
    toast.success(t('messages.runSuccess'))
    await loadMetrics()
  } catch (e) {
    applyValidationErrorsFromApi(e)
    toast.error(resolveRunErrorMessage(e))
  } finally {
    running.value = false
  }
}

onMounted(() => {
  loadMetrics()
})
</script>

<template src="./performanceMetrics/PerformanceMetricsView.template.html"></template>

<style scoped src="./performanceMetrics/PerformanceMetricsView.css"></style>
