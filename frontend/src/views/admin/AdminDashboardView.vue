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
  { key: 'new_posts', label: 'Príspevky' },
  { key: 'new_users', label: 'Používatelia' },
  { key: 'new_events', label: 'Udalosti' },
]

const emailVerificationHint =
  'Platí len pre nových používateľov. Pri vypnutí sa nové účty overia automaticky.'

const emailVerificationEnabled = computed(() =>
  Boolean(authSettings.value.require_email_verification),
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
      viewTo: '/admin/users',
    },
    {
      key: 'users_active_30d',
      label: 'Aktívni (30 dní)',
      value: Number(kpi.users_active_30d || 0),
      viewTo: '/admin/users',
    },
    {
      key: 'posts_total',
      label: 'Príspevky',
      value: Number(kpi.posts_total || 0),
      viewTo: '/admin/moderation',
    },
    {
      key: 'events_total',
      label: 'Udalosti',
      value: Number(kpi.events_total || 0),
      viewTo: '/admin/events',
    },
    {
      key: 'posts_moderated_total',
      label: 'Moderované',
      value: Number(kpi.posts_moderated_total || 0),
      viewTo: '/admin/moderation',
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
      to: '/admin/event-sources',
      badge: Number(kpi.events_total || 0),
    },
    {
      title: 'Kontrola kandidátov',
      subtitle: 'Schválenie alebo odmietnutie čakajúcich udalostí.',
      to: '/admin/event-candidates',
      badge: Number(queues.event_candidates_pending || 0),
      badgeTone: Number(queues.event_candidates_pending || 0) > 0 ? 'accent' : 'neutral',
    },
    {
      title: 'Správa používateľov',
      subtitle: 'Profily, roly, blokácie a stav účtu.',
      to: '/admin/users',
      badge: Number(kpi.users_total || 0),
    },
    {
      title: 'Moderácia',
      subtitle: 'Obsah čakajúci na zásah moderátora.',
      to: '/admin/moderation',
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
    if (payload && typeof payload.require_email_verification === 'boolean') {
      authSettings.value = payload
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
      require_email_verification: required,
    })

    const payload = response?.data?.data
    if (payload && typeof payload.require_email_verification === 'boolean') {
      authSettings.value = payload
    } else {
      authSettings.value = { require_email_verification: required }
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

<template>
  <AdminPageShell title="Prehľad">
    <template #right-actions>
      <button type="button" class="btn btnPrimary" :disabled="loading" @click="loadDashboard(true)">
        {{ loading ? 'Načítavam...' : 'Obnoviť' }}
      </button>
      <button type="button" class="btn btnSecondary" :disabled="exporting" @click="exportCsv">
        {{ exporting ? 'Exportujem...' : 'Export CSV' }}
      </button>
    </template>

    <div class="dashboardView">
      <div v-if="error" class="adminAlert" role="alert">
        <span>{{ error }}</span>
        <button type="button" class="btn btnSecondary" @click="loadDashboard(true)">
          Skúsiť znova
        </button>
      </div>

      <section class="settingsRow sectionFade" aria-label="Overenie e-mailu">
        <div class="settingsCopy">
          <div class="settingsTitleRow">
            <h2 class="settingsLabel">Overenie e-mailu pre nových používateľov</h2>
            <span class="settingsInfo" :title="emailVerificationHint">i</span>
          </div>
          <p v-if="authSettingsError" class="settingsError">{{ authSettingsError }}</p>
        </div>

        <label class="switchField">
          <span class="switchState">{{ emailVerificationStateLabel }}</span>
          <span class="switchControl">
            <input
              :checked="emailVerificationEnabled"
              class="switchInput"
              type="checkbox"
              role="switch"
              :disabled="authSettingsLoading || authSettingsSaving"
              aria-label="Prepnúť overenie e-mailu pre nových používateľov"
              @change="toggleEmailVerification($event.target.checked)"
            />
            <span class="switchTrack"></span>
          </span>
        </label>
      </section>

      <section class="kpiGrid sectionFade" aria-label="Kľúčové ukazovatele">
        <template v-if="loading && !stats">
          <div v-for="idx in 5" :key="`kpi-skeleton-${idx}`" class="skeletonCard"></div>
        </template>

        <template v-else>
          <KpiCard
            v-for="item in kpiCards"
            :key="item.key"
            :label="item.label"
            :value="formatNumber(item.value)"
            :view-to="item.viewTo"
            :tone="item.tone || 'default'"
          />
        </template>
      </section>

      <section class="mainGrid sectionFade" aria-label="Trend a rýchle akcie">
        <DashboardSection title="Trend (30 dní)">
          <template #header-actions>
            <div class="metricTabs" role="group" aria-label="Prepínanie trendu">
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
          </template>

          <StatsChart :points="trendPoints" :metric-key="trendMetric" />
        </DashboardSection>

        <DashboardSection title="Rýchle akcie">
          <div class="actionList">
            <QuickActionTile
              v-for="action in quickActions"
              :key="action.title"
              :title="action.title"
              :subtitle="action.subtitle"
              :to="action.to"
              :badge="formatNumber(action.badge)"
              :badge-tone="action.badgeTone || 'neutral'"
            />
          </div>
        </DashboardSection>
      </section>

      <section class="summaryStrip sectionFade" aria-label="Rozloženie účtov">
        <div class="summaryGroup">
          <p class="summaryLabel">Podľa rolí</p>
          <div class="summaryItems">
            <div v-for="item in byRoleList" :key="item.key" class="summaryItem">
              <strong>{{ formatNumber(item.value) }}</strong>
              <span>{{ item.label }}</span>
            </div>
          </div>
        </div>

        <div class="summaryGroup">
          <p class="summaryLabel">Podľa regiónu</p>
          <div class="summaryItems">
            <div v-for="item in byRegionList" :key="item.key" class="summaryItem">
              <strong>{{ formatNumber(item.value) }}</strong>
              <span>{{ item.label }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AdminPageShell>
</template>

<style scoped>
:deep(.adminPageShell) {
  padding-block: 20px 18px;
}

:deep(.adminPageShell__header) {
  align-items: center;
  margin-bottom: 14px;
}

:deep(.adminPageShell__title) {
  margin: 0;
  font-family:
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif;
  font-size: clamp(1.65rem, 2.4vw, 2rem);
  font-weight: 600;
  letter-spacing: -0.04em;
}

:deep(.adminPageShell__actions) {
  gap: 10px;
}

:deep(.adminPageShell__content) {
  gap: 12px;
}

.dashboardView {
  --dashboard-gap-xs: 6px;
  --dashboard-gap-sm: 10px;
  --dashboard-gap-md: 14px;
  --dashboard-gap-lg: 18px;
  --dashboard-radius: 18px;
  --dashboard-border: rgb(var(--color-surface-rgb) / 0.1);
  --dashboard-border-strong: rgb(var(--color-surface-rgb) / 0.15);
  --dashboard-panel: rgb(var(--color-bg-rgb) / 0.34);
  --dashboard-panel-strong: rgb(var(--color-bg-rgb) / 0.48);
  --dashboard-muted: rgb(var(--color-text-secondary-rgb) / 0.88);
  display: grid;
  gap: var(--dashboard-gap-md);
  font-family:
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif;
}

.adminAlert {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--dashboard-gap-sm);
  padding: 12px 14px;
  border-radius: var(--dashboard-radius);
  border: 1px solid rgb(var(--color-danger-rgb, 239 68 68) / 0.28);
  background: rgb(var(--color-danger-rgb, 239 68 68) / 0.08);
  color: rgb(var(--color-danger-rgb, 239 68 68));
}

.btn {
  height: 34px;
  border: 1px solid var(--dashboard-border-strong);
  border-radius: 999px;
  padding: 0 14px;
  background: transparent;
  color: var(--color-surface);
  font-size: 13px;
  font-weight: 600;
  letter-spacing: -0.01em;
  cursor: pointer;
  transition:
    border-color 160ms ease,
    background-color 160ms ease,
    color 160ms ease;
}

.btn:hover:not(:disabled) {
  border-color: rgb(var(--color-primary-rgb) / 0.28);
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btnPrimary {
  border-color: rgb(var(--color-primary-rgb) / 0.34);
  background: rgb(var(--color-primary-rgb) / 0.18);
}

.btnPrimary:hover:not(:disabled) {
  background: rgb(var(--color-primary-rgb) / 0.24);
}

.btnSecondary {
  background: rgb(var(--color-bg-rgb) / 0.52);
}

.btnSecondary:hover:not(:disabled) {
  background: rgb(var(--color-bg-rgb) / 0.68);
}

.settingsRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--dashboard-gap-md);
  padding: 12px 14px;
  border-radius: var(--dashboard-radius);
  border: 1px solid var(--dashboard-border);
  background: var(--dashboard-panel);
}

.settingsCopy {
  min-width: 0;
}

.settingsTitleRow {
  display: flex;
  align-items: center;
  gap: 8px;
}

.settingsLabel {
  margin: 0;
  font-family:
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.35;
}

.settingsInfo {
  display: inline-grid;
  place-items: center;
  width: 18px;
  height: 18px;
  border-radius: 999px;
  border: 1px solid var(--dashboard-border-strong);
  background: rgb(var(--color-bg-rgb) / 0.58);
  color: var(--dashboard-muted);
  font-size: 11px;
  font-weight: 700;
  flex: 0 0 auto;
  cursor: help;
}

.settingsError {
  margin: 6px 0 0;
  font-size: 12px;
  color: rgb(var(--color-danger-rgb, 239 68 68));
}

.switchField {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  white-space: nowrap;
}

.switchState {
  min-width: 74px;
  color: var(--dashboard-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-align: right;
  text-transform: uppercase;
}

.switchControl {
  position: relative;
  display: inline-flex;
}

.switchInput {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0;
  cursor: pointer;
}

.switchTrack {
  position: relative;
  display: inline-flex;
  width: 46px;
  height: 28px;
  border-radius: 999px;
  border: 1px solid var(--dashboard-border-strong);
  background: rgb(var(--color-surface-rgb) / 0.14);
  transition:
    border-color 160ms ease,
    background-color 160ms ease;
}

.switchTrack::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.92);
  transition: transform 160ms ease;
}

.switchInput:checked + .switchTrack {
  border-color: rgb(var(--color-primary-rgb) / 0.36);
  background: rgb(var(--color-primary-rgb) / 0.32);
}

.switchInput:checked + .switchTrack::after {
  transform: translateX(18px);
}

.switchInput:focus-visible + .switchTrack {
  outline: 2px solid rgb(var(--color-primary-rgb) / 0.35);
  outline-offset: 2px;
}

.switchInput:disabled {
  cursor: not-allowed;
}

.switchInput:disabled + .switchTrack {
  opacity: 0.6;
}

.kpiGrid {
  display: grid;
  gap: 8px;
  grid-template-columns: minmax(0, 1fr);
}

.mainGrid {
  display: grid;
  gap: var(--dashboard-gap-md);
  grid-template-columns: minmax(0, 1fr);
}

.metricTabs {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 3px;
  border: 1px solid var(--dashboard-border);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.42);
}

.metricBtn {
  border: none;
  border-radius: 999px;
  padding: 7px 11px;
  background: transparent;
  color: var(--dashboard-muted);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: -0.01em;
  cursor: pointer;
  transition:
    background-color 160ms ease,
    color 160ms ease;
}

.metricBtn.active {
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: var(--color-surface);
}

.actionList {
  display: grid;
  gap: 4px;
}

.summaryStrip {
  display: grid;
  gap: 12px;
  grid-template-columns: minmax(0, 1fr);
  padding-top: 2px;
}

.summaryGroup {
  display: grid;
  gap: 8px;
  min-width: 0;
}

.summaryLabel {
  color: var(--dashboard-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.summaryItems {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.summaryItem {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid var(--dashboard-border);
  background: rgb(var(--color-bg-rgb) / 0.38);
  color: var(--dashboard-muted);
  font-size: 13px;
}

.summaryItem strong {
  color: var(--color-surface);
  font-size: 14px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.skeletonCard {
  min-height: 98px;
  border-radius: var(--dashboard-radius);
  background: linear-gradient(
    90deg,
    rgb(var(--color-surface-rgb) / 0.04),
    rgb(var(--color-surface-rgb) / 0.1),
    rgb(var(--color-surface-rgb) / 0.04)
  );
  background-size: 200% 100%;
  animation: shimmer 1.15s infinite linear;
}

.sectionFade {
  animation: sectionFadeIn 240ms ease both;
}

@media (min-width: 720px) {
  .kpiGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 980px) {
  .kpiGrid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (min-width: 1100px) {
  .mainGrid {
    grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.95fr);
  }

  .summaryStrip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .adminAlert,
  .settingsRow {
    align-items: flex-start;
    flex-direction: column;
  }

  .switchField {
    align-self: flex-end;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sectionFade,
  .skeletonCard {
    animation: none;
  }
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }

  100% {
    background-position: -200% 0;
  }
}

@keyframes sectionFadeIn {
  0% {
    opacity: 0;
    transform: translateY(4px);
  }

  100% {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
