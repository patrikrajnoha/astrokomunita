<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getMetrics, runMetrics } from '@/services/performance'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const loading = ref(false)
const running = ref(false)
const error = ref('')
const metrics = ref({ logs: [], last_run_per_key: [], trend: [] })
const selectedLog = ref(null)
const lastRunResult = ref(null)

const form = ref({
  run: 'all',
  sample_size: 200,
  bot_source: 'nasa_rss_breaking',
  mode: 'normal',
})

const runOptions = [
  { value: 'all', label: 'All benchmarks' },
  { value: 'events_list', label: 'Events list' },
  { value: 'canonical', label: 'Canonical + publish' },
  { value: 'bot', label: 'Bot import' },
]

const botSources = [
  { value: 'nasa_rss_breaking', label: 'nasa_rss_breaking' },
]

const trendMap = computed(() => {
  const map = new Map()
  const trendList = Array.isArray(metrics.value?.trend) ? metrics.value.trend : []
  for (const item of trendList) {
    map.set(item.key, item.points || [])
  }
  return map
})

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString()
}

function formatNumber(value) {
  if (value === null || value === undefined || value === '') return '-'
  return new Intl.NumberFormat('sk-SK', { maximumFractionDigits: 2 }).format(Number(value))
}

function miniBars(points) {
  if (!Array.isArray(points) || points.length === 0) return []
  const values = points.map((point) => Number(point.avg_ms || 0))
  const max = Math.max(...values, 1)
  return values.map((value) => ({ value, height: Math.max(8, Math.round((value / max) * 40)) }))
}

async function loadMetrics() {
  loading.value = true
  error.value = ''

  try {
    metrics.value = await getMetrics()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load performance metrics.'
  } finally {
    loading.value = false
  }
}

async function runBenchmark() {
  if (running.value) return
  running.value = true

  try {
    const payload = {
      run: form.value.run,
      sample_size: Number(form.value.sample_size || 200),
      mode: form.value.mode,
      bot_source: form.value.bot_source,
    }
    lastRunResult.value = await runMetrics(payload)
    toast.success('Benchmark finished.')
    await loadMetrics()
  } catch (e) {
    const message = e?.response?.data?.message || 'Benchmark failed.'
    toast.error(message)
  } finally {
    running.value = false
  }
}

onMounted(() => {
  loadMetrics()
})
</script>

<template>
  <AdminPageShell title="Performance Metrics" subtitle="Server-side benchmark panel for staging/dev verification.">
    <section class="warningCard">
      <strong>Warning:</strong> Benchmark runs are intended for staging/dev and can temporarily increase load.
    </section>

    <section class="runCard">
      <div class="runGrid">
        <label>
          Run type
          <select v-model="form.run" data-testid="run-type">
            <option v-for="option in runOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </label>

        <label>
          Sample size
          <input v-model.number="form.sample_size" type="number" min="1" max="500" data-testid="sample-size" />
        </label>

        <label>
          Mode
          <select v-model="form.mode" data-testid="mode-select">
            <option value="normal">normal</option>
            <option value="no_cache">no_cache</option>
          </select>
        </label>

        <label v-if="form.run === 'bot' || form.run === 'all'">
          Bot source
          <select v-model="form.bot_source" data-testid="bot-source">
            <option v-for="source in botSources" :key="source.value" :value="source.value">
              {{ source.label }}
            </option>
          </select>
        </label>
      </div>

      <button class="runBtn" :disabled="running" data-testid="run-benchmark-btn" @click="runBenchmark">
        {{ running ? 'Running...' : 'Run benchmark (200 requests)' }}
      </button>

      <pre v-if="lastRunResult" class="resultBox">{{ JSON.stringify(lastRunResult, null, 2) }}</pre>
    </section>

    <section v-if="error" class="errorCard">
      {{ error }}
    </section>

    <section class="tableCard">
      <h3>Latest results</h3>

      <div v-if="loading" class="muted">Loading...</div>
      <table v-else class="resultsTable">
        <thead>
          <tr>
            <th>Key</th>
            <th>Created</th>
            <th>Avg ms</th>
            <th>P95 ms</th>
            <th>DB queries avg</th>
            <th>Trend</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in metrics.logs || []"
            :key="row.id"
            class="clickable"
            @click="selectedLog = row"
          >
            <td>{{ row.key }}</td>
            <td>{{ formatDate(row.created_at) }}</td>
            <td>{{ formatNumber(row.avg_ms) }}</td>
            <td>{{ formatNumber(row.p95_ms) }}</td>
            <td>{{ formatNumber(row.db_queries_avg) }}</td>
            <td>
              <div class="miniChart">
                <span
                  v-for="(bar, idx) in miniBars(trendMap.get(row.key))"
                  :key="`${row.key}-${idx}`"
                  class="miniBar"
                  :style="{ height: `${bar.height}px` }"
                ></span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="selectedLog" class="detailCard">
      <h3>Payload detail: {{ selectedLog.key }} #{{ selectedLog.id }}</h3>
      <pre>{{ JSON.stringify(selectedLog.payload || {}, null, 2) }}</pre>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.warningCard,
.runCard,
.tableCard,
.detailCard,
.errorCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 12px;
  padding: 12px;
  background: rgb(var(--color-bg-rgb) / 0.55);
}

.warningCard {
  color: #92400e;
  border-color: rgb(217 119 6 / 0.35);
  background: rgb(245 158 11 / 0.12);
}

.errorCard {
  color: var(--color-danger);
}

.runGrid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

label {
  display: grid;
  gap: 6px;
  font-size: 13px;
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

.runBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.resultBox {
  margin-top: 10px;
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

.clickable {
  cursor: pointer;
}

.clickable:hover {
  background: rgb(var(--color-surface-rgb) / 0.08);
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

pre {
  white-space: pre-wrap;
  word-break: break-word;
}

@media (max-width: 980px) {
  .runGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .runGrid {
    grid-template-columns: 1fr;
  }
}
</style>

