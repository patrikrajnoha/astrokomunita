<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'

const route = useRoute()
const router = useRouter()

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
  { key: 'type', label: 'Type' },
  { key: 'target', label: 'Target' },
  { key: 'reason', label: 'Reason' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Created' },
  { key: 'actions', label: 'Actions', align: 'right' },
]

const rows = computed(() => data.value?.data || [])
const hasActiveFilters = computed(() => Boolean(search.value || status.value !== 'open'))

function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value || ''), 10)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
}

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
    status: status.value,
    page: String(page.value),
    per_page: String(perPage.value),
  }

  if (search.value) {
    query.search = search.value
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
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load reports.'
  } finally {
    loading.value = false
  }
}

function updateRow(updated) {
  if (!data.value || !updated) return
  const reportRows = data.value.data || []
  const idx = reportRows.findIndex((r) => r.id === updated.id)
  if (idx >= 0) {
    reportRows[idx] = { ...reportRows[idx], ...updated }
  }
}

async function act(report, action) {
  if (!report?.id) return
  const ok = window.confirm(`Confirm ${action}?`)
  if (!ok) return

  loading.value = true
  error.value = ''

  try {
    const res = await api.post(`/admin/reports/${report.id}/${action}`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Action failed.'
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

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString()
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

onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce)
})

readQuery(route.query)
syncQueryWithState()
load()
</script>

<template>
  <AdminPageShell title="Reports" subtitle="Moderation queue (MVP).">
    <div v-if="error" class="adminAlert">
      {{ error }}
    </div>

    <AdminToolbar :loading="loading">
      <template #search>
        <label class="fieldLabel" for="reports-search">Search</label>
        <input
          id="reports-search"
          v-model="searchInput"
          :disabled="loading"
          type="search"
          placeholder="Search reports..."
          class="fieldInput"
        />
      </template>

      <template #filters>
        <div class="filtersRow">
          <div>
            <label class="fieldLabel" for="reports-status">Status</label>
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
            <label class="fieldLabel" for="reports-per-page">Per page</label>
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
        <button type="button" class="btn ghost" :disabled="loading" @click="refresh">Refresh</button>
      </template>
    </AdminToolbar>

    <AdminDataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      empty-title="No reports found"
      empty-description="Try adjusting your filters."
      :can-clear-filters="hasActiveFilters"
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
        {{ formatDate(row.created_at) }}
      </template>

      <template #[`cell(actions)`]="{ row }">
        <div class="rowActions">
          <button class="btn action" :disabled="loading" @click="act(row, 'hide')">Hide</button>
          <button class="btn action" :disabled="loading" @click="act(row, 'delete')">Delete</button>
          <button class="btn action subtle" :disabled="loading" @click="act(row, 'warn')">Warn</button>
          <button class="btn action" :disabled="loading" @click="act(row, 'ban')">Ban</button>
          <button class="btn action" :disabled="loading" @click="act(row, 'dismiss')">Dismiss</button>
        </div>
      </template>
    </AdminDataTable>

    <AdminPagination :meta="data" @page-change="page = $event" />
  </AdminPageShell>
</template>

<style scoped>
.adminAlert {
  color: var(--color-danger);
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-danger-rgb, 239 68 68) / 0.35);
  background: rgb(var(--color-danger-rgb, 239 68 68) / 0.08);
}

.fieldLabel {
  display: block;
  font-size: 12px;
  opacity: 0.8;
  margin-bottom: 6px;
}

.fieldInput {
  width: 100%;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  background: transparent;
  color: inherit;
}

.filtersRow {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.filtersRow > div {
  min-width: 160px;
}

.statusBadge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  text-transform: uppercase;
}

.rowActions {
  display: inline-flex;
  gap: 6px;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.btn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  padding: 6px 10px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.btn.subtle {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
