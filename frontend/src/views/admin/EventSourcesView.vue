<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getCrawlRuns, getEventSources, runEventSourceCrawl, updateEventSource } from '@/services/api/admin/eventSources'

const loading = ref(false)
const running = ref(false)
const error = ref('')
const sources = ref([])
const selectedKeys = ref([])
const year = ref(new Date().getFullYear())
const results = ref([])
const recentRuns = ref([])

const canRun = computed(() => selectedKeys.value.length > 0 && !running.value)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [sourcesRes, runsRes] = await Promise.all([
      getEventSources(),
      getCrawlRuns({ per_page: 10 }),
    ])

    const list = Array.isArray(sourcesRes?.data?.data) ? sourcesRes.data.data : []
    sources.value = list
    selectedKeys.value = list.filter((item) => item.is_enabled).map((item) => item.key)

    recentRuns.value = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to load sources.'
  } finally {
    loading.value = false
  }
}

async function toggleSource(source, checked) {
  try {
    await updateEventSource(source.id, { is_enabled: checked })
    source.is_enabled = checked

    if (!checked) {
      selectedKeys.value = selectedKeys.value.filter((key) => key !== source.key)
      return
    }

    if (!selectedKeys.value.includes(source.key)) {
      selectedKeys.value.push(source.key)
    }
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to update source.'
  }
}

async function runSelected() {
  if (!canRun.value) {
    return
  }

  running.value = true
  error.value = ''

  try {
    const res = await runEventSourceCrawl({
      source_keys: selectedKeys.value,
      year: Number(year.value),
    })

    results.value = Array.isArray(res?.data?.results) ? res.data.results : []
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Manual crawl run failed.'
  } finally {
    running.value = false
  }
}

function formatDate(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(load)
</script>

<template>
  <AdminPageShell title="Event Sources" subtitle="Enable or disable event sources and run manual crawl jobs.">
    <div v-if="error" class="alert">
      {{ error }}
    </div>

    <section class="card">
      <h3>Sources</h3>
      <div v-if="loading" class="muted">Loading sources...</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>Select</th>
            <th>Source</th>
            <th>Key</th>
            <th>Enabled</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="source in sources" :key="source.id">
            <td>
              <input
                :id="`source-select-${source.id}`"
                v-model="selectedKeys"
                :value="source.key"
                type="checkbox"
                :disabled="!source.is_enabled || running"
              />
            </td>
            <td>{{ source.name }}</td>
            <td><code>{{ source.key }}</code></td>
            <td>
              <label :for="`source-enabled-${source.id}`" class="toggleLabel">
                <input
                  :id="`source-enabled-${source.id}`"
                  :checked="source.is_enabled"
                  type="checkbox"
                  :disabled="running"
                  @change="toggleSource(source, $event.target.checked)"
                />
                <span>{{ source.is_enabled ? 'on' : 'off' }}</span>
              </label>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card">
      <h3>Manual Run</h3>
      <div class="runRow">
        <label for="manual-year">Year</label>
        <input id="manual-year" v-model.number="year" type="number" min="2000" max="2100" />
        <button type="button" :disabled="!canRun" @click="runSelected">
          {{ running ? 'Running...' : 'Run selected sources' }}
        </button>
      </div>
      <ul v-if="results.length > 0" class="resultList">
        <li v-for="result in results" :key="`${result.source_key}-${result.crawl_run_id || result.status}`">
          <strong>{{ result.source_key }}</strong>: {{ result.status }}<span v-if="result.message"> ({{ result.message }})</span>
        </li>
      </ul>
    </section>

    <section class="card">
      <h3>Recent Crawl Runs</h3>
      <div v-if="recentRuns.length === 0" class="muted">No runs yet.</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>Source</th>
            <th>Status</th>
            <th>Started</th>
            <th>Created</th>
            <th>Errors</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="run in recentRuns" :key="run.id">
            <td>{{ run.source_name }}</td>
            <td>{{ run.status }}</td>
            <td>{{ formatDate(run.started_at) }}</td>
            <td>{{ run.created_candidates_count || 0 }}</td>
            <td>{{ run.errors_count || 0 }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  padding: 14px;
  background: rgb(var(--color-bg-rgb) / 0.65);
}

.card + .card {
  margin-top: 12px;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  text-align: left;
  padding: 8px 6px;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.1);
}

.toggleLabel {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.runRow {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.runRow input {
  width: 120px;
  padding: 6px 8px;
  border-radius: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: transparent;
  color: inherit;
}

.runRow button {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  border-radius: 10px;
  padding: 7px 11px;
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: inherit;
  cursor: pointer;
}

.runRow button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.resultList {
  margin-top: 10px;
  padding-left: 18px;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
}

.alert {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.1);
  color: rgb(185 28 28);
}
</style>
