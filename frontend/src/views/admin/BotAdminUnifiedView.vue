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
const botsCount = computed(() =>
  Array.isArray(overview.value?.bots) ? overview.value.bots.length : 0,
)

const activeTab = computed(() => {
  const currentName = String(route.name || '')
  return tabs.find((tab) => tab.routeNames.includes(currentName)) || tabs[0]
})

const systemStatus = computed(() => {
  const dead = Number(overall.value.dead_sources || 0)
  const failing = Number(overall.value.failing_sources || 0)
  if (dead > 0) {
    return {
      label: 'Critical',
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
  <AdminPageShell title="Sprava botov" subtitle="Zjednotene admin rozhranie pre spravu bot pipeline.">
    <template #right-actions>
      <button class="actionBtn" type="button" :disabled="loading" @click="loadOverview">
        {{ loading ? 'Nacitavam...' : 'Obnovit prehlad' }}
      </button>
      <RouterLink class="ghostBtn" :to="{ name: 'admin.bots.activity' }">Otvorit aktivitu</RouterLink>
    </template>

    <section class="summaryWrap">
      <article class="summaryCard summaryCard--status">
        <p class="summaryLabel">Stav systemu</p>
        <div class="statusRow">
          <span :class="systemStatus.className">{{ systemStatus.label }}</span>
          <span class="summaryHint">Aktualizovane {{ formatDateTime(overview.generated_at) }}</span>
        </div>
      </article>

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
        <p class="summaryLabel">Preskocenia cooldownu (24h)</p>
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
        <span class="tabTitle">{{ tab.label }}</span>
        <span class="tabHint">{{ tab.subtitle }}</span>
      </RouterLink>
    </nav>

    <section class="sectionIntro">
      <h2>{{ activeTab.label }}</h2>
      <p>{{ activeTab.subtitle }}</p>
    </section>

    <component :is="activeTab.component" embedded />
  </AdminPageShell>
</template>

<style scoped>
.summaryWrap {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
}

.summaryCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.72);
  padding: 12px;
  display: grid;
  gap: 6px;
}

.summaryCard--status {
  grid-column: span 2;
}

.summaryLabel {
  margin: 0;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.summaryValue {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 800;
}

.statusRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.summaryHint {
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.statusBadge {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 0.72rem;
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

.tabNav {
  position: sticky;
  top: 0;
  z-index: 5;
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.92);
  padding: 8px;
  backdrop-filter: blur(6px);
}

.tabLink {
  display: grid;
  gap: 4px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 10px;
  padding: 10px;
  text-decoration: none;
  color: inherit;
}

.tabLink:hover {
  border-color: rgb(var(--color-surface-rgb) / 0.28);
  background: rgb(var(--color-surface-rgb) / 0.06);
}

.tabLink.active {
  border-color: rgb(var(--color-primary-rgb) / 0.45);
  background: rgb(var(--color-primary-rgb) / 0.14);
}

.tabTitle {
  font-size: 0.86rem;
  font-weight: 700;
}

.tabHint {
  font-size: 0.76rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
  line-height: 1.3;
}

.sectionIntro {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  padding-bottom: 10px;
}

.sectionIntro h2 {
  margin: 0 0 4px;
  font-size: 1.1rem;
}

.sectionIntro p {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.86rem;
}

.actionBtn,
.ghostBtn {
  border-radius: 10px;
  padding: 7px 11px;
  font-size: 0.82rem;
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

@media (max-width: 980px) {
  .summaryCard--status {
    grid-column: span 1;
  }

  .tabNav {
    top: 6px;
  }
}

@media (max-width: 767px) {
  .tabNav {
    grid-template-columns: 1fr;
  }
}
</style>
