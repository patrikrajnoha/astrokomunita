<template>
  <section class="card panel">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <div v-if="loading" class="event-list">
      <div v-for="i in 3" :key="i" class="skeleton-row">
        <div class="skeleton sk-name"></div>
        <div class="skeleton sk-date"></div>
      </div>
    </div>

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
      v-else-if="!upcomingEvents.length"
      mode="empty"
      title="Žiadne blížiace sa udalosti"
      message="Pre tento rok nie sú ďalšie špeciálne lunárne udalosti."
      compact
    />

    <div v-else class="event-list">
      <article
        v-for="event in upcomingEvents"
        :key="`${event.key}-${event.at || event.label}`"
        class="event-row"
      >
        <div class="event-row__body">
          <div class="event-name">
            <span class="event-icon" aria-hidden="true">{{ eventIcon(event.key) }}</span>
            <span class="event-label">{{ event.label }}</span>
          </div>
          <div v-if="formatCompactDateTime(event)" class="event-when">
            {{ formatCompactDateTime(event) }}
          </div>
        </div>
        <svg class="row-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </article>
    </div>
  </section>
</template>

<script>
import { computed, onMounted, ref } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import { getMoonEventsWidget } from '@/services/widgets'

const SHORT_DATE_FORMATTER = new Intl.DateTimeFormat('sk-SK', {
  day: 'numeric',
  month: 'short',
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
      default: 'Najbližšie lunárne udalosti',
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

    const buildQuery = () => {
      const query = {}
      const lat = Number(props.lat)
      const lon = Number(props.lon)
      const tz = String(props.tz || '').trim()
      const requestedYear = resolveYearFromDate(props.date)

      if (Number.isFinite(lat)) query.lat = lat
      if (Number.isFinite(lon)) query.lon = lon
      if (tz) query.tz = tz
      if (Number.isInteger(requestedYear)) query.year = requestedYear

      return query
    }

    const fetchEvents = async () => {
      loading.value = true
      error.value = ''

      try {
        const payload = await getMoonEventsWidget(buildQuery())
        events.value = normalizeEvents(payload?.events)
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

    const upcomingEvents = computed(() => {
      const todayStr = new Date().toISOString().slice(0, 10)
      return events.value
        .filter((e) => e.date && e.date >= todayStr)
        .slice(0, 3)
    })

    const formatCompactDateTime = (event) => {
      const datePart = formatShortDate(event?.date || extractIsoDate(event?.at || ''))
      const timePart = sanitizeTime(event?.time || extractIsoTime(event?.at || ''))
      if (datePart && timePart) return `${datePart} · ${timePart}`
      return datePart || timePart || ''
    }

    onMounted(() => {
      fetchEvents()
    })

    return {
      upcomingEvents,
      loading,
      error,
      fetchEvents,
      formatCompactDateTime,
      eventIcon,
    }
  },
}

function eventIcon(key) {
  const k = String(key || '').toLowerCase()
  if (k.includes('black') || (k.includes('new') && !k.includes('blue'))) return '🌑'
  if (k.includes('blue')) return '🌕'
  if (k.includes('full') || k.includes('super') || k.includes('micro') || k.includes('wolf') || k.includes('harvest')) return '🌕'
  return '🌙'
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
  if (!Number.isInteger(year) || year < 1700 || year > 2100) return fallbackYear

  return year
}

function formatShortDate(value) {
  const text = String(value || '').trim()
  if (!text) return ''

  const match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!match) return ''

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return ''

  try {
    return SHORT_DATE_FORMATTER.format(new Date(Date.UTC(year, month - 1, day)))
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
  gap: 0.5rem;
  min-width: 0;
}

.panelTitle {
  margin: 0;
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

/* ── Event list ── */
.event-list {
  display: grid;
  gap: 0.3rem;
}

/* ── Event row ── */
.event-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  background: rgb(var(--color-bg-rgb) / 0.18);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
  min-width: 0;
}

.event-row:hover {
  background: rgb(var(--color-primary-rgb) / 0.07);
  border-color: rgb(var(--color-primary-rgb) / 0.22);
}

.event-row__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.18rem;
}

/* ── Name line ── */
.event-name {
  display: flex;
  align-items: center;
  gap: 0.36rem;
  min-width: 0;
}

.event-icon {
  flex-shrink: 0;
  font-size: 0.8rem;
  line-height: 1;
}

.event-label {
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 700;
  line-height: 1.22;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Date / time line ── */
.event-when {
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.25;
}

/* ── Chevron ── */
.row-chevron {
  flex-shrink: 0;
  width: 0.7rem;
  height: 0.7rem;
  color: var(--color-text-secondary);
  opacity: 0.45;
  transition: opacity 0.15s ease;
}

.event-row:hover .row-chevron {
  opacity: 0.75;
}

/* ── Skeleton loading ── */
.skeleton-row {
  display: grid;
  gap: 0.22rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.08);
}

.skeleton {
  border-radius: 0.25rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.07),
    rgb(var(--color-text-secondary-rgb) / 0.14),
    rgb(var(--color-text-secondary-rgb) / 0.07)
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.sk-name { height: 0.75rem; width: 68%; }
.sk-date { height: 0.6rem;  width: 45%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
