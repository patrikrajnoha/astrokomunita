<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
import { getEvents } from '@/services/api/admin/events'
import {
  getNewsletterPreview,
  getNewsletterRuns,
  sendNewsletter,
  updateNewsletterFeaturedEvents,
} from '@/services/api/admin/newsletter'

const loading = ref(false)
const savingSelection = ref(false)
const sending = ref(false)
const error = ref('')
const success = ref('')

const preview = ref(null)
const runs = ref([])
const selectedEventIds = ref([])
const candidateEvents = ref([])
const maxFeaturedEvents = ref(10)

const sendOptions = reactive({
  force: false,
  dry_run: false,
})

const selectedCount = computed(() => selectedEventIds.value.length)
const canSaveSelection = computed(
  () =>
    !loading.value &&
    !savingSelection.value &&
    selectedCount.value <= maxFeaturedEvents.value,
)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [previewRes, runsRes, eventsRes] = await Promise.all([
      getNewsletterPreview(),
      getNewsletterRuns({ per_page: 20 }),
      getEvents({ per_page: 100 }),
    ])

    preview.value = previewRes?.data?.data || null
    maxFeaturedEvents.value = Number(previewRes?.data?.meta?.max_featured_events || 10)
    selectedEventIds.value = Array.isArray(preview.value?.top_events)
      ? preview.value.top_events
          .map((row) => Number(row?.id || 0))
          .filter((id) => id > 0)
      : []

    runs.value = Array.isArray(runsRes?.data?.data) ? runsRes.data.data : []
    const eventsPayload = eventsRes?.data?.data || []
    candidateEvents.value = Array.isArray(eventsPayload)
      ? eventsPayload.map((item) => ({
          id: Number(item.id),
          title: item.title,
          start_at: item.start_at,
        }))
      : []
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Failed to load newsletter data.'
  } finally {
    loading.value = false
  }
}

function isSelected(eventId) {
  return selectedEventIds.value.includes(Number(eventId))
}

function toggleSelected(eventId, checked) {
  const id = Number(eventId)
  if (id <= 0) return

  if (checked) {
    if (selectedEventIds.value.includes(id)) return
    if (selectedEventIds.value.length >= maxFeaturedEvents.value) return
    selectedEventIds.value = [...selectedEventIds.value, id]
    return
  }

  selectedEventIds.value = selectedEventIds.value.filter((value) => value !== id)
}

async function saveFeaturedEvents() {
  if (!canSaveSelection.value) return

  savingSelection.value = true
  error.value = ''
  success.value = ''
  try {
    await updateNewsletterFeaturedEvents({
      event_ids: selectedEventIds.value,
    })
    success.value = 'Featured events saved.'
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Failed to save featured events.'
  } finally {
    savingSelection.value = false
  }
}

async function triggerSend() {
  if (sending.value) return

  sending.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await sendNewsletter({
      force: Boolean(sendOptions.force),
      dry_run: Boolean(sendOptions.dry_run),
    })

    const reason = response?.data?.reason || 'created'
    const runId = response?.data?.data?.id
    success.value = runId
      ? `Newsletter run ${runId} accepted (${reason}).`
      : `Newsletter action completed (${reason}).`

    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || e?.userMessage || 'Failed to trigger newsletter send.'
  } finally {
    sending.value = false
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
  <AdminPageShell
    title="Newsletter"
    subtitle="Curate weekly highlights, preview payload, and trigger queued newsletter runs."
  >
    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-if="success" class="alert alert-success">{{ success }}</div>

    <section class="card">
      <div class="cardHead">
        <h3>Featured events for next week</h3>
        <p class="counter">{{ selectedCount }}/{{ maxFeaturedEvents }}</p>
      </div>
      <p class="muted">Select up to {{ maxFeaturedEvents }} events for "Top udalosti buduceho tyzdna".</p>

      <div class="eventsGrid">
        <label
          v-for="event in candidateEvents"
          :key="event.id"
          class="eventOption"
          :class="{ active: isSelected(event.id) }"
        >
          <input
            type="checkbox"
            :checked="isSelected(event.id)"
            :disabled="savingSelection || (!isSelected(event.id) && selectedCount >= maxFeaturedEvents)"
            @change="toggleSelected(event.id, $event.target.checked)"
          />
          <span class="eventTitle">{{ event.title }}</span>
          <span class="eventDate">{{ formatDateTime(event.start_at) }}</span>
        </label>
      </div>

      <div class="actions">
        <button type="button" :disabled="!canSaveSelection" @click="saveFeaturedEvents">
          {{ savingSelection ? 'Saving...' : 'Save featured events' }}
        </button>
      </div>
    </section>

    <section class="card">
      <h3>Preview payload</h3>
      <p v-if="loading" class="muted">Loading preview...</p>
      <template v-else>
        <p class="muted">
          Week: {{ preview?.week?.start || '-' }} - {{ preview?.week?.end || '-' }}
        </p>

        <h4>Top events</h4>
        <ul>
          <li v-for="event in preview?.top_events || []" :key="event.id">
            {{ event.title }}
          </li>
        </ul>

        <h4>Top articles</h4>
        <ul>
          <li v-for="article in preview?.top_articles || []" :key="article.id">
            {{ article.title }} ({{ article.views }})
          </li>
        </ul>

        <h4>Astronomical tip</h4>
        <p class="muted">{{ preview?.astronomical_tip || '-' }}</p>
      </template>
    </section>

    <section class="card">
      <h3>Manual send</h3>
      <div class="toggles">
        <label class="toggleLabel">
          <input v-model="sendOptions.force" type="checkbox" />
          <span>Force send</span>
        </label>
        <label class="toggleLabel">
          <input v-model="sendOptions.dry_run" type="checkbox" />
          <span>Dry-run</span>
        </label>
      </div>
      <button type="button" :disabled="sending || loading" @click="triggerSend">
        {{ sending ? 'Sending...' : 'Trigger newsletter run' }}
      </button>
    </section>

    <section class="card">
      <h3>Past runs</h3>
      <div v-if="runs.length === 0" class="muted">No runs yet.</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Week</th>
            <th>Status</th>
            <th>Total</th>
            <th>Sent</th>
            <th>Failed</th>
            <th>Flags</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="run in runs" :key="run.id">
            <td>{{ run.id }}</td>
            <td>{{ run.week_start_date }}</td>
            <td>{{ run.status }}</td>
            <td>{{ run.total_recipients }}</td>
            <td>{{ run.sent_count }}</td>
            <td>{{ run.failed_count }}</td>
            <td>
              <span v-if="run.forced">forced </span>
              <span v-if="run.dry_run">dry-run</span>
            </td>
            <td>{{ formatDateTime(run.created_at) }}</td>
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

.eventsGrid {
  margin-top: 10px;
  display: grid;
  gap: 8px;
  max-height: 260px;
  overflow: auto;
}

.eventOption {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 8px 10px;
  padding: 8px 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 10px;
}

.eventOption.active {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.eventTitle {
  font-weight: 600;
}

.eventDate {
  grid-column: 2 / 3;
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
}

.actions,
.toggles {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 12px;
}

.toggleLabel {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

button {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.35);
  border-radius: 10px;
  padding: 8px 12px;
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: inherit;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 8px;
}

.table th,
.table td {
  border-bottom: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  padding: 8px 6px;
  text-align: left;
  font-size: 13px;
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
</style>
