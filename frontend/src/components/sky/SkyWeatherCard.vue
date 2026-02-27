<template>
  <article class="skyCard">
    <header class="cardHead">
      <h2>Pozorovacie podmienky</h2>
      <p>Zdroj: Open-Meteo</p>
    </header>

    <div v-if="loading" class="state">Nacitavam data...</div>

    <div v-else-if="error" class="state stateError">
      <p>{{ error }}</p>
      <button type="button" class="retryBtn" @click="fetchWeather">Skusit znova</button>
    </div>

    <div v-else-if="weather" class="body">
      <div class="metricGrid">
        <div class="metric">
          <span>Oblacnost</span>
          <strong>{{ pct(weather.cloud_percent) }}</strong>
        </div>
        <div class="metric">
          <span>Vlhkost</span>
          <strong>{{ pct(weather.humidity_percent) }}</strong>
        </div>
        <div class="metric">
          <span>Vietor</span>
          <strong>{{ windLine }}</strong>
        </div>
      </div>

      <div class="scoreWrap">
        <div class="scoreHeader">
          <span>Observing score</span>
          <strong>{{ scoreLine }}</strong>
        </div>
        <div class="bar" role="progressbar" :aria-valuenow="observingScore" aria-valuemin="0" aria-valuemax="100">
          <span class="fill" :style="{ width: `${observingScore}%` }"></span>
        </div>
      </div>

      <p class="metaLine">Aktualizovane: {{ formatIso(weather.as_of) }}</p>
    </div>
  </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  tz: { type: String, default: '' },
})

const loading = ref(false)
const error = ref('')
const weather = ref(null)

const observingScore = computed(() => {
  const value = Number(weather.value?.observing_score)
  if (!Number.isFinite(value)) return 0
  return Math.max(0, Math.min(100, Math.round(value)))
})

const scoreLine = computed(() => `${observingScore.value}/100`)
const windLine = computed(() => {
  const speed = Number(weather.value?.wind_speed)
  const unit = String(weather.value?.wind_unit || 'km/h')
  if (!Number.isFinite(speed)) return '-'
  return `${speed.toFixed(1)} ${unit}`
})

const toNumber = (value) => {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value !== 'string' || value.trim() === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const requestParams = () => {
  const params = {}
  const lat = toNumber(props.lat)
  const lon = toNumber(props.lon)
  const tz = String(props.tz || '').trim()

  if (lat !== null && lon !== null) {
    params.lat = lat
    params.lon = lon
  }
  if (tz !== '') {
    params.tz = tz
  }

  return params
}

const fetchWeather = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/sky/weather', {
      params: requestParams(),
      meta: { skipErrorToast: true },
    })
    weather.value = response?.data || null
  } catch (err) {
    weather.value = null
    error.value = err?.response?.data?.message || err?.userMessage || 'Nepodarilo sa nacitat podmienky.'
  } finally {
    loading.value = false
  }
}

const pct = (value) => {
  const parsed = Number(value)
  if (!Number.isFinite(parsed)) return '-'
  return `${Math.round(parsed)}%`
}

const formatIso = (value) => {
  if (typeof value !== 'string' || value.trim() === '') return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat('sk-SK', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date)
}

watch(
  () => [props.lat, props.lon, props.tz],
  () => {
    fetchWeather()
  },
  { immediate: true },
)
</script>

<style scoped>
.skyCard {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22);
  border-radius: 1rem;
  background: rgb(var(--color-bg-rgb) / 0.72);
  padding: 0.85rem;
  display: grid;
  gap: 0.7rem;
}

.cardHead h2,
.cardHead p {
  margin: 0;
}

.cardHead h2 {
  font-size: 0.98rem;
}

.cardHead p {
  font-size: 0.75rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.state {
  font-size: 0.82rem;
}

.stateError {
  border: 1px solid rgb(248 113 113 / 0.45);
  border-radius: 0.8rem;
  padding: 0.55rem;
  background: rgb(185 28 28 / 0.12);
  color: rgb(254 202 202 / 0.95);
}

.stateError p {
  margin: 0 0 0.45rem;
}

.retryBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  border-radius: 0.65rem;
  background: transparent;
  color: inherit;
  padding: 0.34rem 0.56rem;
  font-size: 0.75rem;
}

.body {
  display: grid;
  gap: 0.7rem;
}

.metricGrid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.45rem;
}

.metric {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 0.75rem;
  padding: 0.45rem;
  display: grid;
  gap: 0.2rem;
}

.metric span {
  font-size: 0.68rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.metric strong {
  font-size: 0.9rem;
}

.scoreWrap {
  display: grid;
  gap: 0.32rem;
}

.scoreHeader {
  display: flex;
  justify-content: space-between;
  gap: 0.55rem;
  font-size: 0.76rem;
}

.bar {
  width: 100%;
  height: 0.58rem;
  border-radius: 999px;
  overflow: hidden;
  background: rgb(var(--color-text-secondary-rgb) / 0.16);
}

.fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, rgb(34 197 94 / 0.85), rgb(59 130 246 / 0.85));
}

.metaLine {
  margin: 0;
  font-size: 0.72rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

@media (max-width: 640px) {
  .metricGrid {
    grid-template-columns: 1fr;
  }
}
</style>
