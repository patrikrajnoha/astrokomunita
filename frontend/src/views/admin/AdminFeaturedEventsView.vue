<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getEvents } from '@/services/api/admin/events'
import {
  applyFallbackAsFeatured,
  createFeaturedEvent,
  deleteFeaturedEvent,
  forceFeaturedEventsPopup,
  getFeaturedEvents,
  updateFeaturedEvent,
  updateFeaturedPopupSettings,
} from '@/services/api/admin/featuredEvents'

const loading = ref(false)
const saving = ref(false)
const forcing = ref(false)
const applyingFallback = ref(false)
const refreshingFallback = ref(false)
const error = ref('')
const success = ref('')
const featured = ref([])
const fallbackPreview = ref([])
const resolvedEvents = ref([])
const selectionMode = ref('fallback')
const selectedMonth = ref(currentMonthKey())
const settings = ref({ enabled: true, force_version: 0, force_at: null })
const maxItems = ref(10)
const candidateEvents = ref([])
const selectedEventId = ref('')
const calendarBundleUrl = ref('')

const activeCount = computed(() => featured.value.filter((item) => item.is_active).length)
const canAdd = computed(() => Number(selectedEventId.value) > 0 && !saving.value)
const modeBadgeText = computed(() => {
  return selectionMode.value === 'admin' ? 'Pouziva sa: Admin vyber' : 'Pouziva sa: Auto fallback'
})

const monthOptions = computed(() => {
  const current = currentMonthKey()
  const next = addMonths(current, 1)

  return [
    { value: current, label: `${formatMonthLabel(current)} (aktualny)` },
    { value: next, label: `${formatMonthLabel(next)} (dalsi)` },
  ]
})

async function load({ refreshFallback = false } = {}) {
  loading.value = true
  error.value = ''

  try {
    const [featuredRes, eventsRes] = await Promise.all([
      getFeaturedEvents({
        month: selectedMonth.value,
        refresh_fallback: refreshFallback ? 1 : 0,
      }),
      getEvents({ per_page: 50 }),
    ])

    const payload = featuredRes?.data || {}

    featured.value = Array.isArray(payload?.data) ? payload.data : []
    fallbackPreview.value = Array.isArray(payload?.fallback_preview) ? payload.fallback_preview : []
    resolvedEvents.value = Array.isArray(payload?.resolved_events) ? payload.resolved_events : []
    selectionMode.value = payload?.selection_mode || 'fallback'
    settings.value = payload?.settings || settings.value
    maxItems.value = Number(payload?.meta?.max_items || 10)
    calendarBundleUrl.value = payload?.calendar?.bundle_ics_url || ''

    const eventsPayload = eventsRes?.data?.data || []
    candidateEvents.value = Array.isArray(eventsPayload)
      ? eventsPayload.map((row) => ({ id: row.id, title: row.title }))
      : []
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to load featured events.'
  } finally {
    loading.value = false
  }
}

function eventLabel(eventId) {
  const row = candidateEvents.value.find((item) => Number(item.id) === Number(eventId))
  return row ? `${row.id} - ${row.title}` : String(eventId)
}

async function addFeaturedEvent() {
  if (!canAdd.value) return

  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await createFeaturedEvent({
      event_id: Number(selectedEventId.value),
      month: selectedMonth.value,
    })

    selectedEventId.value = ''
    success.value = 'Event bol pridany do popup zoznamu.'
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to add featured event.'
  } finally {
    saving.value = false
  }
}

async function moveItem(item, direction) {
  const next = Number(item.position) + direction
  if (next < 0) return

  saving.value = true
  error.value = ''
  try {
    await updateFeaturedEvent(item.id, { position: next })
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to reorder event.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(item, checked) {
  saving.value = true
  error.value = ''
  try {
    await updateFeaturedEvent(item.id, { is_active: checked })
    item.is_active = checked
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to update active state.'
  } finally {
    saving.value = false
  }
}

async function removeItem(item) {
  saving.value = true
  error.value = ''
  try {
    await deleteFeaturedEvent(item.id)
    await load()
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to remove event.'
  } finally {
    saving.value = false
  }
}

async function forceNow() {
  forcing.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await forceFeaturedEventsPopup()
    settings.value = {
      ...settings.value,
      force_version: Number(response?.data?.force_version || settings.value.force_version),
      force_at: response?.data?.force_at || settings.value.force_at,
    }
    success.value = 'Popup bol naplanovany pre vsetkych pouzivatelov.'
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to force popup.'
  } finally {
    forcing.value = false
  }
}

async function refreshFallbackPreview() {
  refreshingFallback.value = true
  error.value = ''
  try {
    await load({ refreshFallback: true })
  } finally {
    refreshingFallback.value = false
  }
}

async function useFallbackAsFeatured() {
  applyingFallback.value = true
  error.value = ''
  success.value = ''

  try {
    await applyFallbackAsFeatured({ month: selectedMonth.value })
    success.value = 'Fallback vyber bol ulozeny ako admin vyber pre zvoleny mesiac.'
    await load({ refreshFallback: true })
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to apply fallback as featured.'
  } finally {
    applyingFallback.value = false
  }
}

async function toggleEnabled(checked) {
  saving.value = true
  error.value = ''
  try {
    const response = await updateFeaturedPopupSettings({ enabled: checked })
    settings.value = response?.data?.data || settings.value
  } catch (fetchError) {
    error.value = fetchError?.response?.data?.message || fetchError?.userMessage || 'Failed to update popup setting.'
  } finally {
    saving.value = false
  }
}

function formatDateTime(value) {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'
  return parsed.toLocaleString('sk-SK', { dateStyle: 'medium', timeStyle: 'short' })
}

function onMonthChange() {
  load({ refreshFallback: true })
}

function currentMonthKey() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

function addMonths(monthKey, monthsToAdd) {
  const [yearRaw, monthRaw] = String(monthKey).split('-')
  const year = Number(yearRaw)
  const month = Number(monthRaw)
  const date = new Date(Date.UTC(year, month - 1 + monthsToAdd, 1))

  return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`
}

function formatMonthLabel(monthKey) {
  const parsed = new Date(`${monthKey}-01T00:00:00Z`)
  if (Number.isNaN(parsed.getTime())) return monthKey
  return parsed.toLocaleDateString('sk-SK', { month: 'long', year: 'numeric' })
}

onMounted(load)
</script>

<template>
  <AdminPageShell title="Featured events popup" subtitle="Manage events for the monthly Mark your calendar popup.">
    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-if="success" class="alert alert-success">{{ success }}</div>

    <section class="card">
      <div class="monthBar">
        <div>
          <p class="muted">Month</p>
          <select v-model="selectedMonth" :disabled="loading || saving" @change="onMonthChange">
            <option v-for="option in monthOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
        <span class="modeBadge" :class="selectionMode === 'admin' ? 'modeAdmin' : 'modeFallback'">{{ modeBadgeText }}</span>
      </div>

      <div class="ctaRow">
        <button type="button" class="forceBtn" :disabled="refreshingFallback || loading" @click="refreshFallbackPreview">
          {{ refreshingFallback ? 'Loading...' : 'Generate fallback preview' }}
        </button>
        <button type="button" class="forceBtn" :disabled="applyingFallback || loading" @click="useFallbackAsFeatured">
          {{ applyingFallback ? 'Applying...' : 'Use fallback as featured' }}
        </button>
      </div>

      <p v-if="calendarBundleUrl" class="muted">
        Bundle ICS:
        <a :href="calendarBundleUrl" target="_blank" rel="noopener">Download featured bundle .ics</a>
      </p>
    </section>

    <section class="card">
      <div class="cardHead">
        <h3>Popup settings</h3>
        <label class="toggleLabel">
          <input
            :checked="Boolean(settings.enabled)"
            type="checkbox"
            :disabled="saving"
            @change="toggleEnabled($event.target.checked)"
          />
          <span>{{ settings.enabled ? 'Enabled' : 'Disabled' }}</span>
        </label>
      </div>
      <p class="muted">Force version: {{ settings.force_version || 0 }}</p>
      <p class="muted">Last forced at: {{ formatDateTime(settings.force_at) }}</p>
      <button type="button" class="forceBtn" :disabled="forcing || loading" @click="forceNow">
        {{ forcing ? 'Sending...' : 'Show popup to everyone now' }}
      </button>
    </section>

    <section class="card">
      <div class="cardHead">
        <h3>Admin selection</h3>
        <p class="counter">{{ activeCount }}/{{ maxItems }}</p>
      </div>

      <div class="addRow">
        <label for="event-select" class="srOnly">Event ID</label>
        <select id="event-select" v-model="selectedEventId" :disabled="saving || loading">
          <option value="">Select event...</option>
          <option v-for="event in candidateEvents" :key="event.id" :value="String(event.id)">
            {{ event.id }} - {{ event.title }}
          </option>
        </select>
        <button type="button" :disabled="!canAdd" @click="addFeaturedEvent">Add</button>
      </div>

      <div v-if="loading" class="muted">Loading...</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>Position</th>
            <th>Event</th>
            <th>Active</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in featured" :key="item.id">
            <td>{{ item.position }}</td>
            <td>{{ item.event?.title || eventLabel(item.event_id) }}</td>
            <td>
              <label class="toggleLabel">
                <input
                  :checked="item.is_active"
                  type="checkbox"
                  :disabled="saving"
                  @change="toggleActive(item, $event.target.checked)"
                />
                <span>{{ item.is_active ? 'on' : 'off' }}</span>
              </label>
            </td>
            <td class="actions">
              <button type="button" :disabled="saving || item.position <= 0" @click="moveItem(item, -1)">Up</button>
              <button type="button" :disabled="saving" @click="moveItem(item, 1)">Down</button>
              <button type="button" class="danger" :disabled="saving" @click="removeItem(item)">Delete</button>
            </td>
          </tr>
          <tr v-if="featured.length === 0">
            <td colspan="4" class="muted">No featured events yet.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card">
      <div class="cardHead">
        <h3>Auto fallback preview</h3>
        <p class="muted">Read-only</p>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Score</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in fallbackPreview" :key="`fallback-${item.id}`">
            <td>{{ item.title }}</td>
            <td>{{ formatDateTime(item.start_at) }}</td>
            <td>{{ item.fallback_score ?? '-' }}</td>
          </tr>
          <tr v-if="fallbackPreview.length === 0">
            <td colspan="3" class="muted">Fallback found no events for this month.</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card">
      <div class="cardHead">
        <h3>What popup will show</h3>
        <p class="muted">{{ selectionMode === 'admin' ? 'Admin selection' : 'Auto fallback' }}</p>
      </div>

      <ul class="resolvedList">
        <li v-for="item in resolvedEvents" :key="`resolved-${item.id}`" class="resolvedItem">
          <span>
            <strong>{{ item.title }}</strong>
            <small class="muted">{{ formatDateTime(item.start_at) }}</small>
          </span>
          <span class="resolvedActions">
            <a v-if="item.google_calendar_url" :href="item.google_calendar_url" target="_blank" rel="noopener">Google</a>
            <a v-if="item.ics_url" :href="item.ics_url" target="_blank" rel="noopener">ICS</a>
          </span>
        </li>
        <li v-if="resolvedEvents.length === 0" class="muted">No events to display for this month.</li>
      </ul>
    </section>
  </AdminPageShell>
</template>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 12px;
  padding: 14px;
  background: rgb(var(--color-bg-rgb) / 0.65);
}

.card + .card {
  margin-top: 12px;
}

.cardHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.monthBar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.monthBar select {
  min-width: 220px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 8px 10px;
  background: transparent;
  color: inherit;
}

.modeBadge {
  border-radius: 999px;
  padding: 5px 10px;
  font-size: 0.8rem;
  font-weight: 700;
}

.modeAdmin {
  border: 1px solid rgb(34 197 94 / 0.45);
  background: rgb(34 197 94 / 0.12);
}

.modeFallback {
  border: 1px solid rgb(245 158 11 / 0.45);
  background: rgb(245 158 11 / 0.12);
}

.ctaRow {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin: 12px 0;
}

.counter {
  margin: 0;
  font-weight: 700;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.addRow {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 10px 0 12px;
}

.addRow select {
  min-width: 260px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 10px;
  padding: 8px 10px;
  background: transparent;
  color: inherit;
}

.addRow button,
.actions button,
.forceBtn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  border-radius: 10px;
  padding: 7px 11px;
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: inherit;
  cursor: pointer;
}

.actions button.danger {
  border-color: rgb(239 68 68 / 0.45);
  background: rgb(239 68 68 / 0.12);
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table th,
.table td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  padding: 8px 6px;
  text-align: left;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.toggleLabel {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.resolvedList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}

.resolvedItem {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  padding: 8px 10px;
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.resolvedActions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.alert {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 10px;
}

.alert-error {
  border: 1px solid rgb(239 68 68 / 0.35);
  background: rgb(239 68 68 / 0.1);
  color: rgb(185 28 28);
}

.alert-success {
  border: 1px solid rgb(34 197 94 / 0.35);
  background: rgb(34 197 94 / 0.12);
  color: rgb(22 101 52);
}

.srOnly {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}
</style>
