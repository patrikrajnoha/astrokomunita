<template>
  <section class="forecastStrip" aria-live="polite">
    <div class="forecastStrip__top">
      <p class="forecastStrip__title">Okno pozorovania</p>
      <span v-if="windowLabel" class="forecastStrip__window">{{ windowLabel }}</span>
      <span
        v-if="summaryBadgeLabel"
        class="forecastStrip__badge"
        :class="`forecastStrip__badge--${summary?.rating || 'avg'}`"
      >
        {{ summaryBadgeLabel }}
      </span>
    </div>

    <div v-if="loading" class="forecastStrip__loading" aria-hidden="true">
      <span class="forecastStrip__loadingLine forecastStrip__loadingLine--wide"></span>
      <span class="forecastStrip__loadingLine"></span>
    </div>

    <p v-else-if="showMissingLocation" class="forecastStrip__empty">
      Nastav polohu a zobrazíme predpoveď priamo pre tvoje miesto.
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
    </div>

    <p v-if="summaryReason" class="forecastStrip__reason">{{ summaryReason }}</p>
    <p v-if="!loading && !showMissingLocation && !summary" class="forecastStrip__empty">
      Predpoveď pre toto okno nie je dostupná.
    </p>
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

const summaryBadgeLabel = computed(() => {
  if (!summary.value) return ''

  const label = summaryLabel.value || 'Priemerné'
  const rating = String(summary.value.rating || 'avg').trim()
  const icon = rating === 'good' ? '✅' : rating === 'bad' ? '❌' : '⚠️'

  return `${icon} ${label}`
})

const summaryReason = computed(() => {
  if (!summary.value) return ''

  const clouds = toFiniteNumber(summary.value.clouds_pct)
  const precipitation = toFiniteNumber(summary.value.precip_pct)
  const windMs = toFiniteNumber(summary.value.wind_ms)
  const rating = String(summary.value.rating || 'avg').trim()

  const limits = []

  if (clouds !== null && clouds > 60) {
    limits.push(`oblačnosť ${Math.round(clouds)}%`)
  }

  if (precipitation !== null && precipitation > 40) {
    limits.push(`zrážky ${Math.round(precipitation)}%`)
  }

  if (windMs !== null && windMs > 10) {
    limits.push(`vietor ${formatMeasure(windMs, 'm/s')}`)
  }

  if (rating === 'bad') {
    if (limits.length > 0) {
      return `Nepriaznivé hlavne kvôli: ${limits.join(', ')}.`
    }
    return 'Nepriaznivé podľa kombinácie počasia v tomto okne.'
  }

  if (rating === 'good') {
    return 'Podmienky sú priaznivé na pozorovanie.'
  }

  if (limits.length > 0) {
    return `Podmienky sú priemerné (limitujú: ${limits.join(', ')}).`
  }

  return 'Podmienky sú priemerné.'
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
  gap: 0.9rem;
}

.forecastStrip__top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.65rem;
}

.forecastStrip__title {
  margin: 0;
  color: #ABB8C9;
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.forecastStrip__window {
  color: #FFFFFF;
  font-size: 1.05rem;
  font-weight: 650;
  line-height: 1.35;
}

.forecastStrip__badge {
  display: inline-flex;
  align-items: center;
  min-height: 1.9rem;
  padding: 0 0.8rem;
  border-radius: 999px;
  background: #1c2736;
  color: #FFFFFF;
  font-size: 0.74rem;
  font-weight: 700;
}

.forecastStrip__badge--good {
  background: rgb(15 115 255 / 0.18);
}

.forecastStrip__badge--avg {
  background: rgb(15 115 255 / 0.14);
}

.forecastStrip__badge--bad {
  background: rgb(235 36 82 / 0.18);
}

.forecastStrip__metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 0.7rem;
  color: #FFFFFF;
  font-size: 0.9rem;
  line-height: 1.4;
}

.forecastMetric {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  white-space: nowrap;
  min-height: 2.5rem;
  padding: 0 0.9rem;
  border-radius: 999px;
  background: #1c2736;
}

.forecastMetric__icon {
  opacity: 0.9;
}

.forecastStrip__empty {
  margin: 0;
  border-radius: 1.1rem;
  background: #1c2736;
  color: #ABB8C9;
  font-size: 0.9rem;
  line-height: 1.55;
  padding: 0.9rem 1rem;
}

.forecastStrip__reason {
  margin: 0;
  color: #ABB8C9;
  font-size: 0.88rem;
  line-height: 1.55;
}

.forecastStrip__loading {
  display: grid;
  gap: 0.55rem;
}

.forecastStrip__loadingLine {
  display: block;
  height: 0.86rem;
  border-radius: 999px;
  background: linear-gradient(
    90deg,
    rgb(255 255 255 / 0.05),
    rgb(255 255 255 / 0.12),
    rgb(255 255 255 / 0.05)
  );
  background-size: 200% 100%;
  animation: forecastStripShimmer 1.2s linear infinite;
}

.forecastStrip__loadingLine--wide {
  width: 62%;
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
    gap: 0.55rem;
    font-size: 0.84rem;
  }

  .forecastMetric {
    min-height: 2.35rem;
    padding-inline: 0.8rem;
  }
}
</style>
