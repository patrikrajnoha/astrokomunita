<template>
  <article class="skyCard">
    <header class="cardHead">
      <h2>Viditelne planety</h2>
      <p>Zoradene podla vysky nad horizontom</p>
    </header>

    <div v-if="loading" class="state">Nacitavam data...</div>

    <div v-else-if="error" class="state stateError">
      <p>{{ error }}</p>
      <button type="button" class="retryBtn" @click="fetchPlanets">Skusit znova</button>
    </div>

    <div v-else class="body">
      <div v-if="sortedPlanets.length === 0" class="emptyState">
        <p v-if="reason === 'sky_service_unavailable'">Udaje o planetach su docasne nedostupne.</p>
        <p v-else>Momentalne nie su nad horizontom.</p>
      </div>

      <ul v-else class="planetList">
        <li v-for="planet in sortedPlanets" :key="planet.name" class="planetItem">
          <div class="planetMain">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <strong>{{ planet.name }}</strong>
                <span
                  v-if="qualityBadge(planet.quality)"
                  :class="qualityBadge(planet.quality).class"
                >
                  {{ qualityBadge(planet.quality).label }}
                </span>
              </div>
              <p v-if="planet.magnitude !== undefined && planet.magnitude !== null" class="mt-1 text-xs text-slate-400">
                Magnituda: {{ planet.magnitude }}
              </p>
            </div>
            <span>{{ planet.direction }}</span>
          </div>
          <div class="planetMeta">
            <span>Alt: {{ formatDeg(planet.altitude_deg) }}</span>
            <span>Az: {{ formatDeg(planet.azimuth_deg) }}</span>
            <span v-if="planet.best_time_window">Best: {{ planet.best_time_window }}</span>
          </div>
          <p
            v-if="planet.quality === 'low'"
            class="rounded-lg border border-amber-400/20 bg-amber-400/10 px-2 py-1 text-xs text-amber-200"
          >
            Planeta je nizko nad horizontom, viditelnost moze byt obmedzena.
          </p>
        </li>
      </ul>
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
const planetsPayload = ref({ planets: [] })

const sortedPlanets = computed(() => {
  const rows = Array.isArray(planetsPayload.value?.planets) ? planetsPayload.value.planets : []
  return [...rows].sort((a, b) => Number(b?.altitude_deg || 0) - Number(a?.altitude_deg || 0))
})

const reason = computed(() => String(planetsPayload.value?.reason || ''))

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

const fetchPlanets = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/sky/visible-planets', {
      params: requestParams(),
      meta: { skipErrorToast: true },
    })
    planetsPayload.value = response?.data || { planets: [] }
  } catch (err) {
    planetsPayload.value = { planets: [] }
    error.value = err?.response?.data?.message || err?.userMessage || 'Nepodarilo sa nacitat planety.'
  } finally {
    loading.value = false
  }
}

const formatDeg = (value) => {
  const parsed = Number(value)
  if (!Number.isFinite(parsed)) return '-'
  return `${parsed.toFixed(1)}deg`
}

const qualityBadge = (quality) => {
  switch (String(quality || '').toLowerCase()) {
    case 'excellent':
      return {
        label: 'Vyborne',
        class: 'inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-medium text-emerald-300',
      }
    case 'good':
      return {
        label: 'Dobre',
        class: 'inline-flex rounded-full bg-sky-500/15 px-2 py-0.5 text-[11px] font-medium text-sky-300',
      }
    case 'low':
      return {
        label: 'Nizko nad horizontom',
        class: 'inline-flex rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-medium text-amber-300',
      }
    default:
      return null
  }
}

watch(
  () => [props.lat, props.lon, props.tz],
  () => {
    fetchPlanets()
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

.emptyState p {
  margin: 0;
  font-size: 0.8rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.planetList {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.45rem;
}

.planetItem {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 0.75rem;
  padding: 0.5rem;
  display: grid;
  gap: 0.24rem;
}

.planetMain {
  display: flex;
  justify-content: space-between;
  gap: 0.6rem;
  font-size: 0.86rem;
}

.planetMeta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem 0.65rem;
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}
</style>
