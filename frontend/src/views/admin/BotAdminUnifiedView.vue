<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getBotOverview, getBotTranslationHealth } from '@/services/api/admin/bots'
import BotActivityView from '@/views/admin/BotActivityView.vue'
import BotEngineDashboardView from '@/views/admin/BotEngineDashboardView.vue'
import BotSchedulesView from '@/views/admin/BotSchedulesView.vue'
import BotSourcesHealthView from '@/views/admin/BotSourcesHealthView.vue'

const route = useRoute()

const loadingOverview = ref(false)
const refreshingWorkspace = ref(false)
const loadingTranslationHealth = ref(false)
const error = ref('')
const refreshToken = ref(0)
const overview = ref({
  generated_at: null,
  overall: {
    active_sources: 0,
    failing_sources: 0,
    dead_sources: 0,
    cooldown_skips_24h: 0,
  },
  bots: [],
})
const translationHealth = ref({
  provider: null,
  fallback_provider: null,
  degraded: false,
  result: {
    ok: null,
    error_type: null,
  },
  provider_probes: {},
})

const tabs = Object.freeze([
  {
    key: 'dashboard',
    label: 'Dashboard',
    routeNames: ['admin.bots'],
    component: BotEngineDashboardView,
  },
  {
    key: 'sources',
    label: 'Zdroje',
    routeNames: ['admin.bots.sources'],
    component: BotSourcesHealthView,
  },
  {
    key: 'schedules',
    label: 'Plány',
    routeNames: ['admin.bots.schedules'],
    component: BotSchedulesView,
  },
  {
    key: 'logs',
    label: 'Logy',
    routeNames: ['admin.bots.activity'],
    component: BotActivityView,
  },
])

const activeTab = computed(() => {
  const currentName = String(route.name || '')
  return tabs.find((tab) => tab.routeNames.includes(currentName)) || tabs[0]
})

const isDashboardTab = computed(() => activeTab.value.key === 'dashboard')
const overall = computed(() => overview.value?.overall || {})
const botsCount = computed(() => (Array.isArray(overview.value?.bots) ? overview.value.bots.length : 0))

const systemStatus = computed(() => {
  const dead = Number(overall.value.dead_sources || 0)
  const failing = Number(overall.value.failing_sources || 0)

  if (dead > 0) {
    return {
      label: 'Kritický',
      className: 'statusBadge statusBadge--danger',
    }
  }

  if (failing > 0) {
    return {
      label: 'Upozornenie',
      className: 'statusBadge statusBadge--warn',
    }
  }

  return {
    label: 'V poriadku',
    className: 'statusBadge statusBadge--ok',
  }
})

const overviewMetaLine = computed(() => `Aktualizované ${formatDateTime(overview.value?.generated_at)}`)
const translationServiceBadges = computed(() => {
  const health = translationHealth.value || {}
  const primaryProvider = normalizeTranslationProvider(health.provider)
  const fallbackProvider = normalizeTranslationProvider(health.fallback_provider)
  const probes = health?.provider_probes && typeof health.provider_probes === 'object'
    ? health.provider_probes
    : {}
  const providers = []

  for (const value of [primaryProvider, fallbackProvider]) {
    if (!value) continue
    if (providers.includes(value)) continue
    providers.push(value)
  }

  if (providers.length === 0) {
    return []
  }

  return providers.map((providerName) => {
    const probe = probes[providerName] && typeof probes[providerName] === 'object'
      ? probes[providerName]
      : null
    const probeOk = probe?.ok
    const isPrimary = providerName === primaryProvider
    const resultOk = health?.result?.ok
    const isDegraded = Boolean(health?.degraded)

    let state = 'muted'
    if (probeOk === true) {
      state = 'ok'
    } else if (probeOk === false) {
      state = isPrimary && isDegraded && resultOk === true ? 'warn' : 'danger'
    } else if (isPrimary) {
      if (resultOk === true && isDegraded) {
        state = 'warn'
      } else if (resultOk === true) {
        state = 'ok'
      } else if (resultOk === false) {
        state = 'danger'
      }
    }

    return {
      provider: providerName,
      name: translationProviderLabel(providerName),
      statusLabel: translationStatusLabel(state),
      className: `translationPill translationPill--${state}`,
    }
  })
})

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function normalizeTranslationProvider(value) {
  const normalized = String(value || '').trim().toLowerCase()
  if (!normalized || normalized === 'none' || normalized === 'dummy') return ''
  if (normalized === 'libre' || normalized === 'http') return 'libretranslate'
  return normalized
}

function translationProviderLabel(provider) {
  const normalized = normalizeTranslationProvider(provider)
  if (normalized === 'libretranslate') return 'LibreTranslate'
  if (normalized === 'ollama') return 'Ollama'
  return normalized || 'Preklad'
}

function translationStatusLabel(state) {
  if (state === 'ok') return 'Aktívny'
  if (state === 'warn') return 'Degradované'
  if (state === 'danger') return 'Nedostupný'
  return 'Neznáme'
}

async function loadOverview({ silent = false } = {}) {
  if (!silent) {
    loadingOverview.value = true
  }
  error.value = ''

  try {
    const response = await getBotOverview()
    overview.value = response?.data || overview.value
  } catch (e) {
    error.value = e?.response?.data?.message || 'Načítanie prehľadu botov zlyhalo.'
  } finally {
    if (!silent) {
      loadingOverview.value = false
    }
  }
}

async function loadTranslationHealth() {
  loadingTranslationHealth.value = true
  try {
    const response = await getBotTranslationHealth()
    translationHealth.value = response?.data || translationHealth.value
  } catch {
    translationHealth.value = {
      provider: null,
      fallback_provider: null,
      degraded: false,
      result: {
        ok: false,
        error_type: 'Nedostupné',
      },
      provider_probes: {},
    }
  } finally {
    loadingTranslationHealth.value = false
  }
}

async function refreshWorkspace() {
  if (refreshingWorkspace.value) return

  refreshingWorkspace.value = true
  await Promise.all([
    loadOverview(),
    loadTranslationHealth(),
  ])
  refreshToken.value += 1
  refreshingWorkspace.value = false
}

onMounted(() => {
  void Promise.all([
    loadOverview(),
    loadTranslationHealth(),
  ])
})
</script>

<template>
  <AdminPageShell title="Správa botov" subtitle="Control panel pre bot pipeline, zdroje, plány a logy.">
    <template #right-actions>
      <button class="actionBtn" type="button" :disabled="loadingOverview || refreshingWorkspace" @click="refreshWorkspace">
        {{ loadingOverview || refreshingWorkspace ? 'Načítavam…' : 'Obnoviť' }}
      </button>
    </template>

    <section v-if="isDashboardTab" class="metaBar">
      <span :class="systemStatus.className">{{ systemStatus.label }}</span>
      <p class="metaLine">{{ overviewMetaLine }}</p>
      <div
        v-if="translationServiceBadges.length > 0 || loadingTranslationHealth"
        class="translationMeta"
        aria-label="Stav prekladov"
      >
        <span class="translationMetaLabel">Preklady</span>
        <template v-if="loadingTranslationHealth && translationServiceBadges.length === 0">
          <span class="translationPill translationPill--muted">Načítavam</span>
        </template>
        <template v-else>
          <span
            v-for="badge in translationServiceBadges"
            :key="`translation-badge-${badge.provider}`"
            :class="badge.className"
          >
            {{ badge.name }}: {{ badge.statusLabel }}
          </span>
        </template>
      </div>
    </section>

    <section v-if="isDashboardTab" class="summaryWrap" aria-label="Kľúčové metriky botov">
      <article class="summaryCard">
        <p class="summaryLabel">Aktívne zdroje</p>
        <p class="summaryValue">{{ Number(overall.active_sources || 0) }}</p>
      </article>

      <article class="summaryCard">
        <p class="summaryLabel">Chybové zdroje</p>
        <p class="summaryValue">{{ Number(overall.failing_sources || 0) }}</p>
      </article>

      <article class="summaryCard">
        <p class="summaryLabel">Neaktívne zdroje</p>
        <p class="summaryValue">{{ Number(overall.dead_sources || 0) }}</p>
      </article>

      <article class="summaryCard">
        <p class="summaryLabel">Preskočenia cooldownu (24h)</p>
        <p class="summaryValue">{{ Number(overall.cooldown_skips_24h || 0) }}</p>
      </article>

      <article class="summaryCard">
        <p class="summaryLabel">Spravované boty</p>
        <p class="summaryValue">{{ botsCount }}</p>
      </article>
    </section>

    <p v-if="error" class="error">{{ error }}</p>

    <section class="tabHeader">
      <nav class="tabNav" aria-label="Sekcie botov">
        <RouterLink
          v-for="tab in tabs"
          :key="tab.key"
          :to="{ name: tab.routeNames[0] }"
          class="tabLink"
          :class="{ active: tab.key === activeTab.key }"
          :aria-current="tab.key === activeTab.key ? 'page' : undefined"
        >
          {{ tab.label }}
        </RouterLink>
      </nav>
    </section>

    <div class="botWorkspace">
      <component
        :is="activeTab.component"
        embedded
        :refresh-token="refreshToken"
        :overview="overview"
        :overview-loading="loadingOverview"
      />
    </div>
  </AdminPageShell>
</template>

<style scoped>
.botWorkspace {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.38);
  padding: 10px;
  min-width: 0;
  container-type: inline-size;
}

.metaBar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.summaryWrap {
  display: grid;
  gap: 6px;
  grid-template-columns: repeat(auto-fit, minmax(138px, 1fr));
}

.summaryCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 9px;
  background: rgb(var(--color-bg-rgb) / 0.62);
  padding: 8px 9px;
  display: grid;
  gap: 3px;
}

.summaryLabel {
  margin: 0;
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
}

.summaryValue {
  margin: 0;
  font-size: 1.12rem;
  font-weight: 800;
}

.statusBadge {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 999px;
  padding: 2px 9px;
  font-size: 0.7rem;
  font-weight: 700;
}

.statusBadge--ok {
  border-color: rgb(var(--color-success-rgb) / 0.52);
  color: var(--color-success);
}

.statusBadge--warn {
  border-color: rgb(var(--color-warning-rgb) / 0.52);
  color: var(--color-warning);
}

.statusBadge--danger {
  border-color: rgb(var(--color-danger-rgb) / 0.55);
  color: var(--color-danger);
}

.metaLine {
  margin: 0;
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  overflow-wrap: anywhere;
}

.translationMeta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.translationMetaLabel {
  margin: 0;
  font-size: 0.68rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.86);
}

.translationPill {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 0.68rem;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.96);
}

.translationPill--ok {
  border-color: rgb(var(--color-success-rgb) / 0.52);
  color: var(--color-success);
}

.translationPill--warn {
  border-color: rgb(var(--color-warning-rgb) / 0.52);
  color: var(--color-warning);
}

.translationPill--danger {
  border-color: rgb(var(--color-danger-rgb) / 0.52);
  color: var(--color-danger);
}

.tabHeader {
  display: grid;
  gap: 5px;
}

.tabNav {
  display: flex;
  gap: 4px;
  padding: 4px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.42);
  overflow-x: auto;
}

.tabLink {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 8px;
  padding: 6px 9px;
  text-decoration: none;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  white-space: nowrap;
  font-size: 0.77rem;
  font-weight: 700;
}

.tabLink:hover {
  border-color: rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.05);
}

.tabLink.active {
  border-color: rgb(var(--color-primary-rgb) / 0.4);
  background: rgb(var(--color-primary-rgb) / 0.15);
  color: rgb(var(--color-surface-rgb) / 0.98);
}

.actionBtn {
  border-radius: 8px;
  padding: 5px 9px;
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.actionBtn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error {
  margin: 0;
  color: var(--color-danger);
}

.botWorkspace :deep(.botSection),
.botWorkspace :deep(.botEngineShell) {
  display: grid;
  gap: 10px;
  min-width: 0;
}

.botWorkspace :deep(.embeddedHeader) {
  align-items: center;
  gap: 8px;
}

.botWorkspace :deep(.embeddedTitle) {
  margin-bottom: 2px;
  font-size: 0.92rem;
}

.botWorkspace :deep(.embeddedSubtitle) {
  font-size: 0.74rem;
}

.botWorkspace :deep(.card),
.botWorkspace :deep(.panel) {
  border-radius: 10px;
  padding: 10px;
  min-width: 0;
}

.botWorkspace :deep(.tableWrap) {
  max-width: 100%;
}

.botWorkspace :deep(.filterRow),
.botWorkspace :deep(.filters),
.botWorkspace :deep(.formGrid),
.botWorkspace :deep(.advancedFiltersBody),
.botWorkspace :deep(.filtersAdvancedBody),
.botWorkspace :deep(.retentionMainRow) {
  min-width: 0;
}

.botWorkspace :deep(.table th),
.botWorkspace :deep(.table td),
.botWorkspace :deep(.activityTable th),
.botWorkspace :deep(.activityTable td) {
  padding: 7px 8px;
  font-size: 0.76rem;
}

.botWorkspace :deep(.table th),
.botWorkspace :deep(.activityTable th) {
  font-size: 0.66rem;
}

.botWorkspace :deep(.field input),
.botWorkspace :deep(.field select),
.botWorkspace :deep(.field textarea),
.botWorkspace :deep(.filterField input),
.botWorkspace :deep(.filterField select),
.botWorkspace :deep(.filterField textarea) {
  min-height: 32px;
  font-size: 12px;
  padding: 6px 8px;
}

.botWorkspace :deep(.actionBtn),
.botWorkspace :deep(.ghostBtn),
.botWorkspace :deep(.dangerBtn),
.botWorkspace :deep(.runBtn) {
  min-height: 32px;
  padding: 5px 9px;
  font-size: 0.74rem;
}

@container (max-width: 860px) {
  .botWorkspace :deep(.embeddedHeader) {
    align-items: stretch;
    flex-direction: column;
  }

  .botWorkspace :deep(.embeddedHeaderActions),
  .botWorkspace :deep(.filterActions),
  .botWorkspace :deep(.createActions),
  .botWorkspace :deep(.advancedActions),
  .botWorkspace :deep(.paginationActions) {
    width: 100%;
    justify-content: stretch;
  }

  .botWorkspace :deep(.filterRow),
  .botWorkspace :deep(.formGrid),
  .botWorkspace :deep(.advancedFiltersBody),
  .botWorkspace :deep(.filtersAdvancedBody),
  .botWorkspace :deep(.retentionMainRow) {
    grid-template-columns: 1fr !important;
    align-items: stretch;
  }

  .botWorkspace :deep(.field--compact),
  .botWorkspace :deep(.filterField),
  .botWorkspace :deep(.filterField--compact),
  .botWorkspace :deep(.filtersAdvanced) {
    min-width: 0;
    max-width: 100%;
  }

  .botWorkspace :deep(.embeddedHeaderActions .actionBtn),
  .botWorkspace :deep(.embeddedHeaderActions .ghostBtn),
  .botWorkspace :deep(.filterActions .actionBtn),
  .botWorkspace :deep(.filterActions .ghostBtn),
  .botWorkspace :deep(.createActions .actionBtn),
  .botWorkspace :deep(.advancedActions .runBtn),
  .botWorkspace :deep(.advancedActions .ghostBtn),
  .botWorkspace :deep(.advancedActions .dangerBtn),
  .botWorkspace :deep(.paginationActions .ghostBtn) {
    flex: 1 1 auto;
    width: 100%;
    text-align: center;
  }
}

@container (max-width: 740px) {
  .botWorkspace {
    padding: 8px;
  }

  .botWorkspace :deep(.table) {
    min-width: 620px;
  }

  .botWorkspace :deep(.activityTable) {
    min-width: 680px;
  }

  .botWorkspace :deep(.pager),
  .botWorkspace :deep(.pagination) {
    justify-content: stretch;
  }
}

@media (max-width: 767px) {
  .metaBar {
    align-items: flex-start;
    flex-direction: column;
    gap: 6px;
  }

  .botWorkspace {
    padding: 8px;
  }
}
</style>
