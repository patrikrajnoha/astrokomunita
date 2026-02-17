<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getEvents } from '@/services/api/admin/events'
import {
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
const error = ref('')
const success = ref('')
const featured = ref([])
const settings = ref({ enabled: true, force_version: 0, force_at: null })
const maxItems = ref(10)
const candidateEvents = ref([])
const selectedEventId = ref('')

const activeCount = computed(() => featured.value.filter((item) => item.is_active).length)
const canAdd = computed(() => Number(selectedEventId.value) > 0 && !saving.value)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [featuredRes, eventsRes] = await Promise.all([
      getFeaturedEvents(),
      getEvents({ per_page: 50 }),
    ])

    featured.value = Array.isArray(featuredRes?.data?.data) ? featuredRes.data.data : []
    settings.value = featuredRes?.data?.settings || settings.value
    maxItems.value = Number(featuredRes?.data?.meta?.max_items || 10)

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

onMounted(load)
</script>

<template>
  <AdminPageShell title="Featured events popup" subtitle="Manage events for the monthly Mark your calendar popup.">
    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-if="success" class="alert alert-success">{{ success }}</div>

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
        <h3>Top events</h3>
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

