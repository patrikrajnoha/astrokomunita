<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import DashboardSection from '@/components/admin/dashboard/DashboardSection.vue'
import KpiCard from '@/components/admin/dashboard/KpiCard.vue'
import QuickActionTile from '@/components/admin/dashboard/QuickActionTile.vue'
import StatsChart from '@/components/admin/dashboard/StatsChart.vue'
import { getStats, downloadStatsCsv } from '@/services/api/admin/stats'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'

const toast = useToast()
const auth = useAuthStore()

const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const stats = ref(null)
const trendMetric = ref('new_posts')
const secondaryTab = ref('roles')

const trendMetricOptions = [
  { key: 'new_posts', label: 'Príspevky' },
  { key: 'new_users', label: 'Používatelia' },
  { key: 'new_events', label: 'Udalosti' },
]

const kpiCards = computed(() => {
  const kpi = stats.value?.kpi || {}

  return [
    {
      key: 'users_total',
      label: 'Používatelia',
      value: Number(kpi.users_total || 0),
      viewTo: { name: 'admin.users' },
      weight: 'primary',
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
  ]
})

const byRoleList = computed(() => {
  const byRole = stats.value?.demographics?.by_role || {}
  return [
    { key: 'user', label: 'Používatelia', value: Number(byRole.user || 0) },
    { key: 'admin', label: 'Administrátori', value: Number(byRole.admin || 0) },
    { key: 'editor', label: 'Editori', value: Number(byRole.editor || 0) },
    { key: 'bot', label: 'Boti', value: Number(byRole.bot || 0) },
  ]
})

const byRegionProfileList = computed(() => {
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

const activityHighlights = computed(() => {
  const points = trendPoints.value
  if (!points.length) {
    return [
      { key: 'posts_30d', label: 'Príspevky (30 dní)', value: 0 },
      { key: 'users_30d', label: 'Používatelia (30 dní)', value: 0 },
      { key: 'events_30d', label: 'Udalosti (30 dní)', value: 0 },
    ]
  }

  const totals = points.reduce(
    (acc, point) => {
      acc.posts += Number(point?.new_posts || 0)
      acc.users += Number(point?.new_users || 0)
      acc.events += Number(point?.new_events || 0)
      return acc
    },
    { posts: 0, users: 0, events: 0 },
  )

  return [
    { key: 'posts_30d', label: 'Príspevky (30 dní)', value: totals.posts },
    { key: 'users_30d', label: 'Používatelia (30 dní)', value: totals.users },
    { key: 'events_30d', label: 'Udalosti (30 dní)', value: totals.events },
  ]
})

const quickActions = computed(() => {
  const queues = stats.value?.queues || {}
  const moderationAttention =
    Number(queues.moderation_pending || 0) + Number(queues.moderation_flagged || 0)

  return [
    {
      title: 'Spracovať kandidátov',
      subtitle: 'Skontrolovať čakajúce udalosti',
      to: { name: 'admin.event-candidates' },
      badge: Number(queues.event_candidates_pending || 0),
      badgeTone: Number(queues.event_candidates_pending || 0) > 0 ? 'accent' : 'neutral',
    },
    {
      title: 'Spravovať moderáciu',
      subtitle: 'Obsah čakajúci na zásah',
      to: { name: 'admin.moderation' },
      badge: moderationAttention,
      badgeTone: moderationAttention > 0 ? 'accent' : 'neutral',
    },
    {
      title: 'Správa používateľov',
      subtitle: 'Profily, roly a stav účtu',
      to: { name: 'admin.users' },
    },
    {
      title: 'Centrum zberu',
      subtitle: 'Zdroje a behy crawlera',
      to: { name: 'admin.event-sources' },
    },
  ]
})

const moderationSnapshot = computed(() => {
  const queues = stats.value?.queues || {}
  const kpi = stats.value?.kpi || {}

  return {
    pending: Number(queues.moderation_pending || 0),
    flagged: Number(queues.moderation_flagged || 0),
    processed: Number(kpi.posts_moderated_total || 0),
  }
})

const secondaryTabs = [
  { key: 'roles', label: 'Podľa rolí' },
  { key: 'region', label: 'Podľa regiónu' },
  { key: 'activity', label: 'Aktivita (30 dní)' },
]

const secondaryStats = computed(() => {
  if (secondaryTab.value === 'region') return byRegionProfileList.value
  if (secondaryTab.value === 'activity') return activityHighlights.value
  return byRoleList.value
})

function formatNumber(value) {
  return new Intl.NumberFormat('sk-SK').format(Number(value || 0))
}

async function loadDashboard(force = false) {
  loading.value = true
  error.value = ''

  try {
    if (!auth.bootstrapDone) {
      await auth.waitForBootstrap()
    }

    if (!auth.isAuthed) return
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

onMounted(() => {
  loadDashboard(false)
})
</script>

<template src="./dashboard/AdminDashboardView.template.html"></template>

<style scoped src="./dashboard/AdminDashboardView.css"></style>
