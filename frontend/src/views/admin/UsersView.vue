<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const error = ref('')
const searchInput = ref('')
const search = ref('')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)

let searchDebounce = null

const columns = [
  { key: 'name', label: 'Username' },
  { key: 'email', label: 'Email' },
  { key: 'role', label: 'Role' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Actions', align: 'right' },
]

const rows = computed(() => data.value?.data || [])
const hasActiveFilters = computed(() => Boolean(search.value))

function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value || ''), 10)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
}

function readQuery(query) {
  const qSearch = typeof query.search === 'string' ? query.search : ''
  const qPage = parsePositiveInt(query.page, 1)
  const qPerPage = parsePositiveInt(query.per_page, 20)

  searchInput.value = qSearch
  search.value = qSearch
  page.value = qPage
  perPage.value = qPerPage
}

function buildQuery() {
  const query = {
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
    page: String(parsePositiveInt(route.query.page, 1)),
    per_page: String(parsePositiveInt(route.query.per_page, 20)),
    search: typeof route.query.search === 'string' ? route.query.search : '',
  }
}

function syncQueryWithState() {
  const next = buildQuery()
  const now = currentQuery()

  if (
    next.page === now.page &&
    next.per_page === now.per_page &&
    (next.search || '') === now.search
  ) {
    return
  }

  router.replace({ query: next })
}

function statusLabel(user) {
  if (!user?.is_active) return 'inactive'
  if (user?.is_banned) return 'banned'
  return 'active'
}

function isSelf(user) {
  return auth.user && user && Number(auth.user.id) === Number(user.id)
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const params = {
      page: page.value,
      per_page: perPage.value,
    }

    if (search.value) {
      params.search = search.value
    }

    const res = await api.get('/admin/users', { params })
    data.value = res.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load users.'
  } finally {
    loading.value = false
  }
}

function updateRow(updated) {
  if (!data.value || !updated) return
  const userRows = data.value.data || []
  const idx = userRows.findIndex((u) => u.id === updated.id)
  if (idx >= 0) {
    userRows[idx] = { ...userRows[idx], ...updated }
  }
}

async function banUser(user) {
  if (!user || isSelf(user)) return
  const ok = window.confirm(`Ban user ${user.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/ban`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Ban failed.'
  }
}

async function unbanUser(user) {
  if (!user || isSelf(user)) return
  const ok = window.confirm(`Unban user ${user.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/unban`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Unban failed.'
  }
}

async function deactivateUser(user) {
  if (!user || isSelf(user) || !user.is_active) return
  const ok = window.confirm(`Deactivate user ${user.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/deactivate`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Deactivate failed.'
  }
}

async function resetProfile(user) {
  if (!user) return
  const ok = window.confirm(`Reset profile for ${user.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/reset-profile`)
    updateRow(res.data)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reset profile failed.'
  }
}

function clearFilters() {
  searchInput.value = ''
  search.value = ''
  page.value = 1
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

watch([search, page, perPage], () => {
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
  <AdminPageShell title="Users" subtitle="Admin user management (MVP).">
    <div v-if="error" class="adminAlert">
      {{ error }}
    </div>

    <AdminToolbar :loading="loading">
      <template #search>
        <label class="fieldLabel" for="users-search">Search</label>
        <input
          id="users-search"
          v-model="searchInput"
          :disabled="loading"
          type="search"
          placeholder="Search users..."
          class="fieldInput"
        />
      </template>

      <template #filters>
        <label class="fieldLabel" for="users-per-page">Per page</label>
        <select
          id="users-per-page"
          v-model.number="perPage"
          :disabled="loading"
          class="fieldInput"
          @change="page = 1"
        >
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
        </select>
      </template>

      <template #actions>
        <button type="button" class="btn ghost" :disabled="loading" @click="refresh">Refresh</button>
      </template>
    </AdminToolbar>

    <AdminDataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      empty-title="No users found"
      empty-description="Try adjusting your filters."
      :can-clear-filters="hasActiveFilters"
      @clear-filters="clearFilters"
    >
      <template #[`cell(name)`]="{ row }">
        <RouterLink :to="{ name: 'admin.users.detail', params: { id: row.id } }" class="userLink">
          {{ row.name || '-' }}
        </RouterLink>
      </template>

      <template #[`cell(status)`]="{ row }">
        <span class="statusBadge">{{ statusLabel(row) }}</span>
      </template>

      <template #[`cell(actions)`]="{ row }">
        <div class="rowActions">
          <RouterLink :to="{ name: 'admin.users.detail', params: { id: row.id } }" class="btn action">
            View
          </RouterLink>
          <button
            v-if="!row.is_banned"
            class="btn action"
            :disabled="loading || isSelf(row)"
            @click="banUser(row)"
          >
            Ban
          </button>
          <button
            v-else
            class="btn action"
            :disabled="loading || isSelf(row)"
            @click="unbanUser(row)"
          >
            Unban
          </button>
          <button
            class="btn action"
            :disabled="loading || isSelf(row) || !row.is_active"
            @click="deactivateUser(row)"
          >
            Deactivate
          </button>
          <button class="btn action subtle" :disabled="loading" @click="resetProfile(row)">
            Reset profile
          </button>
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

.userLink {
  color: inherit;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 2px;
}
</style>
