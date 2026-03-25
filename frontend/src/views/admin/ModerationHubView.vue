<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import ReportsPanel from '@/components/admin/moderation/ReportsPanel.vue'
import QueuePanel from '@/components/admin/moderation/QueuePanel.vue'
import ReviewPanel from '@/components/admin/moderation/ReviewPanel.vue'

const route = useRoute()
const router = useRouter()

const tabs = [
  { id: 'review', label: 'Na kontrolu' },
  { id: 'reports', label: 'Reporty' },
  { id: 'queue', label: 'Fronta' },
  { id: 'reviewed', label: 'Skontrolované' },
]

const overview = ref(null)
const overviewLoading = ref(false)

const activeTab = computed(() => {
  const requested = typeof route.query.tab === 'string' ? route.query.tab : 'review'
  return tabs.some((tab) => tab.id === requested) ? requested : 'review'
})

const reviewCount = computed(() => {
  const counts = overview.value?.counts || {}
  return Number(counts.reports_open || 0) + Number(counts.queue_pending || 0) + Number(counts.queue_flagged || 0)
})

const queueCount = computed(() => {
  const counts = overview.value?.counts || {}
  return Number(counts.queue_pending || 0) + Number(counts.queue_flagged || 0) + Number(counts.queue_blocked || 0)
})

const reviewedCount = computed(() => {
  const counts = overview.value?.counts || {}
  return Number(counts.reports_closed || 0) + Number(counts.queue_reviewed || 0)
})

function tabBadge(tabId) {
  if (tabId === 'review') return reviewCount.value
  if (tabId === 'reports') return Number(overview.value?.counts?.reports_open || 0)
  if (tabId === 'queue') return queueCount.value
  if (tabId === 'reviewed') return reviewedCount.value
  return ''
}

async function loadOverview() {
  overviewLoading.value = true

  try {
    const res = await api.get('/admin/moderation/overview')
    overview.value = res.data
  } catch {
    overview.value = null
  } finally {
    overviewLoading.value = false
  }
}

function setTab(tabId) {
  router.push({
    query: {
      ...route.query,
      tab: tabId,
    },
  })
}

function inspectItem(item) {
  if (item?.kind === 'report') {
    router.push({
      query: {
        ...route.query,
        tab: 'reports',
        reportId: item.id,
        status: item.status || 'open',
        queueId: undefined,
        queueStatus: undefined,
      },
    })
    return
  }

  router.push({
    query: {
      ...route.query,
      tab: 'queue',
      queueId: item.id,
      queueStatus: item.status || 'pending',
      reportId: undefined,
    },
  })
}

watch(
  () => route.query.tab,
  (value) => {
    if (typeof value === 'string' && tabs.some((tab) => tab.id === value)) {
      return
    }

    router.replace({
      query: {
        ...route.query,
        tab: 'review',
      },
    })
  },
  { immediate: true },
)

watch(activeTab, () => {
  loadOverview()
}, { immediate: true })
</script>

<template>
  <AdminPageShell title="Moderácia">
    <div class="flex gap-1 flex-wrap mb-4 p-1 bg-hover rounded-2xl w-fit" role="tablist" aria-label="Moderačné sekcie">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-[13px] font-semibold border-0 cursor-pointer transition-colors duration-150"
        :class="activeTab === tab.id ? 'bg-vivid text-white' : 'bg-transparent text-muted'"
        type="button"
        @click="setTab(tab.id)"
      >
        <span>{{ tab.label }}</span>
        <span
          v-if="tabBadge(tab.id) !== ''"
          class="min-w-[1.1rem] text-center text-[11px] font-bold px-1.5 py-0.5 rounded-full tabular-nums"
          :class="activeTab === tab.id ? 'bg-white/20 text-white' : 'bg-secondary-btn text-muted'"
        >{{ overviewLoading ? '…' : tabBadge(tab.id) }}</span>
      </button>
    </div>

    <ReviewPanel
      v-if="activeTab === 'review'"
      mode="actionable"
      @inspect="inspectItem"
    />
    <ReportsPanel
      v-else-if="activeTab === 'reports'"
      :selected-report-id="route.query.reportId"
      @changed="loadOverview"
    />
    <QueuePanel
      v-else-if="activeTab === 'queue'"
      :selected-queue-id="route.query.queueId"
      :initial-status="route.query.queueStatus || 'pending'"
      @changed="loadOverview"
    />
    <ReviewPanel
      v-else
      mode="reviewed"
      @inspect="inspectItem"
    />
  </AdminPageShell>
</template>
