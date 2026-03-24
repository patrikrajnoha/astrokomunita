<template>
  <section class="panel">
    <div class="panelTitle sidebarSection__header">Nočná obloha</div>

    <!-- Loading -->
    <div v-if="showLoading" class="skeletonStack">
      <div class="skeleton skW60"></div>
      <div class="skeleton skW45"></div>
      <div class="skeleton skW50"></div>
    </div>

    <!-- No location -->
    <div v-else-if="!hasLocationCoords" class="stateBox">
      <div class="stateName">Poloha nie je nastavená</div>
      <div class="stateSub">Nastav polohu pre nočnú oblohu.</div>
    </div>

    <!-- Error -->
    <div v-else-if="showAstronomyError" class="stateBox">
      <div class="stateError">{{ astronomyErrorMessage }}</div>
      <button type="button" class="retryBtn" @click="refreshBlock('astronomy')">Skúsiť znova</button>
    </div>

    <!-- Content -->
    <div v-else class="nightBody">
      <!-- Planets: primary, one per row -->
      <div v-if="planetRows.length" class="planetList">
        <div v-for="planet in planetRows" :key="planet.name" class="planetRow">
          <span class="planetIcon" aria-hidden="true">🪐</span>
          <span class="planetName">{{ planet.name }}</span>
          <span v-if="planet.window" class="planetWindow">{{ planet.window }}</span>
        </div>
      </div>
      <div v-else class="noPlanets">🪐 Dnes žiadne planéty</div>

      <!-- Moon: compact -->
      <div class="moonLine">
        <span aria-hidden="true">{{ moonEmoji }}</span>
        <span v-if="moonIllumination !== null">{{ moonIllumination }}%</span>
        <span v-if="moonIllumination !== null && moonPhaseLabel" class="sep" aria-hidden="true">·</span>
        <span>{{ moonPhaseLabel }}</span>
      </div>

      <!-- Conditions: human label -->
      <div v-if="conditionsLabel" class="conditionsLine">
        <span aria-hidden="true">🌃</span>
        <span>{{ conditionsLabel }}</span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, toRef } from 'vue'
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
  planetCandidates,
  planetsDisplayList,
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
  includeEphemeris: false,
})

const showLoading = computed(() => astronomyLoading.value && !astronomy.value)
const showAstronomyError = computed(() => Boolean(astronomyError.value) && !astronomy.value)
const astronomyErrorMessage = computed(() => (
  String(astronomyError.value || '').trim() || 'Nepodarilo sa načítať nočnú oblohu.'
))

// ── Moon ──────────────────────────────────────────────────────────────────

const MOON_EMOJIS = {
  new_moon:        '🌑',
  waxing_crescent: '🌒',
  first_quarter:   '🌓',
  waxing_gibbous:  '🌔',
  full_moon:       '🌕',
  waning_gibbous:  '🌖',
  last_quarter:    '🌗',
  waning_crescent: '🌘',
}

const MOON_PHASES = {
  new_moon:        'Nov',
  waxing_crescent: 'Dorastajúci kosáčik',
  first_quarter:   'Prvá štvrt',
  waxing_gibbous:  'Dorastajúci mesiac',
  full_moon:       'Spln',
  waning_gibbous:  'Ubúdajúci mesiac',
  last_quarter:    'Posledná štvrt',
  waning_crescent: 'Ubúdajúci kosáčik',
}

const moonEmoji = computed(() => MOON_EMOJIS[astronomy.value?.moon_phase] || '🌑')
const moonPhaseLabel = computed(() => MOON_PHASES[astronomy.value?.moon_phase] || '')
const moonIllumination = computed(() => {
  const v = toFiniteNumber(astronomy.value?.moon_illumination_percent)
  return v !== null ? Math.round(v) : null
})

// ── Conditions ────────────────────────────────────────────────────────────

const conditionsLabel = computed(() => {
  const bortle = toFiniteNumber(lightPollution.value?.bortle_class)
  if (bortle === null) return ''
  if (bortle <= 2) return 'Výborné podmienky'
  if (bortle <= 4) return 'Dobré podmienky'
  if (bortle <= 5) return 'Stredné podmienky'
  if (bortle <= 7) return 'Horšie podmienky'
  return 'Silné znečistenie'
})

// ── Planets ───────────────────────────────────────────────────────────────

const MIN_TODAY_ELONGATION_DEG = 20

function collectTodayPlanets(rows, strategy = 'confirmed', limit = 3) {
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
    planets.push({ name, window: bestTimeWindow })
    if (planets.length >= limit) break
  }

  return planets
}

const planetRows = computed(() => {
  // Priority 1: currently visible (planetsDisplayList from useSkyWidget)
  const visibleNow = (Array.isArray(planetsDisplayList.value) ? planetsDisplayList.value : [])
    .filter((p) => p?.isVisible)
    .map((p) => ({ name: String(p?.name || '').trim(), window: String(p?.bestTimeWindow || '').trim() }))
    .filter((p) => p.name)
    .slice(0, 3)
  if (visibleNow.length > 0) return visibleNow

  // Priority 2: today (confirmed best_time_window)
  const confirmed = collectTodayPlanets(planetCandidates.value, 'confirmed', 3)
  if (confirmed.length > 0) return confirmed

  // Priority 3: estimated (elongation only, no window)
  return collectTodayPlanets(planetCandidates.value, 'estimated', 3)
})

// ── Helpers ───────────────────────────────────────────────────────────────

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
.panel {
  display: grid;
  gap: 0.32rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
  margin: 0;
}

/* ── Skeleton ── */
.skeletonStack {
  display: grid;
  gap: 0.28rem;
}

.skeleton {
  height: 0.72rem;
  border-radius: 0.25rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.07),
    rgb(var(--color-text-secondary-rgb) / 0.14),
    rgb(var(--color-text-secondary-rgb) / 0.07)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

.skW60 { width: 60%; }
.skW45 { width: 45%; }
.skW50 { width: 50%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── States ── */
.stateBox {
  display: grid;
  gap: 0.16rem;
}

.stateName {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--color-surface);
  line-height: 1.22;
}

.stateSub {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
}

.stateError {
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--color-danger, #f87171);
  line-height: 1.3;
}

.retryBtn {
  display: inline;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  color: rgb(var(--color-primary-rgb) / 0.85);
  font-size: 0.72rem;
  font-weight: 600;
  text-align: left;
}

.retryBtn:hover {
  color: var(--color-primary);
  text-decoration: underline;
}

/* ── Night body ── */
.nightBody {
  display: grid;
  gap: 0.24rem;
}

/* Planets */
.planetList {
  display: grid;
  gap: 0.18rem;
}

.planetRow {
  display: flex;
  align-items: baseline;
  gap: 0.28rem;
  min-width: 0;
}

.planetIcon {
  font-size: 0.78rem;
  flex-shrink: 0;
  line-height: 1;
}

.planetName {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--color-surface);
  line-height: 1.2;
  flex-shrink: 0;
}

.planetWindow {
  color: var(--color-text-secondary);
  font-size: 0.70rem;
  font-weight: 400;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}

.noPlanets {
  font-size: 0.78rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
}

/* Moon compact */
.moonLine {
  display: flex;
  align-items: baseline;
  gap: 0.22rem;
  font-size: 0.74rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
}

.moonLine .sep {
  opacity: 0.35;
  font-size: 0.66rem;
}

/* Conditions */
.conditionsLine {
  display: flex;
  align-items: baseline;
  gap: 0.22rem;
  font-size: 0.74rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
}
</style>
