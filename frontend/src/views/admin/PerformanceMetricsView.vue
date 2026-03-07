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

function miniBars(points) {
  if (!Array.isArray(points) || points.length === 0) return []
  const values = points.map((point) => {
    const numeric = Number(point.avg_ms)
    return Number.isFinite(numeric) ? numeric : 0
  })
  const max = Math.max(...values, 1)
  return values.map((value) => ({ value, height: Math.max(8, Math.round((value / max) * 40)) }))
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

<template>
  <AdminPageShell :title="t('page.title')" :subtitle="t('page.subtitle')">
    <section class="warningCard">
      <strong>{{ t('cards.warningTitle') }}</strong>
      <span>{{ t('cards.warningText') }}</span>
    </section>

    <section class="runCard">
      <header class="cardHeader">
        <h2>{{ t('cards.runTitle') }}</h2>
        <p>{{ t('cards.runDescription') }}</p>
      </header>

      <div class="runGrid">
        <label class="field">
          <span class="fieldLabel">{{ t('form.runType.label') }}</span>
          <select v-model="form.run" data-testid="run-type">
            <option v-for="option in runOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
          <span class="fieldHint">{{ t('form.runType.help') }}</span>
        </label>

        <label class="field">
          <span class="fieldLabel">{{ t('form.sampleSize.label') }}</span>
          <input
            v-model.number="form.sample_size"
            type="number"
            :min="SAMPLE_MIN"
            :max="SAMPLE_MAX"
            :placeholder="t('form.sampleSize.placeholder')"
            data-testid="sample-size"
          />
          <span class="fieldHint">{{ t('form.sampleSize.help') }}</span>
          <span v-if="fieldErrors.sample_size" class="fieldError">{{ fieldErrors.sample_size }}</span>
        </label>

        <label class="field">
          <span class="fieldLabel">{{ t('form.mode.label') }}</span>
          <select v-model="form.mode" data-testid="mode-select">
            <option v-for="option in modeOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
          <span class="fieldHint">{{ t('form.mode.help') }}</span>
        </label>

        <label v-if="showBotSource" class="field">
          <span class="fieldLabel">{{ t('form.botSource.label') }}</span>
          <select v-model="form.bot_source" data-testid="bot-source">
            <option v-for="source in botSources" :key="source.value" :value="source.value">
              {{ source.label }}
            </option>
          </select>
          <span class="fieldHint">{{ t('form.botSource.help') }}</span>
          <span v-if="fieldErrors.bot_source" class="fieldError">{{ fieldErrors.bot_source }}</span>
        </label>

        <label class="field field--checkbox">
          <span class="checkboxRow">
            <input v-model="confirmLoadImpact" type="checkbox" data-testid="confirm-load-checkbox" />
            <span>{{ t('form.confirmLoad.label') }}</span>
          </span>
          <span class="fieldHint">{{ t('form.confirmLoad.help') }}</span>
        </label>
      </div>

      <button class="runBtn" :disabled="runButtonDisabled" data-testid="run-benchmark-btn" @click="runBenchmark">
        <span class="btnContent">
          <span v-if="running" class="inlineSpinner" aria-hidden="true"></span>
          <span>{{ runButtonLabel }}</span>
        </span>
      </button>

      <p v-if="running" class="progressText" data-testid="run-progress">{{ t('form.progress') }}</p>

      <section v-if="lastRunResult" class="resultCard">
        <strong>{{ t('messages.resultStored') }}</strong>
        <p>{{ t('messages.resultHint') }}</p>
        <details>
          <summary>{{ t('actions.showRawResult') }}</summary>
          <pre class="resultBox">{{ JSON.stringify(lastRunResult, null, 2) }}</pre>
        </details>
      </section>
    </section>

    <section v-if="errorMessage" class="errorCard">
      {{ errorMessage }}
    </section>

    <section class="tableCard">
      <header class="cardHeader cardHeader--withToolbar">
        <div>
          <h2>{{ t('cards.resultsTitle') }}</h2>
          <p>{{ t('cards.resultsDescription') }}</p>
        </div>
        <div class="tableToolbar">
          <label class="toolbarField">
            <span>{{ t('form.limit.label') }}</span>
            <select v-model.number="tableLimit" data-testid="results-limit">
              <option v-for="option in limitOptions" :key="option" :value="option">{{ option }}</option>
            </select>
          </label>

          <label class="toolbarField">
            <span>{{ t('form.sortBy.label') }}</span>
            <select v-model="sortBy" data-testid="results-sort-by">
              <option v-for="option in sortByOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="toolbarField">
            <span>{{ t('form.sortDirection.label') }}</span>
            <select v-model="sortDirection" data-testid="results-sort-direction">
              <option v-for="option in sortDirectionOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>
        </div>
      </header>

      <p class="tableHint">{{ t('table.rowHint') }}</p>

      <div v-if="loading" class="muted">{{ t('messages.loading') }}</div>
      <table v-else class="resultsTable">
        <thead>
          <tr>
            <th>{{ t('table.columns.key') }}</th>
            <th>{{ t('table.columns.created') }}</th>
            <th class="metricCol">{{ t('table.columns.avg_ms') }}</th>
            <th class="metricCol">
              <span class="headerWithHint">
                <span>{{ t('table.columns.p95_ms') }}</span>
                <span class="hint" :title="t('table.tooltips.p95_ms')" :aria-label="t('table.tooltips.p95_ms')">i</span>
              </span>
            </th>
            <th class="metricCol">
              <span class="headerWithHint">
                <span>{{ t('table.columns.db_queries_avg') }}</span>
                <span class="hint" :title="t('table.tooltips.db_queries_avg')" :aria-label="t('table.tooltips.db_queries_avg')">i</span>
              </span>
            </th>
            <th>{{ t('table.columns.trend') }}</th>
          </tr>
        </thead>
        <tbody v-if="visibleLogs.length">
          <tr
            v-for="row in visibleLogs"
            :key="row.id"
            class="clickable"
            @click="openDetail(row)"
          >
            <td>{{ row.key }}</td>
            <td>{{ formatDate(row.created_at) }}</td>
            <td class="metricCol">{{ formatMilliseconds(row.avg_ms) }}</td>
            <td class="metricCol">{{ formatMilliseconds(row.p95_ms) }}</td>
            <td class="metricCol">{{ formatDecimal(row.db_queries_avg) }}</td>
            <td>
              <div class="trendCell">
                <div class="miniChart">
                  <span
                    v-for="(bar, idx) in miniBars(trendPointsByKey.get(row.key))"
                    :key="`${row.key}-${idx}`"
                    class="miniBar"
                    :style="{ height: `${bar.height}px` }"
                  ></span>
                </div>
                <span
                  class="trendBadge"
                  :class="`trendBadge--${trendForRow(row).tone}`"
                  :title="trendForRow(row).text"
                >
                  {{ trendForRow(row).symbol }}
                </span>
              </div>
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr>
            <td colspan="6" class="emptyState" data-testid="empty-state">
              <strong>{{ t('table.empty.title') }}</strong>
              <span>{{ t('table.empty.description') }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <BaseModal
      :open="Boolean(selectedLogDetail)"
      :title="selectedLogDetail ? t('detail.title', { key: selectedLogDetail.key, id: selectedLogDetail.id }) : t('cards.detailTitle')"
      test-id="performance-log-detail-modal"
      close-test-id="performance-log-detail-close"
      @update:open="closeDetail"
    >
      <template v-if="selectedLogDetail">
        <section class="detailSection">
          <h3>{{ t('detail.sections.parameters') }}</h3>
          <dl class="detailGrid">
            <dt>{{ t('detail.fields.runType') }}</dt>
            <dd>{{ selectedLogDetail.runType }}</dd>

            <dt>{{ t('detail.fields.sampleSize') }}</dt>
            <dd>{{ selectedLogDetail.sampleSize }}</dd>

            <dt>{{ t('detail.fields.mode') }}</dt>
            <dd>{{ selectedLogDetail.mode }}</dd>

            <dt>{{ t('detail.fields.botSource') }}</dt>
            <dd>{{ selectedLogDetail.botSource }}</dd>

            <dt>{{ t('detail.fields.createdAt') }}</dt>
            <dd>{{ selectedLogDetail.createdAt }}</dd>
          </dl>
        </section>

        <section class="detailSection">
          <h3>{{ t('detail.sections.summary') }}</h3>
          <dl class="detailGrid">
            <dt>{{ t('detail.fields.avgMs') }}</dt>
            <dd>{{ selectedLogDetail.avgMs }}</dd>

            <dt>{{ t('detail.fields.p95Ms') }}</dt>
            <dd>{{ selectedLogDetail.p95Ms }}</dd>

            <dt>{{ t('detail.fields.dbQueriesAvg') }}</dt>
            <dd>{{ selectedLogDetail.dbQueriesAvg }}</dd>
          </dl>
        </section>

        <section class="detailSection">
          <details>
            <summary>{{ t('detail.sections.payload') }}</summary>
            <pre class="detailPayload">{{ selectedLogPayload }}</pre>
          </details>
        </section>
      </template>
    </BaseModal>
  </AdminPageShell>
</template>

<style scoped>
.warningCard,
.runCard,
.tableCard,
.errorCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 12px;
  padding: 12px;
  background: rgb(var(--color-bg-rgb) / 0.55);
}

.warningCard {
  display: flex;
  gap: 8px;
  color: var(--color-warning);
  border-color: rgb(217 119 6 / 0.35);
  background: rgb(245 158 11 / 0.12);
}

.errorCard {
  color: var(--color-danger);
}

.cardHeader {
  margin-bottom: 10px;
}

.cardHeader h2 {
  margin: 0;
  font-size: 17px;
}

.cardHeader p {
  margin: 4px 0 0;
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.cardHeader--withToolbar {
  display: flex;
  gap: 12px;
  justify-content: space-between;
  align-items: flex-start;
}

.runGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.field {
  display: grid;
  gap: 6px;
  font-size: 13px;
}

.fieldLabel {
  font-size: 13px;
  font-weight: 600;
}

.fieldHint {
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.fieldError {
  color: var(--color-danger);
  font-size: 12px;
}

.field--checkbox {
  grid-column: 1 / -1;
}

.checkboxRow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}

input,
select {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.25);
  border-radius: 8px;
  min-height: 36px;
  background: rgb(var(--color-bg-rgb) / 0.65);
  color: inherit;
  padding: 0 10px;
}

.runBtn {
  margin-top: 12px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.5);
  border-radius: 10px;
  min-height: 38px;
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: inherit;
  cursor: pointer;
}

.btnContent {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.inlineSpinner {
  width: 14px;
  height: 14px;
  border-radius: 999px;
  border: 2px solid rgb(var(--color-surface-rgb) / 0.5);
  border-top-color: rgb(var(--color-primary-rgb) / 0.95);
  animation: spin 1s linear infinite;
}

.runBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.progressText {
  margin-top: 8px;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.resultCard {
  margin-top: 10px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.28);
  background: rgb(var(--color-primary-rgb) / 0.1);
}

.resultCard p {
  margin: 6px 0 0;
  font-size: 12px;
}

.resultCard details {
  margin-top: 8px;
}

.resultBox {
  max-height: 210px;
  overflow: auto;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 10px;
  font-size: 12px;
}

.resultsTable {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
}

.resultsTable th,
.resultsTable td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  text-align: left;
  padding: 8px;
  vertical-align: middle;
  font-size: 13px;
}

.metricCol {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
}

.headerWithHint {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.hint {
  display: inline-grid;
  place-items: center;
  width: 14px;
  height: 14px;
  border-radius: 999px;
  font-size: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.35);
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.clickable {
  cursor: pointer;
}

.clickable:hover {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.tableHint {
  margin: 0;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.tableToolbar {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.toolbarField {
  display: grid;
  gap: 4px;
  min-width: 130px;
  font-size: 12px;
}

.miniChart {
  display: flex;
  align-items: end;
  gap: 3px;
  min-height: 42px;
}

.miniBar {
  width: 5px;
  border-radius: 3px;
  background: rgb(var(--color-primary-rgb) / 0.7);
}

.trendCell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.trendBadge {
  min-width: 20px;
  min-height: 20px;
  border-radius: 999px;
  display: inline-grid;
  place-items: center;
  font-size: 12px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.7);
}

.trendBadge--up {
  color: rgb(252 165 165);
  border-color: rgb(239 68 68 / 0.45);
}

.trendBadge--down {
  color: rgb(110 231 183);
  border-color: rgb(16 185 129 / 0.45);
}

.trendBadge--neutral {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.emptyState {
  text-align: center !important;
  padding: 18px 12px !important;
}

.emptyState strong {
  display: block;
  margin-bottom: 5px;
}

.emptyState span {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.detailSection + .detailSection {
  margin-top: 12px;
}

.detailSection h3 {
  margin: 0 0 8px;
  font-size: 14px;
}

.detailGrid {
  margin: 0;
  display: grid;
  grid-template-columns: 170px 1fr;
  gap: 6px 10px;
}

.detailGrid dt {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.detailGrid dd {
  margin: 0;
  font-size: 13px;
}

.detailPayload {
  margin: 8px 0 0;
  max-height: 240px;
  overflow: auto;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  padding: 8px;
  font-size: 12px;
}

pre {
  white-space: pre-wrap;
  word-break: break-word;
}

@media (max-width: 980px) {
  .cardHeader--withToolbar {
    flex-direction: column;
  }

  .tableToolbar {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .runGrid {
    grid-template-columns: 1fr;
  }

  .tableToolbar {
    grid-template-columns: 1fr;
  }

  .detailGrid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .trendCell {
    align-items: flex-start;
    flex-direction: column;
  }
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}
</style>
