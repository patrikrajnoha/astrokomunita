<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import AdminUserDetailView from '@/views/admin/AdminUserDetailView.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { clearStatsCache } from '@/services/api/admin/stats'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { confirm, prompt } = useConfirm()
const toast = useToast()

const loading = ref(false)
const error = ref('')
const searchInput = ref('')
const search = ref('')
const filterRole = ref('')
const filterStatus = ref('')
const page = ref(1)
const perPage = ref(20)
const data = ref(null)
const manageModalOpen = ref(false)
const manageModalLoading = ref(false)
const manageModalError = ref('')
const manageUser = ref(null)
const manageModalView = ref('summary')
let searchDebounce = null

const rows = computed(() => data.value?.data || [])
const totalUsers = computed(() => Number(data.value?.total || 0))
const hasActiveFilters = computed(() => Boolean(search.value || filterRole.value || filterStatus.value))
const showSkeleton = computed(() => loading.value && rows.value.length === 0)
const isCurrentActorAdmin = computed(() => Boolean(auth.isAdmin))
const shouldShowPagination = computed(() => Number(data.value?.last_page || 1) > 1)
const currentPage = computed(() => Number(data.value?.current_page || page.value))
const lastPage = computed(() => Number(data.value?.last_page || 1))
const detailRouteUserId = computed(() => {
  if (route.name !== 'admin.users.detail') return null
  const rawId = Array.isArray(route.params?.id) ? route.params.id[0] : route.params?.id
  const parsed = parsePositiveInt(rawId, 0)
  return parsed > 0 ? parsed : null
})

const manageAccountInfoRows = computed(() => {
  if (!manageUser.value) return []

  return [
    { key: 'id', label: 'ID používateľa', value: manageUser.value.id ?? '-' },
    { key: 'email', label: 'E-mail', value: userEmail(manageUser.value) },
    { key: 'username', label: 'Používateľské meno', value: manageUser.value.username ? `@${manageUser.value.username}` : '-' },
    { key: 'role', label: 'Rola', value: roleLabel(manageUser.value) },
    { key: 'status', label: 'Stav', value: statusLabel(manageUser.value) },
    { key: 'created_at', label: 'Vytvorený', value: formatDate(manageUser.value.created_at) },
    { key: 'banned_at', label: 'Zablokovaný od', value: formatDate(manageUser.value.banned_at) },
    { key: 'ban_reason', label: 'Dôvod blokovania', value: manageUser.value.ban_reason || '-' },
  ]
})

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'
  return date.toLocaleString('sk-SK', {
    dateStyle: 'medium',
    timeStyle: 'medium',
  })
}

function parsePositiveInt(value, fallback) {
  const parsed = Number.parseInt(String(value || ''), 10)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
}

function sanitizeRoleFilter(value) {
  const normalized = String(value || '').trim().toLowerCase()
  if (normalized === 'admin' || normalized === 'editor' || normalized === 'user') {
    return normalized
  }
  return ''
}

function readQuery(query) {
  const qSearch = typeof query.search === 'string' ? query.search : ''
  const qPage = parsePositiveInt(query.page, 1)
  const qPerPage = parsePositiveInt(query.per_page, 20)
  const qRole = sanitizeRoleFilter(typeof query.role === 'string' ? query.role : '')
  const qStatus = typeof query.status === 'string' ? query.status : ''

  searchInput.value = qSearch
  search.value = qSearch
  page.value = qPage
  perPage.value = qPerPage
  filterRole.value = qRole
  filterStatus.value = qStatus
}

function buildQuery() {
  const query = {
    page: String(page.value),
    per_page: String(perPage.value),
  }

  if (search.value) query.search = search.value
  if (filterRole.value) query.role = filterRole.value
  if (filterStatus.value) query.status = filterStatus.value

  return query
}

function currentQuery() {
  return {
    page: String(parsePositiveInt(route.query.page, 1)),
    per_page: String(parsePositiveInt(route.query.per_page, 20)),
    search: typeof route.query.search === 'string' ? route.query.search : '',
    role: sanitizeRoleFilter(typeof route.query.role === 'string' ? route.query.role : ''),
    status: typeof route.query.status === 'string' ? route.query.status : '',
  }
}

function syncQueryWithState() {
  const next = buildQuery()
  const now = currentQuery()

  if (
    next.page === now.page
    && next.per_page === now.per_page
    && (next.search || '') === now.search
    && (next.role || '') === now.role
    && (next.status || '') === now.status
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

function canManageAccount(user) {
  return isCurrentActorAdmin.value && !isSelf(user) && !isAdminAccount(user)
}

function canToggleEditorRole(user) {
  if (!canManageAccount(user)) return false
  const targetRole = String(user?.role || '').toLowerCase()
  return targetRole !== 'bot' && targetRole !== 'admin'
}

function userName(user) {
  return String(user?.name || `User #${user?.id || '-'}`)
}

function userHandle(user) {
  if (user?.username) return `@${user.username}`
  return `ID: ${user?.id || '-'}`
}

function userEmail(user) {
  if (isBotAccount(user)) return '-'
  return String(user?.email || '-')
}

function botAccountHint() {
  return 'Automatizovaný účet — e-mail je zámerne prázdny.'
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const params = {
      page: page.value,
      per_page: perPage.value,
      include_bots: false,
    }

    if (search.value) params.search = search.value
    if (filterRole.value) params.role = filterRole.value
    if (filterStatus.value) params.status = filterStatus.value

    const res = await api.get('/admin/users', { params })
    data.value = res.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať používateľov.'
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
    return userRows[idx]
  }
  return updated
}

function syncManageUser(updated) {
  if (!manageUser.value || !updated) return
  if (Number(manageUser.value.id) !== Number(updated.id)) return
  manageUser.value = { ...manageUser.value, ...updated }
}

function openUserDetail(user) {
  if (!user?.id) return
  manageModalView.value = 'summary'
  manageModalOpen.value = true
  manageModalLoading.value = false
  manageModalError.value = ''
  manageUser.value = { ...user }
  loadManageUser(user.id)
}

function openUserDetailPage(user) {
  if (!user?.id) return
  manageModalView.value = 'detail'
  manageModalOpen.value = true
  manageModalError.value = ''
}

function openPublicProfile(user) {
  if (!user?.username) return
  router.push({ name: 'user-profile', params: { username: user.username } })
}

async function banUser(user) {
  if (!user || isSelf(user)) return

  const targetLabel = user.email || userName(user)
  const reason = await prompt({
    title: 'Zablokovať používateľa',
    message: `Zadajte dôvod blokovania pre ${targetLabel}.`,
    confirmText: 'Zablokovať',
    cancelText: 'Zrušiť',
    placeholder: 'Dôvod blokovania...',
    required: true,
    multiline: true,
    variant: 'danger',
  })

  if (!reason) return

  try {
    const res = await api.patch(`/admin/users/${user.id}/ban`, { reason: String(reason).trim() })
    const updated = updateRow(res.data)
    syncManageUser(updated)
    toast.success('Používateľ bol zablokovaný.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Blokovanie zlyhalo.'
    toast.error(error.value)
  }
}

async function unbanUser(user) {
  if (!user || isSelf(user)) return

  const targetLabel = user.email || userName(user)
  const ok = await confirm({
    title: 'Odblokovať používateľa',
    message: `Odblokovať používateľa ${targetLabel}?`,
    confirmText: 'Odblokovať',
    cancelText: 'Zrušiť',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/unban`)
    const updated = updateRow(res.data)
    syncManageUser(updated)
    toast.success('Používateľ bol odblokovaný.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Odblokovanie zlyhalo.'
    toast.error(error.value)
  }
}

async function deactivateUser(user) {
  if (!user || isSelf(user) || !user.is_active) return

  const targetLabel = user.email || userName(user)
  const ok = await confirm({
    title: 'Deaktivovať používateľa',
    message: `Deaktivovať používateľa ${targetLabel}?`,
    confirmText: 'Deaktivovať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/deactivate`)
    const updated = updateRow(res.data)
    syncManageUser(updated)
    toast.success('Používateľ bol deaktivovaný.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Deaktivácia zlyhala.'
    toast.error(error.value)
  }
}

async function reactivateUser(user) {
  if (!user || isSelf(user) || user.is_active) return

  const ok = await confirm({
    title: 'Reaktivovať používateľa',
    message: `Reaktivovať používateľa ${user.email || userName(user)}?`,
    confirmText: 'Reaktivovať',
    cancelText: 'Zrušiť',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/reactivate`)
    const updated = updateRow(res.data)
    syncManageUser(updated)
    toast.success('Používateľ bol reaktivovaný.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reaktivácia zlyhala.'
    toast.error(error.value)
  }
}

async function resetProfile(user) {
  if (!user) return

  const targetLabel = user.email || userName(user)
  const ok = await confirm({
    title: 'Resetovať profil',
    message: `Resetovať profil pre ${targetLabel}?`,
    confirmText: 'Resetovať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/reset-profile`)
    const updated = updateRow(res.data)
    syncManageUser(updated)
    toast.success('Profil bol resetovaný.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reset profilu zlyhal.'
    toast.error(error.value)
  }
}

async function updateEditorRole(user, nextRole) {
  if (!user || !isCurrentActorAdmin.value) return

  try {
    const res = await api.patch(`/admin/users/${user.id}/role`, { role: nextRole })
    const updated = updateRow(res.data)
    syncManageUser(updated)
    clearStatsCache()
    toast.success(nextRole === 'editor' ? 'Rola editora pridaná.' : 'Rola editora odobratá.')
  } catch (e) {
    const status = Number(e?.response?.status || 0)
    if (status === 403) {
      error.value = 'Nemáte oprávnenie meniť roly.'
    } else if (status === 422) {
      error.value = e?.response?.data?.message || 'Zmena roly je neplatná.'
    } else {
      error.value = e?.response?.data?.message || 'Zmena roly zlyhala.'
    }
    toast.error(error.value)
  }
}

async function loadManageUser(userId) {
  if (!userId) return

  manageModalLoading.value = true
  manageModalError.value = ''

  try {
    const res = await api.get(`/admin/users/${userId}`)
    manageUser.value = res.data
    updateRow(res.data)
  } catch (e) {
    manageModalError.value = e?.response?.data?.message || 'Nepodarilo sa načítať detail používateľa.'
  } finally {
    manageModalLoading.value = false
  }
}

function openManageUserModal(user) {
  openUserDetail(user)
}

function closeManageUserModal(isOpen) {
  if (isOpen) return
  manageModalView.value = 'summary'
  manageModalOpen.value = false
  manageModalLoading.value = false
  manageModalError.value = ''
  manageUser.value = null
}

function rowActionItems(user) {
  if (!user) return []

  const items = []
  const canManage = canManageAccount(user)

  if (user.username) {
    items.push({ key: 'view', label: 'Zobraziť profil' })
  }

  items.push({ key: 'manage', label: 'Správa účtu' })

  const targetRole = String(user.role || '').toLowerCase()
  const canToggle = canManage && targetRole !== 'bot' && targetRole !== 'admin'

  if (canToggle) {
    if (targetRole === 'user') {
      items.push({ key: 'grant-editor', label: 'Pridať rolu editor' })
    } else if (targetRole === 'editor') {
      items.push({ key: 'remove-editor', label: 'Odobrať rolu editor' })
    }
  }

  if (canManage) {
    if (user.is_banned) {
      items.push({ key: 'unban', label: 'Odblokovať účet' })
    } else {
      items.push({ key: 'ban', label: 'Zablokovať účet', danger: true })
    }

    if (user.is_active) {
      items.push({ key: 'deactivate', label: 'Deaktivovať účet', danger: true })
    } else {
      items.push({ key: 'reactivate', label: 'Reaktivovať účet' })
    }
    items.push({ key: 'reset', label: 'Resetovať profil', danger: true })
  }

  return items
}

async function onRowActionSelect(user, item) {
  if (loading.value || !item?.key) return

  if (item.key === 'view') { openPublicProfile(user); return }
  if (item.key === 'manage') { openManageUserModal(user); return }
  if (item.key === 'ban') { await banUser(user); return }
  if (item.key === 'unban') { await unbanUser(user); return }
  if (item.key === 'deactivate') { await deactivateUser(user); return }
  if (item.key === 'reactivate') { await reactivateUser(user); return }
  if (item.key === 'reset') { await resetProfile(user); return }
  if (item.key === 'grant-editor') { await updateEditorRole(user, 'editor'); return }
  if (item.key === 'remove-editor') { await updateEditorRole(user, 'user') }
}

function clearFilters() {
  searchInput.value = ''
  search.value = ''
  filterRole.value = ''
  filterStatus.value = ''
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
  (query) => { readQuery(query) },
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

watch([search, filterRole, filterStatus, page, perPage], () => {
  syncQueryWithState()
  load()
})

watch(
  () => detailRouteUserId.value,
  (userId) => {
    if (!userId) return

    const existing = rows.value.find((row) => Number(row?.id) === Number(userId))
    if (existing) {
      manageUser.value = { ...existing }
    }
    manageModalError.value = ''
    manageModalOpen.value = true
    manageModalView.value = 'summary'
    loadManageUser(userId)

    router.replace({
      name: 'admin.users',
      query: { ...route.query },
    })
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce)
})

readQuery(route.query)
syncQueryWithState()
load()
</script>

<template src="./users/UsersView.template.html"></template>

<style scoped src="./users/UsersView.css"></style>
