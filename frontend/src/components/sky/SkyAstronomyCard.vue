<template>
  <article class="skyCard">
    <header class="cardHead">
      <h2>Astronomicke podmienky</h2>
      <p>Faza mesiaca a casy vychodu/zapadu</p>
    </header>

    <div v-if="loading" class="state">Nacitavam data...</div>

    <div v-else-if="error" class="state stateError">
      <p>{{ error }}</p>
      <button type="button" class="retryBtn" @click="fetchAstronomy">Skusit znova</button>
    </div>

    <div v-else-if="astronomy" class="body">
      <div class="metricGrid">
        <div class="metric">
          <span>Faza mesiaca</span>
          <strong>{{ moonPhaseLabel }}</strong>
        </div>
        <div class="metric">
          <span>Osvetlenie</span>
          <strong>{{ illuminationLabel }}</strong>
        </div>
      </div>

      <div class="timeGrid">
        <div class="timeRow">
          <span>Vychod Slnka</span>
          <strong>{{ formatIso(astronomy.sunrise_at) }}</strong>
        </div>
        <div class="timeRow">
          <span>Zapad Slnka</span>
          <strong>{{ formatIso(astronomy.sunset_at) }}</strong>
        </div>
        <div class="timeRow">
          <span>Vychod Mesiaca</span>
          <strong>{{ formatIso(astronomy.moonrise_at) }}</strong>
        </div>
        <div class="timeRow">
          <span>Zapad Mesiaca</span>
          <strong>{{ formatIso(astronomy.moonset_at) }}</strong>
        </div>
      </div>
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
const astronomy = ref(null)

const phaseDictionary = {
  new_moon: 'Nov',
  waxing_crescent: 'Dorastajuci kosacik',
  first_quarter: 'Prva stvrt',
  waxing_gibbous: 'Dorastajuci mesiac',
  full_moon: 'Spln',
  waning_gibbous: 'Ubudajuci mesiac',
  last_quarter: 'Posledna stvrt',
  waning_crescent: 'Ubudajuci kosacik',
  unknown: 'Nezname',
}

const moonPhaseLabel = computed(() => {
  const key = String(astronomy.value?.moon_phase || 'unknown')
  return phaseDictionary[key] || phaseDictionary.unknown
})

const illuminationLabel = computed(() => {
  const value = Number(astronomy.value?.moon_illumination_percent)
  return Number.isFinite(value) ? `${Math.round(value)}%` : '-'
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

const fetchAstronomy = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/sky/astronomy', {
      params: requestParams(),
      meta: { skipErrorToast: true },
    })
    astronomy.value = response?.data || null
  } catch (err) {
    astronomy.value = null
    error.value = err?.response?.data?.message || err?.userMessage || 'Nepodarilo sa nacitat astronomiu.'
  } finally {
    loading.value = false
  }
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
    fetchAstronomy()
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
  gap: 0.65rem;
}

.metricGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
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

.timeGrid {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 0.75rem;
  padding: 0.5rem;
  display: grid;
  gap: 0.34rem;
}

.timeRow {
  display: flex;
  justify-content: space-between;
  gap: 0.6rem;
  font-size: 0.78rem;
}

.timeRow strong {
  text-align: right;
}

@media (max-width: 640px) {
  .metricGrid {
    grid-template-columns: 1fr;
  }
}
</style>
