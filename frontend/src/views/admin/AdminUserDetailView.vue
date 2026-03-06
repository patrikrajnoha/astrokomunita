<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminSectionHeader from '@/components/admin/AdminSectionHeader.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import DefaultAvatar from '@/components/DefaultAvatar.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  coerceAvatarIndex,
  hashAvatarString,
  normalizeAvatarMode,
  pickDeterministicAvatarIndex,
} from '@/constants/avatar'
import { compressImageFileToMaxBytes } from '@/utils/imageCompression'
import { normalizeAvatarUrl, resolveAvatarState } from '@/utils/avatar'
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
const mediaError = ref('')
const avatarErr = ref('')
const profileForm = ref({
  name: '',
  bio: '',
})
const avatarModalOpen = ref(false)
const coverModalOpen = ref(false)
const avatarSaving = ref(false)
const avatarUploading = ref(false)
const avatarRemoving = ref(false)
const coverSaving = ref(false)
const coverUploading = ref(false)
const coverRemoving = ref(false)
const avatarInput = ref(null)
const coverInput = ref(null)
const avatarPreview = ref('')
const coverPreview = ref('')
const avatarRemoveRequested = ref(false)
const coverRemoveRequested = ref(false)
const pendingAvatarFile = ref(null)
const pendingCoverFile = ref(null)
const avatarSnapshot = ref({
  mode: 'image',
  color: null,
  icon: null,
  seed: '',
})
const avatarDraft = reactive({
  mode: 'image',
  color: null,
  icon: null,
  seed: '',
})

let searchDebounce = null

const userId = computed(() => route.params.id)
const usersListRoute = computed(() => ({
  name: 'admin.users',
  query: { ...route.query },
}))
const reportRows = computed(() => reportsData.value?.data || [])
const isCurrentActorAdmin = computed(() => Boolean(auth.isAdmin))
const isBotTarget = computed(() => String(user.value?.role || '').toLowerCase() === 'bot' || Boolean(user.value?.is_bot))
const canEditProfile = computed(() => {
  if (!user.value) return false
  if (!isBotTarget.value) return true
  return isCurrentActorAdmin.value
})
const canUploadBotMedia = computed(() => isBotTarget.value && canEditProfile.value && isCurrentActorAdmin.value)
const avatarSrc = computed(() =>
  avatarPreview.value || normalizeAvatarUrl(user.value?.avatar_url || user.value?.avatarUrl || ''),
)
const persistedAvatarMode = computed(() => {
  const persistedImage = normalizeAvatarUrl(user.value?.avatar_url || user.value?.avatarUrl || '')
  const hasImage = String(avatarPreview.value || persistedImage || '').trim() !== '' && !avatarRemoveRequested.value
  if (hasImage) return 'image'
  return normalizeAvatarMode(user.value?.avatar_mode || user.value?.avatarMode)
})
const avatarResolved = computed(() =>
  resolveAvatarState(user.value, {
    avatarUrl: avatarSrc.value,
    mode: avatarDraft.mode,
    colorIndex: avatarDraft.color,
    iconIndex: avatarDraft.icon,
    seed: avatarDraft.seed,
  }),
)
const iconOptions = computed(() =>
  AVATAR_ICONS.map((iconKey, index) => ({
    key: iconKey,
    index,
    label: formatIconLabel(iconKey),
  })),
)
const botCoverMedia = computed(() => resolveUserCoverMedia(user.value))
const coverEditorMedia = computed(() => {
  if (coverPreview.value) {
    return {
      ...botCoverMedia.value,
      imageUrl: coverPreview.value,
      hasImage: true,
      isBotFallback: false,
    }
  }
  if (coverRemoveRequested.value) {
    return {
      ...botCoverMedia.value,
      imageUrl: '',
      hasImage: false,
      isBotFallback: true,
    }
  }

  return botCoverMedia.value
})
const mediaActionBusy = computed(() =>
  avatarSaving.value ||
  avatarUploading.value ||
  avatarRemoving.value ||
  coverSaving.value ||
  coverUploading.value ||
  coverRemoving.value ||
  userLoading.value,
)

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
    }
    syncAvatarDraftFromUser()
    clearPendingMedia('avatar')
    clearPendingMedia('cover')
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
  }
  syncAvatarDraftFromUser()
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

  try {
    const res = await api.patch(`/admin/users/${user.value.id}/profile`, payload)
    updateUser(res.data)
    toast.success('Profile updated.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Profile update failed.'
    toast.error(userError.value)
  }
}

function normalizeAvatarIndex(value, max) {
  const index = coerceAvatarIndex(value, max)
  return index === null ? null : index
}

function buildAvatarSnapshot(sourceUser) {
  const imageUrl = normalizeAvatarUrl(sourceUser?.avatar_url || sourceUser?.avatarUrl || '')

  return {
    mode: imageUrl ? 'image' : normalizeAvatarMode(sourceUser?.avatar_mode || sourceUser?.avatarMode),
    color: normalizeAvatarIndex(sourceUser?.avatar_color ?? sourceUser?.avatarColor, AVATAR_COLORS.length - 1),
    icon: normalizeAvatarIndex(sourceUser?.avatar_icon ?? sourceUser?.avatarIcon, AVATAR_ICONS.length - 1),
    seed: String(sourceUser?.avatar_seed || sourceUser?.avatarSeed || '').trim(),
  }
}

function applyAvatarSnapshot(snapshot) {
  avatarDraft.mode = snapshot.mode
  avatarDraft.color = snapshot.color
  avatarDraft.icon = snapshot.icon
  avatarDraft.seed = snapshot.seed
}

function syncAvatarDraftFromUser() {
  const snapshot = buildAvatarSnapshot(user.value)
  avatarSnapshot.value = snapshot
  applyAvatarSnapshot(snapshot)
}

function formatIconLabel(iconKey) {
  const map = {
    planet: 'Planeta',
    star: 'Hviezda',
    comet: 'Kometa',
    constellation: 'Suhvezdie',
    moon: 'Mesiac',
  }

  return map[iconKey] || iconKey
}

function clearMediaPreview(type) {
  if (type === 'avatar') {
    if (avatarPreview.value) {
      URL.revokeObjectURL(avatarPreview.value)
    }
    avatarPreview.value = ''
    return
  }

  if (coverPreview.value) {
    URL.revokeObjectURL(coverPreview.value)
  }
  coverPreview.value = ''
}

function setMediaPreview(type, file) {
  const previewUrl = URL.createObjectURL(file)
  if (type === 'avatar') {
    clearMediaPreview('avatar')
    avatarPreview.value = previewUrl
    return
  }

  clearMediaPreview('cover')
  coverPreview.value = previewUrl
}

function clearPendingMedia(type) {
  if (type === 'avatar') {
    pendingAvatarFile.value = null
    avatarRemoveRequested.value = false
    clearMediaPreview('avatar')
    return
  }

  pendingCoverFile.value = null
  coverRemoveRequested.value = false
  clearMediaPreview('cover')
}

function openBotMediaPicker(type) {
  if (!canUploadBotMedia.value || mediaActionBusy.value) return
  const input = type === 'avatar' ? avatarInput.value : coverInput.value
  if (input) {
    input.click()
  }
}

function openAvatarEditor() {
  if (!user.value || !canUploadBotMedia.value) return
  mediaError.value = ''
  avatarErr.value = ''
  clearPendingMedia('avatar')
  syncAvatarDraftFromUser()
  avatarModalOpen.value = true
}

function closeAvatarEditor() {
  avatarModalOpen.value = false
}

function openCoverEditor() {
  if (!user.value || !canUploadBotMedia.value) return
  mediaError.value = ''
  clearPendingMedia('cover')
  coverModalOpen.value = true
}

function closeCoverEditor() {
  coverModalOpen.value = false
}

function setAvatarMode(mode) {
  avatarDraft.mode = mode === 'generated' ? 'generated' : 'image'
  avatarErr.value = ''
  if (avatarDraft.mode === 'generated') {
    clearPendingMedia('avatar')
  }
}

function selectAvatarColor(index) {
  avatarDraft.color = normalizeAvatarIndex(index, AVATAR_COLORS.length - 1)
  avatarErr.value = ''
}

function selectAvatarIcon(index) {
  avatarDraft.icon = normalizeAvatarIndex(index, AVATAR_ICONS.length - 1)
  avatarErr.value = ''
}

function resetGeneratedAvatar() {
  avatarDraft.color = null
  avatarDraft.icon = null
  avatarDraft.seed = ''
  avatarErr.value = ''
}

function buildRandomAvatarSeed() {
  const base = `${user.value?.id || 'user'}:${Date.now()}:${Math.random()}`
  return `rnd-${hashAvatarString(base).toString(36)}`
}

function randomizeAvatar() {
  const seed = buildRandomAvatarSeed()
  avatarDraft.seed = seed
  avatarDraft.color = pickDeterministicAvatarIndex(seed, 'color', AVATAR_COLORS.length)
  avatarDraft.icon = pickDeterministicAvatarIndex(seed, 'icon', AVATAR_ICONS.length)
}

function markAvatarImageForRemoval() {
  avatarRemoveRequested.value = true
  pendingAvatarFile.value = null
  clearMediaPreview('avatar')
}

function markCoverForRemoval() {
  coverRemoveRequested.value = true
  pendingCoverFile.value = null
  clearMediaPreview('cover')
}

function resolveMediaErrorMessage(error, fallback = 'Media update failed.') {
  const status = error?.response?.status ?? null
  const data = error?.response?.data

  if (status === 422 && data?.errors) {
    const firstField = Object.keys(data.errors)[0]
    const first = firstField && Array.isArray(data.errors[firstField]) ? data.errors[firstField][0] : ''
    return String(first || data?.message || fallback)
  }

  return String(data?.message || fallback)
}

async function compressProfileMedia(file) {
  let uploadFile = file
  try {
    uploadFile = await compressImageFileToMaxBytes(file, {
      maxBytes: PROFILE_MEDIA_TARGET_MAX_BYTES,
    })
  } catch {
    uploadFile = file
  }

  if ((uploadFile?.size || 0) > PROFILE_MEDIA_UPLOAD_MAX_BYTES) {
    throw new Error('Selected image is too large. Maximum size is 20 MB.')
  }

  return uploadFile
}

async function onBotMediaChange(type, event) {
  const selectedFile = event?.target?.files?.[0]
  if (!selectedFile || !canUploadBotMedia.value) return
  event.target.value = ''

  mediaError.value = ''
  avatarErr.value = ''

  try {
    const uploadFile = await compressProfileMedia(selectedFile)
    setMediaPreview(type, uploadFile)
    if (type === 'avatar') {
      pendingAvatarFile.value = uploadFile
      avatarRemoveRequested.value = false
      avatarDraft.mode = 'image'
    } else {
      pendingCoverFile.value = uploadFile
      coverRemoveRequested.value = false
    }
  } catch (error) {
    const message = String(error?.message || 'Media update failed.')
    mediaError.value = message
    if (type === 'avatar') {
      avatarErr.value = message
    }
    toast.error(message)
  }
}

async function uploadBotMedia(type, file) {
  const form = new FormData()
  form.append('file', file)
  const response = await api.patch(`/admin/users/${user.value.id}/${type}`, form)
  updateUser(response.data)
}

async function saveAvatarPreferences() {
  if (!user.value || !canUploadBotMedia.value || avatarSaving.value) return

  avatarSaving.value = true
  mediaError.value = ''
  avatarErr.value = ''

  try {
    if (avatarRemoveRequested.value) {
      avatarRemoving.value = true
      const removeResponse = await api.delete(`/admin/users/${user.value.id}/avatar`)
      updateUser(removeResponse.data)
    }

    if (pendingAvatarFile.value) {
      avatarUploading.value = true
      await uploadBotMedia('avatar', pendingAvatarFile.value)
    }

    const payload = {
      avatar_mode: avatarDraft.mode,
      avatar_color: avatarDraft.color,
      avatar_icon: avatarDraft.icon,
      avatar_seed: avatarDraft.seed || null,
    }
    const response = await api.patch(`/admin/users/${user.value.id}/avatar/preferences`, payload)
    updateUser(response.data)

    syncAvatarDraftFromUser()
    clearPendingMedia('avatar')
    avatarModalOpen.value = false
    toast.success('Bot avatar updated.')
  } catch (error) {
    const message = resolveMediaErrorMessage(error, 'Avatar update failed.')
    avatarErr.value = message
    mediaError.value = message
    toast.error(message)
  } finally {
    avatarSaving.value = false
    avatarUploading.value = false
    avatarRemoving.value = false
  }
}

async function saveCoverEditor() {
  if (!user.value || !canUploadBotMedia.value || coverSaving.value) return

  coverSaving.value = true
  mediaError.value = ''

  try {
    if (coverRemoveRequested.value) {
      coverRemoving.value = true
      const removeResponse = await api.delete(`/admin/users/${user.value.id}/cover`)
      updateUser(removeResponse.data)
    } else if (pendingCoverFile.value) {
      coverUploading.value = true
      await uploadBotMedia('cover', pendingCoverFile.value)
    }

    clearPendingMedia('cover')
    coverModalOpen.value = false
    toast.success('Bot cover updated.')
  } catch (error) {
    const message = resolveMediaErrorMessage(error, 'Cover update failed.')
    mediaError.value = message
    toast.error(message)
  } finally {
    coverSaving.value = false
    coverUploading.value = false
    coverRemoving.value = false
  }
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
    mediaError.value = ''
    avatarErr.value = ''
    avatarModalOpen.value = false
    coverModalOpen.value = false
    clearPendingMedia('avatar')
    clearPendingMedia('cover')
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

watch(
  () => avatarModalOpen.value,
  (isOpen, wasOpen) => {
    if (!isOpen && wasOpen) {
      syncAvatarDraftFromUser()
      clearPendingMedia('avatar')
      avatarErr.value = ''
    }
  },
)

watch(
  () => coverModalOpen.value,
  (isOpen, wasOpen) => {
    if (!isOpen && wasOpen) {
      clearPendingMedia('cover')
      mediaError.value = ''
    }
  },
)

loadUser()

onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce)
  clearMediaPreview('avatar')
  clearMediaPreview('cover')
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

      </div>

      <div v-if="isBotTarget" class="botMediaGrid">
        <section class="botMediaCard profileCardLike">
          <div class="avatarCardHead">
            <div>
              <h4 class="avatarCardTitle">Profilovy avatar</h4>
              <p class="avatarCardSub">Fotka alebo generovany avatar.</p>
            </div>
            <button
              v-if="canUploadBotMedia"
              type="button"
              data-testid="admin-bot-avatar-edit"
              class="btn outline avatarOpenBtn"
              :disabled="mediaActionBusy"
              @click="openAvatarEditor"
            >
              Upravit
            </button>
          </div>

          <div class="avatarCardMeta">
            <div class="avatar sm avatarCardPreview">
              <UserAvatar
                class="avatarImg"
                :user="user"
                :avatar-url="avatarSrc"
                :alt="`${user?.name || 'Bot'} avatar`"
              />
            </div>
            <div class="avatarCardInfo">
              <div class="avatarModePill">{{ persistedAvatarMode === 'generated' ? 'Rezim Avatar' : 'Rezim Fotka' }}</div>
              <p class="avatarHint avatarCardHint">Bez fotky sa pouzije fallback avatar.</p>
            </div>
          </div>

          <div v-if="!canUploadBotMedia" class="botMediaReadonly">Read-only preview</div>
          <div v-if="mediaError" class="msg err">{{ mediaError }}</div>
        </section>

        <section class="botMediaCard profileCardLike">
          <div class="avatarCardHead">
            <div>
              <h4 class="avatarCardTitle">Titulna fotka</h4>
              <p class="avatarCardSub">Nahraj alebo odstran titulnu fotku bota.</p>
            </div>
            <button
              v-if="canUploadBotMedia"
              type="button"
              data-testid="admin-bot-cover-edit"
              class="btn outline avatarOpenBtn"
              :disabled="mediaActionBusy"
              @click="openCoverEditor"
            >
              Upravit
            </button>
          </div>

          <div
            class="botMediaPreview cover"
            :class="{ 'botMediaPreview--fallback': botCoverMedia.isBotFallback }"
            :style="botCoverMedia.fallbackStyle"
          >
            <img v-if="botCoverMedia.hasImage" :src="botCoverMedia.imageUrl" alt="Bot cover" class="botCoverImage" />
            <div class="coverGlow"></div>
          </div>

          <div v-if="!canUploadBotMedia" class="botMediaReadonly">Read-only preview</div>
        </section>
      </div>

      <div class="headerActions">
        <button class="btn action" :disabled="!canEditProfile || userLoading" @click="saveProfile">
          Save profile
        </button>
      </div>

      <BaseModal
        v-if="isBotTarget"
        v-model:open="avatarModalOpen"
        title="Upravit profilovy avatar"
        test-id="admin-bot-avatar-modal"
        close-test-id="admin-bot-avatar-modal-close"
      >
        <template #description>
          <p class="avatarCardSub avatarModalSub">Vyber si fotku alebo personalizovany avatar.</p>
        </template>

        <div class="avatarEditorBody">
          <div class="avatarModeSwitch" role="tablist" aria-label="Rezim profiloveho avatara">
            <button
              type="button"
              class="modeBtn"
              :class="{ active: avatarDraft.mode === 'image' }"
              :disabled="mediaActionBusy"
              @click="setAvatarMode('image')"
            >
              Fotka
            </button>
            <button
              type="button"
              class="modeBtn"
              :class="{ active: avatarDraft.mode === 'generated' }"
              :disabled="mediaActionBusy"
              @click="setAvatarMode('generated')"
            >
              Avatar
            </button>
          </div>

          <div class="avatarPreviewWrap">
            <div class="avatar avatarPreviewAvatar">
              <UserAvatar
                class="avatarImg"
                :user="user"
                :size="112"
                :avatar-url="avatarSrc"
                :mode="avatarDraft.mode"
                :prefer-image="avatarDraft.mode === 'image'"
                :color-index="avatarDraft.color"
                :icon-index="avatarDraft.icon"
                :seed="avatarDraft.seed"
                :alt="`${user?.name || 'Bot'} avatar`"
              />
            </div>
            <p class="avatarHint">Pri mode Fotka bez obrazka ostava fallback avatar.</p>
          </div>

          <div v-if="avatarErr" class="msg err avatarMsg">{{ avatarErr }}</div>

          <template v-if="avatarDraft.mode === 'image'">
            <div class="avatarImageActions">
              <button
                type="button"
                class="btn outline"
                data-testid="admin-bot-avatar-upload"
                :disabled="mediaActionBusy"
                @click="openBotMediaPicker('avatar')"
              >
                {{ avatarUploading ? 'Nahravam...' : 'Nahrat fotku' }}
              </button>
              <button
                type="button"
                class="btn ghost"
                data-testid="admin-bot-avatar-remove"
                :disabled="mediaActionBusy"
                @click="markAvatarImageForRemoval"
              >
                {{ avatarRemoving ? 'Odstranujem...' : 'Odstranit fotku' }}
              </button>
              <input
                ref="avatarInput"
                class="fileInput"
                data-testid="admin-bot-avatar-input"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                :disabled="mediaActionBusy"
                @change="onBotMediaChange('avatar', $event)"
              />
            </div>
            <p class="avatarHint">Odporucana velkost: aspon 512x512 px, JPG/PNG/WebP, max 3 MB.</p>
          </template>

          <template v-else>
            <div class="avatarPicker">
              <div class="avatarPickerLabel">Symbol</div>
              <div class="avatarIconGrid">
                <button
                  v-for="option in iconOptions"
                  :key="option.index"
                  type="button"
                  class="avatarChoice iconChoice"
                  :class="{ active: avatarResolved.iconIndex === option.index }"
                  :disabled="mediaActionBusy"
                  @click="selectAvatarIcon(option.index)"
                >
                  <DefaultAvatar
                    class="choiceAvatar"
                    :size="40"
                    :color-index="avatarResolved.colorIndex"
                    :icon-index="option.index"
                  />
                  <span class="choiceLabel">{{ option.label }}</span>
                </button>
              </div>
            </div>

            <div class="avatarPicker">
              <div class="avatarPickerLabel">Farba</div>
              <div class="avatarColorGrid">
                <button
                  v-for="(color, index) in AVATAR_COLORS"
                  :key="color"
                  type="button"
                  class="avatarChoice colorChoice"
                  :class="{ active: avatarResolved.colorIndex === index }"
                  :style="{ '--avatar-choice-color': color }"
                  :disabled="mediaActionBusy"
                  @click="selectAvatarColor(index)"
                >
                  <span class="colorSwatch" aria-hidden="true"></span>
                  <span class="choiceLabel">Farba {{ index + 1 }}</span>
                </button>
              </div>
            </div>

            <div class="avatarActionRow">
              <button type="button" class="btn outline" :disabled="mediaActionBusy" @click="randomizeAvatar">
                Nahodne
              </button>
              <button type="button" class="btn ghost" :disabled="mediaActionBusy" @click="resetGeneratedAvatar">
                Reset
              </button>
            </div>
          </template>

          <div class="avatarActionRow avatarActionRowSave">
            <button type="button" class="btn ghost" :disabled="mediaActionBusy" @click="closeAvatarEditor">
              Zavriet
            </button>
            <button
              type="button"
              class="btn"
              data-testid="admin-bot-avatar-save"
              :disabled="mediaActionBusy"
              @click="saveAvatarPreferences"
            >
              {{ avatarSaving ? 'Ukladam...' : 'Ulozit' }}
            </button>
          </div>
        </div>
      </BaseModal>

      <BaseModal
        v-if="isBotTarget"
        v-model:open="coverModalOpen"
        title="Upravit titulnu fotku"
        test-id="admin-bot-cover-modal"
        close-test-id="admin-bot-cover-modal-close"
      >
        <template #description>
          <p class="avatarCardSub avatarModalSub">Zmen titulnu fotku alebo pouzi fallback pozadie.</p>
        </template>

        <div class="coverEditorBody">
          <div
            class="botMediaPreview cover coverEditorPreview"
            :class="{ 'botMediaPreview--fallback': coverEditorMedia.isBotFallback }"
            :style="coverEditorMedia.fallbackStyle"
          >
            <img v-if="coverEditorMedia.hasImage" :src="coverEditorMedia.imageUrl" alt="Bot cover" class="botCoverImage" />
            <div class="coverGlow"></div>
          </div>

          <div class="avatarImageActions">
            <button
              type="button"
              class="btn outline"
              data-testid="admin-bot-cover-upload"
              :disabled="mediaActionBusy"
              @click="openBotMediaPicker('cover')"
            >
              {{ coverUploading ? 'Nahravam...' : 'Nahrat fotku' }}
            </button>
            <button
              type="button"
              class="btn ghost"
              data-testid="admin-bot-cover-remove"
              :disabled="mediaActionBusy"
              @click="markCoverForRemoval"
            >
              {{ coverRemoving ? 'Odstranujem...' : 'Odstranit fotku' }}
            </button>
            <input
              ref="coverInput"
              class="fileInput"
              data-testid="admin-bot-cover-input"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              :disabled="mediaActionBusy"
              @change="onBotMediaChange('cover', $event)"
            />
          </div>

          <div v-if="mediaError" class="msg err">{{ mediaError }}</div>

          <div class="avatarActionRow avatarActionRowSave">
            <button type="button" class="btn ghost" :disabled="mediaActionBusy" @click="closeCoverEditor">
              Zavriet
            </button>
            <button
              type="button"
              class="btn"
              data-testid="admin-bot-cover-save"
              :disabled="mediaActionBusy"
              @click="saveCoverEditor"
            >
              {{ coverSaving ? 'Ukladam...' : 'Ulozit' }}
            </button>
          </div>
        </div>
      </BaseModal>
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
  padding: 12px;
  display: grid;
  gap: 8px;
}

.profileCardLike {
  background: rgb(var(--color-surface-rgb) / 0.02);
}

.avatarCardHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.avatarCardTitle {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.avatarCardSub {
  margin: 4px 0 0;
  font-size: 13px;
  opacity: 0.78;
}

.avatarOpenBtn {
  min-height: 34px;
  padding: 0 14px;
}

.avatarCardMeta {
  margin-top: 2px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  background: rgb(var(--color-bg-rgb) / 0.22);
  display: flex;
  align-items: center;
  gap: 10px;
}

.avatar {
  width: 96px;
  height: 96px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  border: 2px solid rgb(var(--color-bg-rgb) / 0.95);
  outline: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.avatar.sm {
  width: 44px;
  height: 44px;
  border-width: 1px;
  outline: 1px solid rgb(var(--color-primary-rgb) / 0.35);
}

.avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 999px;
}

.avatarCardInfo {
  min-width: 0;
  display: grid;
  gap: 4px;
}

.avatarModePill {
  width: fit-content;
  max-width: 100%;
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.14);
  font-size: 12px;
  font-weight: 700;
}

.avatarCardHint {
  text-align: left;
  font-size: 12px;
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

.coverEditorPreview {
  min-height: 150px;
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

.coverGlow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(2px 2px at 20% 30%, rgb(var(--text-primary-rgb) / 0.35), transparent 60%),
    radial-gradient(2px 2px at 70% 40%, rgb(var(--text-primary-rgb) / 0.25), transparent 60%),
    radial-gradient(2px 2px at 50% 70%, rgb(var(--text-primary-rgb) / 0.2), transparent 60%);
  opacity: 0.6;
}

.botMediaReadonly {
  font-size: 12px;
  opacity: 0.75;
}

.msg {
  margin-top: 4px;
  padding: 8px 10px;
  border-radius: 10px;
  font-size: 13px;
}

.msg.err {
  border: 1px solid rgb(var(--color-danger-rgb, 239 68 68) / 0.35);
  background: rgb(var(--color-danger-rgb, 239 68 68) / 0.08);
  color: var(--color-danger);
}

.fileInput {
  display: none;
}

.avatarModalSub {
  margin: 4px 0 0;
}

.avatarEditorBody,
.coverEditorBody {
  display: grid;
  gap: 12px;
}

.avatarModeSwitch {
  margin-top: 0;
  padding: 3px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.06);
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 3px;
}

.modeBtn {
  min-height: 38px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: inherit;
  font-weight: 700;
  cursor: pointer;
}

.modeBtn.active {
  background: rgb(var(--color-primary-rgb) / 0.18);
}

.avatarPreviewWrap {
  display: grid;
  justify-items: center;
  gap: 6px;
}

.avatarPreviewAvatar {
  width: 112px;
  height: 112px;
  margin: 0;
}

.avatarImageActions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.avatarHint {
  margin: 0;
  font-size: 12px;
  opacity: 0.8;
  text-align: center;
}

.avatarMsg {
  margin-top: 0;
}

.avatarPickerLabel {
  font-size: 13px;
  opacity: 0.85;
  margin-bottom: 6px;
}

.avatarIconGrid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 6px;
}

.avatarColorGrid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 6px;
}

.avatarChoice {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 6px;
  background: rgb(var(--color-bg-rgb) / 0.28);
  display: grid;
  justify-items: center;
  gap: 4px;
  color: inherit;
  transition: border-color 160ms ease, background-color 160ms ease;
  cursor: pointer;
}

.avatarChoice.active {
  border-color: rgb(var(--color-primary-rgb) / 0.8);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.choiceAvatar {
  width: 40px;
  height: 40px;
}

.choiceLabel {
  font-size: 11px;
  opacity: 0.8;
}

.colorSwatch {
  width: 26px;
  height: 26px;
  border-radius: 999px;
  border: 2px solid rgb(var(--color-bg-rgb) / 0.95);
  outline: 1px solid rgb(var(--text-primary-rgb) / 0.25);
  background: var(--avatar-choice-color);
}

.avatarActionRow {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.avatarActionRowSave {
  justify-content: flex-end;
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
  min-height: 38px;
  border: 1px solid transparent;
  border-radius: 999px;
  padding: 0 14px;
  background: rgb(var(--color-primary-rgb) / 0.9);
  color: rgb(var(--color-bg-rgb) / 1);
  font-weight: 600;
  cursor: pointer;
  transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
  background: rgb(var(--color-primary-rgb) / 1);
}

.btn.subtle {
  background: rgb(var(--color-surface-rgb) / 0.08);
  color: inherit;
  border-color: rgb(var(--color-surface-rgb) / 0.2);
}

.btn.outline {
  background: transparent;
  color: inherit;
  border-color: rgb(var(--color-surface-rgb) / 0.2);
}

.btn.ghost {
  background: transparent;
  color: inherit;
  border-color: rgb(var(--color-surface-rgb) / 0.16);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}
</style>
