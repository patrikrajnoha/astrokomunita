<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getBotOverview, getBotTranslationHealth } from '@/services/api/admin/bots'
import BotActivityView from '@/views/admin/BotActivityView.vue'
import BotEngineDashboardView from '@/views/admin/BotEngineDashboardView.vue'
import BotEngineView from '@/views/admin/BotEngineView.vue'
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

const showEngineModal = ref(false)

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
      <button class="secondaryBtn" type="button" @click="showEngineModal = true">
        Legacy nástroje
      </button>
      <button class="refreshBtn" type="button" :disabled="loadingOverview || refreshingWorkspace" @click="refreshWorkspace">
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

    <teleport to="body">
      <transition name="modal-fade">
        <div v-if="showEngineModal" class="engineModalBackdrop" @mousedown.self="showEngineModal = false">
          <transition name="modal-pop">
            <div v-if="showEngineModal" class="engineModalCard" role="dialog" aria-modal="true" aria-label="Legacy nástroje">
              <div class="engineModalHead">
                <p class="engineModalTitle">Legacy nástroje</p>
                <button class="engineModalClose" type="button" aria-label="Zavrieť" @click="showEngineModal = false">✕</button>
              </div>
              <div class="engineModalBody">
                <BotEngineView embedded />
              </div>
            </div>
          </transition>
        </div>
      </transition>
    </teleport>
  </AdminPageShell>
</template>

<style scoped>
.botWorkspace {
  border-radius: 14px;
  background: #1c2736;
  padding: 12px;
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
  gap: 8px;
  grid-template-columns: repeat(auto-fit, minmax(138px, 1fr));
}

.summaryCard {
  border-radius: 12px;
  background: #222E3F;
  padding: 14px 16px;
  display: grid;
  gap: 6px;
}

.summaryLabel {
  margin: 0;
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #ABB8C9;
}

.summaryValue {
  margin: 0;
  font-size: 1.18rem;
  font-weight: 800;
  color: #FFFFFF;
}

.statusBadge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 0.7rem;
  font-weight: 700;
}

.statusBadge--ok {
  background: rgba(34, 197, 94, 0.12);
  color: #22C55E;
}

.statusBadge--warn {
  background: rgba(245, 158, 11, 0.12);
  color: #F59E0B;
}

.statusBadge--danger {
  background: rgba(235, 36, 82, 0.12);
  color: #EB2452;
}

.metaLine {
  margin: 0;
  font-size: 0.74rem;
  color: #ABB8C9;
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
  color: #ABB8C9;
}

.translationPill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 0.68rem;
  font-weight: 700;
}

.translationPill--ok {
  background: rgba(34, 197, 94, 0.12);
  color: #22C55E;
}

.translationPill--warn {
  background: rgba(245, 158, 11, 0.12);
  color: #F59E0B;
}

.translationPill--danger {
  background: rgba(235, 36, 82, 0.12);
  color: #EB2452;
}

.translationPill--muted {
  background: rgba(171, 184, 201, 0.1);
  color: #ABB8C9;
}

.tabHeader {
  display: grid;
  gap: 5px;
}

.tabNav {
  display: flex;
  gap: 2px;
  padding: 4px;
  border-radius: 999px;
  background: #1c2736;
  overflow-x: auto;
  scrollbar-width: none;
}

.tabNav::-webkit-scrollbar {
  display: none;
}

.tabLink {
  border-radius: 999px;
  padding: 7px 16px;
  text-decoration: none;
  color: #ABB8C9;
  white-space: nowrap;
  font-size: 0.8rem;
  font-weight: 600;
  transition: background-color 150ms ease, color 150ms ease;
}

.tabLink:hover {
  background: #222E3F;
  color: #FFFFFF;
}

.tabLink.active {
  background: #0F73FF;
  color: #FFFFFF;
}

.refreshBtn {
  border: none;
  border-radius: 12px;
  padding: 8px 18px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  background: #0F73FF;
  color: #FFFFFF;
  transition: opacity 150ms ease;
}

.refreshBtn:hover:not(:disabled) {
  opacity: 0.88;
}

.refreshBtn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.error {
  margin: 0;
  color: #EB2452;
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
  border-radius: 12px;
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
  padding: 5px 14px;
  font-size: 0.74rem;
  border: none;
  border-radius: 12px;
}

.botWorkspace :deep(.runBtn) {
  background: #0F73FF;
  color: #FFFFFF;
}

.botWorkspace :deep(.ghostBtn),
.botWorkspace :deep(.actionBtn) {
  background: #222E3F;
  color: #ABB8C9;
}

.botWorkspace :deep(.dangerBtn) {
  background: #EB2452;
  color: #FFFFFF;
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

/* Secondary button */
.secondaryBtn {
  border: none;
  border-radius: 12px;
  padding: 8px 18px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  background: #222E3F;
  color: #ABB8C9;
  transition: opacity 150ms ease;
}

.secondaryBtn:hover {
  opacity: 0.88;
}

/* Engine modal */
.engineModalBackdrop {
  position: fixed;
  inset: 0;
  z-index: 1400;
  display: grid;
  place-items: center;
  background: rgba(0, 0, 0, 0.6);
  padding: 16px;
  backdrop-filter: blur(4px);
}

.engineModalCard {
  width: min(680px, 100%);
  max-height: min(88vh, 860px);
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  background: #151d28;
  overflow: hidden;
}

.engineModalHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 20px 14px;
  border-bottom: 1px solid rgba(171, 184, 201, 0.1);
  flex-shrink: 0;
}

.engineModalTitle {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #FFFFFF;
}

.engineModalClose {
  border: none;
  border-radius: 10px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #222E3F;
  color: #ABB8C9;
  font-size: 0.85rem;
  cursor: pointer;
  transition: opacity 150ms ease;
  flex-shrink: 0;
}

.engineModalClose:hover {
  opacity: 0.8;
}

.engineModalBody {
  overflow-y: auto;
  padding: 16px 20px 20px;
  flex: 1;
}

/* Modal transitions */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 180ms ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-pop-enter-active,
.modal-pop-leave-active {
  transition: transform 200ms ease, opacity 200ms ease;
}

.modal-pop-enter-from,
.modal-pop-leave-to {
  transform: translateY(16px) scale(0.97);
  opacity: 0;
}
</style>
