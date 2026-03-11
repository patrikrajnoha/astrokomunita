<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import DashboardSection from '@/components/admin/dashboard/DashboardSection.vue'
import KpiCard from '@/components/admin/dashboard/KpiCard.vue'
import QuickActionTile from '@/components/admin/dashboard/QuickActionTile.vue'
import StatsChart from '@/components/admin/dashboard/StatsChart.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { getStats, downloadStatsCsv } from '@/services/api/admin/stats'
import { getAuthSettings, updateAuthSettings } from '@/services/api/admin/authSettings'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const stats = ref(null)
const trendMetric = ref('new_posts')
const authSettings = ref({ require_email_verification_for_new_users: true })
const authSettingsLoading = ref(false)
const authSettingsSaving = ref(false)
const authSettingsError = ref('')

const trendMetricOptions = [
  { key: 'new_posts', label: 'Príspevky' },
  { key: 'new_users', label: 'Používatelia' },
  { key: 'new_events', label: 'Udalosti' },
]

const emailVerificationHint =
  'Platí len pre nových používateľov. Pri vypnutí sa nové účty overia automaticky.'

const emailVerificationEnabled = computed(() =>
  Boolean(authSettings.value.require_email_verification_for_new_users),
)

const emailVerificationStateLabel = computed(() =>
  emailVerificationEnabled.value ? 'Zapnuté' : 'Vypnuté',
)

const kpiCards = computed(() => {
  const kpi = stats.value?.kpi || {}

  return [
    {
      key: 'users_total',
      label: 'Používatelia',
      value: Number(kpi.users_total || 0),
      viewTo: { name: 'admin.users' },
    },
    {
      key: 'users_active_30d',
      label: 'Aktívni (30 dní)',
      value: Number(kpi.users_active_30d || 0),
      viewTo: { name: 'admin.users' },
    },
    {
      key: 'posts_total',
      label: 'Príspevky',
      value: Number(kpi.posts_total || 0),
      viewTo: { name: 'admin.moderation' },
    },
    {
      key: 'events_total',
      label: 'Udalosti',
      value: Number(kpi.events_total || 0),
      viewTo: { name: 'admin.events' },
    },
    {
      key: 'posts_moderated_total',
      label: 'Moderované',
      value: Number(kpi.posts_moderated_total || 0),
      viewTo: { name: 'admin.moderation' },
      tone: 'accent',
    },
  ]
})

const byRoleList = computed(() => {
  const byRole = stats.value?.demographics?.by_role || {}
  return [
    { key: 'user', label: 'Používatelia', value: Number(byRole.user || 0) },
    { key: 'admin', label: 'Administrátori', value: Number(byRole.admin || 0) },
    { key: 'bot', label: 'Boti', value: Number(byRole.bot || 0) },
  ]
})

const byRegionList = computed(() => {
  const byRegion = stats.value?.demographics?.by_region || {}
  return [
    { key: 'unknown', label: 'Nezadané', value: Number(byRegion.unknown || 0) },
    { key: 'sk', label: 'Slovensko', value: Number(byRegion.sk || 0) },
    { key: 'cz', label: 'Česko', value: Number(byRegion.cz || 0) },
    { key: 'other', label: 'Ostatné', value: Number(byRegion.other || 0) },
  ]
})

const trendPoints = computed(() => {
  const points = stats.value?.trend?.points
  return Array.isArray(points) ? points : []
})

const quickActions = computed(() => {
  const queues = stats.value?.queues || {}
  const kpi = stats.value?.kpi || {}
  const moderationAttention =
    Number(queues.moderation_pending || 0) + Number(queues.moderation_flagged || 0)

  return [
    {
      title: 'Centrum zberu',
      subtitle: 'Zdroje, behy a kandidáti na jednom mieste.',
      to: { name: 'admin.event-sources' },
      badge: Number(kpi.events_total || 0),
    },
    {
      title: 'Kontrola kandidátov',
      subtitle: 'Schválenie alebo odmietnutie čakajúcich udalostí.',
      to: { name: 'admin.event-candidates' },
      badge: Number(queues.event_candidates_pending || 0),
      badgeTone: Number(queues.event_candidates_pending || 0) > 0 ? 'accent' : 'neutral',
    },
    {
      title: 'Správa používateľov',
      subtitle: 'Profily, roly, blokácie a stav účtu.',
      to: { name: 'admin.users' },
      badge: Number(kpi.users_total || 0),
    },
    {
      title: 'Moderácia',
      subtitle: 'Obsah čakajúci na zásah moderátora.',
      to: { name: 'admin.moderation' },
      badge: moderationAttention,
      badgeTone: moderationAttention > 0 ? 'accent' : 'neutral',
    },
  ]
})

function formatNumber(value) {
  return new Intl.NumberFormat('sk-SK').format(Number(value || 0))
}

async function loadDashboard(force = false) {
  loading.value = true
  error.value = ''

  try {
    stats.value = await getStats({ force })
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nepodarilo sa načítať štatistiky administrácie.'
  } finally {
    loading.value = false
  }
}

async function exportCsv() {
  if (exporting.value) return
  exporting.value = true

  try {
    const { blob, filename } = await downloadStatsCsv()
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)

    toast.success('CSV bolo exportované.')
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Export CSV zlyhal.')
  } finally {
    exporting.value = false
  }
}

async function loadAuthSettings() {
  authSettingsLoading.value = true
  authSettingsError.value = ''

  try {
    const response = await getAuthSettings()
    const payload = response?.data?.data
    const required =
      typeof payload?.require_email_verification_for_new_users === 'boolean'
        ? payload.require_email_verification_for_new_users
        : payload?.require_email_verification

    if (typeof required === 'boolean') {
      authSettings.value = { require_email_verification_for_new_users: required }
    }
  } catch (e) {
    authSettingsError.value =
      e?.response?.data?.message || 'Nepodarilo sa načítať nastavenie overenia.'
  } finally {
    authSettingsLoading.value = false
  }
}

async function toggleEmailVerification(required) {
  if (authSettingsSaving.value) return

  authSettingsSaving.value = true
  authSettingsError.value = ''

  try {
    const response = await updateAuthSettings({
      require_email_verification_for_new_users: required,
    })

    const payload = response?.data?.data
    const resolved =
      typeof payload?.require_email_verification_for_new_users === 'boolean'
        ? payload.require_email_verification_for_new_users
        : payload?.require_email_verification

    if (typeof resolved === 'boolean') {
      authSettings.value = { require_email_verification_for_new_users: resolved }
    } else {
      authSettings.value = { require_email_verification_for_new_users: required }
    }

    toast.success(
      required
        ? 'Overenie e-mailu bolo zapnuté.'
        : 'Overenie e-mailu pre nových používateľov bolo vypnuté.',
    )
  } catch (e) {
    authSettingsError.value =
      e?.response?.data?.message || 'Nepodarilo sa uložiť nastavenie overenia.'
    toast.error(authSettingsError.value)
  } finally {
    authSettingsSaving.value = false
  }
}

onMounted(() => {
  loadDashboard(false)
  loadAuthSettings()
})
</script>

<template src="./dashboard/AdminDashboardView.template.html"></template>

<style scoped src="./dashboard/AdminDashboardView.css"></style>
