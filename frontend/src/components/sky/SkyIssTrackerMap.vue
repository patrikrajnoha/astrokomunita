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

      <div class="mapBackground" aria-hidden="true"></div>
      <div class="mapGrid" aria-hidden="true"></div>

      <svg class="mapOverlay" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <defs>
          <marker id="iss-arrow" markerWidth="5" markerHeight="5" refX="4.2" refY="2.5" orient="auto">
            <path d="M0 0L5 2.5L0 5Z" class="pathArrow" />
          </marker>
        </defs>

        <line
          v-if="showTracker && showObserver"
          :x1="observerX"
          :y1="observerY"
          :x2="trackerX"
          :y2="trackerY"
          class="pathLine"
          marker-end="url(#iss-arrow)"
        />
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

const trackerX = computed(() => projectLon(trackerLonNum.value))
const trackerY = computed(() => projectLat(trackerLatNum.value))
const observerX = computed(() => projectLon(observerLonNum.value))
const observerY = computed(() => projectLat(observerLatNum.value))

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

function projectLon(value) {
  if (!Number.isFinite(value)) return 50
  return Math.max(4, Math.min(96, ((value + 180) / 360) * 100))
}

function projectLat(value) {
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
  const earthRadiusKm = 6371
  const phi1 = toRadians(lat1)
  const phi2 = toRadians(lat2)
  const dPhi = toRadians(lat2 - lat1)
  const dLambda = toRadians(lon2 - lon1)

  const a = Math.sin(dPhi / 2) ** 2
    + Math.cos(phi1) * Math.cos(phi2) * Math.sin(dLambda / 2) ** 2
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))

  return earthRadiusKm * c
}

function initialBearing(lat1, lon1, lat2, lon2) {
  const phi1 = toRadians(lat1)
  const phi2 = toRadians(lat2)
  const lambda1 = toRadians(lon1)
  const lambda2 = toRadians(lon2)

  const y = Math.sin(lambda2 - lambda1) * Math.cos(phi2)
  const x = Math.cos(phi1) * Math.sin(phi2)
    - Math.sin(phi1) * Math.cos(phi2) * Math.cos(lambda2 - lambda1)

  const theta = Math.atan2(y, x)
  return (toDegrees(theta) + 360) % 360
}

function bearingToCompass(value) {
  const sectors = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']
  const normalized = ((value % 360) + 360) % 360
  const index = Math.round(normalized / 45) % 8
  return sectors[index]
}

function toRadians(value) {
  return (value * Math.PI) / 180
}

function toDegrees(value) {
  return (value * 180) / Math.PI
}
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
  min-height: 7.9rem;
  border-radius: 0.54rem;
  overflow: hidden;
}

.cardinal {
  position: absolute;
  z-index: 4;
  font-size: 0.58rem;
  font-weight: 700;
  color: rgb(var(--color-surface-rgb) / 0.8);
  text-shadow: 0 1px 1px rgb(var(--color-bg-rgb) / 0.7);
}

.cardinal.north {
  left: 50%;
  top: 0.24rem;
  transform: translateX(-50%);
}

.cardinal.south {
  left: 50%;
  bottom: 0.24rem;
  transform: translateX(-50%);
}

.cardinal.west {
  left: 0.24rem;
  top: 50%;
  transform: translateY(-50%);
}

.cardinal.east {
  right: 0.24rem;
  top: 50%;
  transform: translateY(-50%);
}

.mapBackground {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(80% 100% at 12% 18%, rgb(var(--color-primary-rgb) / 0.24), transparent 58%),
    radial-gradient(70% 85% at 90% 80%, rgb(var(--color-success-rgb) / 0.16), transparent 62%),
    linear-gradient(160deg, rgb(var(--color-bg-rgb) / 0.95), rgb(var(--color-bg-rgb) / 0.8));
}

.mapGrid {
  position: absolute;
  inset: 0;
  opacity: 0.34;
  background-image:
    linear-gradient(rgb(var(--color-text-secondary-rgb) / 0.26) 1px, transparent 1px),
    linear-gradient(90deg, rgb(var(--color-text-secondary-rgb) / 0.26) 1px, transparent 1px);
  background-size: 20px 20px;
}

.mapOverlay {
  position: absolute;
  inset: 0;
}

.pathLine {
  stroke: rgb(var(--color-primary-rgb) / 0.8);
  stroke-width: 0.65;
  stroke-dasharray: 3 1.5;
}

.pathArrow {
  fill: rgb(var(--color-primary-rgb) / 0.9);
}

.marker {
  position: absolute;
  transform: translate(-50%, -50%);
  z-index: 5;
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
