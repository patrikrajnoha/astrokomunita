<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import ReportsPanel from '@/components/admin/moderation/ReportsPanel.vue'
import QueuePanel from '@/components/admin/moderation/QueuePanel.vue'
import ReviewPanel from '@/components/admin/moderation/ReviewPanel.vue'
import ServicePanel from '@/components/admin/moderation/ServicePanel.vue'

const route = useRoute()
const router = useRouter()

const tabs = [
  { id: 'review', label: 'Na kontrolu' },
  { id: 'reports', label: 'Reporty' },
  { id: 'queue', label: 'Fronta' },
  { id: 'service', label: 'Sluzba' },
  { id: 'reviewed', label: 'Skontrolovane' },
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
  if (tabId === 'service') {
    return overview.value?.service?.status === 'down' ? '!' : ''
  }
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
  <AdminPageShell title="Moderacia">
    <div class="hubTabs" role="tablist" aria-label="Moderacne sekcie">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="hubTab"
        :class="{ active: activeTab === tab.id }"
        type="button"
        @click="setTab(tab.id)"
      >
        <span>{{ tab.label }}</span>
        <span v-if="tabBadge(tab.id) !== ''" class="hubTabBadge">
          {{ overviewLoading ? '...' : tabBadge(tab.id) }}
        </span>
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
    <ServicePanel v-else-if="activeTab === 'service'" />
    <ReviewPanel
      v-else
      mode="reviewed"
      @inspect="inspectItem"
    />
  </AdminPageShell>
</template>

<style scoped>
.hubTabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.hubTab {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 999px;
  padding: 8px 12px;
  background: rgb(var(--color-bg-rgb) / 0.32);
  color: inherit;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.hubTab.active {
  background: linear-gradient(
    135deg,
    rgb(var(--color-primary-rgb) / 0.2),
    rgb(var(--color-surface-rgb) / 0.08)
  );
  border-color: rgb(var(--color-primary-rgb) / 0.45);
}

.hubTabBadge {
  min-width: 1.4rem;
  padding: 2px 7px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.12);
  font-size: 12px;
  line-height: 1.2;
}
</style>
