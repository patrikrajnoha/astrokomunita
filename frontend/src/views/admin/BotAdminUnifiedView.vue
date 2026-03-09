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
    label: 'Prehlad',
    subtitle: 'Rychly prehlad stavu bot pipeline.',
    routeNames: ['admin.bots'],
    component: BotEngineDashboardView,
  },
  {
    key: 'sources',
    label: 'Zdroje',
    subtitle: 'Zdravie zdrojov, cooldown a recovery.',
    routeNames: ['admin.bots.sources'],
    component: BotSourcesHealthView,
  },
  {
    key: 'schedules',
    label: 'Plany',
    subtitle: 'Intervaly behov a planovanie automatizacie.',
    routeNames: ['admin.bots.schedules'],
    component: BotSchedulesView,
  },
  {
    key: 'engine',
    label: 'Modul',
    subtitle: 'Run control, preklady a publish workflow.',
    routeNames: ['admin.bots.engine'],
    component: BotEngineView,
  },
  {
    key: 'activity',
    label: 'Aktivita',
    subtitle: 'Audit a chronologia behov/publish akcii.',
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
      label: 'Kriticky',
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
  return `Stav ${systemStatus.value.label} | aktualizovane ${formatDateTime(overview.value?.generated_at)}`
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
    error.value = e?.response?.data?.message || 'Nacitanie bot overview zlyhalo.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadOverview()
})
</script>

<template>
  <AdminPageShell title="Sprava botov" subtitle="Jednotne riadenie bot pipeline, zdrojov a behov.">
    <template #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="loadOverview">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
      <RouterLink v-if="activeTab.key !== 'activity'" class="ghostBtn" :to="{ name: 'admin.bots.activity' }">
        Aktivita
      </RouterLink>
    </template>

    <section class="metaBar">
      <span :class="systemStatus.className">{{ systemStatus.label }}</span>
      <p class="metaLine">{{ overviewMetaLine }}</p>
    </section>

    <section class="summaryWrap">
      <article class="summaryCard">
        <p class="summaryLabel">Aktivne zdroje</p>
        <p class="summaryValue">{{ Number(overall.active_sources || 0) }}</p>
      </article>

      <article class="summaryCard">
        <p class="summaryLabel">Chybove zdroje</p>
        <p class="summaryValue">{{ Number(overall.failing_sources || 0) }}</p>
      </article>

      <article class="summaryCard">
        <p class="summaryLabel">Neaktivne zdroje</p>
        <p class="summaryValue">{{ Number(overall.dead_sources || 0) }}</p>
      </article>

      <article class="summaryCard">
        <p class="summaryLabel">Cooldown skipy (24h)</p>
        <p class="summaryValue">{{ Number(overall.cooldown_skips_24h || 0) }}</p>
      </article>

      <article class="summaryCard">
        <p class="summaryLabel">Spravovane boty</p>
        <p class="summaryValue">{{ botsCount }}</p>
      </article>
    </section>

    <p v-if="error" class="error">{{ error }}</p>

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

    <p class="activeModeHint">{{ activeTab.subtitle }}</p>

    <component :is="activeTab.component" embedded />
  </AdminPageShell>
</template>

<style scoped>
.metaBar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.summaryWrap {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.summaryCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.62);
  padding: 10px;
  display: grid;
  gap: 4px;
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
  font-size: 1.22rem;
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
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.tabNav {
  display: inline-flex;
  gap: 4px;
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  padding-bottom: 6px;
  overflow-x: auto;
}

.tabLink {
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 7px 10px;
  text-decoration: none;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  white-space: nowrap;
  font-size: 0.8rem;
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

.activeModeHint {
  margin: 0;
  font-size: 0.82rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.actionBtn,
.ghostBtn {
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 0.78rem;
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

@media (max-width: 767px) {
  .metaBar {
    align-items: flex-start;
    flex-direction: column;
    gap: 6px;
  }
}
</style>
