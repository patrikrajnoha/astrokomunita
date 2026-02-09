<template>
  <div class="adminDashboard">
    <header class="adminHeader">
      <h1 class="adminTitle">Dashboard</h1>
      <p class="adminSubtitle">Admin prehľad a metriky</p>
    </header>

    <!-- Range Switcher -->
    <div class="rangeSwitcher">
      <button
        v-for="option in rangeOptions"
        :key="option.key"
        class="rangeBtn"
        :class="{ active: selectedRange === option.key }"
        @click="selectedRange = option.key"
      >
        {{ option.label }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loadingState">
      <div class="skeleton h-8 w-32 mb-4"></div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div v-for="i in 8" :key="i" class="skeleton h-24 w-full"></div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div v-for="i in 4" :key="i" class="skeleton h-64 w-full"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="errorState">
      <div class="errorTitle">Nepodarilo sa načítať dáta</div>
      <div class="errorText">{{ error }}</div>
      <button class="retryBtn" @click="fetchDashboardData">Skúsiť znova</button>
    </div>

    <!-- Dashboard Content -->
    <div v-else-if="data" class="dashboardContent">
      <!-- KPI Cards -->
      <section class="kpiSection">
        <h2 class="sectionTitle">Celkové štatistiky</h2>
        <div class="kpiGrid">
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.totals.total_users) }}</div>
            <div class="kpiLabel">Používatelia</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.totals.total_posts) }}</div>
            <div class="kpiLabel">Príspevky</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.totals.total_events) }}</div>
            <div class="kpiLabel">Udalosti</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.totals.total_event_candidates) }}</div>
            <div class="kpiLabel">Kandidáti</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.totals.total_reports) }}</div>
            <div class="kpiLabel">Nahlásenia</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.totals.total_blog_posts) }}</div>
            <div class="kpiLabel">Blog články</div>
          </div>
        </div>
      </section>

      <!-- Range Metrics -->
      <section class="rangeSection">
        <h2 class="sectionTitle">
          {{ rangeLabel }} ({{ selectedRange === 'today' ? 'Dnes' : selectedRange === '7d' ? 'Posledných 7 dní' : 'Posledných 30 dní' }})
        </h2>
        <div class="kpiGrid">
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.range_metrics.new_users) }}</div>
            <div class="kpiLabel">Noví používatelia</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.range_metrics.new_posts) }}</div>
            <div class="kpiLabel">Nové príspevky</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.range_metrics.new_events_published) }}</div>
            <div class="kpiLabel">Nové udalosti</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.range_metrics.new_event_candidates) }}</div>
            <div class="kpiLabel">Noví kandidáti</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.range_metrics.likes_count) }}</div>
            <div class="kpiLabel">Lajky</div>
          </div>
          <div class="kpiCard">
            <div class="kpiValue">{{ formatNumber(data.range_metrics.replies_count) }}</div>
            <div class="kpiLabel">Odpovede</div>
          </div>
        </div>
      </section>

      <!-- Charts and Activity -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Simple Chart -->
        <section class="chartSection">
          <h2 class="sectionTitle">Vývoj počas {{ selectedRange === 'today' ? 'dnes' : selectedRange === '7d' ? 'posledných 7 dní' : 'posledných 30 dní' }}</h2>
          <div class="chartContainer">
            <div class="chartLegend">
              <div class="legendItem">
                <div class="legendColor" style="background: #3b82f6;"></div>
                <span>Používatelia</span>
              </div>
              <div class="legendItem">
                <div class="legendColor" style="background: #10b981;"></div>
                <span>Príspevky</span>
              </div>
            </div>
            <div class="simpleChart">
              <div
                v-for="(item, index) in data.chart_series.users_series"
                :key="index"
                class="chartRow"
              >
                <div class="chartDate">{{ formatDate(item.date) }}</div>
                <div class="chartBars">
                  <div
                    class="chartBar users"
                    :style="{ width: getBarWidth(data.chart_series.users_series, item.count) + '%' }"
                    :title="`Používatelia: ${item.count}`"
                  ></div>
                  <div
                    class="chartBar posts"
                    :style="{ width: getBarWidth(data.chart_series.posts_series, item.count) + '%' }"
                    :title="`Príspevky: ${item.count}`"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Recent Activity -->
        <section class="activitySection">
          <h2 class="sectionTitle">Najnovšia aktivita</h2>
          <div class="activityTabs">
            <button
              v-for="tab in activityTabs"
              :key="tab.key"
              class="activityTab"
              :class="{ active: activeActivityTab === tab.key }"
              @click="activeActivityTab = tab.key"
            >
              {{ tab.label }}
            </button>
          </div>
          <div class="activityList">
            <div
              v-for="item in currentActivityData"
              :key="item.id"
              class="activityItem"
            >
              <div class="activityContent">
                <div class="activityTitle">{{ getActivityTitle(item) }}</div>
                <div class="activityMeta">{{ getActivityMeta(item) }}</div>
              </div>
              <div class="activityTime">{{ formatDateTime(item.created_at) }}</div>
            </div>
            <div v-if="currentActivityData.length === 0" class="emptyState">
              Žiadna aktivita
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import { http } from '@/lib/http'

export default {
  name: 'AdminDashboardView',
  setup() {
    const loading = ref(false)
    const error = ref(null)
    const data = ref(null)
    const selectedRange = ref('7d')
    const activeActivityTab = ref('users')

    const rangeOptions = [
      { key: 'today', label: 'Dnes' },
      { key: '7d', label: '7 dní' },
      { key: '30d', label: '30 dní' },
    ]

    const activityTabs = [
      { key: 'users', label: 'Používatelia' },
      { key: 'posts', label: 'Príspevky' },
      { key: 'candidates', label: 'Kandidáti' },
      { key: 'events', label: 'Udalosti' },
    ]

    const currentActivityData = computed(() => {
      if (!data.value?.activity) return []
      return data.value.activity[`latest_${activeActivityTab.value}`] || []
    })

    const fetchDashboardData = async () => {
      loading.value = true
      error.value = null
      
      try {
        const response = await http.get(`/admin/dashboard?range=${selectedRange.value}`)
        data.value = response.data
      } catch (err) {
        error.value = err.response?.data?.message || 'Nastala chyba'
      } finally {
        loading.value = false
      }
    }

    const formatNumber = (num) => {
      return new Intl.NumberFormat('sk-SK').format(num)
    }

    const formatDate = (dateString) => {
      const date = new Date(dateString)
      return date.toLocaleDateString('sk-SK', { day: '2-digit', month: '2-digit' })
    }

    const formatDateTime = (dateString) => {
      const date = new Date(dateString)
      return date.toLocaleString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const getBarWidth = (series, count) => {
      const maxCount = Math.max(...series.map(item => item.count))
      return maxCount > 0 ? Math.max((count / maxCount) * 100, 2) : 0
    }

    const getActivityTitle = (item) => {
      switch (activeActivityTab.value) {
        case 'users':
          return item.name
        case 'posts':
          return item.title
        case 'candidates':
          return item.title
        case 'events':
          return item.title
        default:
          return item.name || item.title
      }
    }

    const getActivityMeta = (item) => {
      switch (activeActivityTab.value) {
        case 'users':
          return `ID: ${item.id}`
        case 'posts':
          return `ID: ${item.id} • User: ${item.user_id}`
        case 'candidates':
          return `${item.status} • ${item.source}`
        case 'events':
          return `ID: ${item.id} • Začína: ${formatDateTime(item.starts_at)}`
        default:
          return ''
      }
    }

    const rangeLabel = computed(() => {
      switch (selectedRange.value) {
        case 'today':
          return 'Dnes'
        case '7d':
          return 'Posledných 7 dní'
        case '30d':
          return 'Posledných 30 dní'
        default:
          return 'Posledných 7 dní'
      }
    })

    // Watch for range changes
    watch(selectedRange, () => {
      fetchDashboardData()
    })

    onMounted(() => {
      fetchDashboardData()
    })

    return {
      loading,
      error,
      data,
      selectedRange,
      activeActivityTab,
      rangeOptions,
      activityTabs,
      currentActivityData,
      rangeLabel,
      fetchDashboardData,
      formatNumber,
      formatDate,
      formatDateTime,
      getBarWidth,
      getActivityTitle,
      getActivityMeta,
    }
  }
}
</script>

<style scoped>
.adminDashboard {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.adminHeader {
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding-bottom: 1rem;
}

.adminTitle {
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff;
}

.adminSubtitle {
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.25rem;
}

.rangeSwitcher {
  display: flex;
  gap: 0.5rem;
  padding: 0.25rem;
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 0.5rem;
  width: fit-content;
}

.rangeBtn {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.375rem;
  transition: all 0.2s;
  color: rgba(255, 255, 255, 0.8);
  background: transparent;
  border: none;
  cursor: pointer;
}

.rangeBtn:hover {
  color: #ffffff;
  background-color: rgba(255, 255, 255, 0.1);
}

.rangeBtn.active {
  background-color: #3b82f6;
  color: #ffffff;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3);
}

.loadingState {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.errorState {
  text-align: center;
  padding: 3rem 0;
}

.errorTitle {
  font-size: 1.125rem;
  font-weight: 600;
  color: #ef4444;
}

.errorText {
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.5rem;
}

.retryBtn {
  margin-top: 1rem;
  padding: 0.5rem 1rem;
  background-color: #3b82f6;
  color: white;
  border-radius: 0.375rem;
  border: none;
  cursor: pointer;
}

.retryBtn:hover {
  background-color: #2563eb;
}

.dashboardContent {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.sectionTitle {
  font-size: 1.125rem;
  font-weight: 600;
  color: #ffffff;
  margin-bottom: 1rem;
}

.kpiSection,
.rangeSection {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.kpiGrid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 1rem;
}

@media (min-width: 640px) {
  .kpiGrid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .kpiGrid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (min-width: 1280px) {
  .kpiGrid {
    grid-template-columns: repeat(6, 1fr);
  }
}

.kpiCard {
  background-color: rgba(255, 255, 255, 0.05);
  padding: 1rem;
  border-radius: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(10px);
}

.kpiCard:hover {
  background-color: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.15);
}

.kpiValue {
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff;
}

.kpiLabel {
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.25rem;
}

.chartSection,
.activitySection {
  background-color: rgba(255, 255, 255, 0.05);
  padding: 1.5rem;
  border-radius: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(10px);
}

.chartContainer {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.chartLegend {
  display: flex;
  gap: 1rem;
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.8);
}

.legendItem {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.legendColor {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 0.125rem;
}

.simpleChart {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.chartRow {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.chartDate {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.6);
  width: 4rem;
}

.chartBars {
  flex: 1;
  display: flex;
  gap: 0.25rem;
  height: 1.5rem;
}

.chartBar {
  height: 100%;
  border-radius: 0.125rem;
  transition: all 0.2s;
}

.chartBar.users {
  background-color: #3b82f6;
}

.chartBar.posts {
  background-color: #10b981;
}

.activityTabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.activityTab {
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.7);
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
}

.activityTab:hover {
  color: #ffffff;
}

.activityTab.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.activityList {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: 24rem;
  overflow-y: auto;
}

.activityItem {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 0.75rem;
  background-color: rgba(255, 255, 255, 0.03);
  border-radius: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.05);
  transition: all 0.2s;
}

.activityItem:hover {
  background-color: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.1);
}

.activityContent {
  flex: 1;
  min-width: 0;
}

.activityTitle {
  font-weight: 500;
  color: #ffffff;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.activityMeta {
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.6);
  margin-top: 0.25rem;
}

.activityTime {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.5);
  margin-left: 1rem;
  white-space: nowrap;
}

.emptyState {
  text-align: center;
  padding: 2rem 0;
  color: rgba(255, 255, 255, 0.5);
}

/* Skeleton styles */
.skeleton {
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 0.375rem;
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Grid for charts and activity */
.grid {
  display: grid;
  gap: 1.5rem;
}

.grid-cols-1 {
  grid-template-columns: repeat(1, 1fr);
}

@media (min-width: 1024px) {
  .lg\:grid-cols-2 {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Custom scrollbar for dark theme */
.activityList::-webkit-scrollbar {
  width: 6px;
}

.activityList::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 3px;
}

.activityList::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 3px;
}

.activityList::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.3);
}
</style>
