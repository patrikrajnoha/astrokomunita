<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import AdminToolbar from '@/components/admin/shared/AdminToolbar.vue'
import AdminDataTable from '@/components/admin/shared/AdminDataTable.vue'
import AdminPagination from '@/components/admin/shared/AdminPagination.vue'
import DashboardSection from '@/components/admin/dashboard/DashboardSection.vue'
import KpiCard from '@/components/admin/dashboard/KpiCard.vue'
import { formatRelativeShort } from '@/utils/dateUtils'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const toast = useToast()
const FILTERS_STORAGE_KEY = 'admin-dashboard-filters-v1'

const loadingDashboard = ref(false)
const loadingQueues = ref(false)
const loadingTable = ref(false)
const refreshing = ref(false)

const dashboardError = ref('')
const queueError = ref('')
const tableError = ref('')

const dashboard = ref(null)
const lastUpdatedAt = ref(null)

const reportsQueue = ref([])
const candidatesQueue = ref([])
const moderationQueue = ref([])
const astroStatus = ref(null)

const openReportsTotal = ref(0)
const pendingCandidatesTotal = ref(0)
const pendingModerationTotal = ref(0)
const flaggedModerationTotal = ref(0)
const blockedModerationTotal = ref(0)

const tableData = ref(null)

const rangeOptions = [
  { key: 'today', label: '24h' },
  { key: '7d', label: '7d' },
  { key: '30d', label: '30d' },
]

const savedFilters = readSavedFilters()
const selectedRange = ref(savedFilters.range)
const fromDate = ref(savedFilters.fromDate)
const toDate = ref(savedFilters.toDate)
const reportSearchInput = ref(savedFilters.reportSearch)
const reportSearch = ref(savedFilters.reportSearch)
const reportStatus = ref(savedFilters.reportStatus)
const reportPage = ref(1)
const reportPerPage = ref(savedFilters.reportPerPage)
const actionBusyKey = ref('')

const commandPaletteOpen = ref(false)
const commandPaletteQuery = ref('')
const commandPaletteInput = ref(null)

let dashboardController = null
let queueController = null
let tableController = null
let searchDebounce = null

const reportColumns = [
  { key: 'id', label: '#' },
  { key: 'target', label: 'Target' },
  { key: 'reason', label: 'Reason' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Created' },
  { key: 'actions', label: 'Actions', align: 'right' },
]

const tableRows = computed(() => tableData.value?.data || [])
const tableMeta = computed(() => tableData.value || null)

const hasActiveTableFilters = computed(() => {
  return Boolean(reportSearch.value || reportStatus.value !== 'open' || reportPerPage.value !== 20)
})

const rangeLabel = computed(() => {
  if (selectedRange.value === 'today') return 'Last 24h'
  if (selectedRange.value === '30d') return 'Last 30 days'
  return 'Last 7 days'
})

const totals = computed(() => dashboard.value?.totals || {})
const metrics = computed(() => dashboard.value?.range_metrics || {})

const activityItems = computed(() => {
  const activity = dashboard.value?.activity || {}
  const users = (activity.latest_users || []).map((item) => ({ id: `u-${item.id}`, title: item.name || 'User', meta: `User #${item.id}`, created_at: item.created_at }))
  const posts = (activity.latest_posts || []).map((item) => ({ id: `p-${item.id}`, title: item.title || 'Post', meta: `Post #${item.id} by user ${item.user_id}`, created_at: item.created_at }))
  const candidates = (activity.latest_event_candidates || []).map((item) => ({ id: `c-${item.id}`, title: item.title || 'Candidate', meta: `${item.status || '-'} from ${item.source || '-'}`, created_at: item.created_at }))
  const events = (activity.latest_events || []).map((item) => ({ id: `e-${item.id}`, title: item.title || 'Event', meta: `Event #${item.id}`, created_at: item.created_at }))

  return [...users, ...posts, ...candidates, ...events]
    .filter((item) => inDateRange(item.created_at))
    .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
    .slice(0, 10)
})

const kpiCards = computed(() => {
  const usersTrend = trendFromSeries(dashboard.value?.chart_series?.users_series)
  const postsTrend = trendFromSeries(dashboard.value?.chart_series?.posts_series)
  const candidatesTrend = trendFromSeries(dashboard.value?.chart_series?.candidates_series)
  const moderationBacklog = pendingModerationTotal.value + flaggedModerationTotal.value
  const astroLastRun = astroStatus.value?.last_run

  return [
    { label: 'New users', value: formatNumber(metrics.value.new_users || 0), delta: usersTrend, hint: rangeLabel.value, help: 'Count of users created in selected range.', tone: 'default' },
    { label: 'New posts', value: formatNumber(metrics.value.new_posts || 0), delta: postsTrend, hint: rangeLabel.value, help: 'Count of posts created in selected range.', tone: 'default' },
    { label: 'Reports open', value: formatNumber(openReportsTotal.value), delta: null, hint: `total ${formatNumber(totals.value.total_reports || 0)}`, help: 'Open reports waiting for review.', tone: openReportsTotal.value > 0 ? 'attention' : 'default' },
    { label: 'Event candidates', value: formatNumber(pendingCandidatesTotal.value), delta: candidatesTrend, hint: 'pending approval', help: 'Pending event candidates.', tone: pendingCandidatesTotal.value > 0 ? 'attention' : 'default' },
    { label: 'Moderation backlog', value: formatNumber(moderationBacklog), delta: null, hint: `blocked ${formatNumber(blockedModerationTotal.value)}`, help: 'Pending + flagged posts in moderation queue.', tone: moderationBacklog > 0 ? 'danger' : 'default' },
    { label: 'AstroBot RSS', value: formatNumber(astroLastRun?.new_items || 0), delta: null, hint: `published ${formatNumber(astroLastRun?.published_items || 0)}`, help: 'Last AstroBot RSS run output.', tone: astroLastRun?.error_message ? 'danger' : 'default' },
  ]
})

const alerts = computed(() => {
  const items = []

  if ((astroStatus.value?.last_run?.error_message || '').trim()) items.push({ level: 'danger', text: `AstroBot error: ${astroStatus.value.last_run.error_message}` })
  if (openReportsTotal.value > 20) items.push({ level: 'warn', text: `High open reports backlog (${openReportsTotal.value}).` })
  if (pendingModerationTotal.value > 20) items.push({ level: 'warn', text: `Moderation queue is growing (${pendingModerationTotal.value} pending).` })
  if (!items.length) items.push({ level: 'ok', text: 'No active alerts.' })

  return items
})

const integrationStatus = computed(() => {
  const astroLastRun = astroStatus.value?.last_run
  const astrobotState = astroLastRun?.error_message ? 'offline' : astroLastRun ? 'online' : 'unknown'
  const moderationState = pendingModerationTotal.value + flaggedModerationTotal.value > 0 ? 'degraded' : 'online'

  return [
    { key: 'astrobot', name: 'AstroBot scheduler', state: astrobotState, detail: astroLastRun ? `last run ${formatDateTime(astroLastRun.finished_at)}` : 'no run recorded' },
    { key: 'rss', name: 'NASA RSS fetch', state: astroLastRun ? 'online' : 'unknown', detail: astroLastRun ? `${astroLastRun.new_items || 0} new / ${astroLastRun.published_items || 0} published` : 'unknown' },
    { key: 'moderation', name: 'Moderation queue', state: moderationState, detail: `${pendingModerationTotal.value} pending, ${flaggedModerationTotal.value} flagged` },
    { key: 'reports', name: 'Reports queue', state: openReportsTotal.value > 0 ? 'degraded' : 'online', detail: `${openReportsTotal.value} open` },
  ]
})

const commandPaletteCommands = computed(() => {
  const nextCandidate = candidatesQueue.value[0]
  const nextReport = reportsQueue.value[0]

  return [
    { id: 'refresh', label: 'Refresh dashboard', hint: 'Reload all dashboard blocks', run: () => refreshAll() },
    { id: 'users', label: 'Open Admin Users', hint: '/admin/users', run: () => router.push('/admin/users') },
    { id: 'reports', label: 'Open Reports queue', hint: '/admin/reports?status=open', run: () => openReportsPage() },
    { id: 'moderation', label: 'Open Moderation queue', hint: '/admin/moderation', run: () => router.push('/admin/moderation') },
    {
      id: 'candidate',
      label: 'Open next pending candidate',
      hint: nextCandidate ? `#${nextCandidate.id}` : 'none',
      disabled: !nextCandidate,
      run: () => {
        if (nextCandidate) router.push(`/admin/candidates/${nextCandidate.id}`)
      },
    },
    {
      id: 'report-resolve',
      label: 'Resolve next report',
      hint: nextReport ? `#${nextReport.id}` : 'none',
      disabled: !nextReport,
      run: () => {
        if (nextReport) resolveReport(nextReport)
      },
    },
  ]
})

const filteredCommandPaletteCommands = computed(() => {
  const needle = commandPaletteQuery.value.trim().toLowerCase()
  if (!needle) return commandPaletteCommands.value
  return commandPaletteCommands.value.filter((item) => item.label.toLowerCase().includes(needle) || item.hint.toLowerCase().includes(needle))
})

function readSavedFilters() {
  const fallback = { range: '7d', fromDate: '', toDate: '', reportSearch: '', reportStatus: 'open', reportPerPage: 20 }

  try {
    const raw = window.localStorage.getItem(FILTERS_STORAGE_KEY)
    if (!raw) return fallback
    const parsed = JSON.parse(raw)

    return {
      range: ['today', '7d', '30d'].includes(parsed.range) ? parsed.range : fallback.range,
      fromDate: typeof parsed.fromDate === 'string' ? parsed.fromDate : fallback.fromDate,
      toDate: typeof parsed.toDate === 'string' ? parsed.toDate : fallback.toDate,
      reportSearch: typeof parsed.reportSearch === 'string' ? parsed.reportSearch : fallback.reportSearch,
      reportStatus: typeof parsed.reportStatus === 'string' ? parsed.reportStatus : fallback.reportStatus,
      reportPerPage: [10, 20, 50].includes(Number(parsed.reportPerPage)) ? Number(parsed.reportPerPage) : fallback.reportPerPage,
    }
  } catch {
    return fallback
  }
}

function saveFilters() {
  window.localStorage.setItem(
    FILTERS_STORAGE_KEY,
    JSON.stringify({ range: selectedRange.value, fromDate: fromDate.value, toDate: toDate.value, reportSearch: reportSearch.value, reportStatus: reportStatus.value, reportPerPage: reportPerPage.value }),
  )
}

function abortWith(name) {
  if (name === 'dashboard' && dashboardController) dashboardController.abort()
  if (name === 'queue' && queueController) queueController.abort()
  if (name === 'table' && tableController) tableController.abort()

  const controller = new AbortController()
  if (name === 'dashboard') dashboardController = controller
  if (name === 'queue') queueController = controller
  if (name === 'table') tableController = controller
  return controller
}

function isCanceledError(error) {
  return error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED'
}

async function loadDashboard() {
  const controller = abortWith('dashboard')
  loadingDashboard.value = true
  dashboardError.value = ''

  try {
    const res = await api.get('/admin/dashboard', { params: { range: selectedRange.value }, signal: controller.signal, meta: { skipErrorToast: true } })
    dashboard.value = res.data
  } catch (error) {
    if (!isCanceledError(error)) dashboardError.value = error?.response?.data?.message || error?.userMessage || 'Failed to load dashboard metrics.'
  } finally {
    if (dashboardController === controller) loadingDashboard.value = false
  }
}

async function loadQueues() {
  const controller = abortWith('queue')
  loadingQueues.value = true
  queueError.value = ''

  try {
    const [reportsList, candidatesList, moderationList, reportsCount, candidatesCount, moderationPendingCount, moderationFlaggedCount, moderationBlockedCount, astro] =
      await Promise.all([
        api.get('/admin/reports', { params: { status: 'open', per_page: 5 }, signal: controller.signal, meta: { skipErrorToast: true } }),
        api.get('/admin/event-candidates', { params: { status: 'pending', per_page: 5 }, signal: controller.signal, meta: { skipErrorToast: true } }),
        api.get('/admin/moderation', { params: { status: 'pending', per_page: 5 }, signal: controller.signal, meta: { skipErrorToast: true } }),
        api.get('/admin/reports', { params: { status: 'open', per_page: 1 }, signal: controller.signal, meta: { skipErrorToast: true } }),
        api.get('/admin/event-candidates', { params: { status: 'pending', per_page: 1 }, signal: controller.signal, meta: { skipErrorToast: true } }),
        api.get('/admin/moderation', { params: { status: 'pending', per_page: 1 }, signal: controller.signal, meta: { skipErrorToast: true } }),
        api.get('/admin/moderation', { params: { status: 'flagged', per_page: 1 }, signal: controller.signal, meta: { skipErrorToast: true } }),
        api.get('/admin/moderation', { params: { status: 'blocked', per_page: 1 }, signal: controller.signal, meta: { skipErrorToast: true } }),
        api.get('/admin/astrobot/nasa/status', { signal: controller.signal, meta: { skipErrorToast: true } }),
      ])

    reportsQueue.value = reportsList?.data?.data || []
    candidatesQueue.value = candidatesList?.data?.data || []
    moderationQueue.value = moderationList?.data?.data || []

    openReportsTotal.value = Number(reportsCount?.data?.total || 0)
    pendingCandidatesTotal.value = Number(candidatesCount?.data?.total || 0)
    pendingModerationTotal.value = Number(moderationPendingCount?.data?.total || 0)
    flaggedModerationTotal.value = Number(moderationFlaggedCount?.data?.total || 0)
    blockedModerationTotal.value = Number(moderationBlockedCount?.data?.total || 0)
    astroStatus.value = astro?.data || null
  } catch (error) {
    if (!isCanceledError(error)) queueError.value = error?.response?.data?.message || error?.userMessage || 'Failed to load admin queues.'
  } finally {
    if (queueController === controller) loadingQueues.value = false
  }
}

async function loadReportsTable() {
  const controller = abortWith('table')
  loadingTable.value = true
  tableError.value = ''

  try {
    const params = { status: reportStatus.value, page: reportPage.value, per_page: reportPerPage.value }
    if (reportSearch.value) params.search = reportSearch.value
    const res = await api.get('/admin/reports', { params, signal: controller.signal, meta: { skipErrorToast: true } })
    tableData.value = res.data
  } catch (error) {
    if (!isCanceledError(error)) tableError.value = error?.response?.data?.message || error?.userMessage || 'Failed to load latest reports.'
  } finally {
    if (tableController === controller) loadingTable.value = false
  }
}

async function refreshAll() {
  refreshing.value = true
  await Promise.all([loadDashboard(), loadQueues(), loadReportsTable()])
  lastUpdatedAt.value = new Date().toISOString()
  refreshing.value = false
}

function inDateRange(value) {
  if (!value) return true
  const current = new Date(value)
  if (Number.isNaN(current.getTime())) return true

  const from = fromDate.value ? new Date(`${fromDate.value}T00:00:00`) : null
  const to = toDate.value ? new Date(`${toDate.value}T23:59:59`) : null
  if (from && current < from) return false
  if (to && current > to) return false
  return true
}

function trendFromSeries(series) {
  if (!Array.isArray(series) || series.length < 2) return null
  const current = Number(series[series.length - 1]?.count || 0)
  const previous = Number(series[series.length - 2]?.count || 0)
  if (previous === 0) return current === 0 ? 0 : 100
  return ((current - previous) / previous) * 100
}

function formatNumber(value) {
  return new Intl.NumberFormat('sk-SK').format(Number(value || 0))
}

function formatDateTime(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatRelative(value) {
  if (!value) return '-'
  return formatRelativeShort(value)
}

function reportTargetSummary(report) {
  const author = report?.target?.user?.name || '-'
  const snippet = report?.target?.content ? String(report.target.content).slice(0, 48) : ''
  return snippet ? `${author}: ${snippet}` : author
}

function openReportsPage() {
  router.push({ path: '/admin/reports', query: { status: 'open' } })
}

function openCandidatesPage() {
  router.push('/admin/event-candidates')
}

function openModerationPage() {
  router.push('/admin/moderation')
}

function openReport(report) {
  router.push({ path: '/admin/reports', query: { status: report?.status || 'open', search: report?.id ? String(report.id) : '' } })
}

async function resolveReport(report) {
  if (!report?.id) return
  const key = `report-${report.id}-dismiss`
  actionBusyKey.value = key

  try {
    await api.post(`/admin/reports/${report.id}/dismiss`, {}, { meta: { skipErrorToast: true } })
    toast.success('Report resolved.')
    await Promise.all([loadQueues(), loadReportsTable()])
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Resolve action failed.')
  } finally {
    if (actionBusyKey.value === key) actionBusyKey.value = ''
  }
}

async function approveCandidate(candidate) {
  if (!candidate?.id) return
  const key = `candidate-${candidate.id}-approve`
  actionBusyKey.value = key

  try {
    await api.post(`/admin/event-candidates/${candidate.id}/approve`, {}, { meta: { skipErrorToast: true } })
    toast.success('Candidate approved.')
    await Promise.all([loadQueues(), loadDashboard()])
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Approve failed.')
  } finally {
    if (actionBusyKey.value === key) actionBusyKey.value = ''
  }
}

async function rejectCandidate(candidate) {
  if (!candidate?.id) return
  const key = `candidate-${candidate.id}-reject`
  actionBusyKey.value = key

  try {
    await api.post(`/admin/event-candidates/${candidate.id}/reject`, { reason: 'Rejected from dashboard quick action.' }, { meta: { skipErrorToast: true } })
    toast.success('Candidate rejected.')
    await Promise.all([loadQueues(), loadDashboard()])
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Reject failed.')
  } finally {
    if (actionBusyKey.value === key) actionBusyKey.value = ''
  }
}

async function moderationAction(item, action) {
  if (!item?.id) return
  const key = `moderation-${item.id}-${action}`
  actionBusyKey.value = key

  try {
    await api.post(`/admin/moderation/${item.id}/action`, { action, note: 'Dashboard quick action.' }, { meta: { skipErrorToast: true } })
    toast.success(`Moderation action "${action}" applied.`)
    await Promise.all([loadQueues(), loadDashboard()])
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Moderation action failed.')
  } finally {
    if (actionBusyKey.value === key) actionBusyKey.value = ''
  }
}

function clearTableFilters() {
  reportSearchInput.value = ''
  reportSearch.value = ''
  reportStatus.value = 'open'
  reportPerPage.value = 20
  reportPage.value = 1
}

function exportReportsCsv() {
  const rows = tableRows.value
  if (!rows.length) {
    toast.warn('No rows to export.')
    return
  }

  const csv = [['id', 'target', 'reason', 'status', 'created_at'], ...rows.map((row) => [row.id, reportTargetSummary(row), row.reason || '', row.status || '', row.created_at || ''])]
    .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `admin-reports-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

function onRangeClick(range) {
  if (selectedRange.value !== range) selectedRange.value = range
}

function openCommandPalette() {
  commandPaletteOpen.value = true
  nextTick(() => commandPaletteInput.value?.focus())
}

function closeCommandPalette() {
  commandPaletteOpen.value = false
  commandPaletteQuery.value = ''
}

function runCommand(item) {
  if (!item || item.disabled) return
  item.run()
  closeCommandPalette()
}

function handleGlobalKeydown(event) {
  const isCmdK = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k'
  if (isCmdK) {
    event.preventDefault()
    if (commandPaletteOpen.value) closeCommandPalette()
    else openCommandPalette()
    return
  }
  if (event.key === 'Escape' && commandPaletteOpen.value) closeCommandPalette()
}

watch([selectedRange, fromDate, toDate, reportStatus, reportPerPage], () => saveFilters())
watch(selectedRange, () => loadDashboard())

watch(reportSearchInput, (value) => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    if (reportSearch.value !== value) {
      reportSearch.value = value
      reportPage.value = 1
      saveFilters()
    }
  }, 350)
})

watch([reportStatus, reportPerPage, reportSearch], () => {
  reportPage.value = 1
})

watch([reportStatus, reportPage, reportPerPage, reportSearch], () => {
  loadReportsTable()
})

onMounted(() => {
  lastUpdatedAt.value = new Date().toISOString()
  refreshAll()
  window.addEventListener('keydown', handleGlobalKeydown)
})

onBeforeUnmount(() => {
  if (dashboardController) dashboardController.abort()
  if (queueController) queueController.abort()
  if (tableController) tableController.abort()
  if (searchDebounce) clearTimeout(searchDebounce)
  window.removeEventListener('keydown', handleGlobalKeydown)
})
</script>

<template>
  <AdminPageShell title="Admin Dashboard" subtitle="Operational overview and work queue for daily moderation and publishing flow.">
    <template #right-actions>
      <span class="roleBadge" aria-label="Role badge">ADMIN</span>
      <a class="ghostLink" href="/README.md" target="_blank" rel="noopener">Flow docs</a>
    </template>

    <!-- IA rationale: KPI first for at-a-glance monitoring, then work queue for decisions, then detailed table for drill-down. -->
    <AdminToolbar :loading="refreshing">
      <template #search>
        <div class="rangeGroup" role="group" aria-label="Range filter">
          <button
            v-for="option in rangeOptions"
            :key="option.key"
            type="button"
            class="rangeBtn"
            :class="{ active: selectedRange === option.key }"
            :aria-label="`Set range ${option.label}`"
            @click="onRangeClick(option.key)"
          >
            {{ option.label }}
          </button>
        </div>
      </template>

      <template #filters>
        <div class="dateFilters">
          <label class="fieldLabel" for="from-date">From</label>
          <input id="from-date" v-model="fromDate" type="date" class="fieldInput" />
        </div>
        <div class="dateFilters">
          <label class="fieldLabel" for="to-date">To</label>
          <input id="to-date" v-model="toDate" type="date" class="fieldInput" />
        </div>
      </template>

      <template #actions>
        <div class="headerActions">
          <span class="updatedLabel">Updated: {{ formatDateTime(lastUpdatedAt) }}</span>
          <button type="button" class="btn" :disabled="refreshing" @click="refreshAll">
            {{ refreshing ? 'Loading...' : 'Refresh' }}
          </button>
        </div>
      </template>
    </AdminToolbar>

    <div v-if="dashboardError" class="adminAlert">Metrics: {{ dashboardError }}</div>
    <div v-if="queueError" class="adminAlert">Queues: {{ queueError }}</div>

    <section class="kpiGrid" aria-label="KPI metrics">
      <div v-if="loadingDashboard" v-for="i in 6" :key="`kpi-skeleton-${i}`" class="skeletonCard"></div>
      <KpiCard
        v-for="card in kpiCards"
        v-else
        :key="card.label"
        :label="card.label"
        :value="card.value"
        :delta="card.delta"
        :hint="card.hint"
        :help="card.help"
        :tone="card.tone"
      />
    </section>

    <section class="mainGrid">
      <div class="stack">
        <DashboardSection title="Pending Reports" subtitle="Top 5 requiring moderation" action-label="See all" @action="openReportsPage">
          <div v-if="loadingQueues" class="blockHint">Loading reports queue...</div>
          <div v-else-if="!reportsQueue.filter((item) => inDateRange(item.created_at)).length" class="blockHint">No pending reports.</div>
          <div v-else class="queueList">
            <article v-for="item in reportsQueue.filter((row) => inDateRange(row.created_at))" :key="`report-${item.id}`" class="queueItem">
              <div class="queueMain">
                <strong>#{{ item.id }}</strong>
                <span class="queueMeta">{{ reportTargetSummary(item) }}</span>
                <span class="queueSub">{{ item.reason || '-' }} · {{ formatRelative(item.created_at) }}</span>
              </div>
              <div class="queueActions">
                <button type="button" class="btn ghost" @click="openReport(item)">Open</button>
                <button type="button" class="btn" :disabled="actionBusyKey === `report-${item.id}-dismiss`" @click="resolveReport(item)">Resolve</button>
              </div>
            </article>
          </div>
        </DashboardSection>

        <DashboardSection title="Pending Event Candidates" subtitle="Approve or reject quickly" action-label="See all" @action="openCandidatesPage">
          <div v-if="loadingQueues" class="blockHint">Loading candidates queue...</div>
          <div v-else-if="!candidatesQueue.filter((item) => inDateRange(item.created_at)).length" class="blockHint">No pending candidates.</div>
          <div v-else class="queueList">
            <article v-for="item in candidatesQueue.filter((row) => inDateRange(row.created_at))" :key="`candidate-${item.id}`" class="queueItem">
              <div class="queueMain">
                <strong>#{{ item.id }}</strong>
                <span class="queueMeta">{{ item.title || '-' }}</span>
                <span class="queueSub">{{ item.source_name || '-' }} · {{ formatRelative(item.created_at) }}</span>
              </div>
              <div class="queueActions">
                <button type="button" class="btn ghost" @click="router.push(`/admin/candidates/${item.id}`)">Open</button>
                <button type="button" class="btn" :disabled="actionBusyKey === `candidate-${item.id}-approve`" @click="approveCandidate(item)">Approve</button>
                <button type="button" class="btn subtle" :disabled="actionBusyKey === `candidate-${item.id}-reject`" @click="rejectCandidate(item)">Reject</button>
              </div>
            </article>
          </div>
        </DashboardSection>

        <DashboardSection title="Pending Moderation" subtitle="Posts blocked by AI moderation" action-label="See all" @action="openModerationPage">
          <div v-if="loadingQueues" class="blockHint">Loading moderation queue...</div>
          <div v-else-if="!moderationQueue.filter((item) => inDateRange(item.created_at)).length" class="blockHint">No moderation items.</div>
          <div v-else class="queueList">
            <article v-for="item in moderationQueue.filter((row) => inDateRange(row.created_at))" :key="`moderation-${item.id}`" class="queueItem">
              <div class="queueMain">
                <strong>#{{ item.id }}</strong>
                <span class="queueMeta">{{ item.snippet || '-' }}</span>
                <span class="queueSub">{{ item.moderation_status || '-' }} · {{ formatRelative(item.created_at) }}</span>
              </div>
              <div class="queueActions">
                <button type="button" class="btn ghost" @click="openModerationPage">Review</button>
                <button type="button" class="btn" :disabled="actionBusyKey === `moderation-${item.id}-approve`" @click="moderationAction(item, 'approve')">Approve</button>
                <button type="button" class="btn subtle" :disabled="actionBusyKey === `moderation-${item.id}-reject`" @click="moderationAction(item, 'reject')">Reject</button>
              </div>
            </article>
          </div>
        </DashboardSection>
      </div>

      <div class="stack">
        <DashboardSection title="Recent Activity" subtitle="Latest records across users, posts, candidates and events">
          <div v-if="loadingDashboard" class="blockHint">Loading activity...</div>
          <div v-else-if="!activityItems.length" class="blockHint">No activity in selected range.</div>
          <div v-else class="activityList">
            <article v-for="item in activityItems" :key="item.id" class="activityItem">
              <div>
                <div class="activityTitle">{{ item.title }}</div>
                <div class="activityMeta">{{ item.meta }}</div>
              </div>
              <div class="activityTime">{{ formatRelative(item.created_at) }}</div>
            </article>
          </div>
        </DashboardSection>

        <DashboardSection title="System Status" subtitle="Services and queue health">
          <div class="statusList">
            <article v-for="status in integrationStatus" :key="status.key" class="statusItem">
              <div>
                <strong>{{ status.name }}</strong>
                <div class="activityMeta">{{ status.detail }}</div>
              </div>
              <span class="statusBadge" :class="`status-${status.state}`">{{ status.state }}</span>
            </article>
          </div>
        </DashboardSection>

        <DashboardSection title="Alerts" subtitle="Operational warnings and incidents">
          <div class="alertsList">
            <article v-for="alert in alerts" :key="alert.text" class="alertItem" :class="`alert-${alert.level}`">
              {{ alert.text }}
            </article>
          </div>
        </DashboardSection>
      </div>
    </section>

    <DashboardSection title="Latest Reports" subtitle="Compact drill-down table with search, filter and CSV export">
      <div v-if="tableError" class="adminAlert">{{ tableError }}</div>

      <AdminToolbar :loading="loadingTable">
        <template #search>
          <label class="fieldLabel" for="dash-reports-search">Search</label>
          <input id="dash-reports-search" v-model="reportSearchInput" type="search" class="fieldInput" placeholder="Search reports..." />
        </template>

        <template #filters>
          <div class="filtersRow">
            <div>
              <label class="fieldLabel" for="dash-reports-status">Status</label>
              <select id="dash-reports-status" v-model="reportStatus" class="fieldInput">
                <option value="open">open</option>
                <option value="reviewed">reviewed</option>
                <option value="dismissed">dismissed</option>
                <option value="action_taken">action_taken</option>
              </select>
            </div>
            <div>
              <label class="fieldLabel" for="dash-reports-per-page">Per page</label>
              <select id="dash-reports-per-page" v-model.number="reportPerPage" class="fieldInput">
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
              </select>
            </div>
          </div>
        </template>

        <template #actions>
          <button type="button" class="btn ghost" :disabled="loadingTable" @click="exportReportsCsv">Export CSV</button>
        </template>
      </AdminToolbar>

      <AdminDataTable
        :columns="reportColumns"
        :rows="tableRows"
        :loading="loadingTable"
        empty-title="No reports"
        empty-description="Try adjusting status or search filter."
        :can-clear-filters="hasActiveTableFilters"
        @clear-filters="clearTableFilters"
      >
        <template #[`cell(id)`]="{ row }">#{{ row.id }}</template>
        <template #[`cell(target)`]="{ row }">{{ reportTargetSummary(row) }}</template>
        <template #[`cell(created_at)`]="{ row }">{{ formatRelative(row.created_at) }}</template>
        <template #[`cell(actions)`]="{ row }">
          <div class="rowActions">
            <button class="btn ghost" type="button" @click="openReport(row)">Open</button>
            <button class="btn" type="button" :disabled="actionBusyKey === `report-${row.id}-dismiss`" @click="resolveReport(row)">Resolve</button>
          </div>
        </template>
      </AdminDataTable>

      <AdminPagination :meta="tableMeta" @page-change="reportPage = $event" />
    </DashboardSection>

    <div v-if="commandPaletteOpen" class="commandPalette" role="dialog" aria-modal="true" aria-label="Admin command palette">
      <div class="paletteSurface">
        <input
          ref="commandPaletteInput"
          v-model="commandPaletteQuery"
          class="paletteInput"
          type="text"
          placeholder="Type a command..."
          aria-label="Command search"
          @keydown.enter.prevent="runCommand(filteredCommandPaletteCommands[0])"
        />

        <div class="paletteList">
          <button v-for="item in filteredCommandPaletteCommands" :key="item.id" type="button" class="paletteItem" :disabled="item.disabled" @click="runCommand(item)">
            <span>{{ item.label }}</span>
            <span class="paletteHint">{{ item.hint }}</span>
          </button>
          <div v-if="!filteredCommandPaletteCommands.length" class="blockHint">No matching commands.</div>
        </div>
      </div>
      <button type="button" class="paletteBackdrop" aria-label="Close command palette" @click="closeCommandPalette"></button>
    </div>
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

.roleBadge {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.5);
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 11px;
  letter-spacing: 0.08em;
  font-weight: 700;
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.ghostLink {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 6px 10px;
  font-size: 12px;
  color: inherit;
}

.rangeGroup {
  display: inline-flex;
  gap: 6px;
  padding: 4px;
  border-radius: 11px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.15);
}

.rangeBtn {
  border: 0;
  border-radius: 8px;
  padding: 7px 10px;
  background: transparent;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  cursor: pointer;
  font-size: 13px;
}

.rangeBtn.active {
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.dateFilters {
  min-width: 140px;
}

.fieldLabel {
  display: block;
  font-size: 12px;
  opacity: 0.85;
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

.filtersRow { display: flex; gap: 10px; flex-wrap: wrap; }
.filtersRow > div { min-width: 145px; }

.headerActions { display: inline-flex; gap: 8px; align-items: center; }
.updatedLabel { font-size: 12px; color: rgb(var(--color-text-secondary-rgb) / 0.95); }

.kpiGrid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 10px;
}

@media (min-width: 700px) {
  .kpiGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (min-width: 1080px) {
  .kpiGrid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

.skeletonCard {
  border-radius: 14px;
  min-height: 120px;
  background: linear-gradient(90deg, rgb(var(--color-surface-rgb) / 0.06), rgb(var(--color-surface-rgb) / 0.12), rgb(var(--color-surface-rgb) / 0.06));
  background-size: 200% 100%;
  animation: shimmer 1.1s infinite linear;
}

.mainGrid { display: grid; gap: 12px; grid-template-columns: 1fr; }
@media (min-width: 1200px) { .mainGrid { grid-template-columns: 1.3fr 1fr; } }

.stack { display: grid; gap: 12px; }
.blockHint { font-size: 13px; color: rgb(var(--color-text-secondary-rgb) / 0.9); }

.queueList,
.activityList,
.statusList,
.alertsList { display: grid; gap: 8px; }

.queueItem,
.activityItem,
.statusItem {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  padding: 10px;
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: flex-start;
  background: rgb(var(--color-bg-rgb) / 0.35);
}

.queueMain { display: grid; gap: 3px; }
.queueMeta { font-size: 13px; color: var(--color-surface); }
.queueSub,
.activityMeta { font-size: 12px; color: rgb(var(--color-text-secondary-rgb) / 0.9); }

.queueActions,
.rowActions { display: inline-flex; gap: 6px; flex-wrap: wrap; }

.activityTitle { font-size: 13px; color: var(--color-surface); font-weight: 600; }
.activityTime { font-size: 12px; color: rgb(var(--color-text-secondary-rgb) / 0.92); white-space: nowrap; }

.statusBadge {
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.05em;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 999px;
  padding: 3px 8px;
}

.status-online { border-color: rgb(34 197 94 / 0.45); color: rgb(134 239 172); }
.status-degraded { border-color: rgb(251 191 36 / 0.45); color: rgb(253 224 71); }
.status-offline { border-color: rgb(239 68 68 / 0.45); color: rgb(252 165 165); }
.status-unknown { color: rgb(var(--color-text-secondary-rgb) / 0.92); }

.alertItem { border-radius: 10px; padding: 9px 10px; border: 1px solid rgb(var(--color-surface-rgb) / 0.16); font-size: 13px; }
.alert-danger { color: var(--color-danger); border-color: rgb(var(--color-danger-rgb, 239 68 68) / 0.35); background: rgb(var(--color-danger-rgb, 239 68 68) / 0.08); }
.alert-warn { color: rgb(253 224 71); border-color: rgb(251 191 36 / 0.35); background: rgb(251 191 36 / 0.08); }
.alert-ok { color: rgb(134 239 172); border-color: rgb(34 197 94 / 0.3); background: rgb(34 197 94 / 0.07); }

.btn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  padding: 6px 10px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.btn.subtle { background: rgb(var(--color-surface-rgb) / 0.08); }
.btn.ghost { border-color: rgb(var(--color-surface-rgb) / 0.14); color: rgb(var(--color-text-secondary-rgb) / 0.95); }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }

.btn:focus-visible,
.rangeBtn:focus-visible,
.fieldInput:focus-visible,
.ghostLink:focus-visible {
  outline: 2px solid rgb(var(--color-primary-rgb) / 0.9);
  outline-offset: 1px;
}

.commandPalette {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: grid;
  place-items: center;
}

.paletteBackdrop { position: absolute; inset: 0; border: 0; background: rgb(2 6 23 / 0.7); }

.paletteSurface {
  position: relative;
  width: min(700px, calc(100vw - 24px));
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 14px;
  background: rgb(var(--color-bg-rgb) / 0.94);
  padding: 12px;
  display: grid;
  gap: 10px;
}

.paletteInput {
  width: 100%;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 10px;
  background: transparent;
  color: inherit;
}

.paletteList { display: grid; gap: 6px; max-height: 50vh; overflow: auto; }
.paletteItem {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
  padding: 8px 10px;
  background: transparent;
  color: inherit;
  display: flex;
  justify-content: space-between;
  gap: 8px;
  text-align: left;
}

.paletteItem:disabled { opacity: 0.5; }
.paletteHint { font-size: 12px; color: rgb(var(--color-text-secondary-rgb) / 0.92); }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
