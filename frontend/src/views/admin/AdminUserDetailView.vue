<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'

const route = useRoute()
const auth = useAuthStore()

const activeTab = ref('overview')

const user = ref(null)
const userLoading = ref(false)
const userError = ref('')

const reportsLoading = ref(false)
const reportsError = ref('')
const reportsData = ref(null)
const reportsSearchInput = ref('')
const reportsSearch = ref('')
const reportsStatus = ref('')
const reportsPage = ref(1)
const reportsPerPage = ref(20)

let searchDebounce = null

const userId = computed(() => route.params.id)
const reportRows = computed(() => reportsData.value?.data || [])

const reportColumns = [
  { key: 'type', label: 'Type' },
  { key: 'reason', label: 'Reason' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Created' },
  { key: 'actions', label: 'Actions', align: 'right' },
]

function statusLabel(userRow) {
  if (!userRow?.is_active) return 'inactive'
  if (userRow?.is_banned) return 'banned'
  return 'active'
}

function isSelf(userRow) {
  return auth.user && userRow && Number(auth.user.id) === Number(userRow.id)
}

function reportType(report) {
  if (!report?.target_type) return 'post'
  const parts = String(report.target_type).split('\\')
  return (parts[parts.length - 1] || 'post').toLowerCase()
}

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString()
}

async function loadUser() {
  userLoading.value = true
  userError.value = ''

  try {
    const res = await api.get(`/admin/users/${userId.value}`)
    user.value = res.data
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Failed to load user.'
  } finally {
    userLoading.value = false
  }
}

async function loadReports() {
  reportsLoading.value = true
  reportsError.value = ''

  try {
    const params = {
      page: reportsPage.value,
      per_page: reportsPerPage.value,
    }

    if (reportsStatus.value) {
      params.status = reportsStatus.value
    }
    if (reportsSearch.value) {
      params.search = reportsSearch.value
    }

    const res = await api.get(`/admin/users/${userId.value}/reports`, { params })
    reportsData.value = res.data
  } catch (e) {
    reportsError.value = e?.response?.data?.message || 'Failed to load reports.'
  } finally {
    reportsLoading.value = false
  }
}

function updateUser(updated) {
  if (!user.value || !updated) return
  user.value = { ...user.value, ...updated }
}

async function banUser() {
  if (!user.value || isSelf(user.value)) return
  const ok = window.confirm(`Ban user ${user.value.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/ban`)
    updateUser(res.data)
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Ban failed.'
  }
}

async function unbanUser() {
  if (!user.value || isSelf(user.value)) return
  const ok = window.confirm(`Unban user ${user.value.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/unban`)
    updateUser(res.data)
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Unban failed.'
  }
}

async function deactivateUser() {
  if (!user.value || isSelf(user.value) || !user.value.is_active) return
  const ok = window.confirm(`Deactivate user ${user.value.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/deactivate`)
    updateUser(res.data)
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Deactivate failed.'
  }
}

async function resetProfile() {
  if (!user.value) return
  const ok = window.confirm(`Reset profile for ${user.value.email}?`)
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/reset-profile`)
    updateUser(res.data)
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Reset profile failed.'
  }
}

async function reportAction(report, action) {
  if (!report?.id) return
  const ok = window.confirm(`Confirm ${action}?`)
  if (!ok) return

  try {
    await api.post(`/admin/reports/${report.id}/${action}`)
    loadReports()
  } catch (e) {
    reportsError.value = e?.response?.data?.message || 'Action failed.'
  }
}

function clearReportFilters() {
  reportsSearchInput.value = ''
  reportsSearch.value = ''
  reportsStatus.value = ''
  reportsPage.value = 1
}

watch(reportsSearchInput, (value) => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    if (reportsSearch.value !== value) {
      reportsSearch.value = value
      reportsPage.value = 1
    }
  }, 400)
})

watch([reportsSearch, reportsStatus, reportsPage, reportsPerPage], () => {
  if (activeTab.value === 'reports') {
    loadReports()
  }
})

watch(
  () => route.params.id,
  () => {
    clearReportFilters()
    loadUser()
    if (activeTab.value === 'reports') {
      loadReports()
    }
  },
)

watch(activeTab, (tab) => {
  if (tab === 'reports' && !reportsData.value) {
    loadReports()
  }
})

loadUser()

onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce)
})
</script>

<template>
  <AdminPageShell
    :title="user?.name ? `User: ${user.name}` : 'User detail'"
    :subtitle="user?.email || 'Admin user detail'"
  >
    <div v-if="userError" class="adminAlert">{{ userError }}</div>
    <div v-if="reportsError && activeTab === 'reports'" class="adminAlert">{{ reportsError }}</div>

    <section class="userHeader">
      <div class="userMeta">
        <div class="userRow"><strong>Email:</strong> {{ user?.email || '-' }}</div>
        <div class="userRow"><strong>Role:</strong> {{ user?.role || '-' }}</div>
        <div class="userRow">
          <strong>Status:</strong>
          <span class="statusBadge">{{ statusLabel(user) }}</span>
        </div>
      </div>

      <div class="headerActions">
        <button v-if="!user?.is_banned" class="btn action" :disabled="userLoading || isSelf(user)" @click="banUser">
          Ban
        </button>
        <button v-else class="btn action" :disabled="userLoading || isSelf(user)" @click="unbanUser">Unban</button>
        <button
          class="btn action"
          :disabled="userLoading || isSelf(user) || !user?.is_active"
          @click="deactivateUser"
        >
          Deactivate
        </button>
        <button class="btn action subtle" :disabled="userLoading" @click="resetProfile">Reset profile</button>
      </div>
    </section>

    <div class="tabs">
      <button
        type="button"
        class="tabBtn"
        :class="{ active: activeTab === 'overview' }"
        @click="activeTab = 'overview'"
      >
        Overview
      </button>
      <button
        type="button"
        class="tabBtn"
        :class="{ active: activeTab === 'reports' }"
        @click="activeTab = 'reports'"
      >
        Reports
      </button>
    </div>

    <section v-if="activeTab === 'overview'" class="overviewCard">
      <div><strong>ID:</strong> {{ user?.id || '-' }}</div>
      <div><strong>Name:</strong> {{ user?.name || '-' }}</div>
      <div><strong>Email:</strong> {{ user?.email || '-' }}</div>
      <div><strong>Role:</strong> {{ user?.role || '-' }}</div>
      <div><strong>Status:</strong> {{ statusLabel(user) }}</div>
      <div><strong>Created:</strong> {{ formatDate(user?.created_at) }}</div>
    </section>

    <section v-if="activeTab === 'reports'" class="reportsTab">
      <AdminToolbar :loading="reportsLoading">
        <template #search>
          <label class="fieldLabel" for="user-reports-search">Search</label>
          <input
            id="user-reports-search"
            v-model="reportsSearchInput"
            type="search"
            class="fieldInput"
            :disabled="reportsLoading"
            placeholder="Search reports..."
          />
        </template>

        <template #filters>
          <div class="filtersRow">
            <div>
              <label class="fieldLabel" for="user-reports-status">Status</label>
              <select
                id="user-reports-status"
                v-model="reportsStatus"
                class="fieldInput"
                :disabled="reportsLoading"
                @change="reportsPage = 1"
              >
                <option value="">all</option>
                <option value="open">open</option>
                <option value="reviewed">reviewed</option>
                <option value="dismissed">dismissed</option>
                <option value="action_taken">action_taken</option>
              </select>
            </div>
            <div>
              <label class="fieldLabel" for="user-reports-per-page">Per page</label>
              <select
                id="user-reports-per-page"
                v-model.number="reportsPerPage"
                class="fieldInput"
                :disabled="reportsLoading"
                @change="reportsPage = 1"
              >
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
              </select>
            </div>
          </div>
        </template>

        <template #actions>
          <button type="button" class="btn action" :disabled="reportsLoading" @click="loadReports">Refresh</button>
          <button type="button" class="btn action subtle" :disabled="reportsLoading" @click="clearReportFilters">
            Clear filters
          </button>
        </template>
      </AdminToolbar>

      <AdminDataTable
        :columns="reportColumns"
        :rows="reportRows"
        :loading="reportsLoading"
        empty-title="No reports for this user"
        empty-description="No report matched current filters."
      >
        <template #[`cell(type)`]="{ row }">
          <span class="statusBadge">{{ reportType(row) }}</span>
        </template>

        <template #[`cell(status)`]="{ row }">
          <span class="statusBadge">{{ row.status || '-' }}</span>
        </template>

        <template #[`cell(created_at)`]="{ row }">
          {{ formatDate(row.created_at) }}
        </template>

        <template #[`cell(actions)`]="{ row }">
          <div class="rowActions">
            <RouterLink
              v-if="row?.target?.id"
              :to="{ name: 'post-detail', params: { id: row.target.id } }"
              class="btn action"
            >
              View
            </RouterLink>
            <button class="btn action" :disabled="reportsLoading" @click="reportAction(row, 'hide')">Resolve</button>
            <button class="btn action subtle" :disabled="reportsLoading" @click="reportAction(row, 'dismiss')">
              Dismiss
            </button>
          </div>
        </template>
      </AdminDataTable>

      <AdminPagination :meta="reportsData" @page-change="reportsPage = $event" />
    </section>
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

.userHeader {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 14px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.userMeta {
  display: grid;
  gap: 6px;
}

.userRow {
  display: flex;
  align-items: center;
  gap: 8px;
}

.headerActions,
.rowActions {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 6px;
}

.tabs {
  display: flex;
  gap: 8px;
}

.tabBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  padding: 8px 12px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.tabBtn.active {
  background: rgb(var(--color-surface-rgb) / 0.1);
}

.overviewCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 14px;
  display: grid;
  gap: 8px;
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
