<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getBotOverview } from '@/services/api/admin/bots'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const loading = ref(false)
const error = ref('')
const payload = ref({
  window_hours: 24,
  generated_at: null,
  overall: {
    active_sources: 0,
    failing_sources: 0,
    dead_sources: 0,
    cooldown_skips_24h: 0,
  },
  bots: [],
})

const bots = computed(() => (Array.isArray(payload.value?.bots) ? payload.value.bots : []))
const overall = computed(() => payload.value?.overall || {})

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function rateLimitLabel(row) {
  const state = row?.rate_limit_state || {}
  if (state.limited) {
    return `LIMIT (${Number(state.retry_after_sec || 0)}s)`
  }
  const remaining = Number(state.remaining_attempts || 0)
  const max = Number(state.max_attempts || 0)
  if (max <= 0) return 'OFF'
  return `${remaining}/${max}`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await getBotOverview()
    payload.value = response?.data || payload.value
  } catch (e) {
    error.value = e?.response?.data?.message || 'Nacitanie bot overview zlyhalo.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <component
    :is="props.embedded ? 'section' : AdminPageShell"
    v-bind="props.embedded ? {} : { title: 'Bot Engine', subtitle: 'Pipeline dashboard za poslednych 24 hodin.' }"
    class="botSection"
  >
    <div v-if="props.embedded" class="embeddedHeader">
      <div>
        <h2 class="embeddedTitle">Overview</h2>
        <p class="embeddedSubtitle">Pipeline dashboard za poslednych 24 hodin.</p>
      </div>
      <button type="button" class="actionBtn" :disabled="loading" @click="load">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </div>

    <template v-if="!props.embedded" #right-actions>
      <button type="button" class="actionBtn" :disabled="loading" @click="load">
        {{ loading ? 'Nacitavam...' : 'Obnovit' }}
      </button>
    </template>

    <section class="cards">
      <article class="card metricCard">
        <p class="metricLabel">Active Sources</p>
        <p class="metricValue">{{ Number(overall.active_sources || 0) }}</p>
      </article>
      <article class="card metricCard">
        <p class="metricLabel">Failing Sources</p>
        <p class="metricValue">{{ Number(overall.failing_sources || 0) }}</p>
      </article>
      <article class="card metricCard">
        <p class="metricLabel">Dead Sources</p>
        <p class="metricValue">{{ Number(overall.dead_sources || 0) }}</p>
      </article>
      <article class="card metricCard">
        <p class="metricLabel">Cooldown Skips (24h)</p>
        <p class="metricValue">{{ Number(overall.cooldown_skips_24h || 0) }}</p>
      </article>
    </section>

    <section v-if="!props.embedded" class="card quickLinks">
      <RouterLink :to="{ name: 'admin.bots.sources' }" class="quickLink">Source Health</RouterLink>
      <RouterLink :to="{ name: 'admin.bots.schedules' }" class="quickLink">Schedules</RouterLink>
      <RouterLink :to="{ name: 'admin.bots.engine' }" class="quickLink">Engine Controls</RouterLink>
    </section>

    <section class="card">
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="!loading && bots.length === 0" class="muted">Zatial nie su dostupne bot metriky.</p>

      <div v-else class="tableWrap">
        <table class="table">
          <thead>
            <tr>
              <th>Bot</th>
              <th>Last activity</th>
              <th>Posts (24h)</th>
              <th>Duplicates (24h)</th>
              <th>Failures (24h)</th>
              <th>Rate limit</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in bots" :key="row.id">
              <td>
                <div class="name">{{ row.username || '-' }}</div>
                <div class="muted">{{ row.role || '-' }}</div>
              </td>
              <td>{{ formatDateTime(row.last_activity_at) }}</td>
              <td>{{ Number(row.posts_24h || 0) }}</td>
              <td>{{ Number(row.duplicates_24h || 0) }}</td>
              <td>{{ Number(row.errors_24h || 0) }}</td>
              <td>
                <span class="rateState" :class="{ limited: Boolean(row?.rate_limit_state?.limited) }">
                  {{ rateLimitLabel(row) }}
                </span>
              </td>
              <td>
                <RouterLink
                  class="activityLink"
                  :to="{ name: 'admin.bots.activity', query: { bot_identity: row.bot_identity || undefined } }"
                >
                  View activity
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </component>
</template>

<style scoped>
.botSection {
  display: grid;
  gap: 14px;
}

.embeddedHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.embeddedTitle {
  margin: 0 0 6px;
  font-size: 1.06rem;
  font-weight: 800;
}

.embeddedSubtitle {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.85rem;
}

.cards {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.72);
  padding: 14px;
}

.metricCard {
  display: grid;
  gap: 4px;
}

.metricLabel {
  margin: 0;
  font-size: 0.74rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.86);
}

.metricValue {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 800;
}

.quickLinks {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.quickLink {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 7px 11px;
  color: rgb(var(--color-surface-rgb) / 0.95);
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 700;
}

.quickLink:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
}

.tableWrap {
  width: 100%;
  overflow-x: auto;
}

.table {
  width: 100%;
  min-width: 800px;
  border-collapse: collapse;
}

.table th,
.table td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 9px 10px;
  text-align: left;
  font-size: 0.82rem;
}

.table th {
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-size: 0.72rem;
}

.name {
  font-weight: 700;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  margin: 0;
}

.rateState {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 0.72rem;
  font-weight: 700;
}

.rateState.limited {
  border-color: rgb(var(--color-danger-rgb) / 0.6);
  color: var(--color-danger);
}

.activityLink {
  color: var(--color-primary);
  font-weight: 700;
}

.error {
  color: var(--color-danger);
  margin: 0;
}

.actionBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  border-radius: 10px;
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
  padding: 7px 11px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}
</style>
