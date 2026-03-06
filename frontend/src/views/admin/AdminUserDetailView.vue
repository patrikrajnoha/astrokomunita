<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminSectionHeader from '@/components/admin/AdminSectionHeader.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { compressImageFileToMaxBytes } from '@/utils/imageCompression'
import { resolveUserCoverMedia } from '@/utils/profileMedia'

const route = useRoute()
const auth = useAuthStore()
const { confirm, prompt } = useConfirm()
const toast = useToast()

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
const PROFILE_MEDIA_TARGET_MAX_BYTES = 3072 * 1024
const PROFILE_MEDIA_UPLOAD_MAX_BYTES = 20480 * 1024
const profileForm = ref({
  name: '',
  bio: '',
  avatar_path: '',
  cover_path: '',
})
const botAvatarInput = ref(null)
const botCoverInput = ref(null)
const botAvatarUploading = ref(false)
const botCoverUploading = ref(false)

let searchDebounce = null

const userId = computed(() => route.params.id)
const usersListRoute = computed(() => ({
  name: 'admin.users',
  query: { ...route.query },
}))
const reportRows = computed(() => reportsData.value?.data || [])
const isCurrentActorAdmin = computed(() => Boolean(auth.isAdmin))
const isBotTarget = computed(() => String(user.value?.role || '').toLowerCase() === 'bot' || Boolean(user.value?.is_bot))
const botCoverMedia = computed(() => resolveUserCoverMedia(user.value))
const canEditProfile = computed(() => {
  if (!user.value) return false
  if (!isBotTarget.value) return true
  return isCurrentActorAdmin.value
})
const canUploadBotMedia = computed(() => isBotTarget.value && canEditProfile.value && isCurrentActorAdmin.value)

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
    profileForm.value = {
      name: String(user.value?.name || ''),
      bio: String(user.value?.bio || ''),
      avatar_path: String(user.value?.avatar_path || ''),
      cover_path: String(user.value?.cover_path || ''),
    }
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
  profileForm.value = {
    name: String(user.value?.name || ''),
    bio: String(user.value?.bio || ''),
    avatar_path: String(user.value?.avatar_path || ''),
    cover_path: String(user.value?.cover_path || ''),
  }
}

async function banUser() {
  if (!user.value || isSelf(user.value)) return
  const reason = await prompt({
    title: 'Ban user',
    message: `Provide ban reason for ${user.value.email}.`,
    confirmText: 'Ban user',
    cancelText: 'Cancel',
    placeholder: 'Ban reason...',
    required: true,
    multiline: true,
    variant: 'danger',
  })
  if (!reason) return

  try {
    const res = await api.patch(`/admin/users/${user.value.id}/ban`, { reason: String(reason).trim() })
    updateUser(res.data)
    toast.success('User banned.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Ban failed.'
    toast.error(userError.value)
  }
}

async function unbanUser() {
  if (!user.value || isSelf(user.value)) return
  const ok = await confirm({
    title: 'Unban user',
    message: `Unban user ${user.value.email}?`,
    confirmText: 'Unban',
    cancelText: 'Cancel',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/unban`)
    updateUser(res.data)
    toast.success('User unbanned.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Unban failed.'
    toast.error(userError.value)
  }
}

async function deactivateUser() {
  if (!user.value || isSelf(user.value) || !user.value.is_active) return
  const ok = await confirm({
    title: 'Deactivate user',
    message: `Deactivate user ${user.value.email}?`,
    confirmText: 'Deactivate',
    cancelText: 'Cancel',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/deactivate`)
    updateUser(res.data)
    toast.success('User deactivated.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Deactivate failed.'
    toast.error(userError.value)
  }
}

async function resetProfile() {
  if (!user.value) return
  const ok = await confirm({
    title: 'Reset profile',
    message: `Reset profile for ${user.value.email}?`,
    confirmText: 'Reset',
    cancelText: 'Cancel',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/reset-profile`)
    updateUser(res.data)
    toast.success('Profile reset done.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Reset profile failed.'
    toast.error(userError.value)
  }
}

async function saveProfile() {
  if (!user.value || !canEditProfile.value) return

  const payload = {
    name: profileForm.value.name,
    bio: profileForm.value.bio || null,
  }

  if (!isBotTarget.value) {
    payload.avatar_path = profileForm.value.avatar_path || null
    payload.cover_path = profileForm.value.cover_path || null
  }

  try {
    const res = await api.patch(`/admin/users/${user.value.id}/profile`, payload)
    updateUser(res.data)
    toast.success('Profile updated.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Profile update failed.'
    toast.error(userError.value)
  }
}

function openBotMediaPicker(type) {
  if (!canUploadBotMedia.value || userLoading.value || botAvatarUploading.value || botCoverUploading.value) return
  const input = type === 'avatar' ? botAvatarInput.value : botCoverInput.value
  if (input) {
    input.click()
  }
}

function clearBotMediaInput(type) {
  const input = type === 'avatar' ? botAvatarInput.value : botCoverInput.value
  if (input) {
    input.value = ''
  }
}

async function uploadBotMedia(type, file) {
  if (!user.value || !canUploadBotMedia.value) return

  userError.value = ''
  if (type === 'avatar') {
    botAvatarUploading.value = true
  } else {
    botCoverUploading.value = true
  }

  try {
    const form = new FormData()
    form.append('file', file)
    const res = await api.patch(`/admin/users/${user.value.id}/${type}`, form)
    updateUser(res.data)
    toast.success(type === 'avatar' ? 'Bot avatar updated.' : 'Bot cover updated.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Media upload failed.'
    toast.error(userError.value)
  } finally {
    if (type === 'avatar') {
      botAvatarUploading.value = false
    } else {
      botCoverUploading.value = false
    }
    clearBotMediaInput(type)
  }
}

async function onBotMediaChange(type, event) {
  const selectedFile = event?.target?.files?.[0]
  if (!selectedFile) return

  let uploadFile = selectedFile
  try {
    uploadFile = await compressImageFileToMaxBytes(selectedFile, {
      maxBytes: PROFILE_MEDIA_TARGET_MAX_BYTES,
    })
  } catch {
    uploadFile = selectedFile
  }

  if ((uploadFile?.size || 0) > PROFILE_MEDIA_UPLOAD_MAX_BYTES) {
    userError.value = 'Selected image is too large. Maximum size is 20 MB.'
    toast.error(userError.value)
    clearBotMediaInput(type)
    return
  }

  await uploadBotMedia(type, uploadFile)
}

async function reportAction(report, action) {
  if (!report?.id) return
  const ok = await confirm({
    title: 'Potvrdenie akcie',
    message: `Naozaj vykonat "${action}"?`,
    confirmText: action === 'hide' ? 'Resolve' : 'Confirm',
    cancelText: 'Cancel',
    variant: action === 'hide' ? 'danger' : 'default',
  })
  if (!ok) return

  try {
    await api.post(`/admin/reports/${report.id}/${action}`)
    loadReports()
    toast.success('Action completed.')
  } catch (e) {
    reportsError.value = e?.response?.data?.message || 'Action failed.'
    toast.error(reportsError.value)
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
    <AdminSectionHeader
      section="community"
      title="Detail pouzivatela"
      back-label="Spat na pouzivatelov"
      :back-to="usersListRoute"
    />

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

    <section class="overviewCard">
      <h3>Profile fields</h3>
      <div v-if="isBotTarget && !isCurrentActorAdmin" class="adminAlert">
        Bot profiles are read-only for non-admin users.
      </div>
      <div class="formGrid">
        <label class="fieldLabel" for="profile-name">Name</label>
        <input
          id="profile-name"
          v-model="profileForm.name"
          class="fieldInput"
          :disabled="!canEditProfile || userLoading"
          type="text"
        />

        <label class="fieldLabel" for="profile-bio">Bio</label>
        <textarea
          id="profile-bio"
          v-model="profileForm.bio"
          class="fieldInput"
          :disabled="!canEditProfile || userLoading"
          rows="3"
        ></textarea>

        <template v-if="!isBotTarget">
          <label class="fieldLabel" for="profile-avatar">Avatar path</label>
          <input
            id="profile-avatar"
            v-model="profileForm.avatar_path"
            class="fieldInput"
            :disabled="!canEditProfile || userLoading"
            type="text"
          />

          <label class="fieldLabel" for="profile-cover">Cover path</label>
          <input
            id="profile-cover"
            v-model="profileForm.cover_path"
            class="fieldInput"
            :disabled="!canEditProfile || userLoading"
            type="text"
          />
        </template>
      </div>

      <div v-if="isBotTarget" class="botMediaGrid">
        <div class="botMediaCard">
          <label class="fieldLabel">Bot avatar</label>
          <UserAvatar class="botMediaPreview avatar" :user="user" :alt="`${user?.name || 'Bot'} avatar`" :size="120" />
          <div v-if="canUploadBotMedia" class="botMediaPath">Path: {{ user?.avatar_path || '-' }}</div>
          <div v-else class="botMediaReadonly">Read-only preview</div>
          <template v-if="canUploadBotMedia">
            <input
              ref="botAvatarInput"
              class="botMediaInput"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              :disabled="!canUploadBotMedia || userLoading || botAvatarUploading || botCoverUploading"
              @change="onBotMediaChange('avatar', $event)"
            />
            <button
              type="button"
              class="btn action"
              :disabled="!canUploadBotMedia || userLoading || botAvatarUploading || botCoverUploading"
              @click="openBotMediaPicker('avatar')"
            >
              {{ botAvatarUploading ? 'Uploading...' : 'Upload avatar' }}
            </button>
          </template>
        </div>

        <div class="botMediaCard">
          <label class="fieldLabel">Bot cover</label>
          <div
            class="botMediaPreview cover"
            :class="{ 'botMediaPreview--fallback': botCoverMedia.isBotFallback }"
            :style="botCoverMedia.fallbackStyle"
          >
            <img v-if="botCoverMedia.hasImage" :src="botCoverMedia.imageUrl" alt="Bot cover" class="botCoverImage" />
          </div>
          <div v-if="canUploadBotMedia" class="botMediaPath">Path: {{ user?.cover_path || '-' }}</div>
          <div v-else class="botMediaReadonly">Read-only preview</div>
          <template v-if="canUploadBotMedia">
            <input
              ref="botCoverInput"
              class="botMediaInput"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              :disabled="!canUploadBotMedia || userLoading || botAvatarUploading || botCoverUploading"
              @change="onBotMediaChange('cover', $event)"
            />
            <button
              type="button"
              class="btn action"
              :disabled="!canUploadBotMedia || userLoading || botAvatarUploading || botCoverUploading"
              @click="openBotMediaPicker('cover')"
            >
              {{ botCoverUploading ? 'Uploading...' : 'Upload cover' }}
            </button>
          </template>
        </div>
      </div>

      <div class="headerActions">
        <button class="btn action" :disabled="!canEditProfile || userLoading" @click="saveProfile">
          Save profile
        </button>
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
      <div><strong>Banned at:</strong> {{ formatDate(user?.banned_at) }}</div>
      <div><strong>Ban reason:</strong> {{ user?.ban_reason || '-' }}</div>
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

.formGrid {
  display: grid;
  gap: 8px;
}

.botMediaGrid {
  display: grid;
  gap: 12px;
}

.botMediaCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  padding: 10px;
  display: grid;
  gap: 8px;
}

.botMediaPreview {
  width: 100%;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  overflow: hidden;
}

.botMediaPreview.avatar {
  max-width: 120px;
  aspect-ratio: 1 / 1;
}

.botMediaPreview.cover {
  min-height: 120px;
  max-height: 180px;
  position: relative;
}

.botMediaPreview--fallback {
  box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.24);
}

.botCoverImage {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.botMediaReadonly {
  font-size: 12px;
  opacity: 0.75;
}

.botMediaPath {
  font-size: 12px;
  opacity: 0.75;
  word-break: break-all;
}

.botMediaInput {
  display: none;
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
