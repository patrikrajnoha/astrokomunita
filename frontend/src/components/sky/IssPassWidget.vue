<template>
  <section v-if="showLoading || shouldRender" class="card panel issPass">
    <h3 class="panelTitle sidebarSection__header">ISS prelet</h3>

    <div v-if="showLoading" class="panelLoading">
      <div class="skeleton h-8 w-3/4"></div>
    </div>

    <div v-else class="passBody">
      <p class="passTime">{{ passHeadline }}</p>
      <p v-if="passDetailLine" class="passDetail">{{ passDetailLine }}</p>

      <SkyIssTrackerMap
        v-if="hasTracker"
        class="passMap"
        :tracker-lat="trackerLat"
        :tracker-lon="trackerLon"
        :tracker-sample-at="trackerSampleAt"
        :observer-lat="lat"
        :observer-lon="lon"
      />

      <p v-if="metaLine" class="sourceLine">{{ metaLine }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, toRef } from 'vue'
import { useSkyWidget } from '@/composables/useSkyWidget'
import SkyIssTrackerMap from '@/components/sky/SkyIssTrackerMap.vue'

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

const passDate = computed(() => {
  const value = String(issPreview.value?.next_pass_at || '').trim()
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
})

const hasVisiblePass = computed(() => {
  return Boolean(issPreview.value?.available) && passDate.value instanceof Date
})

const trackerLat = computed(() => toFiniteNumber(issPreview.value?.tracker?.lat))
const trackerLon = computed(() => toFiniteNumber(issPreview.value?.tracker?.lon))
const trackerSampleAt = computed(() => String(issPreview.value?.tracker?.sample_at || '').trim())
const hasTracker = computed(() => trackerLat.value !== null && trackerLon.value !== null)

const shouldRender = computed(() => hasLocationCoords.value)

const showLoading = computed(() => {
  if (!hasLocationCoords.value) return false
  if (hasVisiblePass.value || hasTracker.value) return false
  return issLoading.value
})

const passTimeLabel = computed(() => formatTime(passDate.value, effectiveTz.value))
const passHeadline = computed(() => {
  if (hasVisiblePass.value) {
    return `Najblizsi prelet: ${passTimeLabel.value}`
  }

  if (hasTracker.value) {
    return 'ISS tracker je aktivny'
  }

  if (issPreview.value?.available === false) {
    return 'Dnes bez viditelneho ISS preletu'
  }

  return 'ISS data docasne nedostupne'
})

const passDetailLine = computed(() => {
  const duration = toFiniteNumber(issPreview.value?.duration_sec)
  const maxAltitude = toFiniteNumber(issPreview.value?.max_altitude_deg)
  const directionStart = sanitizeDirection(issPreview.value?.direction_start)
  const directionEnd = sanitizeDirection(issPreview.value?.direction_end)

  const parts = []
  if (duration !== null) parts.push(`${Math.max(1, Math.round(duration / 60))} min`)
  if (maxAltitude !== null) parts.push(`max ${Math.round(maxAltitude)} deg`)
  if (directionStart && directionEnd) parts.push(`${directionStart} -> ${directionEnd}`)

  return parts.join(' | ')
})

const satelliteSourceLine = computed(() => {
  const satelliteSource = String(issPreview.value?.satellite?.source || '').trim().toLowerCase()
  const trackerSource = String(issPreview.value?.tracker?.source || '').trim().toLowerCase()

  const parts = []
  if (satelliteSource === 'celestrak_gp') parts.push('orbita: CelesTrak GP')
  if (trackerSource === 'iss_tracker') parts.push('tracker: WhereTheISS')

  return parts.join(' | ')
})

const metaLine = computed(() => {
  const parts = []

  if (satelliteSourceLine.value) {
    parts.push(`Zdroj: ${satelliteSourceLine.value}`)
  }

  if (issUpdatedLabel.value && issUpdatedLabel.value !== '-') {
    parts.push(`Aktualizovane: ${issUpdatedLabel.value}`)
  }

  return parts.join(' | ')
})

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

function sanitizeDirection(value) {
  const candidate = String(value || '').trim().toUpperCase()
  return ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'].includes(candidate) ? candidate : ''
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

.passBody {
  display: grid;
  gap: 0.22rem;
}

.passTime {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  line-height: 1.15;
  color: var(--color-surface);
}

.passDetail {
  margin: 0;
  font-size: 0.68rem;
  line-height: 1.25;
  color: var(--color-text-secondary);
}

.passMap {
  margin-top: 0.05rem;
}

.sourceLine {
  margin: 0;
  font-size: 0.66rem;
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
.w-3\/4 { width: 75%; }
</style>
