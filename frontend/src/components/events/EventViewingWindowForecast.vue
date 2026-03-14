<template>
  <section class="forecastStrip" aria-live="polite">
    <div class="forecastStrip__top">
      <p class="forecastStrip__title">Okno pozorovania</p>
      <span v-if="windowLabel" class="forecastStrip__window">{{ windowLabel }}</span>
      <span
        v-if="summaryLabel"
        class="forecastStrip__badge"
        :class="`forecastStrip__badge--${summary?.rating || 'avg'}`"
      >
        {{ summaryLabel }}
      </span>
    </div>

    <div v-if="loading" class="forecastStrip__loading" aria-hidden="true">
      <span class="forecastStrip__loadingLine forecastStrip__loadingLine--wide"></span>
      <span class="forecastStrip__loadingLine"></span>
    </div>

    <p v-else-if="showMissingLocation" class="forecastStrip__empty">
      Predpoved zobrazime po ulozeni polohy.
    </p>

    <div v-else-if="summary" class="forecastStrip__metrics" role="list">
      <span class="forecastMetric" role="listitem">
        <span class="forecastMetric__icon" aria-hidden="true">&#9729;</span>
        <span>{{ formatPercent(summary.clouds_pct) }}</span>
      </span>
      <span class="forecastMetric" role="listitem">
        <span class="forecastMetric__icon" aria-hidden="true">&#127788;</span>
        <span>{{ formatMeasure(summary.wind_ms, 'm/s') }}</span>
      </span>
      <span class="forecastMetric" role="listitem">
        <span class="forecastMetric__icon" aria-hidden="true">&#127777;</span>
        <span>{{ formatTemperature(summary.temp_c) }}</span>
      </span>
      <span class="forecastMetric" role="listitem">
        <span class="forecastMetric__icon" aria-hidden="true">&#128167;</span>
        <span>{{ formatPercent(summary.humidity_pct) }}</span>
      </span>
      <span class="forecastMetric" role="listitem">
        <span class="forecastMetric__icon" aria-hidden="true">&#9730;</span>
        <span>{{ formatPercent(summary.precip_pct) }}</span>
      </span>
    </div>

    <p v-else class="forecastStrip__empty">Predpoved pre toto okno nie je dostupna.</p>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import { EVENT_TIMEZONE, formatEventTime, parseEventDate } from '@/utils/eventTime'

const props = defineProps({
  event: { type: Object, default: null },
  userLocation: { type: Object, default: null },
})

const emit = defineEmits(['state'])

const loading = ref(false)
const viewingWindow = ref(null)
const summary = ref(null)
const requestErrorCode = ref('')
const requestUnavailable = ref(false)

let activeRequestId = 0

const eventId = computed(() => {
  const value = Number(props.event?.id)
  return Number.isFinite(value) ? value : null
})

const normalizedLocation = computed(() => {
  const source = props.userLocation && typeof props.userLocation === 'object' ? props.userLocation : null
  const lat = toFiniteNumber(source?.lat ?? source?.latitude)
  const lon = toFiniteNumber(source?.lon ?? source?.longitude)
  const candidateTz = String(source?.tz || source?.timezone || '').trim()
  const tz = candidateTz || EVENT_TIMEZONE

  return {
    lat,
    lon,
    tz,
  }
})

const hasLocation = computed(() => {
  return Number.isFinite(normalizedLocation.value.lat) && Number.isFinite(normalizedLocation.value.lon)
})

const showMissingLocation = computed(() => {
  return !loading.value && (!hasLocation.value || requestErrorCode.value === 'missing_location')
})

const windowLabel = computed(() => {
  const start = parseDate(viewingWindow.value?.start_at)
  const end = parseDate(viewingWindow.value?.end_at)
  if (!start || !end) return ''
  return `${formatTime(start, normalizedLocation.value.tz)} - ${formatTime(end, normalizedLocation.value.tz)}`
})

const summaryLabel = computed(() => {
  if (!summary.value) return ''
  return String(summary.value.label_sk || summary.value.label || '').trim()
})

watch(
  () => [
    eventId.value,
    normalizedLocation.value.lat,
    normalizedLocation.value.lon,
    normalizedLocation.value.tz,
  ],
  () => {
    loadForecast()
  },
  { immediate: true },
)

async function loadForecast() {
  const requestId = ++activeRequestId

  viewingWindow.value = null
  summary.value = null
  requestErrorCode.value = ''
  requestUnavailable.value = false

  if (!eventId.value) {
    loading.value = false
    emitState()
    return
  }

  if (!hasLocation.value) {
    loading.value = false
    requestErrorCode.value = 'missing_location'
    emitState()
    return
  }

  loading.value = true
  emitState()

  try {
    const response = await api.get(`/events/${eventId.value}/viewing-forecast`, {
      params: {
        lat: normalizedLocation.value.lat,
        lon: normalizedLocation.value.lon,
        tz: normalizedLocation.value.tz,
      },
      meta: { skipErrorToast: true },
    })

    if (requestId !== activeRequestId) return

    const payload = response?.data || {}
    viewingWindow.value = payload.viewing_window || null
    summary.value = normalizeSummary(payload.summary || payload.forecast_summary)
  } catch (error) {
    if (requestId !== activeRequestId) return

    requestErrorCode.value = String(error?.response?.data?.errors?.code || '')
    requestUnavailable.value = requestErrorCode.value === ''
    viewingWindow.value = null
    summary.value = null
  } finally {
    if (requestId === activeRequestId) {
      loading.value = false
      emitState()
    }
  }
}

function emitState() {
  emit('state', {
    loading: loading.value,
    viewingWindow: viewingWindow.value,
    summary: summary.value,
    missingLocation: showMissingLocation.value,
    unavailable: requestUnavailable.value,
  })
}

function normalizeSummary(value) {
  if (!value || typeof value !== 'object') return null

  return {
    clouds_pct: toFiniteNumber(value.clouds_pct),
    wind_ms: toFiniteNumber(value.wind_ms),
    temp_c: toFiniteNumber(value.temp_c),
    humidity_pct: toFiniteNumber(value.humidity_pct),
    precip_pct: toFiniteNumber(value.precip_pct),
    rating: typeof value.rating === 'string' && value.rating.trim() !== '' ? value.rating : 'avg',
    label_sk: String(value.label_sk || value.label || '').trim(),
  }
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function parseDate(value) {
  return parseEventDate(value)
}

function formatTime(value, timeZone) {
  return formatEventTime(value, timeZone).timeString
}

function formatPercent(value) {
  const numeric = toFiniteNumber(value)
  if (numeric === null) return '--'
  return `${Math.round(numeric)}%`
}

function formatTemperature(value) {
  const numeric = toFiniteNumber(value)
  if (numeric === null) return '--'
  const rounded = Number.isInteger(numeric) ? numeric.toFixed(0) : numeric.toFixed(1)
  return `${rounded} C`
}

function formatMeasure(value, unit) {
  const numeric = toFiniteNumber(value)
  if (numeric === null) return `-- ${unit}`
  const rounded = Number.isInteger(numeric) ? numeric.toFixed(0) : numeric.toFixed(1)
  return `${rounded} ${unit}`
}
</script>

<style scoped>
.forecastStrip {
  display: grid;
  gap: 0.5rem;
  padding: 0.8rem 0;
  border-top: 1px solid var(--divider-color);
  border-bottom: 1px solid var(--divider-color);
}

.forecastStrip__top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.42rem 0.58rem;
}

.forecastStrip__title {
  margin: 0;
  color: rgb(255 255 255 / 0.56);
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.forecastStrip__window {
  color: rgb(255 255 255 / 0.94);
  font-size: 0.96rem;
  font-weight: 650;
}

.forecastStrip__badge {
  display: inline-flex;
  align-items: center;
  min-height: 1.45rem;
  padding: 0 0.58rem;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 0.72rem;
  font-weight: 700;
}

.forecastStrip__badge--good {
  border-color: rgb(52 211 153 / 0.24);
  background: rgb(52 211 153 / 0.14);
  color: rgb(209 250 229 / 0.96);
}

.forecastStrip__badge--avg {
  border-color: rgb(251 191 36 / 0.24);
  background: rgb(251 191 36 / 0.14);
  color: rgb(254 243 199 / 0.96);
}

.forecastStrip__badge--bad {
  border-color: rgb(248 113 113 / 0.24);
  background: rgb(248 113 113 / 0.14);
  color: rgb(254 226 226 / 0.96);
}

.forecastStrip__metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem 0.9rem;
  color: rgb(255 255 255 / 0.8);
  font-size: 0.88rem;
  line-height: 1.4;
}

.forecastMetric {
  display: inline-flex;
  align-items: center;
  gap: 0.34rem;
  white-space: nowrap;
}

.forecastMetric__icon {
  opacity: 0.78;
}

.forecastStrip__empty {
  margin: 0;
  color: rgb(255 255 255 / 0.54);
  font-size: 0.88rem;
  line-height: 1.5;
}

.forecastStrip__loading {
  display: grid;
  gap: 0.42rem;
}

.forecastStrip__loadingLine {
  display: block;
  height: 0.78rem;
  border-radius: 999px;
  background: linear-gradient(
    90deg,
    rgb(255 255 255 / 0.06),
    rgb(255 255 255 / 0.14),
    rgb(255 255 255 / 0.06)
  );
  background-size: 200% 100%;
  animation: forecastStripShimmer 1.2s linear infinite;
}

.forecastStrip__loadingLine--wide {
  width: 68%;
}

@keyframes forecastStripShimmer {
  0% {
    background-position: 200% 0;
  }

  100% {
    background-position: -200% 0;
  }
}

@media (max-width: 767px) {
  .forecastStrip__metrics {
    gap: 0.45rem 0.75rem;
    font-size: 0.84rem;
  }
}
</style>
