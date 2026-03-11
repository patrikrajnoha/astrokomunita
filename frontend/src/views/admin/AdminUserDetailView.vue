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
import BaseModal from '@/components/ui/BaseModal.vue'
import DefaultAvatar from '@/components/DefaultAvatar.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { AVATAR_COLORS } from '@/constants/avatar'
import {
  formatDate,
  reportType,
  roleClass,
  roleLabel,
  statusClass,
  statusLabel,
  subjectLabel,
} from './adminUserDetailView.utils'
import { useAdminUserBotMediaEditor } from './userDetail/useAdminUserBotMediaEditor'

const route = useRoute()
const auth = useAuthStore()
const { confirm, prompt } = useConfirm()
const toast = useToast()

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
const profileForm = ref({
  name: '',
  bio: '',
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
const canEditProfile = computed(() => Boolean(user.value && isBotTarget.value && isCurrentActorAdmin.value))
const canModerateAccount = computed(() =>
  Boolean(
    user.value
    && isCurrentActorAdmin.value
    && !isSelf(user.value)
    && roleLabel(user.value) !== 'admin',
  ),
)
const canUseDangerActions = computed(() => canModerateAccount.value)
const canToggleEditorRole = computed(() =>
  Boolean(
    user.value
    && canModerateAccount.value
    && !isBotTarget.value
    && roleLabel(user.value) !== 'admin',
  ),
)
const canUploadBotMedia = computed(() => isBotTarget.value && canEditProfile.value && isCurrentActorAdmin.value)
const profileIdentifier = computed(() => {
  if (!user.value) return '-'
  if (user.value.username) return `@${user.value.username}`
  if (user.value.email) return user.value.email
  return `ID ${user.value.id ?? '-'}`
})
const statusText = computed(() => statusLabel(user.value))
const roleText = computed(() => roleLabel(user.value))
const accountInfoRows = computed(() => {
  if (!user.value) return []

  return [
    { key: 'id', label: 'ID pouzivatela', value: user.value.id ?? '-' },
    { key: 'email', label: 'E-mail', value: user.value.email || '-' },
    { key: 'username', label: 'Pouzivatelske meno', value: user.value.username ? `@${user.value.username}` : '-' },
    { key: 'role', label: 'Rola', value: roleText.value },
    { key: 'status', label: 'Stav', value: statusText.value },
    { key: 'created_at', label: 'Vytvoreny', value: formatDate(user.value.created_at) },
    { key: 'banned_at', label: 'Zablokovany od', value: formatDate(user.value.banned_at) },
    { key: 'ban_reason', label: 'Dovod blokacie', value: user.value.ban_reason || '-' },
  ]
})

const reportColumns = [
  { key: 'type', label: 'Typ' },
  { key: 'reason', label: 'Dovod' },
  { key: 'status', label: 'Stav' },
  { key: 'created_at', label: 'Vytvorene' },
  { key: 'actions', label: 'Akcie', align: 'right' },
]

const {
  avatarDraft,
  avatarErr,
  avatarInput,
  avatarModalOpen,
  avatarRemoving,
  avatarResolved,
  avatarSaving,
  avatarSrc,
  avatarUploading,
  botCoverMedia,
  clearPendingMedia,
  cleanupBotMediaEditor,
  closeAvatarEditor,
  closeCoverEditor,
  coverEditorMedia,
  coverInput,
  coverModalOpen,
  coverRemoving,
  coverSaving,
  coverUploading,
  handleAvatarModalToggle,
  handleCoverModalToggle,
  iconOptions,
  markAvatarImageForRemoval,
  markCoverForRemoval,
  mediaActionBusy,
  mediaError,
  onBotMediaChange,
  openAvatarEditor,
  openBotMediaPicker,
  openCoverEditor,
  persistedAvatarMode,
  randomizeAvatar,
  resetBotMediaEditorState,
  resetGeneratedAvatar,
  saveAvatarPreferences,
  saveCoverEditor,
  selectAvatarColor,
  selectAvatarIcon,
  setAvatarMode,
  syncAvatarDraftFromUser,
} = useAdminUserBotMediaEditor({
  user,
  userLoading,
  canUploadBotMedia,
  updateUser,
  toast,
})

function isSelf(userRow) {
  return auth.user && userRow && Number(auth.user.id) === Number(userRow.id)
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
    userError.value = e?.response?.data?.message || 'Nepodarilo sa nacitat pouzivatela.'
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
    reportsError.value = e?.response?.data?.message || 'Nepodarilo sa nacitat reporty.'
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
  if (!user.value || !canModerateAccount.value) return
  const reason = await prompt({
    title: 'Zablokovat pouzivatela',
    message: `Zadajte dovod blokacie pre ${subjectLabel(user.value)}.`,
    confirmText: 'Zablokovat',
    cancelText: 'Zrusit',
    placeholder: 'Dovod blokacie...',
    required: true,
    multiline: true,
    variant: 'danger',
  })
  if (!reason) return

  try {
    const res = await api.patch(`/admin/users/${user.value.id}/ban`, { reason: String(reason).trim() })
    updateUser(res.data)
    toast.success('Pouzivatel bol zablokovany.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Blokovanie zlyhalo.'
    toast.error(userError.value)
  }
}

async function unbanUser() {
  if (!user.value || !canModerateAccount.value) return
  const ok = await confirm({
    title: 'Odblokovat pouzivatela',
    message: `Odblokovat pouzivatela ${subjectLabel(user.value)}?`,
    confirmText: 'Odblokovat',
    cancelText: 'Zrusit',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/unban`)
    updateUser(res.data)
    toast.success('Pouzivatel bol odblokovany.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Odblokovanie zlyhalo.'
    toast.error(userError.value)
  }
}

async function deactivateUser() {
  if (!user.value || !canModerateAccount.value || !user.value.is_active) return
  const ok = await confirm({
    title: 'Deaktivovat pouzivatela',
    message: `Deaktivovat pouzivatela ${subjectLabel(user.value)}?`,
    confirmText: 'Deaktivovat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/deactivate`)
    updateUser(res.data)
    toast.success('Pouzivatel bol deaktivovany.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Deaktivacia zlyhala.'
    toast.error(userError.value)
  }
}

async function reactivateUser() {
  if (!user.value || !canModerateAccount.value || user.value.is_active) return
  const ok = await confirm({
    title: 'Reaktivovat pouzivatela',
    message: `Reaktivovat pouzivatela ${subjectLabel(user.value)}?`,
    confirmText: 'Reaktivovat',
    cancelText: 'Zrusit',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/reactivate`)
    updateUser(res.data)
    toast.success('Pouzivatel bol reaktivovany.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Reaktivacia zlyhala.'
    toast.error(userError.value)
  }
}

async function resetProfile() {
  if (!user.value || !canUseDangerActions.value) return
  const ok = await confirm({
    title: 'Resetovat profil',
    message: `Resetovat profil pre ${subjectLabel(user.value)}?`,
    confirmText: 'Resetovat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/reset-profile`)
    updateUser(res.data)
    toast.success('Profil bol resetovany.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Reset profilu zlyhal.'
    toast.error(userError.value)
  }
}

async function updateEditorRole(nextRole) {
  if (!user.value || !canToggleEditorRole.value) return

  try {
    const res = await api.patch(`/admin/users/${user.value.id}/role`, { role: nextRole })
    updateUser(res.data)
    toast.success(nextRole === 'editor' ? 'Rola editor bola pridana.' : 'Rola editor bola odobrata.')
  } catch (e) {
    const status = Number(e?.response?.status || 0)
    if (status === 403) {
      userError.value = 'Nemate opravnenie menit roly.'
    } else if (status === 422) {
      userError.value = e?.response?.data?.message || 'Zmena roly je neplatna.'
    } else {
      userError.value = e?.response?.data?.message || 'Zmena roly zlyhala.'
    }
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
    toast.success('Profil bol aktualizovany.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Aktualizacia profilu zlyhala.'
    toast.error(userError.value)
  }
}

async function reportAction(report, action) {
  if (!report?.id) return

  const isHide = action === 'hide'
  const title = isHide ? 'Skryt nahlaseny obsah?' : 'Potvrdit akciu?'
  const message = isHide
    ? 'Nahlaseny obsah bude skryty pre ostatnych pouzivatelov.'
    : `Naozaj vykonat "${action}"?`

  const ok = await confirm({
    title,
    message,
    confirmText: isHide ? 'Skryt obsah' : 'Potvrdit',
    cancelText: 'Zrusit',
    variant: isHide ? 'danger' : 'default',
  })
  if (!ok) return

  try {
    await api.post(`/admin/reports/${report.id}/${action}`)
    loadReports()
    toast.success('Akcia bola dokoncena.')
  } catch (e) {
    reportsError.value = e?.response?.data?.message || 'Akcia zlyhala.'
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
  loadReports()
})

watch(
  () => route.params.id,
  () => {
    clearReportFilters()
    resetBotMediaEditorState()
    loadUser()
    loadReports()
  },
)

watch(
  () => avatarModalOpen.value,
  (isOpen, wasOpen) => handleAvatarModalToggle(isOpen, wasOpen),
)

watch(
  () => coverModalOpen.value,
  (isOpen, wasOpen) => handleCoverModalToggle(isOpen, wasOpen),
)

loadUser()
loadReports()

onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce)
  cleanupBotMediaEditor()
})
</script>

<template src="./userDetail/AdminUserDetailView.template.html"></template>

<style scoped src="./userDetail/AdminUserDetailView.css"></style>
