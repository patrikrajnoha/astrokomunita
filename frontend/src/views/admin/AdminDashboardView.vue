<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import DashboardSection from '@/components/admin/dashboard/DashboardSection.vue'
import KpiCard from '@/components/admin/dashboard/KpiCard.vue'
import QuickActionTile from '@/components/admin/dashboard/QuickActionTile.vue'
import StatsChart from '@/components/admin/dashboard/StatsChart.vue'
import { getStats, downloadStatsCsv } from '@/services/api/admin/stats'
import { getAuthSettings, updateAuthSettings } from '@/services/api/admin/authSettings'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const stats = ref(null)
const trendMetric = ref('new_posts')
const authSettings = ref({ require_email_verification: true })
const authSettingsLoading = ref(false)
const authSettingsSaving = ref(false)
const authSettingsError = ref('')

const trendMetricOptions = [
  { key: 'new_posts', label: 'New posts' },
  { key: 'new_users', label: 'New users' },
  { key: 'new_events', label: 'New events' },
]

const kpiCards = computed(() => {
  const kpi = stats.value?.kpi || {}

  return [
    { key: 'users_total', label: 'Users total', value: Number(kpi.users_total || 0), viewTo: '/admin/users' },
    { key: 'users_active_30d', label: 'Active (30d)', value: Number(kpi.users_active_30d || 0), viewTo: '/admin/users' },
    { key: 'posts_total', label: 'Posts total', value: Number(kpi.posts_total || 0), viewTo: '/admin/moderation' },
    { key: 'events_total', label: 'Events total', value: Number(kpi.events_total || 0), viewTo: '/admin/events' },
    { key: 'posts_moderated_total', label: 'Moderated posts', value: Number(kpi.posts_moderated_total || 0), viewTo: '/admin/moderation' },
  ]
})

const byRoleList = computed(() => {
  const byRole = stats.value?.demographics?.by_role || {}
  return [
    { key: 'user', label: 'Users', value: Number(byRole.user || 0) },
    { key: 'admin', label: 'Admins', value: Number(byRole.admin || 0) },
    { key: 'bot', label: 'Bots', value: Number(byRole.bot || 0) },
  ]
})

const byRegionList = computed(() => {
  const byRegion = stats.value?.demographics?.by_region || {}
  return [
    { key: 'unknown', label: 'Unknown', value: Number(byRegion.unknown || 0) },
    { key: 'sk', label: 'SK', value: Number(byRegion.sk || 0) },
    { key: 'cz', label: 'CZ', value: Number(byRegion.cz || 0) },
    { key: 'other', label: 'Other', value: Number(byRegion.other || 0) },
  ]
})

const trendPoints = computed(() => {
  const points = stats.value?.trend?.points
  return Array.isArray(points) ? points : []
})

const generatedAtLabel = computed(() => {
  const value = stats.value?.generated_at
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleString()
})

const quickActions = [
  { title: 'Event sources', subtitle: 'Sources and crawl runs in one place', to: '/admin/event-sources' },
  { title: 'Crawl runs', subtitle: 'Open event sources and inspect recent runs', to: '/admin/event-sources' },
  { title: 'User management', subtitle: 'Review profiles, roles, bans and status', to: '/admin/users' },
  { title: 'Moderation queue', subtitle: 'Process flagged and pending content', to: '/admin/moderation' },
]

function formatNumber(value) {
  return new Intl.NumberFormat('sk-SK').format(Number(value || 0))
}

async function loadDashboard(force = false) {
  loading.value = true
  error.value = ''

  try {
    stats.value = await getStats({ force })
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load admin stats.'
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

    toast.success('CSV downloaded.')
  } catch (e) {
    toast.error(e?.response?.data?.message || 'CSV export failed.')
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
    if (payload && typeof payload.require_email_verification === 'boolean') {
      authSettings.value = payload
    }
  } catch (e) {
    authSettingsError.value = e?.response?.data?.message || 'Failed to load auth settings.'
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
      require_email_verification: required,
    })

    const payload = response?.data?.data
    if (payload && typeof payload.require_email_verification === 'boolean') {
      authSettings.value = payload
    } else {
      authSettings.value = { require_email_verification: required }
    }

    toast.success(required ? 'Email verification enabled.' : 'Email verification disabled for new users.')
  } catch (e) {
    authSettingsError.value = e?.response?.data?.message || 'Failed to update auth settings.'
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

<template>
  <AdminPageShell title="Admin Dashboard" subtitle="Single entry point for core admin decisions within 3 clicks.">
    <template #right-actions>
      <button type="button" class="btn" :disabled="loading" @click="loadDashboard(true)">
        {{ loading ? 'Loading...' : 'Refresh' }}
      </button>
      <button type="button" class="btn" :disabled="exporting" @click="exportCsv">
        {{ exporting ? 'Downloading...' : 'Download CSV' }}
      </button>
    </template>

    <div v-if="error" class="adminAlert">
      <span>{{ error }}</span>
      <button type="button" class="btn" @click="loadDashboard(true)">Retry</button>
    </div>

    <section class="policyCard sectionFade" aria-label="Authentication settings">
      <div class="policyRow">
        <div>
          <h3>Email verification for new users</h3>
          <p class="policyMuted">
            If disabled, newly registered users are auto-verified and no verification email is sent.
          </p>
          <p class="policyMuted">Existing users are not changed.</p>
        </div>
        <label class="toggleLabel">
          <input
            :checked="Boolean(authSettings.require_email_verification)"
            type="checkbox"
            :disabled="authSettingsLoading || authSettingsSaving"
            @change="toggleEmailVerification($event.target.checked)"
          />
          <span>{{ authSettings.require_email_verification ? 'Required' : 'Disabled' }}</span>
        </label>
      </div>

      <p v-if="authSettingsError" class="policyError">{{ authSettingsError }}</p>
    </section>

    <section class="kpiGrid sectionFade" aria-label="KPI overview">
      <template v-if="loading && !stats">
        <div v-for="idx in 5" :key="`kpi-skeleton-${idx}`" class="skeletonCard"></div>
      </template>

      <KpiCard
        v-for="item in kpiCards"
        v-else
        :key="item.key"
        :label="item.label"
        :value="formatNumber(item.value)"
        :hint="`Generated ${generatedAtLabel}`"
        :view-to="item.viewTo"
      />
    </section>

    <section class="twoCol sectionFade" aria-label="Trend and quick actions">
      <DashboardSection title="30-day trend" subtitle="Toggle metric and inspect daily volume.">
        <div class="metricTabs" role="group" aria-label="Trend metric selector">
          <button
            v-for="option in trendMetricOptions"
            :key="option.key"
            type="button"
            class="metricBtn"
            :class="{ active: trendMetric === option.key }"
            @click="trendMetric = option.key"
          >
            {{ option.label }}
          </button>
        </div>

        <StatsChart :points="trendPoints" :metric-key="trendMetric" />
      </DashboardSection>

      <DashboardSection title="Quick actions" subtitle="1-click shortcuts to daily admin tasks.">
        <div class="actionGrid">
          <QuickActionTile
            v-for="action in quickActions"
            :key="action.title"
            :title="action.title"
            :subtitle="action.subtitle"
            :to="action.to"
          />
        </div>
      </DashboardSection>
    </section>

    <section class="twoCol sectionFade" aria-label="Demographics overview">
      <DashboardSection title="Demographics by role" subtitle="Current account distribution.">
        <div class="statList">
          <article v-for="item in byRoleList" :key="item.key" class="statRow">
            <span>{{ item.label }}</span>
            <strong>{{ formatNumber(item.value) }}</strong>
          </article>
        </div>
      </DashboardSection>

      <DashboardSection title="Demographics by region" subtitle="Inferred from user location.">
        <div class="statList">
          <article v-for="item in byRegionList" :key="item.key" class="statRow">
            <span>{{ item.label }}</span>
            <strong>{{ formatNumber(item.value) }}</strong>
          </article>
        </div>
      </DashboardSection>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.adminAlert {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: var(--color-danger);
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-danger-rgb, 239 68 68) / 0.35);
  background: rgb(var(--color-danger-rgb, 239 68 68) / 0.08);
}

.btn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 10px;
  padding: 6px 10px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.policyCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 14px;
  padding: 14px;
  background: rgb(var(--color-bg-rgb) / 0.55);
}

.policyRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.policyRow h3 {
  margin: 0;
}

.policyMuted {
  margin: 2px 0 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 13px;
}

.policyError {
  margin: 10px 0 0;
  color: var(--color-danger);
}

.toggleLabel {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  border-radius: 999px;
  padding: 6px 10px;
  white-space: nowrap;
}

.kpiGrid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 760px) {
  .kpiGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1200px) {
  .kpiGrid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}

.twoCol {
  display: grid;
  gap: 12px;
  grid-template-columns: 1fr;
}

@media (min-width: 1100px) {
  .twoCol {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.skeletonCard {
  min-height: 120px;
  border-radius: 14px;
  background: linear-gradient(90deg, rgb(var(--color-surface-rgb) / 0.06), rgb(var(--color-surface-rgb) / 0.12), rgb(var(--color-surface-rgb) / 0.06));
  background-size: 200% 100%;
  animation: shimmer 1.15s infinite linear;
}

.metricTabs {
  display: inline-flex;
  gap: 6px;
  flex-wrap: wrap;
}

.metricBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 12px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.metricBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.actionGrid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 720px) {
  .actionGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .policyRow {
    align-items: flex-start;
    flex-direction: column;
  }
}

.statList {
  display: grid;
  gap: 8px;
}

.statRow {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  padding: 10px;
  display: flex;
  justify-content: space-between;
  gap: 8px;
  background: rgb(var(--color-bg-rgb) / 0.35);
}

.sectionFade {
  animation: sectionFadeIn 260ms ease both;
}

@media (prefers-reduced-motion: reduce) {
  .sectionFade,
  .skeletonCard {
    animation: none;
  }
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@keyframes sectionFadeIn {
  0% { opacity: 0; transform: translateY(4px); }
  100% { opacity: 1; transform: translateY(0); }
}
</style>
