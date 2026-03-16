<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getBotActivity } from '@/services/api/admin/bots'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const route = useRoute()
const routeQuery = computed(() => route?.query || {})

const loading = ref(false)
const error = ref('')
const rows = ref([])
const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 30,
  total: 0,
})

const filters = reactive({
  sourceKey: '',
  bot_identity: '',
  action: '',
  outcome: '',
  date_from: '',
  date_to: '',
})

const canPrev = computed(() => Number(pagination.current_page || 1) > 1)
const canNext = computed(() => Number(pagination.current_page || 1) < Number(pagination.last_page || 1))
const hasActiveFilters = computed(() => {
  return (
    String(filters.sourceKey || '').trim() !== '' ||
    String(filters.bot_identity || '').trim() !== '' ||
    String(filters.action || '').trim() !== '' ||
    String(filters.outcome || '').trim() !== '' ||
    String(filters.date_from || '').trim() !== '' ||
    String(filters.date_to || '').trim() !== ''
  )
})

const summaryLine = computed(() => {
  if (loading.value) return 'Načítavam aktivitu...'
  return `${pagination.total} zaznamov | strana ${pagination.current_page}/${pagination.last_page}`
})

function readQueryValue(value) {
  if (Array.isArray(value)) {
    return String(value[0] || '').trim()
  }

  return String(value || '').trim()
}

function syncFiltersFromRoute() {
  const query = routeQuery.value
  filters.sourceKey = readQueryValue(query.sourceKey)
  filters.bot_identity = readQueryValue(query.bot_identity)
  filters.action = readQueryValue(query.action)
  filters.outcome = readQueryValue(query.outcome)
  filters.date_from = readQueryValue(query.date_from)
  filters.date_to = readQueryValue(query.date_to)
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

function requestParams(page = 1) {
  return {
    page,
    per_page: pagination.per_page,
    sourceKey: String(filters.sourceKey || '').trim() || undefined,
    bot_identity: String(filters.bot_identity || '').trim() || undefined,
    action: String(filters.action || '').trim() || undefined,
    outcome: String(filters.outcome || '').trim() || undefined,
    date_from: String(filters.date_from || '').trim() || undefined,
    date_to: String(filters.date_to || '').trim() || undefined,
  }
}

async function load(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const response = await getBotActivity(requestParams(page))
    const payload = response?.data || {}

    rows.value = Array.isArray(payload?.data) ? payload.data : []
    pagination.current_page = Number(payload?.current_page || page)
    pagination.last_page = Number(payload?.last_page || 1)
    pagination.per_page = Number(payload?.per_page || pagination.per_page)
    pagination.total = Number(payload?.total || rows.value.length)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nacitanie bot aktivity zlyhalo.'
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  void load(1)
}

function resetFilters() {
  filters.sourceKey = ''
  filters.bot_identity = ''
  filters.action = ''
  filters.outcome = ''
  filters.date_from = ''
  filters.date_to = ''
  void load(1)
}

function previousPage() {
  if (!canPrev.value || loading.value) return
  void load(Number(pagination.current_page || 1) - 1)
}

function nextPage() {
  if (!canNext.value || loading.value) return
  void load(Number(pagination.current_page || 1) + 1)
}

onMounted(() => {
  syncFiltersFromRoute()
  void load(1)
})

watch(
  routeQuery,
  () => {
    syncFiltersFromRoute()
    void load(1)
  },
)
</script>

<template>
  <component
    :is="props.embedded ? 'section' : AdminPageShell"
    v-bind="props.embedded ? {} : { title: 'Bot aktivita', subtitle: 'Logy publikovania a behov s vysledkami.' }"
    class="botSection"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">Aktivita</h2>
        <p class="embeddedSubtitle">{{ summaryLine }}</p>
      </div>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Načítavam...' : 'Obnoviť' }}
      </button>
    </div>

    <template v-if="!props.embedded" #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Načítavam...' : 'Obnoviť' }}
      </button>
    </template>

    <section class="card filterCard">
      <div class="filterRow">
        <label class="field field--search">
          <input v-model="filters.sourceKey" type="text" placeholder="Hľadať podľa source key" />
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
            <option value="partial">partial</option>
            <option value="published">published</option>
            <option value="skipped">skipped</option>
            <option value="failed">failed</option>
          </select>
        </label>

        <div class="filterActions">
          <button class="actionBtn" type="button" :disabled="loading" @click="applyFilters">Filtrovať</button>
          <button v-if="hasActiveFilters" class="ghostBtn" type="button" :disabled="loading" @click="resetFilters">Vyčistiť</button>
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
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loading && rows.length === 0" class="muted">Žiadne logy pre zadané filtre.</p>

      <div v-else class="tableWrap">
        <table class="activityTable">
          <thead>
            <tr>
              <th>Čas</th>
              <th>Bot</th>
              <th>Zdroj</th>
              <th>Akcia</th>
              <th>Výsledok</th>
              <th>Item/Post</th>
              <th>Dôvod</th>
              <th>Správa</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ formatDateTime(row.created_at) }}</td>
              <td>{{ identityLabel(row.bot_identity) }}</td>
              <td>{{ sourceLabel(row) }}</td>
              <td>{{ row.action || '-' }}</td>
              <td>
                <span class="outcome" :class="`outcome--${String(row.outcome || '').toLowerCase()}`">
                  {{ row.outcome || '-' }}
                </span>
              </td>
              <td class="idCell">
                <span>ID item: {{ row.bot_item_id || '-' }}</span>
                <span>ID post: {{ row.post_id || '-' }}</span>
              </td>
              <td>{{ row.reason || '-' }}</td>
              <td class="messageCell">{{ row.message || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <button class="ghostBtn" type="button" :disabled="loading || !canPrev" @click="previousPage">
          Predosla
        </button>
        <span class="muted">
          Strana {{ pagination.current_page }} / {{ pagination.last_page }} - {{ pagination.total }} zaznamov
        </span>
        <button class="ghostBtn" type="button" :disabled="loading || !canNext" @click="nextPage">
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

.actionBtn,
.ghostBtn {
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
}

.actionBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.ghostBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  background: transparent;
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.actionBtn:disabled,
.ghostBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.tableCard {
  display: grid;
  gap: 8px;
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
  max-width: 100%;
}

.activityTable {
  width: 100%;
  border-collapse: collapse;
  min-width: 780px;
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

.outcome--published,
.outcome--success {
  border-color: rgb(var(--color-success-rgb) / 0.5);
  color: var(--color-success);
}

.outcome--partial,
.outcome--skipped {
  border-color: rgb(var(--color-warning-rgb) / 0.5);
  color: var(--color-warning);
}

.outcome--failed {
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  color: var(--color-danger);
}

.idCell {
  display: grid;
  gap: 2px;
}

.messageCell {
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
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

  .embeddedHeader .actionBtn {
    width: 100%;
    text-align: center;
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

  .filterActions .actionBtn,
  .filterActions .ghostBtn {
    flex: 1 1 auto;
    text-align: center;
  }
}

@container (max-width: 720px) {
  .activityTable {
    min-width: 680px;
  }

  .pager {
    justify-content: stretch;
  }

  .pager .ghostBtn {
    flex: 1 1 auto;
    text-align: center;
  }
}
</style>
