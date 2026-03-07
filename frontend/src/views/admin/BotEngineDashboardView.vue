<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import {
  deleteAllBotPosts,
  getBotOverview,
  getBotPostRetentionSettings,
  runBotPostRetentionCleanup,
  updateBotPostRetentionSettings,
} from '@/services/api/admin/bots'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const loading = ref(false)
const error = ref('')
const retentionLoading = ref(false)
const retentionSaving = ref(false)
const retentionRunning = ref(false)
const deletingAllPosts = ref(false)
const retention = ref({
  enabled: false,
  auto_delete_after_hours: 48,
  allowed_hours: [24, 48, 72, 168],
  scheduled_frequency: 'hourly',
})
const retentionForm = ref({
  enabled: false,
  auto_delete_after_hours: 48,
})
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
const { confirm } = useConfirm()
const toast = useToast()

const bots = computed(() => (Array.isArray(payload.value?.bots) ? payload.value.bots : []))
const overall = computed(() => payload.value?.overall || {})
const retentionAllowedHours = computed(() => {
  const values = Array.isArray(retention.value?.allowed_hours) ? retention.value.allowed_hours : []
  return values.filter((value) => Number.isInteger(Number(value)) && Number(value) > 0)
})
const retentionStatusLabel = computed(() => (retentionForm.value.enabled ? 'Zapnute' : 'Vypnute'))

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

async function loadRetentionSettings() {
  retentionLoading.value = true
  try {
    const response = await getBotPostRetentionSettings()
    const data = response?.data?.data || {}
    const allowedHours = Array.isArray(data?.allowed_hours) && data.allowed_hours.length > 0
      ? data.allowed_hours
      : [24, 48, 72, 168]
    const selectedHours = Number(data?.auto_delete_after_hours || allowedHours[0] || 48)

    retention.value = {
      enabled: Boolean(data?.enabled),
      auto_delete_after_hours: selectedHours,
      allowed_hours: allowedHours,
      scheduled_frequency: String(data?.scheduled_frequency || 'hourly'),
    }
    retentionForm.value = {
      enabled: Boolean(data?.enabled),
      auto_delete_after_hours: selectedHours,
    }
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Nacitanie retention nastaveni zlyhalo.')
  } finally {
    retentionLoading.value = false
  }
}

async function saveRetentionSettings() {
  if (retentionSaving.value) return

  retentionSaving.value = true
  try {
    const response = await updateBotPostRetentionSettings({
      enabled: Boolean(retentionForm.value.enabled),
      auto_delete_after_hours: Number(retentionForm.value.auto_delete_after_hours || 0),
    })
    const data = response?.data?.data || {}
    retention.value = {
      enabled: Boolean(data?.enabled),
      auto_delete_after_hours: Number(data?.auto_delete_after_hours || retentionForm.value.auto_delete_after_hours || 48),
      allowed_hours: Array.isArray(data?.allowed_hours) && data.allowed_hours.length > 0
        ? data.allowed_hours
        : retentionAllowedHours.value,
      scheduled_frequency: String(data?.scheduled_frequency || 'hourly'),
    }
    retentionForm.value = {
      enabled: retention.value.enabled,
      auto_delete_after_hours: retention.value.auto_delete_after_hours,
    }
    toast.success('Nastavenie auto mazania bot prispevkov bolo ulozene.')
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Ulozenie retention nastaveni zlyhalo.')
  } finally {
    retentionSaving.value = false
  }
}

async function deleteAllPublishedBotPosts() {
  if (deletingAllPosts.value) return

  const approved = await confirm({
    title: 'Vymazat bot prispevky',
    message: 'Naozaj vymazat publikovane bot prispevky?',
    confirmText: 'Vymazat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!approved) return

  deletingAllPosts.value = true
  try {
    const response = await deleteAllBotPosts({})
    const result = response?.data || {}
    toast.success(
      `Vymazane posty: ${Number(result.deleted_posts || 0)} | bez postu: ${Number(result.missing_posts || 0)} | chyby: ${Number(result.failed_items || 0)}.`,
    )
    await Promise.all([load(), loadRetentionSettings()])
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Mazanie bot prispevkov zlyhalo.')
  } finally {
    deletingAllPosts.value = false
  }
}

async function runCleanupNow() {
  if (retentionRunning.value) return

  const approved = await confirm({
    title: 'Spustit cleanup',
    message: 'Spustit okamzite vymazanie bot prispevkov podla retention pravidla?',
    confirmText: 'Spustit',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!approved) return

  retentionRunning.value = true
  try {
    const response = await runBotPostRetentionCleanup({ limit: 200 })
    const result = response?.data?.data || {}
    toast.success(
      `Cleanup hotovy: vymazane ${Number(result.deleted_posts || 0)} posty, chyby ${Number(result.failed_items || 0)}.`,
    )
    await load()
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Retention cleanup zlyhal.')
  } finally {
    retentionRunning.value = false
  }
}

onMounted(() => {
  void load()
  void loadRetentionSettings()
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

    <section class="card retentionCard">
      <div class="retentionHead">
        <div>
          <h3>Bot Post Cleanup</h3>
          <p class="muted">Automaticke mazanie bot prispevkov podla casovaca.</p>
        </div>
        <span class="retentionStatus" :class="{ 'retentionStatus--on': retentionForm.enabled }">
          {{ retentionStatusLabel }}
        </span>
      </div>

      <div class="retentionGrid">
        <label class="retentionField retentionField--toggle">
          <input
            v-model="retentionForm.enabled"
            type="checkbox"
            :disabled="retentionLoading || retentionSaving"
          />
          <span>Zapnut auto mazanie</span>
        </label>

        <label class="retentionField">
          <span>Zmazat po</span>
          <select
            v-model.number="retentionForm.auto_delete_after_hours"
            :disabled="retentionLoading || retentionSaving || !retentionForm.enabled"
          >
            <option v-for="hours in retentionAllowedHours" :key="`retention-${hours}`" :value="Number(hours)">
              {{ Number(hours) }} h
            </option>
          </select>
        </label>

        <button
          type="button"
          class="actionBtn"
          :disabled="retentionLoading || retentionSaving"
          @click="saveRetentionSettings"
        >
          {{ retentionSaving ? 'Ukladam...' : 'Ulozit' }}
        </button>

        <button
          type="button"
          class="dangerBtn"
          :disabled="deletingAllPosts"
          @click="deleteAllPublishedBotPosts"
        >
          {{ deletingAllPosts ? 'Mazem...' : 'Vymazat bot prispevky' }}
        </button>

        <button
          type="button"
          class="ghostActionBtn"
          :disabled="retentionRunning"
          @click="runCleanupNow"
        >
          {{ retentionRunning ? 'Spustam...' : 'Spustit cleanup teraz' }}
        </button>
      </div>

      <p class="muted retentionHint">
        Scheduler bezi {{ retention.scheduled_frequency || 'hourly' }}. Cleanup maze iba prispevky starsie ako zvoleny limit.
      </p>
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

.retentionCard {
  display: grid;
  gap: 10px;
}

.retentionHead {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  flex-wrap: wrap;
}

.retentionHead h3 {
  margin: 0 0 4px;
  font-size: 0.98rem;
}

.retentionStatus {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.24);
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 0.75rem;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.retentionStatus--on {
  border-color: rgb(var(--color-success-rgb) / 0.55);
  color: var(--color-success);
}

.retentionGrid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 10px;
  align-items: end;
}

.retentionField {
  display: grid;
  gap: 5px;
}

.retentionField span {
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.retentionField select {
  min-height: 36px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.26);
  background: rgb(var(--color-bg-rgb) / 0.36);
  color: var(--color-text-primary);
  padding: 0 10px;
}

.retentionField--toggle {
  min-height: 36px;
  display: inline-flex;
  gap: 8px;
  align-items: center;
}

.retentionField--toggle input {
  width: 16px;
  height: 16px;
}

.retentionHint {
  margin: 0;
  font-size: 0.8rem;
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

.dangerBtn,
.ghostActionBtn {
  border-radius: 10px;
  padding: 7px 11px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

.dangerBtn {
  border: 1px solid rgb(var(--color-danger-rgb) / 0.55);
  background: rgb(var(--color-danger-rgb) / 0.16);
  color: var(--color-text-primary);
}

.ghostActionBtn {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.26);
  background: rgb(var(--color-bg-rgb) / 0.3);
  color: var(--color-text-primary);
}
</style>
