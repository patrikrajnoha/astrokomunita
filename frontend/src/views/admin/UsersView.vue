<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
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
let searchDebounce = null

const rows = computed(() => data.value?.data || [])
const totalUsers = computed(() => Number(data.value?.total || 0))
const hasActiveFilters = computed(() => Boolean(search.value))
const showSkeleton = computed(() => loading.value && rows.value.length === 0)
const isCurrentActorAdmin = computed(() => Boolean(auth.isAdmin))
const shouldShowPagination = computed(() => Number(data.value?.last_page || 1) > 1)

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
  if (role === 'editor') return 'is-editor'
  if (role === 'bot') return 'is-bot'
  return 'is-user'
}

function roleLabel(user) {
  const role = String(user?.role || 'user').trim().toLowerCase()
  if (role === 'admin') return 'admin'
  if (role === 'editor') return 'editor'
  if (role === 'bot') return 'bot'
  return 'user'
}

function isBotAccount(user) {
  return Boolean(user?.is_bot) || String(user?.role || '').trim().toLowerCase() === 'bot'
}

function isAdminAccount(user) {
  return String(user?.role || '').trim().toLowerCase() === 'admin' || Boolean(user?.is_admin)
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
  if (isBotAccount(user)) {
    return '-'
  }

  return String(user?.email || '-')
}

function botAccountHint() {
  return 'Automatizovany ucet - e-mail je zamerne prazdny.'
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
    error.value = e?.response?.data?.message || 'Nepodarilo sa nacitat pouzivatelov.'
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

function openUserDetail(user) {
  if (!user?.id) return

  router.push({
    name: 'admin.users.detail',
    params: { id: user.id },
  })
}

function openPublicProfile(user) {
  if (!user?.username) return

  router.push({
    name: 'user-profile',
    params: { username: user.username },
  })
}

async function banUser(user) {
  if (!user || isSelf(user)) return

  const reason = await prompt({
    title: 'Zablokovat pouzivatela',
    message: `Zadajte dovod blokacie pre ${user.email}.`,
    confirmText: 'Zablokovat',
    cancelText: 'Zrusit',
    placeholder: 'Dovod blokacie...',
    required: true,
    multiline: true,
    variant: 'danger',
  })

  if (!reason) return

  try {
    const res = await api.patch(`/admin/users/${user.id}/ban`, { reason: String(reason).trim() })
    updateRow(res.data)
    toast.success('Pouzivatel bol zablokovany.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Blokovanie zlyhalo.'
    toast.error(error.value)
  }
}

async function unbanUser(user) {
  if (!user || isSelf(user)) return

  const ok = await confirm({
    title: 'Odblokovat pouzivatela',
    message: `Odblokovat pouzivatela ${user.email}?`,
    confirmText: 'Odblokovat',
    cancelText: 'Zrusit',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/unban`)
    updateRow(res.data)
    toast.success('Pouzivatel bol odblokovany.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Odblokovanie zlyhalo.'
    toast.error(error.value)
  }
}

async function deactivateUser(user) {
  if (!user || isSelf(user) || !user.is_active) return

  const ok = await confirm({
    title: 'Deaktivovat pouzivatela',
    message: `Deaktivovat pouzivatela ${user.email}?`,
    confirmText: 'Deaktivovat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/deactivate`)
    updateRow(res.data)
    toast.success('Pouzivatel bol deaktivovany.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Deaktivacia zlyhala.'
    toast.error(error.value)
  }
}

async function reactivateUser(user) {
  if (!user || isSelf(user) || user.is_active) return

  const ok = await confirm({
    title: 'Reaktivovat pouzivatela',
    message: `Reaktivovat pouzivatela ${user.email || userName(user)}?`,
    confirmText: 'Reaktivovat',
    cancelText: 'Zrusit',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/reactivate`)
    updateRow(res.data)
    toast.success('Pouzivatel bol reaktivovany.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reaktivacia zlyhala.'
    toast.error(error.value)
  }
}

async function resetProfile(user) {
  if (!user) return

  const ok = await confirm({
    title: 'Resetovat profil',
    message: `Resetovat profil pre ${user.email}?`,
    confirmText: 'Resetovat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/reset-profile`)
    updateRow(res.data)
    toast.success('Profil bol resetovany.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reset profilu zlyhal.'
    toast.error(error.value)
  }
}

async function updateEditorRole(user, nextRole) {
  if (!user || !isCurrentActorAdmin.value) return

  try {
    const res = await api.patch(`/admin/users/${user.id}/role`, { role: nextRole })
    updateRow(res.data)
    toast.success(nextRole === 'editor' ? 'Rola editora pridana.' : 'Rola editora odobrata.')
  } catch (e) {
    const status = Number(e?.response?.status || 0)
    if (status === 403) {
      error.value = 'Nemate opravnenie menit roly.'
    } else if (status === 422) {
      error.value = e?.response?.data?.message || 'Zmena roly je neplatna.'
    } else {
      error.value = e?.response?.data?.message || 'Zmena roly zlyhala.'
    }
    toast.error(error.value)
  }
}

function rowActionItems(user) {
  if (!user) return []

  const items = []
  const isBot = isBotAccount(user)
  const isAdminTarget = isAdminAccount(user)
  const canManageAccount = isCurrentActorAdmin.value && !isSelf(user) && !isAdminTarget

  if (user.username) {
    items.push({ key: 'view', label: 'Zobrazit profil' })
  }

  items.push({ key: 'manage', label: 'Sprava uctu' })

  const targetRole = String(user.role || '').toLowerCase()
  const canToggleEditorRole = canManageAccount && targetRole !== 'bot' && targetRole !== 'admin'

  if (canToggleEditorRole) {
    if (targetRole === 'user') {
      items.push({ key: 'grant-editor', label: 'Pridat rolu editor' })
    } else if (targetRole === 'editor') {
      items.push({ key: 'remove-editor', label: 'Odobrat rolu editor' })
    }
  }

  if (canManageAccount) {
    if (user.is_banned) {
      items.push({ key: 'unban', label: 'Odblokovat ucet' })
    } else {
      items.push({ key: 'ban', label: 'Zablokovat ucet', danger: true })
    }

    if (user.is_active) {
      items.push({ key: 'deactivate', label: 'Deaktivovat ucet', danger: true })
    } else {
      items.push({ key: 'reactivate', label: 'Reaktivovat ucet' })
    }
    items.push({ key: 'reset', label: 'Resetovat profil', danger: true })
  }

  return items
}

async function onRowActionSelect(user, item) {
  if (loading.value || !item?.key) return

  if (item.key === 'view') {
    openPublicProfile(user)
    return
  }

  if (item.key === 'manage') {
    openUserDetail(user)
    return
  }

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

  if (item.key === 'reactivate') {
    await reactivateUser(user)
    return
  }

  if (item.key === 'reset') {
    await resetProfile(user)
    return
  }

  if (item.key === 'grant-editor') {
    await updateEditorRole(user, 'editor')
    return
  }

  if (item.key === 'remove-editor') {
    await updateEditorRole(user, 'user')
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

function retryLoad() {
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
  <AdminPageShell class="usersPageShell" title="Pouzivatelia">
    <div class="usersView">
      <div v-if="error" class="usersErrorBanner" role="alert">
        <span>{{ error }}</span>
        <button type="button" class="errorRetryBtn" :disabled="loading" @click="retryLoad">Skusit znova</button>
      </div>

      <div v-if="showSkeleton" class="usersSkeleton" aria-hidden="true">
        <div class="usersSkeletonToolbar">
          <span class="skeletonBlock is-wide"></span>
          <span class="skeletonBlock is-short"></span>
        </div>
        <div class="usersSkeletonTable">
          <span v-for="idx in 8" :key="`skeleton-row-${idx}`" class="skeletonRow"></span>
        </div>
      </div>

      <template v-else>
        <section class="usersFilters" aria-label="Filtre pouzivatelov">
          <label class="usersFiltersLabel" for="users-search">Search users...</label>
          <div class="usersFiltersRow">
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
                placeholder="Search users..."
                class="usersSearchInput"
              />
              <button
                v-if="searchInput"
                type="button"
                class="searchClearBtn"
                aria-label="Vymazat hladanie"
                :disabled="loading"
                @click="clearSearch"
              >
                <span aria-hidden="true">x</span>
              </button>
            </div>

            <select
              id="users-per-page"
              v-model.number="perPage"
              :disabled="loading"
              class="perPageSelect"
              @change="page = 1"
            >
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
        </section>

        <section class="usersTableWrap" aria-label="Tabulka pouzivatelov">
          <div class="usersTableScroll">
            <table class="usersTable">
              <thead>
                <tr>
                  <th class="col-user">Pouzivatel</th>
                  <th class="col-email">E-mail</th>
                  <th class="col-role">Rola</th>
                  <th class="col-status">Stav</th>
                  <th class="col-actions">Akcie</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!rows.length">
                  <td colspan="5" class="usersEmptyState">
                    <div class="usersEmptyTitle">Nenasli sa ziadni pouzivatelia</div>
                    <div class="usersEmptyText">Skuste upravit filtre.</div>
                    <button
                      v-if="hasActiveFilters"
                      type="button"
                      class="emptyClearBtn"
                      :disabled="loading"
                      @click="clearFilters"
                    >
                      Vycistit hladanie
                    </button>
                  </td>
                </tr>

                <tr
                  v-for="row in rows"
                  v-else
                  :key="row.id"
                  class="usersRow"
                  :data-row-id="row.id"
                  tabindex="0"
                  role="link"
                  @click="openUserDetail(row)"
                  @keydown.enter.prevent="openUserDetail(row)"
                  @keydown.space.prevent="openUserDetail(row)"
                >
                  <td data-label="Pouzivatel" class="col-user">
                    <div class="userCell">
                      <RouterLink
                        :to="{ name: 'admin.users.detail', params: { id: row.id } }"
                        class="avatarLink"
                        :aria-label="`Avatar ${userName(row)}`"
                        @click.stop
                      >
                        <div class="avatar">
                          <UserAvatar class="avatarFallback" :user="row" :alt="`${userName(row)} avatar`" />
                        </div>
                      </RouterLink>
                      <div class="userMeta">
                        <RouterLink
                          :to="{ name: 'admin.users.detail', params: { id: row.id } }"
                          class="userLink"
                          :title="userName(row)"
                          @click.stop
                        >
                          {{ userName(row) }}
                        </RouterLink>
                        <RouterLink
                          v-if="row.username"
                          :to="{ name: 'user-profile', params: { username: row.username } }"
                          class="userSubline userSublineLink"
                          :title="userHandle(row)"
                          @click.stop
                        >
                          {{ userHandle(row) }}
                        </RouterLink>
                        <span v-else class="userSubline" :title="userHandle(row)">
                          {{ userHandle(row) }}
                        </span>
                      </div>
                    </div>
                  </td>

                  <td data-label="E-mail" class="col-email">
                    <div class="emailCell">
                      <span class="truncateText" :title="userEmail(row)">{{ userEmail(row) }}</span>
                      <span
                        v-if="isBotAccount(row)"
                        class="botAccountHint"
                        :title="botAccountHint()"
                      >
                        (bot ucet)
                      </span>
                    </div>
                  </td>

                  <td data-label="Rola" class="col-role">
                    <span class="badge roleBadge" :class="roleClass(row)">{{ roleLabel(row) }}</span>
                  </td>

                  <td data-label="Stav" class="col-status">
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

                  <td data-label="Akcie" class="col-actions" @click.stop>
                    <div class="rowActions">
                      <DropdownMenu
                        :items="rowActionItems(row)"
                        :label="`Akcie pre ${userName(row)}`"
                        menu-label="Akcie pouzivatela"
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

        <div v-if="shouldShowPagination" class="usersPaginationFooter">
          <div class="usersPaginationMeta">Spolu pouzivatelov: {{ totalUsers }}</div>
          <AdminPagination :meta="data" @page-change="page = $event" />
        </div>
      </template>
    </div>
  </AdminPageShell>
</template>

<style scoped>
:deep(.usersPageShell.adminPageShell) {
  width: min(1140px, 100%);
  padding: 20px clamp(14px, 2vw, 24px);
}

:deep(.usersPageShell .adminPageShell__header) {
  margin-bottom: 14px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgb(var(--color-bg-light-rgb) / 0.45);
}

:deep(.usersPageShell .adminPageShell__content) {
  gap: 14px;
}

.usersView {
  display: grid;
  gap: 12px;
}

.usersErrorBanner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  border: 1px solid rgb(var(--color-danger-rgb) / 0.34);
  border-radius: 10px;
  padding: 10px 12px;
  background: rgb(var(--color-danger-rgb) / 0.1);
  color: var(--color-danger);
}

.errorRetryBtn {
  min-height: 34px;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.64);
  border-radius: 999px;
  padding: 0 12px;
  background: rgb(var(--color-bg-main-rgb) / 0.65);
  color: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.usersSkeleton {
  display: grid;
  gap: 10px;
}

.usersSkeletonToolbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 80px;
  gap: 8px;
}

.skeletonBlock,
.skeletonRow {
  display: block;
  background: linear-gradient(
    90deg,
    rgb(var(--color-bg-light-rgb) / 0.16),
    rgb(var(--color-bg-light-rgb) / 0.28),
    rgb(var(--color-bg-light-rgb) / 0.16)
  );
  background-size: 220% 100%;
  animation: usersShimmer 1.2s linear infinite;
}

.skeletonBlock {
  height: 38px;
  border-radius: 10px;
}

.skeletonBlock.is-short {
  width: 80px;
}

.usersSkeletonTable {
  display: grid;
  gap: 8px;
}

.skeletonRow {
  height: 42px;
  border-radius: 10px;
}

.usersFilters {
  display: grid;
  gap: 6px;
}

.usersFiltersLabel {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.usersFiltersRow {
  display: flex;
  align-items: center;
  gap: 8px;
}

.searchInputWrap {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1 1 auto;
}

.searchIcon {
  position: absolute;
  left: 11px;
  width: 15px;
  height: 15px;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
  pointer-events: none;
}

.searchIcon svg {
  width: 100%;
  height: 100%;
}

.usersSearchInput,
.perPageSelect {
  min-height: 36px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.58);
  background: rgb(var(--color-bg-surface-rgb) / 0.72);
  color: var(--text-primary);
  transition: border-color 150ms ease, box-shadow 150ms ease;
}

.usersSearchInput {
  width: 100%;
  padding: 0 36px 0 34px;
}

.perPageSelect {
  width: 74px;
  padding: 0 28px 0 10px;
  appearance: none;
  background-image:
    linear-gradient(45deg, transparent 50%, rgb(var(--color-text-secondary-rgb) / 0.9) 50%),
    linear-gradient(135deg, rgb(var(--color-text-secondary-rgb) / 0.9) 50%, transparent 50%);
  background-position: calc(100% - 14px) calc(50% - 2px), calc(100% - 9px) calc(50% - 2px);
  background-size: 5px 5px, 5px 5px;
  background-repeat: no-repeat;
}

.usersSearchInput:focus-visible,
.perPageSelect:focus-visible,
.searchClearBtn:focus-visible,
.errorRetryBtn:focus-visible,
.emptyClearBtn:focus-visible {
  outline: none;
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
}

.searchClearBtn {
  position: absolute;
  right: 8px;
  width: 22px;
  height: 22px;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.66);
  border-radius: 999px;
  background: rgb(var(--color-bg-main-rgb) / 0.75);
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  display: grid;
  place-items: center;
  cursor: pointer;
  font-size: 11px;
}

.searchClearBtn:disabled,
.errorRetryBtn:disabled,
.emptyClearBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.usersTableWrap {
  background: transparent;
}

.usersTableScroll {
  overflow-x: auto;
  overflow-y: auto;
  max-height: min(65vh, 700px);
}

.usersTable {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.usersTable th,
.usersTable td {
  padding: 9px 10px;
  border-bottom: 1px solid rgb(var(--color-bg-light-rgb) / 0.34);
  vertical-align: middle;
  text-align: left;
}

.usersTable th {
  position: sticky;
  top: 0;
  z-index: 2;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.75);
  background: rgb(var(--color-bg-main-rgb) / 0.96);
}

.usersTable tbody tr:last-child td {
  border-bottom: 0;
}

.usersRow {
  cursor: pointer;
  transition: background-color 140ms ease;
}

.usersRow:hover,
.usersRow:focus-visible {
  background: rgb(var(--color-bg-light-rgb) / 0.16);
}

.col-user {
  width: 34%;
}

.col-email {
  width: 27%;
}

.col-role {
  width: 12%;
}

.col-status {
  width: 16%;
}

.col-actions {
  width: 11%;
  text-align: right;
}

.userCell {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.avatarLink {
  display: inline-flex;
  border-radius: 999px;
}

.avatar {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.58);
  background: rgb(var(--color-bg-light-rgb) / 0.24);
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
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
  color: var(--color-text-primary);
  font-weight: 600;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-decoration: none;
}

.userSubline {
  font-size: 11px;
  color: rgb(var(--color-text-secondary-rgb) / 0.86);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.userSublineLink {
  text-decoration: none;
}

.userLink:hover,
.userSublineLink:hover {
  color: rgb(var(--color-primary-rgb) / 0.98);
}

.truncateText {
  display: inline-block;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.emailCell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.botAccountHint {
  display: inline-flex;
  align-items: center;
  min-height: 18px;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.55);
  border-radius: 999px;
  padding: 0 6px;
  font-size: 10px;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
  white-space: nowrap;
}

.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 18px;
  padding: 0 6px;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.55);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0;
  line-height: 1;
  text-transform: lowercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  background: rgb(var(--color-bg-light-rgb) / 0.2);
}

.roleBadge.is-admin {
  color: rgb(var(--color-primary-rgb) / 0.95);
  border-color: rgb(var(--color-primary-rgb) / 0.36);
  background: rgb(var(--color-primary-rgb) / 0.14);
}

.roleBadge.is-editor {
  color: rgb(var(--color-text-primary-rgb) / 0.86);
  border-color: rgb(var(--color-bg-light-rgb) / 0.7);
  background: rgb(var(--color-bg-light-rgb) / 0.28);
}

.roleBadge.is-bot {
  color: rgb(var(--color-success-rgb) / 0.95);
  border-color: rgb(var(--color-success-rgb) / 0.34);
  background: rgb(var(--color-success-rgb) / 0.12);
}

.statusCell {
  display: grid;
  gap: 3px;
  justify-items: start;
}

.statusBadge.is-active {
  color: rgb(var(--color-success-rgb) / 0.95);
  border-color: rgb(var(--color-success-rgb) / 0.34);
  background: rgb(var(--color-success-rgb) / 0.12);
}

.statusBadge.is-inactive {
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  border-color: rgb(var(--color-bg-light-rgb) / 0.62);
  background: rgb(var(--color-bg-light-rgb) / 0.2);
}

.statusBadge.is-banned {
  color: rgb(var(--color-danger-rgb) / 0.98);
  border-color: rgb(var(--color-danger-rgb) / 0.35);
  background: rgb(var(--color-danger-rgb) / 0.13);
}

.banReasonPreview {
  max-width: 220px;
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
}

.rowMenuTrigger {
  width: 32px;
  height: 32px;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.62);
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgb(var(--color-bg-surface-rgb) / 0.78);
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
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

:deep(.rowActions .dropdownMenu) {
  right: 0;
  border-color: rgb(var(--color-bg-light-rgb) / 0.58);
  background: rgb(var(--color-bg-surface-rgb) / 0.98);
}

.usersEmptyState {
  padding: 34px 20px;
  text-align: center;
}

.usersEmptyTitle {
  font-size: 15px;
  font-weight: 600;
}

.usersEmptyText {
  margin-top: 4px;
  font-size: 13px;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.emptyClearBtn {
  margin-top: 12px;
  min-height: 34px;
  border: 1px solid rgb(var(--color-bg-light-rgb) / 0.64);
  border-radius: 999px;
  padding: 0 12px;
  background: rgb(var(--color-bg-surface-rgb) / 0.74);
  color: var(--color-text-primary);
  font-size: 12px;
  cursor: pointer;
}

.usersPaginationFooter {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.usersPaginationMeta {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.86);
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
  }

  .usersTable tr {
    border: 1px solid rgb(var(--color-bg-light-rgb) / 0.46);
    border-radius: 12px;
    background: rgb(var(--color-bg-surface-rgb) / 0.72);
  }

  .usersTable td {
    border-bottom: 1px solid rgb(var(--color-bg-light-rgb) / 0.34);
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
    color: rgb(var(--color-text-secondary-rgb) / 0.74);
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
    padding: 14px 12px;
  }

  .usersErrorBanner {
    flex-direction: column;
    align-items: flex-start;
  }

  .usersFiltersRow {
    flex-direction: column;
    align-items: stretch;
  }

  .perPageSelect {
    width: 100%;
  }

  .usersPaginationFooter {
    flex-direction: column;
    align-items: stretch;
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
