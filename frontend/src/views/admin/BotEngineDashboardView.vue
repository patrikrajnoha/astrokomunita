<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import AdminUserDetailView from '@/views/admin/AdminUserDetailView.vue'
import {
  deleteAllBotPosts,
  getBotPostRetentionSettings,
  runBotPostRetentionCleanup,
  updateBotPostRetentionSettings,
} from '@/services/api/admin/bots'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
  overview: {
    type: Object,
    default: () => ({
      window_hours: 24,
      generated_at: null,
      overall: {
        active_sources: 0,
        failing_sources: 0,
        dead_sources: 0,
        cooldown_skips_24h: 0,
      },
      bots: [],
    }),
  },
  overviewLoading: {
    type: Boolean,
    default: false,
  },
  refreshToken: {
    type: Number,
    default: 0,
  },
})

const retentionLoading = ref(false)
const retentionSaving = ref(false)
const retentionRunning = ref(false)
const deletingAllPosts = ref(false)

const retention = ref({
  enabled: false,
  auto_delete_after_hours: 48,
  allowed_hours: [24, 48, 72, 168],
  scheduled_frequency: 'hourly',
})

const retentionForm = ref({
  enabled: false,
  auto_delete_after_hours: 48,
})

const botDetailModalOpen = ref(false)
const selectedBotId = ref(null)
const selectedBotSnapshot = ref(null)
const botOverrides = ref({})

const { confirm } = useConfirm()
const toast = useToast()

const bots = computed(() => {
  const baseRows = Array.isArray(props.overview?.bots) ? props.overview.bots : []
  return baseRows.map((row) => {
    const rowId = Number(row?.id || 0)
    const override = rowId > 0 ? botOverrides.value[rowId] : null

    if (!override) {
      return row
    }

    return {
      ...row,
      ...override,
    }
  })
})
const selectedBot = computed(() => {
  const targetId = Number(selectedBotId.value || 0)
  if (targetId > 0) {
    const live = bots.value.find((row) => Number(row?.id || 0) === targetId)
    if (live) return live
  }

  return selectedBotSnapshot.value
})

const retentionAllowedHours = computed(() => {
  const values = Array.isArray(retention.value?.allowed_hours) ? retention.value.allowed_hours : []
  return values.filter((value) => Number.isInteger(Number(value)) && Number(value) > 0)
})

const retentionStatusLabel = computed(() => (retentionForm.value.enabled ? 'Zapnuté' : 'Vypnuté'))
const dashboardMetaLine = computed(() => {
  const windowHours = Number(props.overview?.window_hours || 24)
  return `Okno ${windowHours}h · aktualizované ${formatDateTime(props.overview?.generated_at)}`
})

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function botDisplayName(row) {
  return String(row?.name || row?.username || `Bot #${row?.id || '-'}`)
}

function botHandle(row) {
  const username = String(row?.username || '').trim()
  if (!username) return '-'
  return `@${username}`
}

function botAccountStatusLabel(row) {
  return row?.is_active ? 'Aktívny' : 'Neaktívny'
}

function botAccountStatusClass(row) {
  return row?.is_active ? 'is-active' : 'is-inactive'
}

function selectBotAccount(row) {
  if (!row) return
  const parsedId = Number(row.id || 0)
  selectedBotId.value = Number.isFinite(parsedId) && parsedId > 0 ? parsedId : null
  selectedBotSnapshot.value = { ...row }
}

function openBotDetailAccount(row) {
  selectBotAccount(row)
  botDetailModalOpen.value = true
}

function handleBotUserUpdated(updatedUser) {
  const targetId = Number(updatedUser?.id || 0)
  if (!Number.isFinite(targetId) || targetId <= 0) return

  botOverrides.value = {
    ...botOverrides.value,
    [targetId]: {
      ...(botOverrides.value[targetId] || {}),
      ...updatedUser,
    },
  }

  if (Number(selectedBotId.value || 0) === targetId) {
    selectedBotSnapshot.value = {
      ...(selectedBotSnapshot.value || {}),
      ...updatedUser,
    }
  }
}

function handleBotDetailModalToggle(isOpen) {
  botDetailModalOpen.value = Boolean(isOpen)
  if (!isOpen) {
    selectedBotId.value = null
    selectedBotSnapshot.value = null
  }
}

async function loadRetentionSettings() {
  retentionLoading.value = true
  try {
    const response = await getBotPostRetentionSettings()
    const data = response?.data?.data || {}
    const allowedHours = Array.isArray(data?.allowed_hours) && data.allowed_hours.length > 0
      ? data.allowed_hours
      : [24, 48, 72, 168]
    const selectedHours = Number(data?.auto_delete_after_hours || allowedHours[0] || 48)

    retention.value = {
      enabled: Boolean(data?.enabled),
      auto_delete_after_hours: selectedHours,
      allowed_hours: allowedHours,
      scheduled_frequency: String(data?.scheduled_frequency || 'hourly'),
    }
    retentionForm.value = {
      enabled: Boolean(data?.enabled),
      auto_delete_after_hours: selectedHours,
    }
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Načítanie retention nastavení zlyhalo.')
  } finally {
    retentionLoading.value = false
  }
}

async function saveRetentionSettings() {
  if (retentionSaving.value) return

  retentionSaving.value = true
  try {
    const response = await updateBotPostRetentionSettings({
      enabled: Boolean(retentionForm.value.enabled),
      auto_delete_after_hours: Number(retentionForm.value.auto_delete_after_hours || 0),
    })
    const data = response?.data?.data || {}
    retention.value = {
      enabled: Boolean(data?.enabled),
      auto_delete_after_hours: Number(data?.auto_delete_after_hours || retentionForm.value.auto_delete_after_hours || 48),
      allowed_hours: Array.isArray(data?.allowed_hours) && data.allowed_hours.length > 0
        ? data.allowed_hours
        : retentionAllowedHours.value,
      scheduled_frequency: String(data?.scheduled_frequency || 'hourly'),
    }
    retentionForm.value = {
      enabled: retention.value.enabled,
      auto_delete_after_hours: retention.value.auto_delete_after_hours,
    }
    toast.success('Nastavenie automatického mazania bolo uložené.')
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Uloženie retention nastavení zlyhalo.')
  } finally {
    retentionSaving.value = false
  }
}

async function deleteAllPublishedBotPosts() {
  if (deletingAllPosts.value) return

  const approved = await confirm({
    title: 'Vymazať príspevky botov',
    message: 'Naozaj vymazať všetky publikované príspevky botov?',
    confirmText: 'Vymazať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })
  if (!approved) return

  deletingAllPosts.value = true
  try {
    const response = await deleteAllBotPosts({})
    const result = response?.data || {}
    toast.success(
      `Vymazané: ${Number(result.deleted_posts || 0)} · chýbajúce: ${Number(result.missing_posts || 0)} · chyby: ${Number(result.failed_items || 0)}.`,
    )
    await loadRetentionSettings()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Mazanie príspevkov botov zlyhalo.')
  } finally {
    deletingAllPosts.value = false
  }
}

async function runCleanupNow() {
  if (retentionRunning.value) return

  const approved = await confirm({
    title: 'Spustiť cleanup',
    message: 'Spustiť okamžité mazanie príspevkov botov podľa retention pravidla?',
    confirmText: 'Spustiť',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })
  if (!approved) return

  retentionRunning.value = true
  try {
    const response = await runBotPostRetentionCleanup({ limit: 200 })
    const result = response?.data?.data || {}
    toast.success(
      `Cleanup dokončený: vymazané ${Number(result.deleted_posts || 0)}, chyby ${Number(result.failed_items || 0)}.`,
    )
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Retention cleanup zlyhal.')
  } finally {
    retentionRunning.value = false
  }
}

async function refreshAuxiliaryData() {
  await loadRetentionSettings()
}

watch(
  () => props.refreshToken,
  () => {
    void refreshAuxiliaryData()
  },
)

onMounted(() => {
  void refreshAuxiliaryData()
})
</script>

<template src="./botEngineDashboard/BotEngineDashboardView.template.html"></template>

<style scoped src="./botEngineDashboard/BotEngineDashboardView.css"></style>
