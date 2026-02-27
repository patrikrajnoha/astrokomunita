<template>
  <section class="skyPage">
    <header class="skyHeader">
      <h1>Sky Dashboard</h1>
      <p>{{ locationLabel }}<span v-if="timezoneLabel"> · {{ timezoneLabel }}</span></p>
    </header>

    <div class="skyGrid">
      <SkyWeatherCard :lat="resolvedLat" :lon="resolvedLon" :tz="resolvedTz" />
      <SkyAstronomyCard :lat="resolvedLat" :lon="resolvedLon" :tz="resolvedTz" />
      <SkyVisiblePlanetsCard :lat="resolvedLat" :lon="resolvedLon" :tz="resolvedTz" />
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import SkyWeatherCard from '@/components/sky/SkyWeatherCard.vue'
import SkyAstronomyCard from '@/components/sky/SkyAstronomyCard.vue'
import SkyVisiblePlanetsCard from '@/components/sky/SkyVisiblePlanetsCard.vue'

const route = useRoute()
const auth = useAuthStore()

const parseNumber = (value) => {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value !== 'string' || value.trim() === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const parseString = (value) => {
  if (typeof value !== 'string') return ''
  return value.trim()
}

const queryLat = computed(() => parseNumber(Array.isArray(route.query.lat) ? route.query.lat[0] : route.query.lat))
const queryLon = computed(() => parseNumber(Array.isArray(route.query.lon) ? route.query.lon[0] : route.query.lon))
const queryTz = computed(() => parseString(Array.isArray(route.query.tz) ? route.query.tz[0] : route.query.tz))
const hasQueryCoords = computed(() => queryLat.value !== null && queryLon.value !== null)

const canonicalLocationData = computed(() => {
  const value = auth.user?.location_data
  return value && typeof value === 'object' ? value : null
})

const canonicalLocationMeta = computed(() => {
  const value = auth.user?.location_meta
  return value && typeof value === 'object' ? value : null
})

const resolvedLat = computed(() => {
  if (hasQueryCoords.value) return queryLat.value

  const dataLat = parseNumber(canonicalLocationData.value?.latitude)
  if (dataLat !== null) return dataLat

  return parseNumber(canonicalLocationMeta.value?.lat)
})

const resolvedLon = computed(() => {
  if (hasQueryCoords.value) return queryLon.value

  const dataLon = parseNumber(canonicalLocationData.value?.longitude)
  if (dataLon !== null) return dataLon

  return parseNumber(canonicalLocationMeta.value?.lon)
})

const resolvedTz = computed(() => {
  if (queryTz.value !== '') return queryTz.value

  const dataTz = parseString(canonicalLocationData.value?.timezone)
  if (dataTz !== '') return dataTz

  const metaTz = parseString(canonicalLocationMeta.value?.tz)
  if (metaTz !== '') return metaTz

  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Bratislava'
})

const locationLabel = computed(() => {
  if (hasQueryCoords.value) {
    const lat = queryLat.value?.toFixed(4)
    const lon = queryLon.value?.toFixed(4)
    return `Lokalita: ${lat}, ${lon}`
  }

  const dataLabel = parseString(canonicalLocationData.value?.label)
  if (dataLabel !== '') return dataLabel

  const metaLabel = parseString(canonicalLocationMeta.value?.label || canonicalLocationMeta.value?.name)
  if (metaLabel !== '') return metaLabel

  return 'Predvolena lokalita'
})

const timezoneLabel = computed(() => parseString(resolvedTz.value))
</script>

<style scoped>
.skyPage {
  display: grid;
  gap: 0.9rem;
}

.skyHeader h1,
.skyHeader p {
  margin: 0;
}

.skyHeader h1 {
  font-size: 1.15rem;
  font-weight: 700;
}

.skyHeader p {
  margin-top: 0.2rem;
  font-size: 0.84rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.skyGrid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.7rem;
}

@media (max-width: 1200px) {
  .skyGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 820px) {
  .skyGrid {
    grid-template-columns: 1fr;
  }
}
</style>
