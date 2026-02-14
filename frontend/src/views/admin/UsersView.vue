<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const loading = ref(false)
const error = ref('')
const searchInput = ref('')
const search = ref('')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)

let searchDebounce = null

const columns = [
  { key: 'name', label: 'User' },
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

function statusClass(user) {
  const status = statusLabel(user)
  if (status === 'active') return 'is-active'
  if (status === 'banned') return 'is-banned'
  return 'is-inactive'
}

function roleClass(user) {
  const role = String(user?.role || '').toLowerCase()
  if (role === 'admin') return 'is-admin'
  if (role === 'moderator') return 'is-moderator'
  return 'is-member'
}

function initials(name) {
  const value = String(name || '').trim()
  if (!value) return '?'
  const parts = value.split(/\s+/).filter(Boolean)
  return parts.slice(0, 2).map((part) => part[0].toUpperCase()).join('')
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
  const ok = await confirm({
    title: 'Ban user',
    message: `Ban user ${user.email}?`,
    confirmText: 'Ban',
    cancelText: 'Cancel',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/ban`)
    updateRow(res.data)
    toast.success('User banned.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Ban failed.'
    toast.error(error.value)
  }
}

async function unbanUser(user) {
  if (!user || isSelf(user)) return
  const ok = await confirm({
    title: 'Unban user',
    message: `Unban user ${user.email}?`,
    confirmText: 'Unban',
    cancelText: 'Cancel',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/unban`)
    updateRow(res.data)
    toast.success('User unbanned.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Unban failed.'
    toast.error(error.value)
  }
}

async function deactivateUser(user) {
  if (!user || isSelf(user) || !user.is_active) return
  const ok = await confirm({
    title: 'Deactivate user',
    message: `Deactivate user ${user.email}?`,
    confirmText: 'Deactivate',
    cancelText: 'Cancel',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/deactivate`)
    updateRow(res.data)
    toast.success('User deactivated.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Deactivate failed.'
    toast.error(error.value)
  }
}

async function resetProfile(user) {
  if (!user) return
  const ok = await confirm({
    title: 'Reset profile',
    message: `Reset profile for ${user.email}?`,
    confirmText: 'Reset',
    cancelText: 'Cancel',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/reset-profile`)
    updateRow(res.data)
    toast.success('Profile reset done.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reset profile failed.'
    toast.error(error.value)
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
        <div class="userCell">
          <div class="avatar" :aria-label="`Avatar ${row.name || 'user ' + row.id}`">
            <img v-if="row.avatar_url" :src="row.avatar_url" :alt="row.name || 'User avatar'" class="avatarImg" />
            <span v-else class="avatarFallback">{{ initials(row.name) }}</span>
          </div>
          <div class="userMeta">
            <RouterLink :to="{ name: 'admin.users.detail', params: { id: row.id } }" class="userLink">
              {{ row.name || '-' }}
            </RouterLink>
            <div class="userSubline">
              {{ row.username ? `@${row.username}` : `ID: ${row.id}` }}
            </div>
          </div>
        </div>
      </template>

      <template #[`cell(role)`]="{ row }">
        <span class="badge roleBadge" :class="roleClass(row)">{{ row.role || '-' }}</span>
      </template>

      <template #[`cell(status)`]="{ row }">
        <span class="badge statusBadge" :class="statusClass(row)">{{ statusLabel(row) }}</span>
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

.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 12px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  text-transform: uppercase;
  letter-spacing: 0.02em;
  font-weight: 600;
}

.statusBadge.is-active {
  color: rgb(21 128 61);
  border-color: rgb(34 197 94 / 0.35);
  background: rgb(34 197 94 / 0.12);
}

.statusBadge.is-banned {
  color: rgb(185 28 28);
  border-color: rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.12);
}

.statusBadge.is-inactive {
  color: rgb(71 85 105);
  border-color: rgb(100 116 139 / 0.35);
  background: rgb(100 116 139 / 0.12);
}

.roleBadge {
  text-transform: none;
}

.roleBadge.is-admin {
  color: rgb(30 64 175);
  border-color: rgb(59 130 246 / 0.35);
  background: rgb(59 130 246 / 0.12);
}

.roleBadge.is-moderator {
  color: rgb(146 64 14);
  border-color: rgb(245 158 11 / 0.35);
  background: rgb(245 158 11 / 0.12);
}

.roleBadge.is-member {
  color: rgb(75 85 99);
  border-color: rgb(107 114 128 / 0.35);
  background: rgb(107 114 128 / 0.12);
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

.userCell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.avatar {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  overflow: hidden;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgb(var(--color-surface-rgb) / 0.12);
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
}

.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.avatarFallback {
  font-size: 11px;
  font-weight: 700;
  opacity: 0.85;
}

.userMeta {
  min-width: 0;
}

.userSubline {
  font-size: 12px;
  opacity: 0.75;
  margin-top: 2px;
}
</style>
