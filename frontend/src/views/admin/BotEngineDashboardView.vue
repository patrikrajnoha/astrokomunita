<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  deleteAllBotPosts,
  getBotOverview,
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
})

const loading = ref(false)
const error = ref('')
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
const payload = ref({
  window_hours: 24,
  generated_at: null,
  overall: {
    active_sources: 0,
    failing_sources: 0,
    dead_sources: 0,
    cooldown_skips_24h: 0,
  },
  bots: [],
})
const { confirm } = useConfirm()
const toast = useToast()

const bots = computed(() => (Array.isArray(payload.value?.bots) ? payload.value.bots : []))
const overall = computed(() => payload.value?.overall || {})
const retentionAllowedHours = computed(() => {
  const values = Array.isArray(retention.value?.allowed_hours) ? retention.value.allowed_hours : []
  return values.filter((value) => Number.isInteger(Number(value)) && Number(value) > 0)
})
const retentionStatusLabel = computed(() => (retentionForm.value.enabled ? 'Zapnute' : 'Vypnute'))
const dashboardMetaLine = computed(() => {
  const windowHours = Number(payload.value?.window_hours || 24)
  return `Okno ${windowHours}h | aktualizovane ${formatDateTime(payload.value?.generated_at)}`
})

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function rateLimitLabel(row) {
  const state = row?.rate_limit_state || {}
  if (state.limited) {
    return `LIMIT (${Number(state.retry_after_sec || 0)}s)`
  }
  const remaining = Number(state.remaining_attempts || 0)
  const max = Number(state.max_attempts || 0)
  if (max <= 0) return 'OFF'
  return `${remaining}/${max}`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await getBotOverview()
    payload.value = response?.data || payload.value
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nacitanie bot overview zlyhalo.'
  } finally {
    loading.value = false
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
    toast.error(e?.response?.data?.message || 'Nacitanie retention nastaveni zlyhalo.')
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
    toast.success('Nastavenie auto mazania bot prispevkov bolo ulozene.')
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Ulozenie retention nastaveni zlyhalo.')
  } finally {
    retentionSaving.value = false
  }
}

async function deleteAllPublishedBotPosts() {
  if (deletingAllPosts.value) return

  const approved = await confirm({
    title: 'Vymazat bot prispevky',
    message: 'Naozaj vymazat publikovane bot prispevky?',
    confirmText: 'Vymazat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!approved) return

  deletingAllPosts.value = true
  try {
    const response = await deleteAllBotPosts({})
    const result = response?.data || {}
    toast.success(
      `Vymazane posty: ${Number(result.deleted_posts || 0)} | bez postu: ${Number(result.missing_posts || 0)} | chyby: ${Number(result.failed_items || 0)}.`,
    )
    await Promise.all([load(), loadRetentionSettings()])
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Mazanie bot prispevkov zlyhalo.')
  } finally {
    deletingAllPosts.value = false
  }
}

async function runCleanupNow() {
  if (retentionRunning.value) return

  const approved = await confirm({
    title: 'Spustit cleanup',
    message: 'Spustit okamzite vymazanie bot prispevkov podla retention pravidla?',
    confirmText: 'Spustit',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!approved) return

  retentionRunning.value = true
  try {
    const response = await runBotPostRetentionCleanup({ limit: 200 })
    const result = response?.data?.data || {}
    toast.success(
      `Cleanup hotovy: vymazane ${Number(result.deleted_posts || 0)} posty, chyby ${Number(result.failed_items || 0)}.`,
    )
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Retention cleanup zlyhal.')
  } finally {
    retentionRunning.value = false
  }
}

onMounted(() => {
  void load()
  void loadRetentionSettings()
})
</script>

<template src="./botEngineDashboard/BotEngineDashboardView.template.html"></template>

<style scoped src="./botEngineDashboard/BotEngineDashboardView.css"></style>
