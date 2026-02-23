<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  getCrawlRuns,
  getEventSources,
  runEventSourceCrawl,
  updateEventSource,
} from '@/services/api/admin/eventSources'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const toast = useToast()

const loading = ref(false)
const error = ref('')
const runningSelected = ref(false)
const runningByKey = ref({})

const sources = ref([])
const selectedKeys = ref([])
const recentRuns = ref([])
const latestRunBySourceKey = ref({})

const yearTouched = ref(false)
const year = ref(new Date().getFullYear())

const supportedSelectedKeys = computed(() => {
  const selectedSet = new Set(selectedKeys.value.map((key) => normalizeSourceKey(key)))

  return sources.value
    .filter((source) => selectedSet.has(normalizeSourceKey(source.key)))
    .filter((source) => Boolean(source?.manual_run_supported) && Boolean(source?.is_enabled))
    .map((source) => normalizeSourceKey(source.key))
})

const canRunSelected = computed(() => {
  return !runningSelected.value && supportedSelectedKeys.value.length > 0
})

function normalizeSourceKey(value) {
  return String(value || '').trim().toLowerCase()
}

function sourceLabel(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  if (key === 'astropixels') return 'AstroPixels'
  if (key === 'imo') return 'IMO'
  if (key === 'nasa_watch_the_skies') return 'NASA WTS'
  if (key === 'nasa') return 'NASA'
  return key || '-'
}

function sourceToneClass(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  if (key === 'astropixels') return 'sourceBadge--astropixels'
  if (key === 'imo') return 'sourceBadge--imo'
  if (key === 'nasa' || key === 'nasa_watch_the_skies') return 'sourceBadge--nasa'
  return 'sourceBadge--generic'
}

function isSourceSupported(source) {
  return Boolean(source?.manual_run_supported)
}

function sourceStatusLabel(source) {
  if (!isSourceSupported(source)) return 'Unsupported'
  return source?.is_enabled ? 'Enabled' : 'Disabled'
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
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
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

function findLatestRunForSource(sourceKey) {
  const key = normalizeSourceKey(sourceKey)
  return latestRunBySourceKey.value[key] || null
}

function runStatusLabel(run) {
  if (!run) return 'Never'
  const status = String(run.status || '').trim()
  return status !== '' ? status : 'Unknown'
}

function isSourceCheckboxDisabled(source) {
  return runningSelected.value || !source?.is_enabled || !isSourceSupported(source)
}

function isRowRunDisabled(source) {
  const key = normalizeSourceKey(source?.key)
  return runningSelected.value || Boolean(runningByKey.value[key]) || !source?.is_enabled || !isSourceSupported(source)
}

function rowRunDisabledReason(source) {
  if (!isSourceSupported(source)) {
    return 'Deferred in MVP'
  }

  if (!source?.is_enabled) {
    return 'Enable source first'
  }

  return ''
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [sourcesRes, runsRes] = await Promise.all([
      getEventSources(),
      getCrawlRuns({ per_page: 10 }),
    ])

    const sourceList = Array.isArray(sourcesRes?.data?.data) ? sourcesRes.data.data : []
    sources.value = sourceList

    const runList = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
    recentRuns.value = runList

    const latestByKey = {}
    for (const run of runList) {
      const key = normalizeSourceKey(run?.source_name)
      if (key === '' || latestByKey[key]) {
        continue
      }
      latestByKey[key] = run
    }
    latestRunBySourceKey.value = latestByKey

    if (!yearTouched.value) {
      const latestYear = Number(runList[0]?.year)
      year.value = Number.isFinite(latestYear) && latestYear >= 2000 ? latestYear : new Date().getFullYear()
    }
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to load crawling data.'
  } finally {
    loading.value = false
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
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to update source.'
  }
}

async function runSelected() {
  if (!canRunSelected.value) {
    return
  }

  runningSelected.value = true
  error.value = ''

  try {
    await runEventSourceCrawl({
      source_keys: supportedSelectedKeys.value,
      year: Number(year.value),
    })
    toast.success('Crawl run created for selected sources.')
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Manual run failed.'
  } finally {
    runningSelected.value = false
  }
}

async function runSingleSource(source) {
  const key = normalizeSourceKey(source?.key)
  if (!key || isRowRunDisabled(source)) {
    return
  }

  runningByKey.value = {
    ...runningByKey.value,
    [key]: true,
  }

  error.value = ''

  try {
    await runEventSourceCrawl({
      source_keys: [key],
      year: Number(year.value),
    })
    toast.success(`Crawl run created for ${sourceLabel(key)}.`)
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Manual run failed.'
  } finally {
    runningByKey.value = {
      ...runningByKey.value,
      [key]: false,
    }
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

onMounted(load)
</script>

<template>
  <AdminPageShell title="Crawling" subtitle="Enable source -> run -> review candidates from the run.">
    <div v-if="error" class="alert">{{ error }}</div>

    <section class="card runPanel">
      <div class="runPanel__head">
        <h2>Run panel</h2>
        <div class="runPanel__meta">Selected {{ supportedSelectedKeys.length }} supported source(s)</div>
      </div>

      <div class="runPanel__actions">
        <label class="runPanel__field" for="run-year">
          <span>Year</span>
          <input
            id="run-year"
            v-model.number="year"
            type="number"
            min="2000"
            max="2100"
            :disabled="runningSelected"
            @input="yearTouched = true"
          />
        </label>

        <button
          type="button"
          class="primaryBtn"
          data-testid="run-selected-btn"
          :disabled="!canRunSelected"
          @click="runSelected"
        >
          {{ runningSelected ? 'Running...' : 'Run selected' }}
        </button>
      </div>

      <p class="runPanel__hint">Creates crawl run and imports candidates (staging).</p>
    </section>

    <section class="card">
      <div class="cardHead">
        <h2>Sources</h2>
      </div>

      <div v-if="loading" class="muted">Loading sources...</div>
      <div v-else class="tableWrap">
        <table class="table compact">
          <thead>
            <tr>
              <th aria-label="Select source">[ ]</th>
              <th>Source</th>
              <th>Status</th>
              <th>Last run</th>
              <th>Counters</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="source in sources" :key="source.id" :data-testid="`source-row-${normalizeSourceKey(source.key)}`">
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
                    <span>{{ source.is_enabled ? 'On' : 'Off' }}</span>
                  </label>

                  <button
                    type="button"
                    class="ghostBtn"
                    :data-testid="`run-source-${normalizeSourceKey(source.key)}`"
                    :disabled="isRowRunDisabled(source)"
                    :title="rowRunDisabledReason(source)"
                    @click="runSingleSource(source)"
                  >
                    {{ runningByKey[normalizeSourceKey(source.key)] ? 'Running...' : 'Run' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <div class="cardHead">
        <h2>Recent runs</h2>
        <span class="muted">Last 10</span>
      </div>

      <div v-if="recentRuns.length === 0" class="muted">No runs yet.</div>
      <div v-else class="tableWrap">
        <table class="table compact">
          <thead>
            <tr>
              <th>Time</th>
              <th>Source</th>
              <th>Year</th>
              <th>Status</th>
              <th>Counters</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="run in recentRuns" :key="run.id">
              <td>{{ formatDate(run.started_at) }}</td>
              <td>
                <span class="sourceBadge" :class="sourceToneClass(run.source_name)">{{ sourceLabel(run.source_name) }}</span>
              </td>
              <td>{{ run.year || '-' }}</td>
              <td>
                <span class="pill" :class="`pill--${runStatusTone(run.status)}`">{{ run.status || 'unknown' }}</span>
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
                <div class="actionRow">
                  <button type="button" class="ghostBtn" @click="viewRunCandidates(run)">View candidates</button>
                  <button type="button" class="ghostBtn" @click="openRunDetails(run)">Details</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  padding: 12px;
  background: rgb(var(--color-bg-rgb) / 0.82);
}

.cardHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.cardHead h2,
.runPanel__head h2 {
  margin: 0;
  font-size: 16px;
}

.runPanel {
  display: grid;
  gap: 8px;
}

.runPanel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.runPanel__meta,
.runPanel__hint,
.muted {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.runPanel__actions {
  display: flex;
  align-items: end;
  gap: 10px;
  flex-wrap: wrap;
}

.runPanel__field {
  display: grid;
  gap: 4px;
  font-size: 12px;
}

.runPanel__field input {
  width: 120px;
}

.runPanel__field input,
.ghostBtn,
.primaryBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  border-radius: 10px;
  padding: 7px 10px;
  background: transparent;
  color: inherit;
}

.primaryBtn {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.ghostBtn:disabled,
.primaryBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  text-align: left;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 7px 8px;
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
  border: 1px solid rgb(var(--color-surface-rgb) / 0.25);
  background: rgb(var(--color-surface-rgb) / 0.1);
  padding: 2px 8px;
  font-size: 12px;
}

.sourceBadge--astropixels {
  border-color: rgb(30 64 175 / 0.35);
  background: rgb(30 64 175 / 0.12);
}

.sourceBadge--imo {
  border-color: rgb(6 95 70 / 0.35);
  background: rgb(6 95 70 / 0.12);
}

.sourceBadge--nasa {
  border-color: rgb(107 33 168 / 0.35);
  background: rgb(107 33 168 / 0.12);
}

.pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  padding: 2px 8px;
  font-size: 12px;
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.pill--success {
  border-color: rgb(22 163 74 / 0.35);
  background: rgb(22 163 74 / 0.12);
}

.pill--warning {
  border-color: rgb(202 138 4 / 0.35);
  background: rgb(202 138 4 / 0.12);
}

.pill--danger {
  border-color: rgb(220 38 38 / 0.35);
  background: rgb(220 38 38 / 0.12);
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

.switchLabel {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}

.alert {
  margin-bottom: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.1);
  color: rgb(185 28 28);
}

@media (max-width: 900px) {
  .card {
    padding: 10px;
  }

  .runPanel__actions {
    align-items: stretch;
  }
}
</style>
