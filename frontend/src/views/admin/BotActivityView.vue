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
    v-bind="props.embedded ? {} : { title: 'Bot aktivita', subtitle: 'Logy publikovania/spusteni s dovodmi preskocenia alebo chyby.' }"
    class="botSection"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">Aktivita</h2>
        <p class="embeddedSubtitle">Logy publikovania/spusteni s dovodmi preskocenia alebo chyby.</p>
      </div>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </div>

    <template v-if="!props.embedded" #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="load(pagination.current_page || 1)">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </template>

    <section class="card filterCard">
      <div class="filterGrid">
        <label class="field">
          <span>Zdroj</span>
          <input v-model="filters.sourceKey" type="text" placeholder="nasa_rss_breaking" />
        </label>
        <label class="field">
          <span>Bot</span>
          <select v-model="filters.bot_identity">
            <option value="">Vsetky</option>
            <option value="kozmo">Kozmo</option>
            <option value="stela">Stela</option>
          </select>
        </label>
        <label class="field">
          <span>Akcia</span>
          <select v-model="filters.action">
            <option value="">Vsetky</option>
            <option value="run">run</option>
            <option value="publish">publish</option>
          </select>
        </label>
        <label class="field">
          <span>Vysledok</span>
          <select v-model="filters.outcome">
            <option value="">Vsetky</option>
            <option value="success">success</option>
            <option value="partial">partial</option>
            <option value="published">published</option>
            <option value="skipped">skipped</option>
            <option value="failed">failed</option>
          </select>
        </label>
        <label class="field">
          <span>Od</span>
          <input v-model="filters.date_from" type="date" />
        </label>
        <label class="field">
          <span>Do</span>
          <input v-model="filters.date_to" type="date" />
        </label>
      </div>
      <div class="filterActions">
        <button class="actionBtn" type="button" :disabled="loading" @click="applyFilters">Filtrovat</button>
        <button class="actionBtn ghost" type="button" :disabled="loading" @click="resetFilters">Reset</button>
      </div>
    </section>

    <section class="card tableCard">
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loading && rows.length === 0" class="muted">Ziadne logy pre zadane filtre.</p>

      <div v-else class="tableWrap">
        <table class="activityTable">
          <thead>
            <tr>
              <th>Cas</th>
              <th>Bot</th>
              <th>Zdroj</th>
              <th>Akcia</th>
              <th>Vysledok</th>
              <th>Item/Post</th>
              <th>Dovod</th>
              <th>Sprava</th>
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
              <td>
                <div>ID item: {{ row.bot_item_id || '-' }}</div>
                <div>ID post: {{ row.post_id || '-' }}</div>
              </td>
              <td>{{ row.reason || '-' }}</td>
              <td>{{ row.message || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pager">
        <button class="actionBtn ghost" type="button" :disabled="loading || !canPrev" @click="previousPage">
          Predosla
        </button>
        <span class="muted">
          Strana {{ pagination.current_page }} / {{ pagination.last_page }} - {{ pagination.total }} zaznamov
        </span>
        <button class="actionBtn ghost" type="button" :disabled="loading || !canNext" @click="nextPage">
          Dalsia
        </button>
      </div>
    </section>
  </component>
</template>

<style scoped>
.botSection {
  display: grid;
  gap: 14px;
}

.embeddedHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.embeddedTitle {
  margin: 0 0 6px;
  font-size: 1.06rem;
  font-weight: 800;
}

.embeddedSubtitle {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.85rem;
}

.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.7);
  padding: 14px;
}

.filterCard {
  display: grid;
  gap: 10px;
}

.filterGrid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

.field {
  display: grid;
  gap: 6px;
  font-size: 0.8rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.field input,
.field select {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
  padding: 8px 10px;
}

.filterActions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.actionBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.6);
  border-radius: 10px;
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
  font-weight: 700;
  padding: 7px 11px;
  cursor: pointer;
}

.actionBtn.ghost {
  border-color: rgb(var(--color-surface-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.45);
}

.actionBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.tableCard {
  display: grid;
  gap: 10px;
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
}

.activityTable {
  width: 100%;
  border-collapse: collapse;
  min-width: 860px;
}

.activityTable th,
.activityTable td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 8px 10px;
  text-align: left;
  vertical-align: top;
  font-size: 0.82rem;
}

.activityTable th {
  font-size: 0.75rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.outcome {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.25);
  font-size: 0.73rem;
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

.pager {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
  align-items: center;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.82rem;
}

.error {
  color: var(--color-danger);
  margin: 0;
}
</style>
