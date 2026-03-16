<template>
  <section class="card panel moonEventsCard">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <AsyncState
      v-if="loading"
      mode="loading"
      title="Načítavam lunárne udalosti"
      loading-style="skeleton"
      :skeleton-rows="4"
      compact
    />

    <AsyncState
      v-else-if="error"
      mode="error"
      title="Nepodarilo sa načítať"
      :message="error"
      action-label="Skúsiť znova"
      compact
      @action="fetchEvents"
    />

    <AsyncState
      v-else-if="!events.length"
      mode="empty"
      title="Žiadne lunárne udalosti"
      message="Pre tento rok nie sú dostupné špeciálne lunárne udalosti."
      compact
    />

    <section v-else class="eventsPanel">
      <div class="eventsTitle">Špeciálne lunárne udalosti v {{ yearLabel }}</div>

      <ul class="eventsList" role="list" aria-label="Špeciálne lunárne udalosti">
        <li
          v-for="event in events"
          :key="`${event.key}-${event.at || event.label}`"
          class="eventRow"
        >
          <span class="eventLabel">{{ event.label }}</span>
          <span v-if="formatEventDateTime(event)" class="eventWhen">
            : {{ formatEventDateTime(event) }}
          </span>
          <span v-if="event.note" class="eventNote"> ({{ event.note }})</span>
        </li>
      </ul>
    </section>
  </section>
</template>

<script>
import { computed, onMounted, ref } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import { getMoonEventsWidget } from '@/services/widgets'

const DATE_FORMATTER = new Intl.DateTimeFormat('sk-SK', {
  day: 'numeric',
  month: 'long',
  timeZone: 'UTC',
})

export default {
  name: 'MoonEventsWidget',
  components: {
    AsyncState,
  },
  props: {
    title: {
      type: String,
      default: 'Lunárne udalosti',
    },
    lat: {
      type: [Number, String],
      default: null,
    },
    lon: {
      type: [Number, String],
      default: null,
    },
    tz: {
      type: String,
      default: '',
    },
    date: {
      type: String,
      default: '',
    },
  },
  setup(props) {
    const events = ref([])
    const loading = ref(true)
    const error = ref('')
    const year = ref(new Date().getFullYear())

    const buildQuery = () => {
      const query = {}
      const lat = Number(props.lat)
      const lon = Number(props.lon)
      const tz = String(props.tz || '').trim()
      const requestedYear = resolveYearFromDate(props.date)

      if (Number.isFinite(lat)) {
        query.lat = lat
      }

      if (Number.isFinite(lon)) {
        query.lon = lon
      }

      if (tz) {
        query.tz = tz
      }

      if (Number.isInteger(requestedYear)) {
        query.year = requestedYear
      }

      return query
    }

    const fetchEvents = async () => {
      loading.value = true
      error.value = ''

      try {
        const payload = await getMoonEventsWidget(buildQuery())
        events.value = normalizeEvents(payload?.events)
        year.value = Number.isFinite(Number(payload?.year))
          ? Number(payload.year)
          : resolveYearFromDate(props.date, new Date().getFullYear())
      } catch (err) {
        events.value = []
        error.value =
          err?.response?.data?.message
          || err?.message
          || 'Skús obnoviť widget neskôr.'
      } finally {
        loading.value = false
      }
    }

    const yearLabel = computed(() => {
      return Number.isFinite(Number(year.value))
        ? Number(year.value)
        : new Date().getFullYear()
    })

    const formatEventDateTime = (event) => {
      const dateLabel = formatDate(event?.date || extractIsoDate(event?.at || ''))
      const timeLabel = sanitizeTime(event?.time || extractIsoTime(event?.at || ''))

      if (dateLabel && timeLabel) return `${dateLabel}, ${timeLabel}`
      return dateLabel || timeLabel || ''
    }

    onMounted(() => {
      fetchEvents()
    })

    return {
      events,
      loading,
      error,
      yearLabel,
      fetchEvents,
      formatEventDateTime,
    }
  },
}

function normalizeEvents(rows) {
  const source = Array.isArray(rows) ? rows : []

  return source
    .map((item) => {
      const label = String(item?.label || '').trim()
      const at = String(item?.at || '').trim()
      const date = sanitizeDate(String(item?.date || '').trim())
        || sanitizeDate(extractIsoDate(at))
      const time = sanitizeTime(String(item?.time || '').trim())
        || sanitizeTime(extractIsoTime(at))

      return {
        key: String(item?.key || '').trim(),
        label,
        note: String(item?.note || '').trim(),
        at,
        date,
        time,
      }
    })
    .filter((item) => item.label !== '')
}

function sanitizeDate(value) {
  const text = String(value || '').trim()
  return /^\d{4}-\d{2}-\d{2}$/.test(text) ? text : ''
}

function sanitizeTime(value) {
  const text = String(value || '').trim()
  return /^\d{2}:\d{2}$/.test(text) ? text : ''
}

function extractIsoDate(value) {
  const text = String(value || '').trim()
  const match = text.match(/^(\d{4}-\d{2}-\d{2})T/)
  return match?.[1] || ''
}

function extractIsoTime(value) {
  const text = String(value || '').trim()
  const match = text.match(/T(\d{2}:\d{2})/)
  return match?.[1] || ''
}

function resolveYearFromDate(value, fallbackYear = new Date().getFullYear()) {
  const text = String(value || '').trim()
  const match = text.match(/^(\d{4})-\d{2}-\d{2}$/)
  if (!match) return fallbackYear

  const year = Number(match[1])
  if (!Number.isInteger(year) || year < 1700 || year > 2100) {
    return fallbackYear
  }

  return year
}

function formatDate(value) {
  const text = String(value || '').trim()
  if (!text) return ''

  const match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!match) return ''

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return ''

  try {
    return DATE_FORMATTER.format(new Date(Date.UTC(year, month - 1, day)))
  } catch {
    return ''
  }
}
</script>

<style scoped>
.card {
  position: relative;
  border: 0;
  background: transparent;
  border-radius: 0;
  padding: 0;
  overflow: visible;
}

.panel {
  display: grid;
  gap: 0.24rem;
  min-width: 0;
}

.panelTitle {
  margin: 0;
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
}

.eventsPanel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.15);
  padding: 0.44rem 0.5rem;
  display: grid;
  gap: 0.28rem;
}

.eventsTitle {
  margin: 0;
  color: var(--color-surface);
  font-weight: 800;
  font-size: 0.76rem;
  line-height: 1.2;
}

.eventsList {
  margin: 0;
  padding: 0 0 0 1rem;
  display: grid;
  gap: 0.2rem;
}

.eventRow {
  color: var(--color-text-secondary);
  font-size: 0.71rem;
  line-height: 1.25;
}

.eventLabel {
  color: var(--color-surface);
  font-weight: 700;
}

.eventWhen {
  color: var(--color-text-secondary);
}

.eventNote {
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
}
</style>
