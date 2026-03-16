<template>
  <div class="trackerMap">
    <header class="mapTop">
      <div class="legend">
        <span class="legendItem">
          <span class="legendDot iss"></span>
          ISS
        </span>
        <span class="legendItem">
          <span class="legendDot observer"></span>
          Tvoja poloha
        </span>
      </div>
      <span v-if="distanceKm !== null" class="distanceBadge">{{ distanceLabel }}</span>
    </header>

    <div class="mapFrame">
      <div class="cardinal north">N</div>
      <div class="cardinal south">S</div>
      <div class="cardinal west">W</div>
      <div class="cardinal east">E</div>

      <svg
        class="mapSvg"
        viewBox="0 0 360 180"
        preserveAspectRatio="none"
        aria-hidden="true"
        xmlns="http://www.w3.org/2000/svg"
      >
        <defs>
          <marker id="iss-arrow" markerWidth="5" markerHeight="5" refX="4.2" refY="2.5" orient="auto">
            <path d="M0 0L5 2.5L0 5Z" class="pathArrow" />
          </marker>
        </defs>

        <!-- Ocean background -->
        <rect width="360" height="180" class="ocean" />

        <!-- Latitude/longitude grid -->
        <line x1="0" y1="30" x2="360" y2="30" class="gridLine" />
        <line x1="0" y1="60" x2="360" y2="60" class="gridLine" />
        <line x1="0" y1="90" x2="360" y2="90" class="gridLine equator" />
        <line x1="0" y1="120" x2="360" y2="120" class="gridLine" />
        <line x1="0" y1="150" x2="360" y2="150" class="gridLine" />
        <line x1="60" y1="0" x2="60" y2="180" class="gridLine" />
        <line x1="120" y1="0" x2="120" y2="180" class="gridLine" />
        <line x1="180" y1="0" x2="180" y2="180" class="gridLine prime" />
        <line x1="240" y1="0" x2="240" y2="180" class="gridLine" />
        <line x1="300" y1="0" x2="300" y2="180" class="gridLine" />

        <!-- ISS inclination limits ±51.6° -->
        <line x1="0" :y1="incLineN" x2="360" :y2="incLineN" class="incLine" />
        <line x1="0" :y1="incLineS" x2="360" :y2="incLineS" class="incLine" />

        <!-- Continent fills -->
        <path v-for="(d, i) in CONTINENT_PATHS" :key="i" :d="d" class="continent" />

        <!-- ISS orbital track (split segments for antimeridian wrapping) -->
        <polyline
          v-for="(seg, i) in orbitSegments"
          :key="`orbit-${i}`"
          :points="seg.map(([x, y]) => `${x.toFixed(1)} ${y.toFixed(1)}`).join(' ')"
          class="orbitTrack"
        />

        <!-- Connection line observer → ISS -->
        <line
          v-if="showTracker && showObserver"
          :x1="obsX"
          :y1="obsY"
          :x2="issX"
          :y2="issY"
          class="pathLine"
          marker-end="url(#iss-arrow)"
        />

        <!-- Observer dot -->
        <circle v-if="showObserver" :cx="obsX" :cy="obsY" r="2.5" class="observerDot" />

        <!-- ISS dot with glow -->
        <circle v-if="showTracker" :cx="issX" :cy="issY" r="4.5" class="issDotGlow" />
        <circle v-if="showTracker" :cx="issX" :cy="issY" r="2.8" class="issDot" />
      </svg>

      <div
        v-if="showObserver"
        class="marker observerMarker"
        :style="{ left: `${observerX}%`, top: `${observerY}%` }"
        title="Tvoja poloha"
      >
        <span class="markerLabel">TY</span>
      </div>

      <div
        v-if="showTracker"
        class="marker issMarker"
        :style="{ left: `${trackerX}%`, top: `${trackerY}%` }"
        :title="trackerTitle"
      >
        <span class="markerLabel">ISS</span>
      </div>
    </div>

    <div class="mapMeta">
      <p class="metaPrimary">{{ primaryLine }}</p>
      <p v-if="secondaryLine" class="metaSecondary">{{ secondaryLine }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Continent outlines in equirectangular SVG space:
//   x = lon + 180  (0–360)
//   y = 90 − lat   (0–180)
const CONTINENT_PATHS = [
  // North America
  'M 12 24 L 18 20 L 40 18 L 100 18 L 125 30 L 120 46 L 100 65 L 97 80 L 90 70 L 84 65 L 63 56 L 56 43 L 32 32 Z',
  // Greenland
  'M 132 29 L 129 14 L 154 7 L 162 15 L 156 30 Z',
  // South America
  'M 103 79 L 145 85 L 145 95 L 141 113 L 127 124 L 115 145 L 105 145 L 109 108 L 102 90 Z',
  // Europe
  'M 171 53 L 171 47 L 182 39 L 190 33 L 195 21 L 213 21 L 210 30 L 210 45 L 203 53 L 196 52 L 175 54 Z',
  // Africa
  'M 174 55 L 190 53 L 205 59 L 224 75 L 231 79 L 221 92 L 220 105 L 216 116 L 213 124 L 198 125 L 196 119 L 192 107 L 192 95 L 189 85 L 177 85 L 165 80 L 163 76 L 166 62 Z',
  // Asia (includes rough Arabian Peninsula, Indian subcontinent, SE Asia)
  'M 213 21 L 240 22 L 290 18 L 340 18 L 342 38 L 311 47 L 302 60 L 290 70 L 284 88 L 271 74 L 260 82 L 250 70 L 248 67 L 239 68 L 225 78 L 223 75 L 228 53 L 216 52 L 206 49 Z',
  // Australia
  'M 294 112 L 310 102 L 326 107 L 330 128 L 327 133 L 295 124 Z',
]

const ISS_INCLINATION_DEG = 51.6

const props = defineProps({
  trackerLat: { type: [Number, String], default: null },
  trackerLon: { type: [Number, String], default: null },
  observerLat: { type: [Number, String], default: null },
  observerLon: { type: [Number, String], default: null },
  trackerSampleAt: { type: String, default: '' },
})

const trackerLatNum = computed(() => toFiniteNumber(props.trackerLat))
const trackerLonNum = computed(() => toFiniteNumber(props.trackerLon))
const observerLatNum = computed(() => toFiniteNumber(props.observerLat))
const observerLonNum = computed(() => toFiniteNumber(props.observerLon))

const showTracker = computed(() => trackerLatNum.value !== null && trackerLonNum.value !== null)
const showObserver = computed(() => observerLatNum.value !== null && observerLonNum.value !== null)

// Percentage positions for CSS-positioned labels (0–100)
const trackerX = computed(() => projectLonPct(trackerLonNum.value))
const trackerY = computed(() => projectLatPct(trackerLatNum.value))
const observerX = computed(() => projectLonPct(observerLonNum.value))
const observerY = computed(() => projectLatPct(observerLatNum.value))

// SVG positions in viewBox 0 0 360 180 — consistent with percentage positions above
const issX = computed(() => trackerLonNum.value !== null ? trackerLonNum.value + 180 : 180)
const issY = computed(() => trackerLatNum.value !== null ? 90 - trackerLatNum.value : 90)
const obsX = computed(() => observerLonNum.value !== null ? observerLonNum.value + 180 : 180)
const obsY = computed(() => observerLatNum.value !== null ? 90 - observerLatNum.value : 90)

// ISS inclination limit lines (y-coordinates in SVG space)
const incLineN = 90 - ISS_INCLINATION_DEG  // 38.4
const incLineS = 90 + ISS_INCLINATION_DEG  // 141.6

// ISS orbital ground track for one full orbit
const orbitSegments = computed(() => {
  if (!showTracker.value) return []
  return computeOrbitSegments(trackerLatNum.value, trackerLonNum.value)
})

const distanceKm = computed(() => {
  if (!showTracker.value || !showObserver.value) return null
  return greatCircleKm(observerLatNum.value, observerLonNum.value, trackerLatNum.value, trackerLonNum.value)
})

const bearingDeg = computed(() => {
  if (!showTracker.value || !showObserver.value) return null
  return initialBearing(observerLatNum.value, observerLonNum.value, trackerLatNum.value, trackerLonNum.value)
})

const directionLabel = computed(() => {
  if (!Number.isFinite(bearingDeg.value)) return ''
  return bearingToCompass(bearingDeg.value)
})

const trackerCoordLabel = computed(() => {
  if (!showTracker.value) return '-'
  return formatCoordinatePair(trackerLatNum.value, trackerLonNum.value)
})

const distanceLabel = computed(() => {
  if (!Number.isFinite(distanceKm.value)) return ''
  return `~${formatDistance(distanceKm.value)} km`
})

const sampleTimeLabel = computed(() => formatSample(props.trackerSampleAt))

const primaryLine = computed(() => {
  if (showTracker.value && showObserver.value) {
    const direction = directionLabel.value ? `${directionLabel.value} | ` : ''
    return `ISS je ${direction}${distanceLabel.value} od teba`
  }
  if (showTracker.value) {
    return `ISS poloha: ${trackerCoordLabel.value}`
  }
  return 'Live poloha ISS je docasne nedostupna.'
})

const secondaryLine = computed(() => {
  if (!showTracker.value) return ''
  const sample = sampleTimeLabel.value
  return sample ? `Snimka: ${sample} | ${trackerCoordLabel.value}` : trackerCoordLabel.value
})

const trackerTitle = computed(() => `ISS tracker: ${trackerCoordLabel.value}`)

// --- ISS orbit track computation ---
function computeOrbitSegments(lat0, lon0) {
  const INC = ISS_INCLINATION_DEG * Math.PI / 180
  const lat0Rad = lat0 * Math.PI / 180

  const sinU0 = Math.sin(lat0Rad) / Math.sin(INC)
  if (Math.abs(sinU0) > 1) return []

  const u0 = Math.asin(Math.max(-1, Math.min(1, sinU0)))
  const deltaLon0 = Math.atan2(Math.cos(INC) * Math.sin(u0), Math.cos(u0)) * 180 / Math.PI
  const raan = lon0 - deltaLon0

  const N = 120
  const segments = [[]]

  for (let i = 0; i <= N; i++) {
    const u = u0 + (i / N) * 2 * Math.PI
    const latRad = Math.asin(Math.sin(INC) * Math.sin(u))
    const lat = latRad * 180 / Math.PI
    const deltaLon = Math.atan2(Math.cos(INC) * Math.sin(u), Math.cos(u)) * 180 / Math.PI
    const lon = raan + deltaLon

    const x = ((lon + 180) % 360 + 360) % 360
    const y = 90 - lat

    const seg = segments[segments.length - 1]
    const last = seg.length > 0 ? seg[seg.length - 1] : null

    // Break polyline at antimeridian crossing to avoid horizontal wrap-around lines
    if (last && Math.abs(x - last[0]) > 180) {
      segments.push([[x, y]])
    } else {
      seg.push([x, y])
    }
  }

  return segments.filter(s => s.length > 1)
}

// --- Projection helpers ---
function projectLonPct(value) {
  if (!Number.isFinite(value)) return 50
  return Math.max(4, Math.min(96, ((value + 180) / 360) * 100))
}

function projectLatPct(value) {
  if (!Number.isFinite(value)) return 50
  return Math.max(4, Math.min(96, ((90 - value) / 180) * 100))
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function formatSample(value) {
  const parsed = new Date(String(value || '').trim())
  if (Number.isNaN(parsed.getTime())) return ''
  return new Intl.DateTimeFormat('sk-SK', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(parsed)
}

function formatCoordinatePair(lat, lon) {
  return `${formatLat(lat)}, ${formatLon(lon)}`
}

function formatLat(value) {
  const suffix = value >= 0 ? 'N' : 'S'
  return `${Math.abs(value).toFixed(2)}${suffix}`
}

function formatLon(value) {
  const suffix = value >= 0 ? 'E' : 'W'
  return `${Math.abs(value).toFixed(2)}${suffix}`
}

function formatDistance(value) {
  if (!Number.isFinite(value)) return '-'
  if (value < 1000) return String(Math.round(value))
  return `${(value / 1000).toFixed(1)}k`
}

function greatCircleKm(lat1, lon1, lat2, lon2) {
  const R = 6371
  const phi1 = toRadians(lat1)
  const phi2 = toRadians(lat2)
  const dPhi = toRadians(lat2 - lat1)
  const dLambda = toRadians(lon2 - lon1)
  const a = Math.sin(dPhi / 2) ** 2 + Math.cos(phi1) * Math.cos(phi2) * Math.sin(dLambda / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

function initialBearing(lat1, lon1, lat2, lon2) {
  const phi1 = toRadians(lat1)
  const phi2 = toRadians(lat2)
  const lambda1 = toRadians(lon1)
  const lambda2 = toRadians(lon2)
  const y = Math.sin(lambda2 - lambda1) * Math.cos(phi2)
  const x = Math.cos(phi1) * Math.sin(phi2) - Math.sin(phi1) * Math.cos(phi2) * Math.cos(lambda2 - lambda1)
  return (toDegrees(Math.atan2(y, x)) + 360) % 360
}

function bearingToCompass(value) {
  const sectors = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']
  return sectors[Math.round(((value % 360) + 360) % 360 / 45) % 8]
}

function toRadians(value) { return (value * Math.PI) / 180 }
function toDegrees(value) { return (value * 180) / Math.PI }
</script>

<style scoped>
.trackerMap {
  display: grid;
  gap: 0.34rem;
  border: 1px solid var(--divider-color);
  border-radius: 0.62rem;
  padding: 0.38rem;
  background: rgb(var(--color-bg-rgb) / 0.35);
}

.mapTop {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.4rem;
}

.legend {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.36rem;
}

.legendItem {
  display: inline-flex;
  align-items: center;
  gap: 0.22rem;
  font-size: 0.62rem;
  color: var(--color-text-secondary);
}

.legendDot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.95);
}

.legendDot.iss {
  background: rgb(var(--color-primary-rgb) / 0.95);
}

.legendDot.observer {
  background: rgb(var(--color-surface-rgb) / 0.88);
}

.distanceBadge {
  font-size: 0.62rem;
  font-weight: 700;
  color: var(--color-surface);
  padding: 0.12rem 0.34rem;
  border-radius: 999px;
  background: rgb(var(--color-primary-rgb) / 0.18);
  border: 1px solid rgb(var(--color-primary-rgb) / 0.42);
}

.mapFrame {
  position: relative;
  aspect-ratio: 2;
  border-radius: 0.54rem;
  overflow: hidden;
}

.cardinal {
  position: absolute;
  z-index: 4;
  font-size: 0.58rem;
  font-weight: 700;
  color: rgb(var(--color-surface-rgb) / 0.6);
  text-shadow: 0 1px 1px rgb(var(--color-bg-rgb) / 0.7);
  pointer-events: none;
}

.cardinal.north { left: 50%; top: 0.24rem; transform: translateX(-50%); }
.cardinal.south { left: 50%; bottom: 0.24rem; transform: translateX(-50%); }
.cardinal.west  { left: 0.24rem; top: 50%; transform: translateY(-50%); }
.cardinal.east  { right: 0.24rem; top: 50%; transform: translateY(-50%); }

/* --- SVG layers --- */

.mapSvg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.ocean {
  fill: rgb(6 18 36 / 0.96);
}

.gridLine {
  stroke: rgb(var(--color-text-secondary-rgb) / 0.14);
  stroke-width: 0.4;
  fill: none;
}

.gridLine.equator {
  stroke: rgb(var(--color-text-secondary-rgb) / 0.28);
}

.gridLine.prime {
  stroke: rgb(var(--color-text-secondary-rgb) / 0.22);
}

.incLine {
  stroke: rgb(var(--color-primary-rgb) / 0.18);
  stroke-width: 0.5;
  stroke-dasharray: 4 3;
  fill: none;
}

.continent {
  fill: rgb(var(--color-surface-rgb) / 0.14);
  stroke: rgb(var(--color-surface-rgb) / 0.38);
  stroke-width: 0.55;
  stroke-linejoin: round;
}

.orbitTrack {
  fill: none;
  stroke: rgb(var(--color-primary-rgb) / 0.55);
  stroke-width: 0.7;
  stroke-dasharray: 3 2;
  stroke-linecap: round;
}

.pathLine {
  stroke: rgb(var(--color-primary-rgb) / 0.75);
  stroke-width: 0.8;
  stroke-dasharray: 3 1.5;
  fill: none;
}

.pathArrow {
  fill: rgb(var(--color-primary-rgb) / 0.9);
}

.issDotGlow {
  fill: rgb(var(--color-primary-rgb) / 0.25);
}

.issDot {
  fill: rgb(var(--color-primary-rgb) / 1);
}

.observerDot {
  fill: rgb(var(--color-surface-rgb) / 0.85);
  stroke: rgb(var(--color-surface-rgb) / 0.5);
  stroke-width: 0.6;
}

/* --- CSS-positioned label markers --- */

.marker {
  position: absolute;
  transform: translate(-50%, -50%);
  z-index: 5;
  pointer-events: none;
}

.markerLabel {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.48rem;
  height: 1.12rem;
  padding: 0 0.3rem;
  font-size: 0.54rem;
  line-height: 1;
  font-weight: 700;
  border-radius: 0.42rem;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.9);
  color: var(--color-surface);
  text-shadow: 0 1px 1px rgb(var(--color-bg-rgb) / 0.7);
}

.issMarker .markerLabel {
  background: rgb(var(--color-primary-rgb) / 0.92);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
}

.observerMarker .markerLabel {
  background: rgb(var(--color-surface-rgb) / 0.24);
  box-shadow: 0 0 0 3px rgb(var(--color-surface-rgb) / 0.12);
}

/* --- Meta text --- */

.mapMeta {
  display: grid;
  gap: 0.08rem;
}

.metaPrimary,
.metaSecondary {
  margin: 0;
  font-size: 0.64rem;
  line-height: 1.2;
}

.metaPrimary {
  color: var(--color-surface);
  font-weight: 700;
}

.metaSecondary {
  color: var(--color-text-secondary);
}
</style>
