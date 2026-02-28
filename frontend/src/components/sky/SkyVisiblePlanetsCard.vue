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
      <div v-if="!isNight" class="emptyState">
        <p>Zobrazíme po zotmení.</p>
      </div>

      <div v-else-if="visiblePlanets.length === 0" class="emptyState">
        <p v-if="reason === 'sky_service_unavailable'">Udaje o planetach su docasne nedostupne.</p>
        <p v-else-if="reason === 'degraded_contract'">Udaje o planetach maju docasne nekompletny kontrakt.</p>
        <p v-else>Momentalne nie su vhodne podmienky na pozorovanie planet.</p>
      </div>

      <ul v-else class="planetList">
        <li v-for="planet in visiblePlanets" :key="planet.name" class="planetItem">
          <div class="planetMain">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <strong>{{ planet.name }}</strong>
                <span
                  v-if="planet.visibilityLabel"
                  :class="['inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium', planet.visibilityToneClass]"
                >
                  {{ planet.visibilityLabel }}
                </span>
              </div>
            </div>
            <span>{{ planet.direction }}</span>
          </div>
          <div class="planetMeta">
            <span>Alt: {{ planet.altitudeLabel }}</span>
            <span>Elong: {{ planet.elongationLabel }}</span>
            <span v-if="planet.bestTimeWindow">Best: {{ planet.bestTimeWindow }}</span>
          </div>
        </li>
      </ul>
    </div>
  </article>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'
import { getVisiblePlanets, isPlanetNight } from '@/utils/skyWidget'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  tz: { type: String, default: '' },
})

const loading = ref(false)
const error = ref('')
const planetsPayload = ref({ planets: [], sample_at: null, sun_altitude_deg: null })

const visiblePlanets = computed(() => getVisiblePlanets(planetsPayload.value))
const reason = computed(() => String(planetsPayload.value?.reason || ''))
const isNight = computed(() => isPlanetNight(planetsPayload.value?.sun_altitude_deg))

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
    planetsPayload.value = response?.data || { planets: [], sample_at: null, sun_altitude_deg: null }
  } catch (err) {
    planetsPayload.value = { planets: [], sample_at: null, sun_altitude_deg: null }
    error.value = err?.response?.data?.message || err?.userMessage || 'Nepodarilo sa nacitat planety.'
  } finally {
    loading.value = false
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
