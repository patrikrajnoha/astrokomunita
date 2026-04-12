<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getBotActivity, getBotRuns } from '@/services/api/admin/bots'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
  refreshToken: {
    type: Number,
    default: 0,
  },
})

const route = useRoute()
const routeQuery = computed(() => route?.query || {})

const loadingLogs = ref(false)
const loadingRuns = ref(false)
const error = ref('')
const rows = ref([])
const runs = ref([])
const runsTotal = ref(0)
const filterDebounce = ref(null)
const syncingFromRoute = ref(false)

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 30,
  total: 0,
})

const filters = reactive({
  search: '',
  bot_identity: '',
  outcome: '',
  action: '',
  date_from: '',
  date_to: '',
})

const canPrev = computed(() => Number(pagination.current_page || 1) > 1)
const canNext = computed(() => Number(pagination.current_page || 1) < Number(pagination.last_page || 1))

const hasActiveFilters = computed(() => {
  return (
    String(filters.search || '').trim() !== '' ||
    String(filters.bot_identity || '').trim() !== '' ||
    String(filters.outcome || '').trim() !== '' ||
    String(filters.action || '').trim() !== '' ||
    String(filters.date_from || '').trim() !== '' ||
    String(filters.date_to || '').trim() !== ''
  )
})

const summaryLine = computed(() => {
  if (loadingLogs.value) return 'Načítavam logy…'
  return `${pagination.total} záznamov · strana ${pagination.current_page}/${pagination.last_page}`
})

function readQueryValue(value) {
  if (Array.isArray(value)) {
    return String(value[0] || '').trim()
  }

  return String(value || '').trim()
}

function syncFiltersFromRoute() {
  syncingFromRoute.value = true

  const query = routeQuery.value
  filters.search = readQueryValue(query.sourceKey)
  filters.bot_identity = readQueryValue(query.bot_identity)
  filters.action = readQueryValue(query.action)
  filters.outcome = readQueryValue(query.outcome)
  filters.date_from = readQueryValue(query.date_from)
  filters.date_to = readQueryValue(query.date_to)

  syncingFromRoute.value = false
}

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return parsed.toLocaleString('sk-SK', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

function identityLabel(value) {
  const normalized = String(value || '')
    .trim()
    .toLowerCase()
  if (normalized === 'kozmo') return 'Kozmo'
  if (normalized === 'stela') return 'Stela'
  return normalized || '-'
}

function sourceLabel(row) {
  const key = String(row?.source_key || '').trim()
  return key || '-'
}

function normalizeOutcome(value) {
  const normalized = String(value || '').trim().toLowerCase()
  if (!normalized) return ''

  if (normalized.includes('duplicate')) return 'duplicate'
  if (normalized === 'created' || normalized === 'updated') return 'published'
  if (normalized === 'skipped_cooldown') return 'skipped'

  return normalized
}

function outcomeLabel(value) {
  const normalized = normalizeOutcome(value)

  if (normalized === 'published') return 'published'
  if (normalized === 'success') return 'success'
  if (normalized === 'skipped') return 'skipped'
  if (normalized === 'failed') return 'failed'
  if (normalized === 'duplicate') return 'duplicate'
  if (normalized === 'partial') return 'partial'

  return normalized || '-'
}

function outcomeClass(value) {
  const normalized = normalizeOutcome(value)
  if (normalized === 'published' || normalized === 'success') return 'outcome outcome--success'
  if (normalized === 'skipped' || normalized === 'partial') return 'outcome outcome--skipped'
  if (normalized === 'failed') return 'outcome outcome--failed'
  if (normalized === 'duplicate') return 'outcome outcome--duplicate'

  return 'outcome'
}

function runStatusClass(status) {
  const normalized = String(status || '').trim().toLowerCase()
  if (normalized === 'success') return 'outcome outcome--success'
  if (normalized === 'partial') return 'outcome outcome--skipped'
  if (normalized === 'failed') return 'outcome outcome--failed'

  return 'outcome'
}

function runStatusFromOutcomeFilter() {
  const normalized = normalizeOutcome(filters.outcome)
  if (normalized === 'success') return 'success'
  if (normalized === 'partial') return 'partial'
  if (normalized === 'failed') return 'failed'
  return undefined
}

function activityParams(page = 1) {
  return {
    page,
    per_page: pagination.per_page,
    sourceKey: String(filters.search || '').trim() || undefined,
    bot_identity: String(filters.bot_identity || '').trim() || undefined,
    action: String(filters.action || '').trim() || undefined,
    outcome: String(filters.outcome || '').trim() || undefined,
    date_from: String(filters.date_from || '').trim() || undefined,
    date_to: String(filters.date_to || '').trim() || undefined,
  }
}

function runsParams() {
  return {
    page: 1,
    per_page: 8,
    sourceKey: String(filters.search || '').trim() || undefined,
    bot_identity: String(filters.bot_identity || '').trim() || undefined,
    status: runStatusFromOutcomeFilter(),
    date_from: String(filters.date_from || '').trim() || undefined,
    date_to: String(filters.date_to || '').trim() || undefined,
  }
}

async function loadLogs(page = 1) {
  loadingLogs.value = true
  error.value = ''

  try {
    const response = await getBotActivity(activityParams(page))
    const payload = response?.data || {}

    rows.value = Array.isArray(payload?.data) ? payload.data : []
    pagination.current_page = Number(payload?.current_page || page)
    pagination.last_page = Number(payload?.last_page || 1)
    pagination.per_page = Number(payload?.per_page || pagination.per_page)
    pagination.total = Number(payload?.total || rows.value.length)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Načítanie bot logov zlyhalo.'
  } finally {
    loadingLogs.value = false
  }
}

async function loadRuns() {
  loadingRuns.value = true

  try {
    const response = await getBotRuns(runsParams())
    const payload = response?.data || {}
    runs.value = Array.isArray(payload?.data) ? payload.data : []
    runsTotal.value = Number(payload?.total || runs.value.length)
  } catch {
    runs.value = []
    runsTotal.value = 0
  } finally {
    loadingRuns.value = false
  }
}

async function loadEverything(page = 1) {
  await Promise.all([
    loadLogs(page),
    loadRuns(),
  ])
}

function queueFilterReload() {
  if (syncingFromRoute.value) return

  if (filterDebounce.value) {
    clearTimeout(filterDebounce.value)
  }

  filterDebounce.value = setTimeout(() => {
    void loadEverything(1)
  }, 250)
}

function resetFilters() {
  filters.search = ''
  filters.bot_identity = ''
  filters.outcome = ''
  filters.action = ''
  filters.date_from = ''
  filters.date_to = ''
  void loadEverything(1)
}

function previousPage() {
  if (!canPrev.value || loadingLogs.value) return
  void loadEverything(Number(pagination.current_page || 1) - 1)
}

function nextPage() {
  if (!canNext.value || loadingLogs.value) return
  void loadEverything(Number(pagination.current_page || 1) + 1)
}

watch(
  () => [
    filters.search,
    filters.bot_identity,
    filters.outcome,
    filters.action,
    filters.date_from,
    filters.date_to,
  ],
  () => {
    queueFilterReload()
  },
)

watch(
  routeQuery,
  () => {
    syncFiltersFromRoute()
    void loadEverything(1)
  },
)

watch(
  () => props.refreshToken,
  () => {
    void loadEverything(pagination.current_page || 1)
  },
)

onMounted(() => {
  syncFiltersFromRoute()
  void loadEverything(1)
})

onBeforeUnmount(() => {
  if (filterDebounce.value) {
    clearTimeout(filterDebounce.value)
  }
})
</script>

<template>
  <component
    :is="props.embedded ? 'section' : AdminPageShell"
    v-bind="props.embedded ? {} : { title: 'Bot logy', subtitle: 'História behov a publikovania na jednom mieste.' }"
    class="botSection"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">Logy</h2>
        <p class="embeddedSubtitle">{{ summaryLine }}</p>
      </div>
    </div>

    <section class="card filterCard">
      <div class="filterRow">
        <label class="field field--search">
          <input v-model="filters.search" type="text" placeholder="Hľadať podľa source key" />
        </label>

        <label class="field field--compact">
          <span>Bot</span>
          <select v-model="filters.bot_identity">
            <option value="">Všetky</option>
            <option value="kozmo">Kozmo</option>
            <option value="stela">Stela</option>
          </select>
        </label>

        <label class="field field--compact">
          <span>Výsledok</span>
          <select v-model="filters.outcome">
            <option value="">Všetky</option>
            <option value="success">success</option>
            <option value="published">published</option>
            <option value="skipped">skipped</option>
            <option value="failed">failed</option>
            <option value="duplicate">duplicate</option>
            <option value="partial">partial</option>
          </select>
        </label>

        <div class="filterActions">
          <button v-if="hasActiveFilters" class="ghostBtn" type="button" :disabled="loadingLogs" @click="resetFilters">
            Vyčistiť
          </button>
        </div>
      </div>

      <details class="advancedFilters">
        <summary>Pokročilé filtre</summary>
        <div class="advancedFiltersBody">
          <label class="field field--compact">
            <span>Akcia</span>
            <select v-model="filters.action">
              <option value="">Všetky</option>
              <option value="run">run</option>
              <option value="publish">publish</option>
              <option value="ingest">ingest</option>
              <option value="skipped_cooldown">skipped_cooldown</option>
            </select>
          </label>
          <label class="field field--compact">
            <span>Od</span>
            <input v-model="filters.date_from" type="date" />
          </label>
          <label class="field field--compact">
            <span>Do</span>
            <input v-model="filters.date_to" type="date" />
          </label>
        </div>
      </details>
    </section>

    <section class="card tableCard">
      <div class="tableHeader">
        <h3>Posledné behy</h3>
        <span class="muted">{{ loadingRuns ? 'Načítavam…' : `${runsTotal} záznamov` }}</span>
      </div>

      <p v-if="!loadingRuns && runs.length === 0" class="muted">Žiadne behy pre zvolené filtre.</p>

      <div v-else>
        <div class="tableWrap tableWrap--runs">
          <table class="activityTable activityTable--runs">
            <thead>
              <tr>
                <th>Čas</th>
                <th>Bot</th>
                <th>Zdroj</th>
                <th>Stav</th>
                <th>Publikované</th>
                <th>Chyby</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="run in runs" :key="run.id">
                <td>{{ formatDateTime(run.started_at) }}</td>
                <td>{{ identityLabel(run.bot_identity) }}</td>
                <td>{{ run.source_key || '-' }}</td>
                <td>
                  <span :class="runStatusClass(run.status)">{{ run.status || '-' }}</span>
                </td>
                <td>{{ Number(run?.stats?.published_count || 0) }}</td>
                <td>{{ Number(run?.stats?.failed_count || 0) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="runsMobileList">
          <article v-for="run in runs" :key="`run-mobile-${run.id}`" class="mobileCard">
            <div class="mobileCard__head">
              <strong class="mobileCard__title">{{ identityLabel(run.bot_identity) }}</strong>
              <span :class="runStatusClass(run.status)">{{ run.status || '-' }}</span>
            </div>

            <div class="mobileCard__meta">
              <span>{{ formatDateTime(run.started_at) }}</span>
              <span>{{ run.source_key || '-' }}</span>
            </div>

            <dl class="mobileStats">
              <div>
                <dt>Publikované</dt>
                <dd>{{ Number(run?.stats?.published_count || 0) }}</dd>
              </div>
              <div>
                <dt>Chyby</dt>
                <dd>{{ Number(run?.stats?.failed_count || 0) }}</dd>
              </div>
            </dl>
          </article>
        </div>
      </div>
    </section>

    <section class="card tableCard">
      <div class="tableHeader">
        <h3>Aktivita</h3>
        <span class="muted">{{ summaryLine }}</span>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loadingLogs && rows.length === 0" class="muted">Žiadne logy pre zadané filtre.</p>

      <div v-else>
        <div class="tableWrap tableWrap--logs">
          <table class="activityTable">
            <thead>
              <tr>
                <th>Čas</th>
                <th>Bot</th>
                <th>Zdroj</th>
                <th>Výsledok</th>
                <th>Správa</th>
                <th>Detail</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td>{{ formatDateTime(row.created_at) }}</td>
                <td>{{ identityLabel(row.bot_identity) }}</td>
                <td>{{ sourceLabel(row) }}</td>
                <td>
                  <span :class="outcomeClass(row.outcome)">
                    {{ outcomeLabel(row.outcome) }}
                  </span>
                </td>
                <td class="messageCell">{{ row.message || '-' }}</td>
                <td class="idCell">
                  <span>akcia: {{ row.action || '-' }}</span>
                  <span v-if="row.bot_item_id || row.post_id">item/post: {{ row.bot_item_id || '-' }}/{{ row.post_id || '-' }}</span>
                  <span v-if="row.reason">dôvod: {{ row.reason }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="logsMobileList">
          <article v-for="row in rows" :key="`row-mobile-${row.id}`" class="mobileCard logMobileCard">
            <div class="mobileCard__head">
              <strong class="mobileCard__title">{{ identityLabel(row.bot_identity) }}</strong>
              <span :class="outcomeClass(row.outcome)">
                {{ outcomeLabel(row.outcome) }}
              </span>
            </div>

            <div class="mobileCard__meta">
              <span>{{ formatDateTime(row.created_at) }}</span>
              <span>{{ sourceLabel(row) }}</span>
            </div>

            <p class="logMobileCard__message">{{ row.message || '-' }}</p>

            <dl class="mobileDetailGrid">
              <div>
                <dt>Akcia</dt>
                <dd>{{ row.action || '-' }}</dd>
              </div>
              <div v-if="row.bot_item_id || row.post_id">
                <dt>Item/Post</dt>
                <dd>{{ row.bot_item_id || '-' }}/{{ row.post_id || '-' }}</dd>
              </div>
              <div v-if="row.reason">
                <dt>Dôvod</dt>
                <dd>{{ row.reason }}</dd>
              </div>
            </dl>
          </article>
        </div>
      </div>

      <div class="pager">
        <button class="ghostBtn" type="button" :disabled="loadingLogs || !canPrev" @click="previousPage">
          Predošlá
        </button>
        <span class="muted">
          Strana {{ pagination.current_page }} / {{ pagination.last_page }} · {{ pagination.total }} záznamov
        </span>
        <button class="ghostBtn" type="button" :disabled="loadingLogs || !canNext" @click="nextPage">
          Ďalšia
        </button>
      </div>
    </section>
  </component>
</template>

<style scoped>
.botSection {
  display: grid;
  gap: 12px;
  min-width: 0;
  container-type: inline-size;
}

.embeddedHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.embeddedTitle {
  margin: 0 0 4px;
  font-size: 1rem;
  font-weight: 800;
}

.embeddedSubtitle {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.8rem;
}

.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.66);
  padding: 12px;
}

.filterCard {
  display: grid;
  gap: 8px;
  padding: 10px;
  min-width: 0;
}

.filterRow {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) minmax(130px, auto) minmax(150px, auto) auto;
  align-items: end;
  gap: 8px;
}

.field {
  display: grid;
  gap: 5px;
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
}

.field--search {
  min-width: 0;
}

.field input,
.field select {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.38);
  color: var(--color-surface);
  min-height: 34px;
  padding: 7px 9px;
}

.field--compact {
  min-width: 120px;
}

.filterActions {
  display: inline-flex;
  gap: 8px;
  align-items: center;
}

.advancedFilters {
  border-top: 1px solid var(--divider-color);
  padding-top: 8px;
}

.advancedFilters > summary {
  cursor: pointer;
  font-size: 0.76rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.advancedFiltersBody {
  margin-top: 8px;
  display: inline-grid;
  gap: 8px;
  grid-template-columns: repeat(3, minmax(120px, 1fr));
}

.ghostBtn {
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  background: transparent;
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.ghostBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.tableCard {
  display: grid;
  gap: 8px;
}

.tableHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.tableHeader h3 {
  margin: 0;
  font-size: 0.9rem;
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
  max-width: 100%;
}

.activityTable {
  width: 100%;
  border-collapse: collapse;
  min-width: 760px;
}

.activityTable--runs {
  min-width: 640px;
}

.activityTable th,
.activityTable td {
  border-bottom: 1px solid var(--divider-color);
  padding: 8px 9px;
  text-align: left;
  vertical-align: top;
  font-size: 0.78rem;
}

.activityTable th {
  font-size: 0.7rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.outcome {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.25);
  font-size: 0.68rem;
  font-weight: 700;
}

.outcome--success {
  border-color: rgb(var(--color-success-rgb) / 0.5);
  color: var(--color-success);
}

.outcome--skipped {
  border-color: rgb(var(--color-warning-rgb) / 0.5);
  color: var(--color-warning);
}

.outcome--failed {
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  color: var(--color-danger);
}

.outcome--duplicate {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  color: var(--color-primary);
}

.idCell {
  display: grid;
  gap: 2px;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.74rem;
}

.messageCell {
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  max-width: 360px;
}

.runsMobileList,
.logsMobileList {
  display: none;
  gap: 8px;
}

.mobileCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.84);
  padding: 9px;
  display: grid;
  gap: 7px;
}

.mobileCard__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.mobileCard__title {
  min-width: 0;
  font-size: 0.82rem;
  line-height: 1.3;
  overflow-wrap: anywhere;
}

.mobileCard__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  font-size: 0.73rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.mobileStats {
  margin: 0;
  display: grid;
  gap: 6px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.mobileStats div {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 8px;
  background: rgb(var(--color-surface-rgb) / 0.05);
  padding: 6px 8px;
}

.mobileStats dt {
  font-size: 0.66rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
}

.mobileStats dd {
  margin: 2px 0 0;
  font-size: 0.82rem;
  font-weight: 700;
}

.logMobileCard__message {
  margin: 0;
  font-size: 0.76rem;
  line-height: 1.35;
  color: rgb(var(--color-text-secondary-rgb) / 0.98);
  overflow-wrap: anywhere;
}

.mobileDetailGrid {
  margin: 0;
  display: grid;
  gap: 6px;
}

.mobileDetailGrid div {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 8px;
  background: rgb(var(--color-surface-rgb) / 0.05);
  padding: 6px 8px;
}

.mobileDetailGrid dt {
  font-size: 0.66rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
}

.mobileDetailGrid dd {
  margin: 2px 0 0;
  font-size: 0.76rem;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.pager {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
  align-items: center;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.8rem;
}

.error {
  color: var(--color-danger);
  margin: 0;
}

@container (max-width: 860px) {
  .embeddedHeader {
    align-items: stretch;
    flex-direction: column;
  }

  .filterRow {
    grid-template-columns: 1fr;
    align-items: stretch;
  }

  .advancedFiltersBody {
    grid-template-columns: 1fr;
  }

  .filterActions {
    width: 100%;
  }

  .filterActions .ghostBtn {
    flex: 1 1 auto;
    text-align: center;
  }
}

@container (max-width: 720px) {
  .tableWrap--runs,
  .tableWrap--logs {
    display: none;
  }

  .runsMobileList,
  .logsMobileList {
    display: grid;
  }

  .pager {
    justify-content: stretch;
  }

  .pager .ghostBtn {
    flex: 1 1 auto;
    text-align: center;
  }
}

@container (max-width: 460px) {
  .mobileStats {
    grid-template-columns: 1fr;
  }
}
</style>
