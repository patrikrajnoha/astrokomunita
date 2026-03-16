<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getBotOverview } from '@/services/api/admin/bots'
import BotActivityView from '@/views/admin/BotActivityView.vue'
import BotEngineDashboardView from '@/views/admin/BotEngineDashboardView.vue'
import BotEngineView from '@/views/admin/BotEngineView.vue'
import BotSchedulesView from '@/views/admin/BotSchedulesView.vue'
import BotSourcesHealthView from '@/views/admin/BotSourcesHealthView.vue'

const route = useRoute()
const loading = ref(false)
const error = ref('')
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

const tabs = Object.freeze([
  {
    key: 'overview',
    label: 'Prehľad',
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
    key: 'engine',
    label: 'Modul',
    routeNames: ['admin.bots.engine'],
    component: BotEngineView,
  },
  {
    key: 'activity',
    label: 'Aktivita',
    routeNames: ['admin.bots.activity'],
    component: BotActivityView,
  },
])

const overall = computed(() => overview.value?.overall || {})
const botsCount = computed(() => (Array.isArray(overview.value?.bots) ? overview.value.bots.length : 0))

const activeTab = computed(() => {
  const currentName = String(route.name || '')
  return tabs.find((tab) => tab.routeNames.includes(currentName)) || tabs[0]
})

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

const overviewMetaLine = computed(() => {
  return `Aktualizované ${formatDateTime(overview.value?.generated_at)}`
})

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

async function loadOverview() {
  loading.value = true
  error.value = ''

  try {
    const response = await getBotOverview()
    overview.value = response?.data || overview.value
  } catch (e) {
    error.value = e?.response?.data?.message || 'Načítanie prehľadu botov zlyhalo.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadOverview()
})
</script>

<template>
  <AdminPageShell title="Správa botov" subtitle="Riadenie bot pipeline, zdrojov a behov.">
    <template #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="loadOverview">
        {{ loading ? 'Načítavam…' : 'Obnoviť' }}
      </button>
    </template>

    <section class="metaBar">
      <span :class="systemStatus.className">{{ systemStatus.label }}</span>
      <p class="metaLine">{{ overviewMetaLine }}</p>
    </section>

    <section class="summaryWrap">
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
      <component :is="activeTab.component" embedded />
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


.actionBtn,
.ghostBtn {
  border-radius: 8px;
  padding: 5px 9px;
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
}

.actionBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.ghostBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.26);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: rgb(var(--color-surface-rgb) / 0.95);
  text-decoration: none;
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

  .botWorkspace :deep(.botsOverviewTable) {
    min-width: 0 !important;
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
