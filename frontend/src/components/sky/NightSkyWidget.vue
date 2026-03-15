<template>
  <section class="card panel nightSky">
    <h3 class="panelTitle sidebarSection__header">Nocna obloha</h3>

    <div v-if="showLoading" class="panelLoading">
      <div class="skeleton h-8 w-full"></div>
      <div class="skeleton h-8 w-4/5"></div>
    </div>

    <AsyncState
      v-else-if="!hasLocationCoords"
      mode="empty"
      title="Poloha nie je nastavena"
      message="Nastav polohu pre nocnu oblohu."
      compact
    />

    <section v-else-if="showAstronomyError" class="state stateError">
      <InlineStatus
        variant="error"
        :message="astronomyErrorMessage"
        action-label="Skusit znova"
        @action="refreshBlock('astronomy')"
      />
    </section>

    <div v-else class="nightBody">
      <div class="statRow">
        <span>Mesiac</span>
        <strong>{{ moonLine }}</strong>
      </div>

      <div v-if="showBortle" class="statRow">
        <span>Svetelne znecistenie</span>
        <strong>{{ bortleLine }}</strong>
      </div>

      <div class="statRow">
        <span>Viditelne planety</span>
        <strong>{{ planetsLine }}</strong>
      </div>

      <div v-if="showComets" class="statRow">
        <span>Komety (JPL)</span>
        <strong>{{ cometsLine }}</strong>
      </div>

      <div v-if="showAsteroids" class="statRow">
        <span>Asteroidy (JPL)</span>
        <strong>{{ asteroidsLine }}</strong>
      </div>

      <p v-if="ephemerisSourceLine" class="sourceLine" :title="ephemerisSourceHint">
        Zdroj: {{ ephemerisSourceLine }}
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, toRef } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSkyWidget } from '@/composables/useSkyWidget'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  date: { type: String, default: '' },
  tz: { type: String, default: '' },
  initialPayload: { type: Object, default: undefined },
  bundlePending: { type: Boolean, default: false },
})

const {
  astronomy,
  astronomyLoading,
  astronomyError,
  lightPollution,
  lightPollutionLine,
  lightPollutionMetaLine,
  lightPollutionEstimateLine,
  planetCandidates,
  planetsDisplayList,
  ephemeris,
  ephemerisError,
  ephemerisLoading,
  hasLocationCoords,
  refreshBlock,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
  initialPayload: toRef(props, 'initialPayload'),
  bundlePending: toRef(props, 'bundlePending'),
  includeWeather: false,
  includeIss: false,
  includeEphemeris: true,
})

const showLoading = computed(() => astronomyLoading.value && !astronomy.value)
const showAstronomyError = computed(() => Boolean(astronomyError.value) && !astronomy.value)
const astronomyErrorMessage = computed(() => {
  const value = String(astronomyError.value || '').trim()
  return value || 'Nepodarilo sa nacitat nocnu oblohu.'
})

const moonLine = computed(() => {
  const phase = translateMoonPhase(astronomy.value?.moon_phase)
  const illumination = toFiniteNumber(astronomy.value?.moon_illumination_percent)

  if (illumination === null) {
    if (phase === 'Neznama faza') {
      return 'docasne nedostupne'
    }

    return phase
  }

  return `${phase} | ${Math.round(illumination)}%`
})

const showBortle = computed(() => {
  if (lightPollutionUnavailable.value) return true
  return String(lightPollutionLine.value || '').trim() !== '' || String(lightPollutionMetaLine.value || '').trim() !== ''
})

const lightPollutionUnavailable = computed(() => {
  const reason = String(lightPollution.value?.reason || '').trim().toLowerCase()
  return reason.includes('light_pollution_provider_')
})

const lightPollutionUsingCached = computed(() => {
  const source = String(lightPollution.value?.source || '').trim().toLowerCase()
  const reason = String(lightPollution.value?.reason || '').trim().toLowerCase()
  return source === 'light_pollution_cached' || reason === 'using_cached_data'
})

const bortleLine = computed(() => {
  if (lightPollutionUnavailable.value) {
    return 'realne data docasne nedostupne'
  }

  const base = String(lightPollutionLine.value || '').trim()
  const meta = String(lightPollutionMetaLine.value || '').trim()
  const isEstimate = String(lightPollutionEstimateLine.value || '').trim() !== ''

  if (lightPollutionUsingCached.value) {
    const snapshot = base && meta ? `${base} | ${meta}` : (base || meta || '')
    return snapshot ? `${snapshot} | posledne dostupne` : 'posledne dostupne'
  }

  if (base && meta) return isEstimate ? `${base} | ${meta} | odhad` : `${base} | ${meta}`
  const fallback = base || meta
  if (!fallback) return isEstimate ? 'odhad podla lokality' : '-'
  return isEstimate ? `${fallback} | odhad` : fallback
})

const visiblePlanetLabels = computed(() => {
  const list = Array.isArray(planetsDisplayList.value) ? planetsDisplayList.value : []
  return list
    .filter((planet) => planet?.isVisible)
    .map((planet) => {
      const name = String(planet?.name || '').trim()
      const bestTimeWindow = String(planet?.bestTimeWindow || '').trim()
      return formatPlanetWithWindow(name, bestTimeWindow)
    })
    .filter((label) => label)
    .slice(0, 4)
})

const MIN_TODAY_ELONGATION_DEG = 20

function collectTodayPlanets(rows, strategy = 'confirmed', limit = 4) {
  const sourceRows = Array.isArray(rows) ? rows : []
  const planets = []
  const seen = new Set()

  for (const planet of sourceRows) {
    const name = String(planet?.name || '').trim()
    if (!name) continue

    const key = name.toLowerCase()
    if (seen.has(key)) continue

    const bestTimeWindow = String(planet?.best_time_window || '').trim()
    const elongation = toFiniteNumber(planet?.elongation_deg)
    const hasBestWindow = bestTimeWindow !== ''
    const hasUsefulElongation = elongation !== null && elongation >= MIN_TODAY_ELONGATION_DEG

    if (strategy === 'confirmed' && !hasBestWindow) continue
    if (strategy === 'estimated' && (hasBestWindow || !hasUsefulElongation)) continue

    seen.add(key)
    planets.push({
      name,
      bestTimeWindow,
    })

    if (planets.length >= limit) break
  }

  return planets
}

function formatPlanetWithWindow(name, bestTimeWindow) {
  const normalizedName = String(name || '').trim()
  const normalizedWindow = String(bestTimeWindow || '').trim()
  if (!normalizedName) return ''
  if (!normalizedWindow) return normalizedName
  return `${normalizedName} (${normalizedWindow})`
}

const todayPlanets = computed(() => {
  return collectTodayPlanets(planetCandidates.value, 'confirmed', 4)
})

const todayPlanetLabels = computed(() => {
  return todayPlanets.value
    .map((planet) => formatPlanetWithWindow(planet?.name, planet?.bestTimeWindow))
    .filter((label) => label)
})

const todayEstimatedPlanetLabels = computed(() => {
  return collectTodayPlanets(planetCandidates.value, 'estimated', 4)
    .map((planet) => String(planet?.name || '').trim())
    .filter((name) => name)
})

const planetsLine = computed(() => {
  if (visiblePlanetLabels.value.length > 0) {
    return visiblePlanetLabels.value.join(', ')
  }

  if (todayPlanetLabels.value.length > 0) {
    return `dnes: ${todayPlanetLabels.value.join(', ')}`
  }

  if (todayEstimatedPlanetLabels.value.length > 0) {
    return `dnes (odhad): ${todayEstimatedPlanetLabels.value.join(', ')}`
  }

  return 'teraz ziadne'
})

const cometNames = computed(() => {
  const rows = Array.isArray(ephemeris.value?.comets) ? ephemeris.value.comets : []
  return rows
    .map((row) => String(row?.name || '').trim())
    .filter((name) => name)
    .slice(0, 2)
})

const asteroidNames = computed(() => {
  const rows = Array.isArray(ephemeris.value?.asteroids) ? ephemeris.value.asteroids : []
  return rows
    .map((row) => String(row?.name || '').trim())
    .filter((name) => name)
    .slice(0, 2)
})

const showComets = computed(() => cometNames.value.length > 0)
const showAsteroids = computed(() => asteroidNames.value.length > 0)
const cometsLine = computed(() => cometNames.value.join(', '))
const asteroidsLine = computed(() => asteroidNames.value.join(', '))

const ephemerisSourceLine = computed(() => {
  if (ephemerisLoading.value && !showComets.value && !showAsteroids.value) {
    return ''
  }

  if (ephemerisError.value) {
    return ''
  }

  const source = ephemeris.value?.source
  const planets = String(source?.planets || '').trim()
  const smallBodies = String(source?.small_bodies || '').trim()
  if (planets === 'jpl_horizons' && smallBodies === 'jpl_sbddb') return 'NASA JPL'
  if (planets === 'jpl_horizons') return 'JPL Horizons'
  if (smallBodies === 'jpl_sbddb') return 'JPL SBDDB'
  return ''
})

const ephemerisSourceHint = computed(() => {
  const source = ephemeris.value?.source
  const planets = String(source?.planets || '').trim()
  const smallBodies = String(source?.small_bodies || '').trim()
  const parts = []
  if (planets === 'jpl_horizons') parts.push('Planety: JPL Horizons')
  if (smallBodies === 'jpl_sbddb') parts.push('Male telesa: JPL SBDDB')
  return parts.join(' | ')
})

function translateMoonPhase(value) {
  const map = {
    new_moon: 'Nov',
    waxing_crescent: 'Dorastajuci kosacik',
    first_quarter: 'Prva stvrt',
    waxing_gibbous: 'Dorastajuci mesiac',
    full_moon: 'Spln',
    waning_gibbous: 'Ubudajuci mesiac',
    last_quarter: 'Posledna stvrt',
    waning_crescent: 'Ubudajuci kosacik',
  }

  const key = String(value || '').trim().toLowerCase()
  return map[key] || 'Neznama faza'
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
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
  gap: 0.28rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
  margin: 0;
}

.nightBody {
  display: grid;
  gap: 0.22rem;
}

.statRow {
  display: flex;
  gap: 0.45rem;
  align-items: baseline;
  justify-content: space-between;
  border-bottom: 1px solid var(--divider-color);
  padding-bottom: 0.2rem;
}

.statRow:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.statRow span {
  font-size: 0.68rem;
  color: var(--color-text-secondary);
}

.statRow strong {
  font-size: 0.74rem;
  line-height: 1.25;
  color: var(--color-surface);
  text-align: right;
  font-weight: 700;
  max-width: 72%;
  overflow-wrap: anywhere;
}

.sourceLine {
  margin: 0;
  font-size: 0.68rem;
  color: var(--color-text-secondary);
}

.panelLoading {
  display: grid;
  gap: 0.2rem;
}

.skeleton {
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
  border-radius: 0;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.h-8 { height: 2rem; }
.w-4\/5 { width: 80%; }
.w-full { width: 100%; }
</style>
