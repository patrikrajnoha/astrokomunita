<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminSectionHeader from '@/components/admin/AdminSectionHeader.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { clearStatsCache } from '@/services/api/admin/stats'
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

const props = defineProps({
  userId: {
    type: [String, Number],
    default: null,
  },
  embedded: {
    type: Boolean,
    default: false,
  },
})
const emit = defineEmits(['user-updated'])

const route = useRoute()
const router = useRouter()
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
let previousBodyOverflow = ''

function normalizeUserId(rawValue) {
  if (Array.isArray(rawValue)) {
    return normalizeUserId(rawValue[0])
  }
  const normalized = String(rawValue ?? '').trim()
  if (normalized === '') return ''
  const parsed = Number.parseInt(normalized, 10)
  return Number.isFinite(parsed) && parsed > 0 ? String(parsed) : ''
}

const userId = computed(() => {
  const fromProps = normalizeUserId(props.userId)
  if (fromProps !== '') return fromProps
  return normalizeUserId(route.params.id)
})
const isFullPopupRoute = computed(() => String(route.name || '') === 'admin.users.detail.page')
const usersListRoute = computed(() => ({
  name: 'admin.users',
  query: { ...route.query },
}))
const reportRows = computed(() => reportsData.value?.data || [])
const isCurrentActorAdmin = computed(() => Boolean(auth.isAdmin))
const isBotTarget = computed(() => String(user.value?.role || '').toLowerCase() === 'bot' || Boolean(user.value?.is_bot))
const canShowReports = computed(() => Boolean(user.value) && !isBotTarget.value)
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
    { key: 'id', label: 'ID používateľa', value: user.value.id ?? '-' },
    { key: 'email', label: 'E-mail', value: user.value.email || '-' },
    { key: 'username', label: 'Používateľské meno', value: user.value.username ? `@${user.value.username}` : '-' },
    { key: 'role', label: 'Rola', value: roleText.value },
    { key: 'status', label: 'Stav', value: statusText.value },
    { key: 'created_at', label: 'Vytvorený', value: formatDate(user.value.created_at) },
    { key: 'banned_at', label: 'Zablokovaný od', value: formatDate(user.value.banned_at) },
    { key: 'ban_reason', label: 'Dôvod blokácie', value: user.value.ban_reason || '-' },
  ]
})

const reportColumns = [
  { key: 'type', label: 'Typ' },
  { key: 'reason', label: 'Dôvod' },
  { key: 'status', label: 'Stav' },
  { key: 'created_at', label: 'Vytvorené' },
  { key: 'actions', label: 'Akcie', align: 'right' },
]

const {
  avatarDraft,
  avatarErr,
  avatarInput,
  avatarModalOpen,
  avatarRemoving,
  avatarSaving,
  avatarSrc,
  avatarUploading,
  botAvatarOptions,
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
  markAvatarImageForRemoval,
  markCoverForRemoval,
  mediaActionBusy,
  mediaError,
  onBotMediaChange,
  openAvatarEditor,
  openBotMediaPicker,
  openCoverEditor,
  persistedAvatarMode,
  resetBotMediaEditorState,
  saveAvatarPreferences,
  saveCoverEditor,
  selectedBotAvatarFile,
  selectBotAvatar,
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
  if (!userId.value) return

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

    if (isBotTarget.value) {
      reportsData.value = null
      reportsError.value = ''
      reportsLoading.value = false
      return
    }

    loadReports()
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Nepodarilo sa načítať používateľa.'
  } finally {
    userLoading.value = false
  }
}

async function loadReports() {
  if (!userId.value || !canShowReports.value) {
    reportsLoading.value = false
    reportsError.value = ''
    reportsData.value = null
    return
  }

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
    reportsError.value = e?.response?.data?.message || 'Nepodarilo sa načítať reporty.'
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
  emit('user-updated', { ...user.value })
}

async function banUser() {
  if (!user.value || !canModerateAccount.value) return
  const reason = await prompt({
    title: 'Zablokovať používateľa',
    message: `Zadajte dôvod blokácie pre ${subjectLabel(user.value)}.`,
    confirmText: 'Zablokovať',
    cancelText: 'Zrušiť',
    placeholder: 'Dôvod blokácie...',
    required: true,
    multiline: true,
    variant: 'danger',
  })
  if (!reason) return

  try {
    const res = await api.patch(`/admin/users/${user.value.id}/ban`, { reason: String(reason).trim() })
    updateUser(res.data)
    toast.success('Používateľ bol zablokovaný.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Blokovanie zlyhalo.'
    toast.error(userError.value)
  }
}

async function unbanUser() {
  if (!user.value || !canModerateAccount.value) return
  const ok = await confirm({
    title: 'Odblokovať používateľa',
    message: `Odblokovať používateľa ${subjectLabel(user.value)}?`,
    confirmText: 'Odblokovať',
    cancelText: 'Zrušiť',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/unban`)
    updateUser(res.data)
    toast.success('Používateľ bol odblokovaný.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Odblokovanie zlyhalo.'
    toast.error(userError.value)
  }
}

async function deactivateUser() {
  if (!user.value || !canModerateAccount.value || !user.value.is_active) return
  const ok = await confirm({
    title: 'Deaktivovať používateľa',
    message: `Deaktivovať používateľa ${subjectLabel(user.value)}?`,
    confirmText: 'Deaktivovať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/deactivate`)
    updateUser(res.data)
    toast.success('Používateľ bol deaktivovaný.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Deaktivácia zlyhala.'
    toast.error(userError.value)
  }
}

async function reactivateUser() {
  if (!user.value || !canModerateAccount.value || user.value.is_active) return
  const ok = await confirm({
    title: 'Reaktivovať používateľa',
    message: `Reaktivovať používateľa ${subjectLabel(user.value)}?`,
    confirmText: 'Reaktivovať',
    cancelText: 'Zrušiť',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/reactivate`)
    updateUser(res.data)
    toast.success('Používateľ bol reaktivovaný.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Reaktivácia zlyhala.'
    toast.error(userError.value)
  }
}

async function resetProfile() {
  if (!user.value || !canUseDangerActions.value) return
  const ok = await confirm({
    title: 'Resetovať profil',
    message: `Resetovať profil pre ${subjectLabel(user.value)}?`,
    confirmText: 'Resetovať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })
  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.value.id}/reset-profile`)
    updateUser(res.data)
    toast.success('Profil bol resetovaný.')
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
    clearStatsCache()
    toast.success(nextRole === 'editor' ? 'Rola editor bola pridaná.' : 'Rola editor bola odobratá.')
  } catch (e) {
    const status = Number(e?.response?.status || 0)
    if (status === 403) {
      userError.value = 'Nemáte oprávnenie meniť roly.'
    } else if (status === 422) {
      userError.value = e?.response?.data?.message || 'Zmena roly je neplatná.'
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
    toast.success('Profil bol aktualizovaný.')
  } catch (e) {
    userError.value = e?.response?.data?.message || 'Aktualizácia profilu zlyhala.'
    toast.error(userError.value)
  }
}

async function reportAction(report, action) {
  if (!report?.id) return

  const isHide = action === 'hide'
  const title = isHide ? 'Skryť nahlásený obsah?' : 'Potvrdiť akciu?'
  const message = isHide
    ? 'Nahlásený obsah bude skrytý pre ostatných používateľov.'
    : `Naozaj vykonať "${action}"?`

  const ok = await confirm({
    title,
    message,
    confirmText: isHide ? 'Skryť obsah' : 'Potvrdiť',
    cancelText: 'Zrušiť',
    variant: isHide ? 'danger' : 'default',
  })
  if (!ok) return

  try {
    await api.post(`/admin/reports/${report.id}/${action}`)
    loadReports()
    toast.success('Akcia bola dokončená.')
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

function closeDetailPopup() {
  if (!isFullPopupRoute.value) return
  router.push(usersListRoute.value)
}

function handleViewportBackdropClick() {
  if (props.embedded) return
  closeDetailPopup()
}

function handlePopupKeydown(event) {
  if (!isFullPopupRoute.value) return
  if (event?.key === 'Escape') {
    event.preventDefault()
    closeDetailPopup()
  }
}

function applyBodyScrollLock(active) {
  if (typeof document === 'undefined') return

  if (active) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return
  }

  document.body.style.overflow = previousBodyOverflow
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
  if (!canShowReports.value) return
  loadReports()
})

watch(
  () => userId.value,
  (nextUserId) => {
    if (!nextUserId) return
    clearReportFilters()
    resetBotMediaEditorState()
    loadUser()
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

watch(isFullPopupRoute, (active) => {
  if (props.embedded) return
  applyBodyScrollLock(active)
}, { immediate: true })

onMounted(() => {
  if (props.embedded) return
  window.addEventListener('keydown', handlePopupKeydown)
})

onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce)
  if (!props.embedded) {
    window.removeEventListener('keydown', handlePopupKeydown)
    applyBodyScrollLock(false)
  }
  cleanupBotMediaEditor()
})
</script>

<template src="./userDetail/AdminUserDetailView.template.html"></template>

<style scoped src="./userDetail/AdminUserDetailView.css"></style>
