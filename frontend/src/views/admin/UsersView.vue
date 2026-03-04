<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { confirm, prompt } = useConfirm()
const toast = useToast()

const loading = ref(false)
const error = ref('')
const searchInput = ref('')
const search = ref('')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)
const lastLoadedAt = ref(null)

let searchDebounce = null

const rows = computed(() => data.value?.data || [])
const totalUsers = computed(() => Number(data.value?.total || 0))
const hasActiveFilters = computed(() => Boolean(search.value))
const showSkeleton = computed(() => loading.value && rows.value.length === 0)

const syncPillLabel = computed(() => {
  if (loading.value) return 'Syncing users'
  if (error.value) return 'Sync issue'
  if (!lastLoadedAt.value) return 'No sync yet'
  return `Updated ${formatRelative(lastLoadedAt.value)}`
})

const syncPillClass = computed(() => {
  if (loading.value) return 'is-loading'
  if (error.value) return 'is-error'
  return 'is-live'
})

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
    next.page === now.page
    && next.per_page === now.per_page
    && (next.search || '') === now.search
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

function banReasonPreview(user) {
  const reason = String(user?.ban_reason || '').trim()
  if (!reason) return ''
  if (reason.length <= 52) return reason
  return `${reason.slice(0, 52)}...`
}

function roleClass(user) {
  const role = String(user?.role || '').trim().toLowerCase()
  if (role === 'admin') return 'is-admin'
  if (role === 'moderator') return 'is-moderator'
  return 'is-user'
}

function roleLabel(user) {
  const role = String(user?.role || 'user').trim().toLowerCase()
  if (role === 'admin') return 'ADMIN'
  if (role === 'moderator') return 'MODERATOR'
  return 'USER'
}

function isSelf(user) {
  return auth.user && user && Number(auth.user.id) === Number(user.id)
}

function userName(user) {
  return String(user?.name || `User #${user?.id || '-'}`)
}

function userHandle(user) {
  if (user?.username) return `@${user.username}`
  return `ID: ${user?.id || '-'}`
}

function userEmail(user) {
  return String(user?.email || '-')
}

function formatRelative(value) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return 'just now'

  const diffMs = Math.max(0, Date.now() - value.getTime())
  const diffMinutes = Math.floor(diffMs / 60000)

  if (diffMinutes <= 0) return 'just now'
  if (diffMinutes < 60) return `${diffMinutes}m ago`

  const diffHours = Math.floor(diffMinutes / 60)
  if (diffHours < 24) return `${diffHours}h ago`

  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays}d ago`
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
    lastLoadedAt.value = new Date()
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

  const reason = await prompt({
    title: 'Ban user',
    message: `Provide ban reason for ${user.email}.`,
    confirmText: 'Ban user',
    cancelText: 'Cancel',
    placeholder: 'Ban reason...',
    required: true,
    multiline: true,
    variant: 'danger',
  })

  if (!reason) return

  try {
    const res = await api.patch(`/admin/users/${user.id}/ban`, { reason: String(reason).trim() })
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

function rowActionItems(user) {
  if (!user) return []

  const items = []

  if (!isSelf(user)) {
    if (user.is_banned) {
      items.push({ key: 'unban', label: 'Unban user' })
    } else {
      items.push({ key: 'ban', label: 'Ban user', danger: true })
    }

    if (user.is_active) {
      items.push({ key: 'deactivate', label: 'Deactivate user', danger: true })
    }
  }

  items.push({ key: 'reset', label: 'Reset profile', danger: true })

  return items
}

async function onRowActionSelect(user, item) {
  if (loading.value || !item?.key) return

  if (item.key === 'ban') {
    await banUser(user)
    return
  }

  if (item.key === 'unban') {
    await unbanUser(user)
    return
  }

  if (item.key === 'deactivate') {
    await deactivateUser(user)
    return
  }

  if (item.key === 'reset') {
    await resetProfile(user)
  }
}

function clearFilters() {
  searchInput.value = ''
  search.value = ''
  page.value = 1
}

function clearSearch() {
  if (!searchInput.value) return
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
  }, 350)
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
  <AdminPageShell
    class="usersPageShell"
    title="Users"
    subtitle="Manage community accounts, roles and account health."
  >
    <template #right-actions>
      <span class="usersSyncPill" :class="syncPillClass">
        <span class="usersSyncDot" aria-hidden="true"></span>
        <span>{{ syncPillLabel }}</span>
      </span>
    </template>

    <div class="usersView">
      <div v-if="error" class="usersErrorBanner" role="alert">
        <span>{{ error }}</span>
        <button type="button" class="errorRetryBtn" :disabled="loading" @click="refresh">Retry</button>
      </div>

      <div v-if="showSkeleton" class="usersSkeleton" aria-hidden="true">
        <div class="usersSkeletonToolbar">
          <span class="skeletonBlock is-wide"></span>
          <span class="skeletonBlock is-mid"></span>
          <span class="skeletonBlock is-short"></span>
        </div>
        <div class="usersSkeletonTable">
          <span v-for="idx in 8" :key="`skeleton-row-${idx}`" class="skeletonRow"></span>
        </div>
      </div>

      <template v-else>
        <AdminToolbar :loading="loading" class="usersToolbar">
          <template #search>
            <div class="toolbarField toolbarField--search">
              <label class="toolbarLabel" for="users-search">Search</label>
              <div class="searchInputWrap">
                <span class="searchIcon" aria-hidden="true">
                  <svg viewBox="0 0 20 20" fill="none">
                    <circle cx="9" cy="9" r="5.5" stroke="currentColor" stroke-width="1.4" />
                    <path d="M13 13L17 17" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                  </svg>
                </span>
                <input
                  id="users-search"
                  v-model="searchInput"
                  :disabled="loading"
                  type="search"
                  placeholder="Search users by name, handle or email"
                  class="toolbarInput"
                />
                <button
                  v-if="searchInput"
                  type="button"
                  class="searchClearBtn"
                  aria-label="Clear search"
                  :disabled="loading"
                  @click="clearSearch"
                >
                  <span aria-hidden="true">x</span>
                </button>
              </div>
            </div>
          </template>

          <template #filters>
            <div class="toolbarInlineField">
              <label class="toolbarLabel" for="users-per-page">Per page</label>
              <select
                id="users-per-page"
                v-model.number="perPage"
                :disabled="loading"
                class="toolbarInput toolbarInput--select"
                @change="page = 1"
              >
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
              </select>
            </div>
          </template>

          <template #actions>
            <button type="button" class="toolbarBtn" :disabled="loading" @click="refresh">
              <span class="toolbarBtnIcon" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none">
                  <path d="M16 5V9H12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M4 15V11H8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M15.2 9A6 6 0 0 0 5 5.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                  <path d="M4.8 11A6 6 0 0 0 15 14.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                </svg>
              </span>
              <span>Refresh</span>
            </button>
          </template>
        </AdminToolbar>

        <section class="usersTableWrap" aria-label="Users table">
          <div class="usersTableScroll">
            <table class="usersTable">
              <thead>
                <tr>
                  <th class="col-user">User</th>
                  <th class="col-email">Email</th>
                  <th class="col-role">Role</th>
                  <th class="col-status">Status</th>
                  <th class="col-actions">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!rows.length">
                  <td colspan="5" class="usersEmptyState">
                    <div class="usersEmptyTitle">No users found</div>
                    <div class="usersEmptyText">Try adjusting your filters.</div>
                    <button
                      v-if="hasActiveFilters"
                      type="button"
                      class="emptyClearBtn"
                      :disabled="loading"
                      @click="clearFilters"
                    >
                      Clear search filter
                    </button>
                  </td>
                </tr>

                <tr v-for="row in rows" v-else :key="row.id" class="usersRow" :data-row-id="row.id">
                  <td data-label="User" class="col-user">
                    <div class="userCell">
                      <div class="avatar" :aria-label="`Avatar ${userName(row)}`">
                        <UserAvatar class="avatarFallback" :user="row" :alt="`${userName(row)} avatar`" />
                      </div>
                      <div class="userMeta">
                        <RouterLink
                          :to="{ name: 'admin.users.detail', params: { id: row.id } }"
                          class="userLink"
                          :title="userName(row)"
                        >
                          {{ userName(row) }}
                        </RouterLink>
                        <span class="userSubline" :title="userHandle(row)">
                          {{ userHandle(row) }}
                        </span>
                      </div>
                    </div>
                  </td>

                  <td data-label="Email" class="col-email">
                    <span class="truncateText" :title="userEmail(row)">{{ userEmail(row) }}</span>
                  </td>

                  <td data-label="Role" class="col-role">
                    <span class="badge roleBadge" :class="roleClass(row)">{{ roleLabel(row) }}</span>
                  </td>

                  <td data-label="Status" class="col-status">
                    <div class="statusCell">
                      <span class="badge statusBadge" :class="statusClass(row)">
                        {{ statusLabel(row) }}
                      </span>
                      <span
                        v-if="row.is_banned && row.ban_reason"
                        class="banReasonPreview"
                        :title="row.ban_reason"
                      >
                        {{ banReasonPreview(row) }}
                      </span>
                    </div>
                  </td>

                  <td data-label="Actions" class="col-actions">
                    <div class="rowActions">
                      <RouterLink :to="{ name: 'admin.users.detail', params: { id: row.id } }" class="actionBtn actionBtn--view">
                        View
                      </RouterLink>

                      <DropdownMenu
                        :items="rowActionItems(row)"
                        :label="`More actions for ${userName(row)}`"
                        menu-label="User actions"
                        @select="onRowActionSelect(row, $event)"
                      >
                        <template #trigger>
                          <span class="rowMenuTrigger" aria-hidden="true">...</span>
                        </template>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <div class="usersPaginationFooter">
          <div class="usersPaginationMeta">Users total: {{ totalUsers }}</div>
          <AdminPagination :meta="data" @page-change="page = $event" />
        </div>
      </template>
    </div>
  </AdminPageShell>
</template>

<style scoped>
:deep(.usersPageShell.adminPageShell) {
  width: min(1160px, 100%);
  padding: 24px clamp(16px, 2.2vw, 28px) 20px;
}

:deep(.usersPageShell .adminPageShell__header) {
  position: sticky;
  top: 0;
  z-index: 6;
  margin-bottom: 16px;
  padding: 2px 0 12px;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  background: rgb(var(--color-bg-rgb) / 0.92);
  backdrop-filter: blur(8px);
}

:deep(.usersPageShell .adminPageShell__title) {
  margin-bottom: 4px;
  font-size: clamp(1.55rem, 2vw, 1.9rem);
  letter-spacing: -0.03em;
}

:deep(.usersPageShell .adminPageShell__subtitle) {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 13px;
}

:deep(.usersPageShell .adminPageShell__content) {
  gap: 16px;
}

.usersView {
  display: grid;
  gap: 14px;
}

.usersSyncPill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 999px;
  padding: 0 12px;
  font-size: 12px;
  font-weight: 600;
  color: rgb(var(--color-text-secondary-rgb) / 0.96);
  background: rgb(var(--color-bg-rgb) / 0.65);
}

.usersSyncDot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: rgb(59 130 246 / 0.9);
  box-shadow: 0 0 0 4px rgb(59 130 246 / 0.2);
}

.usersSyncPill.is-loading .usersSyncDot {
  background: rgb(245 158 11 / 0.95);
  box-shadow: 0 0 0 4px rgb(245 158 11 / 0.22);
}

.usersSyncPill.is-error .usersSyncDot {
  background: rgb(248 113 113 / 0.95);
  box-shadow: 0 0 0 4px rgb(248 113 113 / 0.24);
}

.usersErrorBanner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  border: 1px solid rgb(var(--color-danger-rgb, 239 68 68) / 0.34);
  border-radius: 12px;
  padding: 10px 12px;
  background: rgb(var(--color-danger-rgb, 239 68 68) / 0.1);
  color: rgb(var(--color-danger-rgb, 239 68 68));
}

.errorRetryBtn {
  min-height: 40px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 0 14px;
  background: rgb(var(--color-bg-rgb) / 0.62);
  color: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.errorRetryBtn:hover:not(:disabled) {
  background: rgb(var(--color-bg-rgb) / 0.76);
}

.usersSkeleton {
  display: grid;
  gap: 12px;
}

.usersSkeletonToolbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 120px 100px;
  gap: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  padding: 12px;
}

.skeletonBlock,
.skeletonRow {
  display: block;
  background: linear-gradient(
    90deg,
    rgb(var(--color-surface-rgb) / 0.05),
    rgb(var(--color-surface-rgb) / 0.14),
    rgb(var(--color-surface-rgb) / 0.05)
  );
  background-size: 220% 100%;
  animation: usersShimmer 1.2s linear infinite;
}

.skeletonBlock {
  height: 40px;
  border-radius: 11px;
}

.skeletonBlock.is-mid {
  width: 120px;
}

.skeletonBlock.is-short {
  width: 100px;
}

.usersSkeletonTable {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 14px;
  padding: 12px;
  display: grid;
  gap: 8px;
}

.skeletonRow {
  height: 44px;
  border-radius: 10px;
}

.usersToolbar {
  border-color: rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 14px;
  background: rgb(var(--color-bg-rgb) / 0.42);
  padding: 12px;
  align-items: flex-end;
}

.toolbarField {
  min-width: 0;
}

.toolbarField--search {
  min-width: min(420px, 100%);
}

.toolbarInlineField {
  min-width: 130px;
}

.toolbarLabel {
  display: inline-flex;
  align-items: center;
  min-height: 18px;
  margin-bottom: 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
}

.searchInputWrap {
  position: relative;
  display: flex;
  align-items: center;
}

.searchIcon {
  position: absolute;
  left: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  pointer-events: none;
}

.searchIcon svg,
.toolbarBtnIcon svg {
  width: 100%;
  height: 100%;
}

.toolbarInput {
  width: 100%;
  min-height: 42px;
  border-radius: 11px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  background: rgb(var(--color-bg-rgb) / 0.52);
  color: var(--text-primary);
  padding: 0 12px;
  transition: border-color 150ms ease, box-shadow 150ms ease;
}

.toolbarField--search .toolbarInput {
  padding-left: 34px;
  padding-right: 40px;
}

.toolbarInput--select {
  min-width: 108px;
  appearance: none;
  background-image: linear-gradient(45deg, transparent 50%, rgb(var(--color-text-secondary-rgb) / 0.9) 50%),
    linear-gradient(135deg, rgb(var(--color-text-secondary-rgb) / 0.9) 50%, transparent 50%);
  background-position: calc(100% - 16px) calc(50% - 2px), calc(100% - 11px) calc(50% - 2px);
  background-size: 5px 5px, 5px 5px;
  background-repeat: no-repeat;
  padding-right: 30px;
}

.toolbarInput:focus-visible,
.toolbarBtn:focus-visible,
.actionBtn:focus-visible,
.errorRetryBtn:focus-visible,
.emptyClearBtn:focus-visible,
.searchClearBtn:focus-visible {
  outline: none;
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
}

.searchClearBtn {
  position: absolute;
  right: 8px;
  width: 24px;
  height: 24px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.75);
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  display: grid;
  place-items: center;
  cursor: pointer;
  font-size: 12px;
  line-height: 1;
}

.toolbarBtn {
  min-height: 42px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 11px;
  padding: 0 14px;
  background: rgb(var(--color-bg-rgb) / 0.55);
  color: var(--text-primary);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.toolbarBtnIcon {
  width: 14px;
  height: 14px;
  display: inline-flex;
}

.toolbarBtn:disabled,
.searchClearBtn:disabled,
.errorRetryBtn:disabled,
.emptyClearBtn:disabled,
.actionBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.usersTableWrap {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.11);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.4);
  overflow: hidden;
}

.usersTableScroll {
  overflow-x: hidden;
  overflow-y: auto;
  max-height: min(64vh, 700px);
}

.usersTable {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.usersTable th,
.usersTable td {
  padding: 9px 12px;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.08);
  vertical-align: middle;
  text-align: left;
}

.usersTable th {
  position: sticky;
  top: 0;
  z-index: 2;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  background: rgb(var(--color-bg-rgb) / 0.94);
  backdrop-filter: blur(8px);
}

.usersTable tbody tr:last-child td {
  border-bottom: 0;
}

.usersRow {
  transition: background-color 150ms ease;
}

.usersRow:hover {
  background: rgb(var(--color-surface-rgb) / 0.04);
}

.col-user {
  width: 32%;
}

.col-email {
  width: 26%;
}

.col-role {
  width: 12%;
}

.col-status {
  width: 16%;
}

.col-actions {
  width: 14%;
  text-align: right;
}

.userCell {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.avatar {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  background: rgb(var(--color-surface-rgb) / 0.1);
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.avatarFallback {
  font-size: 10px;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.userMeta {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.userLink {
  color: inherit;
  font-weight: 600;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.userLink:hover {
  color: rgb(var(--color-primary-rgb) / 0.98);
}

.userSubline {
  font-size: 11px;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.truncateText {
  display: inline-block;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 21px;
  padding: 0 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.roleBadge.is-admin {
  color: rgb(147 197 253);
  border-color: rgb(59 130 246 / 0.4);
  background: rgb(59 130 246 / 0.18);
}

.roleBadge.is-moderator {
  color: rgb(253 224 71);
  border-color: rgb(245 158 11 / 0.42);
  background: rgb(245 158 11 / 0.16);
}

.roleBadge.is-user {
  color: rgb(203 213 225);
  border-color: rgb(148 163 184 / 0.34);
  background: rgb(148 163 184 / 0.13);
}

.statusCell {
  display: grid;
  gap: 3px;
  justify-items: start;
}

.statusBadge {
  text-transform: uppercase;
}

.statusBadge.is-active {
  color: rgb(134 239 172);
  border-color: rgb(34 197 94 / 0.42);
  background: rgb(34 197 94 / 0.16);
}

.statusBadge.is-inactive {
  color: rgb(203 213 225);
  border-color: rgb(100 116 139 / 0.36);
  background: rgb(100 116 139 / 0.16);
}

.statusBadge.is-banned {
  color: rgb(252 165 165);
  border-color: rgb(239 68 68 / 0.4);
  background: rgb(239 68 68 / 0.17);
}

.banReasonPreview {
  max-width: 240px;
  font-size: 11px;
  color: rgb(var(--color-text-secondary-rgb) / 0.78);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.rowActions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.actionBtn {
  min-height: 34px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 9px;
  padding: 0 10px;
  background: rgb(var(--color-bg-rgb) / 0.6);
  color: var(--text-primary);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.actionBtn--view {
  border-color: rgb(var(--color-primary-rgb) / 0.34);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.actionBtn:hover:not(:disabled) {
  border-color: rgb(var(--color-primary-rgb) / 0.48);
}

.rowMenuTrigger {
  width: 34px;
  height: 34px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgb(var(--color-bg-rgb) / 0.58);
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-size: 15px;
  line-height: 1;
}

:deep(.rowActions .dropdownTrigger) {
  border: 0;
  padding: 0;
  background: transparent;
}

:deep(.rowActions .dropdownTrigger:hover) {
  background: transparent;
  color: inherit;
}

:deep(.rowActions .dropdownTrigger:focus-visible) {
  outline: none;
}

:deep(.rowActions .dropdownMenu) {
  right: 0;
  border-color: rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.98);
}

.usersEmptyState {
  padding: 36px 20px;
  text-align: center;
}

.usersEmptyTitle {
  font-size: 16px;
  font-weight: 600;
}

.usersEmptyText {
  margin-top: 4px;
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.emptyClearBtn {
  margin-top: 12px;
  min-height: 40px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  padding: 0 14px;
  background: rgb(var(--color-bg-rgb) / 0.58);
  color: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.usersPaginationFooter {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  padding-top: 10px;
}

.usersPaginationMeta {
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

:deep(.usersPaginationFooter .adminPagination) {
  margin-left: auto;
  width: auto;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  padding: 6px 8px;
  background: rgb(var(--color-bg-rgb) / 0.45);
}

:deep(.usersPaginationFooter .adminPagination__btn) {
  min-height: 34px;
  min-width: 76px;
}

@media (max-width: 980px) {
  .usersTable,
  .usersTable thead,
  .usersTable tbody,
  .usersTable tr,
  .usersTable th,
  .usersTable td {
    display: block;
    width: 100%;
  }

  .usersTable thead {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
  }

  .usersTable tbody {
    display: grid;
    gap: 8px;
    padding: 8px;
  }

  .usersTable tr {
    border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
    border-radius: 12px;
    background: rgb(var(--color-bg-rgb) / 0.5);
  }

  .usersTable td {
    border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.08);
    padding: 10px 12px;
    display: grid;
    grid-template-columns: minmax(86px, 110px) minmax(0, 1fr);
    align-items: start;
    gap: 10px;
  }

  .usersTable td:last-child {
    border-bottom: 0;
  }

  .usersTable td::before {
    content: attr(data-label);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: rgb(var(--color-text-secondary-rgb) / 0.78);
    padding-top: 3px;
  }

  .col-actions,
  .usersTable td.col-actions {
    text-align: left;
  }

  .rowActions {
    justify-content: flex-start;
  }

  .banReasonPreview {
    max-width: 100%;
  }
}

@media (max-width: 768px) {
  :deep(.usersPageShell.adminPageShell) {
    padding: 16px 12px;
  }

  :deep(.usersPageShell .adminPageShell__header) {
    top: 0;
    margin-bottom: 12px;
    padding-bottom: 10px;
  }

  .usersErrorBanner {
    flex-direction: column;
    align-items: flex-start;
  }

  .usersSkeletonToolbar {
    grid-template-columns: 1fr;
  }

  .skeletonBlock.is-mid,
  .skeletonBlock.is-short {
    width: 100%;
  }

  .toolbarField--search,
  .toolbarInlineField {
    min-width: 100%;
  }

  .usersPaginationFooter {
    flex-direction: column;
    align-items: stretch;
  }

  :deep(.usersPaginationFooter .adminPagination) {
    margin-left: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .skeletonBlock,
  .skeletonRow {
    animation: none;
  }
}

@keyframes usersShimmer {
  0% {
    background-position: 220% 0;
  }

  100% {
    background-position: -220% 0;
  }
}
</style>
