<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import DashboardSection from '@/components/admin/dashboard/DashboardSection.vue'
import KpiCard from '@/components/admin/dashboard/KpiCard.vue'
import QuickActionTile from '@/components/admin/dashboard/QuickActionTile.vue'
import StatsChart from '@/components/admin/dashboard/StatsChart.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { getStats, downloadStatsCsv } from '@/services/api/admin/stats'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const stats = ref(null)
const trendMetric = ref('new_posts')

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
    { key: 'editor', label: 'Editori', value: Number(byRole.editor || 0) },
    { key: 'bot', label: 'Boti', value: Number(byRole.bot || 0) },
  ]
})

const byRegionProfileList = computed(() => {
  const byRegion = stats.value?.demographics?.by_region || {}
  return [
    { key: 'unknown', label: 'Nezadané', value: Number(byRegion.unknown || 0), icon: '\u{2754}' },
    { key: 'sk', label: 'Slovensko', value: Number(byRegion.sk || 0), icon: '\u{1F1F8}\u{1F1F0}' },
    { key: 'cz', label: 'Česko', value: Number(byRegion.cz || 0), icon: '\u{1F1E8}\u{1F1FF}' },
    { key: 'other', label: 'Ostatné', value: Number(byRegion.other || 0), icon: '\u{1F30D}' },
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

onMounted(() => {
  loadDashboard(false)
})
</script>

<template src="./dashboard/AdminDashboardView.template.html"></template>

<style scoped src="./dashboard/AdminDashboardView.css"></style>
