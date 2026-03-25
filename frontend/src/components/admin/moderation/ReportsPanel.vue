<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { formatRelativeTime, parsePositiveInt, scrollElementIntoView } from './utils'

const props = defineProps({
  selectedReportId: {
    type: [String, Number],
    default: null,
  },
})

const emit = defineEmits(['changed'])

const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const rootRef = ref(null)
const loading = ref(false)
const error = ref('')
const searchInput = ref('')
const search = ref('')
const status = ref('open')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)

let searchDebounce = null

const columns = [
  { key: 'type', label: 'Typ' },
  { key: 'target', label: 'Cieľ' },
  { key: 'reason', label: 'Dôvod' },
  { key: 'status', label: 'Stav' },
  { key: 'created_at', label: 'Vytvorené' },
  { key: 'actions', label: 'Akcie', align: 'right' },
]

const rows = computed(() => data.value?.data || [])
const hasActiveFilters = computed(() => Boolean(search.value || status.value !== 'open'))

function readQuery(query) {
  const qSearch = typeof query.search === 'string' ? query.search : ''
  const qStatus = typeof query.status === 'string' ? query.status : 'open'
  const qPage = parsePositiveInt(query.page, 1)
  const qPerPage = parsePositiveInt(query.per_page, 20)

  searchInput.value = qSearch
  search.value = qSearch
  status.value = qStatus
  page.value = qPage
  perPage.value = qPerPage
}

function buildQuery() {
  const query = {
    ...route.query,
    status: status.value,
    page: String(page.value),
    per_page: String(perPage.value),
  }

  if (search.value) {
    query.search = search.value
  } else {
    delete query.search
  }

  return query
}

function currentQuery() {
  return {
    status: typeof route.query.status === 'string' ? route.query.status : 'open',
    page: String(parsePositiveInt(route.query.page, 1)),
    per_page: String(parsePositiveInt(route.query.per_page, 20)),
    search: typeof route.query.search === 'string' ? route.query.search : '',
  }
}

function syncQueryWithState() {
  const next = buildQuery()
  const now = currentQuery()

  if (
    next.status === now.status &&
    next.page === now.page &&
    next.per_page === now.per_page &&
    (next.search || '') === now.search
  ) {
    return
  }

  router.replace({ query: next })
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const params = {
      status: status.value,
      page: page.value,
      per_page: perPage.value,
    }

    if (search.value) {
      params.search = search.value
    }

    const res = await api.get('/admin/reports', { params })
    data.value = res.data
    await focusSelectedReport()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať reporty.'
  } finally {
    loading.value = false
  }
}

function updateRow(updated) {
  if (!data.value || !updated) return
  const reportRows = data.value.data || []
  const idx = reportRows.findIndex((row) => String(row.id) === String(updated.id))
  if (idx >= 0) {
    reportRows[idx] = { ...reportRows[idx], ...updated }
  }
}

async function act(report, action) {
  if (!report?.id) return

  const isDelete = action === 'delete'
  const isBan = action === 'ban'
  const isDestructive = isDelete || isBan

  const title = isDelete
    ? 'Zmazať obsah?'
    : isBan
      ? 'Zablokovať používateľa?'
      : 'Potvrdiť akciu?'

  const message = isDelete
    ? 'Obsah bude natrvalo odstránený.'
    : isBan
      ? 'Používateľ bude zablokovaný.'
      : `Naozaj chceš vykonať akciu "${action}"?`

  const confirmText = isDelete
    ? 'Zmazať obsah'
    : isBan
      ? 'Zablokovať'
      : 'Potvrdiť'

  const ok = await confirm({
    title,
    message,
    confirmText,
    cancelText: 'Zrušiť',
    variant: isDestructive ? 'danger' : 'default',
  })
  if (!ok) return

  loading.value = true
  error.value = ''

  try {
    const res = await api.post(`/admin/reports/${report.id}/${action}`)
    updateRow(res.data)
    toast.success('Akcia bola vykonaná.')
    emit('changed')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Akcia zlyhala.'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  searchInput.value = ''
  search.value = ''
  status.value = 'open'
  page.value = 1
}

function reportType(report) {
  if (!report?.target_type) return 'post'
  const segments = String(report.target_type).split('\\')
  return (segments[segments.length - 1] || 'post').toLowerCase()
}

function targetSummary(report) {
  const author = report?.target?.user?.name || '-'
  const snippet = report?.target?.content ? String(report.target.content).slice(0, 40) : ''
  return snippet ? `${author}: ${snippet}` : author
}

function rowClass(row) {
  return String(props.selectedReportId || '') === String(row?.id || '') ? 'is-focused' : ''
}

async function focusSelectedReport() {
  if (!props.selectedReportId) return
  await nextTick()
  const row = rootRef.value?.querySelector?.(`tr[data-row-key="${props.selectedReportId}"]`)
  scrollElementIntoView(row)
}

function refresh() {
  load()
}

watch(
  () => route.query,
  (query) => {
    readQuery(query)
  },
)

watch(searchInput, (value) => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    if (search.value !== value) {
      search.value = value
      page.value = 1
    }
  }, 400)
})

watch([search, status, page, perPage], () => {
  syncQueryWithState()
  load()
})

watch(() => props.selectedReportId, () => {
  focusSelectedReport()
})

onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce)
})

readQuery(route.query)
syncQueryWithState()
load()
</script>

<template>
  <section ref="rootRef" class="grid gap-3">
    <div v-if="error" class="rounded-xl bg-danger/10 text-danger px-3 py-2 text-xs">{{ error }}</div>

    <AdminToolbar :loading="loading">
      <template #search>
        <label class="fieldLabel" for="reports-search">Hľadať</label>
        <input
          id="reports-search"
          v-model="searchInput"
          :disabled="loading"
          type="search"
          placeholder="Hľadať reporty…"
          class="fieldInput"
        />
      </template>

      <template #filters>
        <div class="filtersRow">
          <div>
            <label class="fieldLabel" for="reports-status">Stav</label>
            <select
              id="reports-status"
              v-model="status"
              :disabled="loading"
              class="fieldInput"
              @change="page = 1"
            >
              <option value="open">open</option>
              <option value="reviewed">reviewed</option>
              <option value="dismissed">dismissed</option>
              <option value="action_taken">action_taken</option>
            </select>
          </div>

          <div>
            <label class="fieldLabel" for="reports-per-page">Na stranu</label>
            <select
              id="reports-per-page"
              v-model.number="perPage"
              :disabled="loading"
              class="fieldInput"
              @change="page = 1"
            >
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
        </div>
      </template>

      <template #actions>
        <button type="button" class="ghostBtn" :disabled="loading" @click="refresh">Obnoviť</button>
      </template>
    </AdminToolbar>

    <AdminDataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      empty-title="Žiadne reporty"
      empty-description="Skús upraviť filtre."
      :can-clear-filters="hasActiveFilters"
      :row-class="rowClass"
      @clear-filters="clearFilters"
    >
      <template #[`cell(type)`]="{ row }">
        <span class="statusBadge">{{ reportType(row) }}</span>
      </template>

      <template #[`cell(target)`]="{ row }">
        {{ targetSummary(row) }}
      </template>

      <template #[`cell(status)`]="{ row }">
        <span class="statusBadge">{{ row.status || '-' }}</span>
      </template>

      <template #[`cell(created_at)`]="{ row }">
        {{ formatRelativeTime(row.created_at) }}
      </template>

      <template #[`cell(actions)`]="{ row }">
        <div class="rowActions">
          <button class="actionBtn" :disabled="loading" @click="act(row, 'hide')">Skryť</button>
          <button class="actionBtn actionBtn--danger" :disabled="loading" @click="act(row, 'delete')">Zmazať</button>
          <button class="actionBtn" :disabled="loading" @click="act(row, 'warn')">Upozorniť</button>
          <button class="actionBtn actionBtn--danger" :disabled="loading" @click="act(row, 'ban')">Ban</button>
          <button class="actionBtn" :disabled="loading" @click="act(row, 'dismiss')">Zamietnuť</button>
        </div>
      </template>
    </AdminDataTable>

    <AdminPagination :meta="data" @page-change="page = $event" />
  </section>
</template>

<style scoped>
/* ── Field labels & inputs ────────────────────── */
.fieldLabel {
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgba(171, 184, 201, 0.6);
  margin-bottom: 5px;
}

.fieldInput {
  width: 100%;
  min-height: 34px;
  padding: 0 10px;
  border-radius: 12px;
  border: none;
  background: #1c2736;
  color: inherit;
  font-size: 13px;
  font-family: inherit;
  color-scheme: dark;
}

.fieldInput:focus-visible {
  outline: none;
}

/* ── Filter row ───────────────────────────────── */
.filtersRow {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filtersRow > div {
  min-width: 140px;
}

/* ── Ghost button ─────────────────────────────── */
.ghostBtn {
  min-height: 34px;
  padding: 0 14px;
  border-radius: 12px;
  background: #222E3F;
  color: #ABB8C9;
  border: none;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: opacity 0.1s;
}

.ghostBtn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* ── Status badge ─────────────────────────────── */
.statusBadge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  background: #222E3F;
  color: #ABB8C9;
}

/* ── Row actions ──────────────────────────────── */
.rowActions {
  display: inline-flex;
  gap: 4px;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.actionBtn {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 8px;
  background: #222E3F;
  color: #ABB8C9;
  border: none;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: opacity 0.1s;
}

.actionBtn:hover:not(:disabled) {
  opacity: 0.8;
}

.actionBtn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.actionBtn--danger {
  background: rgba(235, 36, 82, 0.1);
  color: #EB2452;
}

/* ── Focused row ──────────────────────────────── */
:deep(.adminTable__row.is-focused) {
  background: rgba(15, 115, 255, 0.07);
  box-shadow: inset 3px 0 0 rgba(15, 115, 255, 0.75);
}
</style>
