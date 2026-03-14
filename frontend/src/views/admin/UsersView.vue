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
const page = ref(1)
const perPage = ref(20)
const data = ref(null)
const manageModalOpen = ref(false)
const manageModalLoading = ref(false)
const manageModalError = ref('')
const manageUser = ref(null)
let searchDebounce = null

const rows = computed(() => data.value?.data || [])
const totalUsers = computed(() => Number(data.value?.total || 0))
const hasActiveFilters = computed(() => Boolean(search.value))
const showSkeleton = computed(() => loading.value && rows.value.length === 0)
const isCurrentActorAdmin = computed(() => Boolean(auth.isAdmin))
const shouldShowPagination = computed(() => Number(data.value?.last_page || 1) > 1)
const manageAccountInfoRows = computed(() => {
  if (!manageUser.value) return []

  return [
    { key: 'id', label: 'ID pouzivatela', value: manageUser.value.id ?? '-' },
    { key: 'email', label: 'E-mail', value: userEmail(manageUser.value) },
    { key: 'username', label: 'Pouzivatelske meno', value: manageUser.value.username ? `@${manageUser.value.username}` : '-' },
    { key: 'role', label: 'Rola', value: roleLabel(manageUser.value) },
    { key: 'status', label: 'Stav', value: statusLabel(manageUser.value) },
    { key: 'created_at', label: 'Vytvoreny', value: formatDate(manageUser.value.created_at) },
    { key: 'banned_at', label: 'Zablokovany od', value: formatDate(manageUser.value.banned_at) },
    { key: 'ban_reason', label: 'Dovod blokacie', value: manageUser.value.ban_reason || '-' },
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

  const targetLabel = user.email || userName(user)
  const reason = await prompt({
    title: 'Zablokovat pouzivatela',
    message: `Zadajte dovod blokacie pre ${targetLabel}.`,
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
    const updated = updateRow(res.data)
    syncManageUser(updated)
    toast.success('Pouzivatel bol zablokovany.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Blokovanie zlyhalo.'
    toast.error(error.value)
  }
}

async function unbanUser(user) {
  if (!user || isSelf(user)) return

  const targetLabel = user.email || userName(user)
  const ok = await confirm({
    title: 'Odblokovat pouzivatela',
    message: `Odblokovat pouzivatela ${targetLabel}?`,
    confirmText: 'Odblokovat',
    cancelText: 'Zrusit',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/unban`)
    const updated = updateRow(res.data)
    syncManageUser(updated)
    toast.success('Pouzivatel bol odblokovany.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Odblokovanie zlyhalo.'
    toast.error(error.value)
  }
}

async function deactivateUser(user) {
  if (!user || isSelf(user) || !user.is_active) return

  const targetLabel = user.email || userName(user)
  const ok = await confirm({
    title: 'Deaktivovat pouzivatela',
    message: `Deaktivovat pouzivatela ${targetLabel}?`,
    confirmText: 'Deaktivovat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/deactivate`)
    const updated = updateRow(res.data)
    syncManageUser(updated)
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
    const updated = updateRow(res.data)
    syncManageUser(updated)
    toast.success('Pouzivatel bol reaktivovany.')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Reaktivacia zlyhala.'
    toast.error(error.value)
  }
}

async function resetProfile(user) {
  if (!user) return

  const targetLabel = user.email || userName(user)
  const ok = await confirm({
    title: 'Resetovat profil',
    message: `Resetovat profil pre ${targetLabel}?`,
    confirmText: 'Resetovat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })

  if (!ok) return

  try {
    const res = await api.post(`/admin/users/${user.id}/reset-profile`)
    const updated = updateRow(res.data)
    syncManageUser(updated)
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
    const updated = updateRow(res.data)
    syncManageUser(updated)
    clearStatsCache()
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

async function loadManageUser(userId) {
  if (!userId) return

  manageModalLoading.value = true
  manageModalError.value = ''

  try {
    const res = await api.get(`/admin/users/${userId}`)
    manageUser.value = res.data
    updateRow(res.data)
  } catch (e) {
    manageModalError.value = e?.response?.data?.message || 'Nepodarilo sa nacitat detail pouzivatela.'
  } finally {
    manageModalLoading.value = false
  }
}

function openManageUserModal(user) {
  if (!user?.id) return
  manageUser.value = { ...user }
  manageModalError.value = ''
  manageModalOpen.value = true
  loadManageUser(user.id)
}

function closeManageUserModal(isOpen) {
  if (isOpen) return
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
    items.push({ key: 'view', label: 'Zobrazit profil' })
  }

  items.push({ key: 'manage', label: 'Sprava uctu' })

  const targetRole = String(user.role || '').toLowerCase()
  const canToggleEditorRole = canManage && targetRole !== 'bot' && targetRole !== 'admin'

  if (canToggleEditorRole) {
    if (targetRole === 'user') {
      items.push({ key: 'grant-editor', label: 'Pridat rolu editor' })
    } else if (targetRole === 'editor') {
      items.push({ key: 'remove-editor', label: 'Odobrat rolu editor' })
    }
  }

  if (canManage) {
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
    openManageUserModal(user)
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

<template src="./users/UsersView.template.html"></template>

<style scoped src="./users/UsersView.css"></style>
