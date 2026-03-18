<template>
  <section v-if="showLoading || shouldRender" class="panel issPass">
    <h3 class="panelTitle sidebarSection__header">ISS prelet</h3>

    <div v-if="showLoading" class="skeletonStack">
      <div class="skeleton skW65"></div>
      <div class="skeleton skW40"></div>
    </div>

    <template v-else>
      <!-- Hero: direction headline + prominent distance + qualitative context -->
      <div v-if="hasTracker" class="issHero">
        <div class="issHeadline">{{ trackerHeadline }}</div>
        <div v-if="distancePrimaryLabel" class="issDistance">{{ distancePrimaryLabel }}</div>
        <div v-if="distanceContextLabel" class="issContext">{{ distanceContextLabel }}</div>
      </div>

      <!-- Fallback when no tracker data -->
      <p v-else class="issFallback">{{ fallbackHeadline }}</p>

      <!-- Visibility hint -->
      <p
        v-if="visibilityHint"
        class="visibilityHint"
        :class="visibilityAvailable ? 'visibilityHintOn' : 'visibilityHintOff'"
      >{{ visibilityHint }}</p>

      <!-- Next visible pass -->
      <div v-if="hasVisiblePass" class="passRow">
        <span class="passRowLabel">Viditeľný prelet</span>
        <span class="passRowValue">
          {{ passTimeLabel }}<span v-if="durationLabel" class="passDuration"> · {{ durationLabel }}</span>
        </span>
      </div>

      <!-- Map -->
      <SkyIssTrackerMap
        v-if="hasTracker"
        class="passMap"
        :tracker-lat="trackerLat"
        :tracker-lon="trackerLon"
        :tracker-sample-at="trackerSampleAt"
        :observer-lat="lat"
        :observer-lon="lon"
      />

      <!-- Footer -->
      <p v-if="updatedFooter" class="metaLine">{{ updatedFooter }}</p>
    </template>
  </section>
</template>

<script setup>
import { computed, toRef } from 'vue'
import { useSkyWidget } from '@/composables/useSkyWidget'
import SkyIssTrackerMap from '@/components/sky/SkyIssTrackerMap.vue'

const DISTANCE_FORMAT = new Intl.NumberFormat('sk-SK', { maximumFractionDigits: 0 })

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  date: { type: String, default: '' },
  tz: { type: String, default: '' },
  initialPayload: { type: Object, default: undefined },
  bundlePending: { type: Boolean, default: false },
})

const {
  issPreview,
  issLoading,
  hasLocationCoords,
  effectiveTz,
  issUpdatedLabel,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
  initialPayload: toRef(props, 'initialPayload'),
  bundlePending: toRef(props, 'bundlePending'),
  includeWeather: false,
  includeAstronomy: false,
  includePlanets: false,
  includeLightPollution: false,
})

// ── Observer ──────────────────────────────────────────────────────────────────

const observerLatNum = computed(() => toFiniteNumber(props.lat))
const observerLonNum = computed(() => toFiniteNumber(props.lon))
const hasObserver = computed(() => observerLatNum.value !== null && observerLonNum.value !== null)

// ── ISS tracker ───────────────────────────────────────────────────────────────

const trackerLat = computed(() => toFiniteNumber(issPreview.value?.tracker?.lat))
const trackerLon = computed(() => toFiniteNumber(issPreview.value?.tracker?.lon))
const trackerSampleAt = computed(() => String(issPreview.value?.tracker?.sample_at || '').trim())
const hasTracker = computed(() => trackerLat.value !== null && trackerLon.value !== null)

// ── Distance and direction ────────────────────────────────────────────────────

const distanceKm = computed(() => {
  if (!hasTracker.value || !hasObserver.value) return null
  return greatCircleKm(
    observerLatNum.value,
    observerLonNum.value,
    trackerLat.value,
    trackerLon.value,
  )
})

const trackerHeadline = computed(() => {
  const d = distanceKm.value
  if (hasObserver.value && d !== null && d < 500) return 'ISS blízko tvojej polohy'
  if (!hasTracker.value) return 'ISS'
  if (hasObserver.value) {
    const bearing = initialBearing(
      observerLatNum.value, observerLonNum.value,
      trackerLat.value, trackerLon.value,
    )
    if (Number.isFinite(bearing)) return `ISS ${bearingToLocative(bearing)}`
  }
  return 'ISS'
})

const distancePrimaryLabel = computed(() => {
  const d = distanceKm.value
  if (d === null) return ''
  return `${DISTANCE_FORMAT.format(Math.round(d))} km`
})

// Qualitative distance context — skip for "nad tebou" (< 500 km), headline already says it
const distanceContextLabel = computed(() => {
  const d = distanceKm.value
  if (d === null || d < 500) return ''
  if (d < 2000) return 'Blízko'
  return 'Ďaleko'
})

// ── Visibility hint ────────────────────────────────────────────────────────────

const visibilityAvailable = computed(() => issPreview.value?.available === true)

const visibilityHint = computed(() => {
  if (!hasTracker.value) return ''
  const av = issPreview.value?.available
  if (av === true) return 'Prelet viditeľný voľným okom'
  if (av === false) return 'Dnes bez viditeľného preletu'
  return ''
})

// ── Next pass ─────────────────────────────────────────────────────────────────

const passDate = computed(() => {
  const value = String(issPreview.value?.next_pass_at || '').trim()
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
})

const hasVisiblePass = computed(() => (
  Boolean(issPreview.value?.available) && passDate.value instanceof Date
))

const passTimeLabel = computed(() => formatTime(passDate.value, effectiveTz.value))

const durationLabel = computed(() => {
  const sec = toFiniteNumber(issPreview.value?.duration_sec)
  if (sec === null) return ''
  return `${Math.max(1, Math.round(sec / 60))} min`
})

// ── Fallback text when no tracker ─────────────────────────────────────────────

const fallbackHeadline = computed(() => {
  if (issPreview.value?.available === false) return 'Dnes bez viditeľného ISS preletu'
  return 'ISS dáta dočasne nedostupné'
})

// ── Render gates ──────────────────────────────────────────────────────────────

const shouldRender = computed(() => hasLocationCoords.value)

const showLoading = computed(() => {
  if (!hasLocationCoords.value) return false
  if (hasVisiblePass.value || hasTracker.value) return false
  return issLoading.value
})

// ── Footer ────────────────────────────────────────────────────────────────────

const updatedFooter = computed(() => {
  const label = issUpdatedLabel.value
  if (!label || label === '-') return ''
  return `Aktualizované ${label}`
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatTime(value, timeZone) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return '-'
  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(value)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(value)
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

function greatCircleKm(lat1, lon1, lat2, lon2) {
  const R = 6371
  const phi1 = toRad(lat1)
  const phi2 = toRad(lat2)
  const dPhi = toRad(lat2 - lat1)
  const dL = toRad(lon2 - lon1)
  const a = Math.sin(dPhi / 2) ** 2 + Math.cos(phi1) * Math.cos(phi2) * Math.sin(dL / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

function initialBearing(lat1, lon1, lat2, lon2) {
  const phi1 = toRad(lat1)
  const phi2 = toRad(lat2)
  const y = Math.sin(toRad(lon2 - lon1)) * Math.cos(phi2)
  const x = Math.cos(phi1) * Math.sin(phi2) - Math.sin(phi1) * Math.cos(phi2) * Math.cos(toRad(lon2 - lon1))
  return (toDeg(Math.atan2(y, x)) + 360) % 360
}

function bearingToLocative(value) {
  const locatives = [
    'na severe',        // N   0°
    'na severovýchode', // NE 45°
    'na východe',       // E  90°
    'na juhovýchode',   // SE 135°
    'na juhu',          // S  180°
    'na juhozápade',    // SW 225°
    'na západe',        // W  270°
    'na severozápade',  // NW 315°
  ]
  return locatives[Math.round(((value % 360) + 360) % 360 / 45) % 8]
}

function toRad(v) { return (v * Math.PI) / 180 }
function toDeg(v) { return (v * 180) / Math.PI }
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.5rem;
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
  gap: 0.3rem;
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

.skW65 { width: 65%; }
.skW40 { width: 40%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Hero: direction → distance → context ── */
.issHero {
  display: grid;
  row-gap: 0;
}

.issHeadline {
  color: var(--color-surface);
  font-size: 0.88rem;
  font-weight: 600;
  line-height: 1.2;
  opacity: 0.85;
}

.issDistance {
  color: var(--color-surface);
  font-size: 1.56rem;
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.025em;
  margin-top: 0.06rem;
}

.issContext {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  font-weight: 500;
  line-height: 1.2;
  margin-top: 0.1rem;
}

/* ── Fallback ── */
.issFallback {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.82rem;
  line-height: 1.3;
}

/* ── Visibility hint ── */
.visibilityHint {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 500;
  line-height: 1.2;
}

.visibilityHintOn {
  color: rgb(var(--color-primary-rgb) / 0.8);
}

.visibilityHintOff {
  color: var(--color-text-secondary);
  opacity: 0.65;
}

/* ── Pass row ── */
.passRow {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  min-width: 0;
}

.passRowLabel {
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  line-height: 1.2;
  flex-shrink: 0;
}

.passRowValue {
  color: var(--color-surface);
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.2;
  min-width: 0;
}

.passDuration {
  color: var(--color-text-secondary);
  font-weight: 400;
}

/* ── Map ── */
.passMap {
  margin-top: 0.1rem;
}

/* ── Footer ── */
.metaLine {
  margin: 0;
  margin-top: 0.3rem;
  font-size: 0.67rem;
  color: var(--color-text-secondary);
  line-height: 1.2;
  opacity: 0.55;
}
</style>
