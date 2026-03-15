<template>
  <section class="card panel">
    <h3 class="panelTitle sidebarSection__header">Aurora watch</h3>

    <AsyncState
      v-if="showMissingLocation"
      mode="empty"
      title="Poloha nie je nastavena"
      message="Nastav polohu pre lokalny aurora watch."
      compact
    />

    <div v-else-if="loading" class="panelLoading">
      <div class="skeleton h-8 w-full"></div>
      <div class="skeleton h-8 w-full"></div>
    </div>

    <section v-else-if="error" class="state stateError">
      <InlineStatus
        variant="error"
        :message="error"
        action-label="Skusit znova"
        @action="fetchPayload"
      />
    </section>

    <AsyncState
      v-else-if="!payload?.available"
      mode="empty"
      title="Aurora watch je nedostupny"
      message="NOAA OVATION forecast sa momentalne nepodarilo nacitat."
      compact
    />

    <div v-else class="content">
      <div class="heroRow">
        <div>
          <p class="metricLabel">Lokalna sanca</p>
          <p class="heroValue">{{ watchLabel }}</p>
        </div>
        <span class="scoreBadge" :class="scoreToneClass">{{ watchScoreLabel }}</span>
      </div>

      <div class="metricGrid">
        <article class="metricItem">
          <p class="metricLabel">Predikcia pre</p>
          <p class="metricValue">
            <time v-if="forecastDateTime" :datetime="forecastDateTime">{{ forecastLabel }}</time>
            <span v-else>{{ forecastLabel }}</span>
          </p>
        </article>
        <article class="metricItem">
          <p class="metricLabel">Model</p>
          <p class="metricValue">{{ inferenceLabel }}</p>
        </article>
      </div>

      <p v-if="detailLine" class="detailLine">{{ detailLine }}</p>
      <p class="sourceLine">
        Zdroj: {{ sourceLabel }} | Aktualizovane:
        <time v-if="updatedDateTime" :datetime="updatedDateTime">{{ updatedLabel }}</time>
        <span v-else>{{ updatedLabel }}</span>
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import api from '@/services/api'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  tz: { type: String, default: 'Europe/Bratislava' },
  initialPayload: { type: Object, default: undefined },
  bundlePending: { type: Boolean, default: false },
})

const payload = ref(null)
const loading = ref(true)
const error = ref('')
const hydratedFromBundle = ref(false)

const numericLat = computed(() => toFiniteCoordinate(props.lat, -90, 90))
const numericLon = computed(() => toFiniteCoordinate(props.lon, -180, 180))
const effectiveTz = computed(() => {
  const candidate = String(props.tz || '').trim()
  return candidate || 'Europe/Bratislava'
})
const showMissingLocation = computed(() => numericLat.value === null || numericLon.value === null)
const sourceLabel = computed(() => String(payload.value?.source?.label || 'NOAA SWPC OVATION').trim() || 'NOAA SWPC OVATION')
const watchLabel = computed(() => String(payload.value?.watch_label || 'Bez dat').trim() || 'Bez dat')
const watchScore = computed(() => toFiniteNumber(payload.value?.watch_score))
const watchScoreLabel = computed(() => {
  const score = watchScore.value
  return score === null ? '-' : `${Math.round(score)}/100`
})
const scoreToneClass = computed(() => {
  const score = watchScore.value

  if (score === null) return 'is-muted'
  if (score >= 70) return 'is-high'
  if (score >= 40) return 'is-medium'
  if (score >= 15) return 'is-low'
  return 'is-muted'
})
const forecastDateTime = computed(() => normalizeTimestamp(payload.value?.forecast_for))
const forecastLabel = computed(() => formatDateTime(forecastDateTime.value, effectiveTz.value))
const updatedDateTime = computed(() => normalizeTimestamp(payload.value?.updated_at || payload.value?.observed_at))
const updatedLabel = computed(() => formatTime(updatedDateTime.value, effectiveTz.value))
const inferenceLabel = computed(() => {
  const inference = String(payload.value?.inference || '').trim()
  if (inference === 'poleward_corridor_peak') return 'Severny koridor'
  return inference || 'NOAA grid'
})
const detailLine = computed(() => {
  const corridorScore = toFiniteNumber(payload.value?.corridor_peak_score)
  const nearestScore = toFiniteNumber(payload.value?.nearest_score)
  const parts = []

  if (corridorScore !== null) {
    parts.push(`Koridor severne od teba: ${Math.round(corridorScore)}/100`)
  }

  if (nearestScore !== null) {
    parts.push(`Najblizsia bunka: ${Math.round(nearestScore)}/100`)
  }

  return parts.join(' | ')
})

async function fetchPayload() {
  if (showMissingLocation.value) {
    payload.value = null
    loading.value = false
    error.value = ''
    return
  }

  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/sky/aurora', {
      params: {
        lat: numericLat.value,
        lon: numericLon.value,
        tz: effectiveTz.value,
      },
      meta: { skipErrorToast: true },
    })

    payload.value = response?.data || null
  } catch (requestError) {
    payload.value = null
    error.value = (
      requestError?.response?.data?.message
      || requestError?.message
      || 'Nepodarilo sa nacitat aurora watch.'
    )
  } finally {
    loading.value = false
  }
}

function applyPayload(nextPayload) {
  payload.value = nextPayload && typeof nextPayload === 'object' ? nextPayload : null
  error.value = ''
  loading.value = false
  hydratedFromBundle.value = true
}

watch(
  () => props.initialPayload,
  (nextPayload) => {
    if (nextPayload !== undefined) {
      applyPayload(nextPayload)
    }
  },
  { immediate: true },
)

watch(
  () => props.bundlePending,
  (pending, wasPending) => {
    if (pending || !wasPending || hydratedFromBundle.value) return
    fetchPayload()
  },
)

watch(
  () => [numericLat.value, numericLon.value, effectiveTz.value],
  () => {
    if (props.initialPayload !== undefined || props.bundlePending) {
      if (props.bundlePending && props.initialPayload === undefined) {
        loading.value = true
      }
      return
    }

    fetchPayload()
  },
  { immediate: true },
)

function toFiniteCoordinate(value, min, max) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value !== 'string') return null
  const normalized = value.trim()
  if (!normalized) return null
  const parsed = Number(normalized)
  if (!Number.isFinite(parsed)) return null
  if (parsed < min || parsed > max) return null
  return parsed
}

function toFiniteNumber(value) {
  return Number.isFinite(Number(value)) ? Number(value) : null
}

function normalizeTimestamp(value) {
  const raw = String(value || '').trim()
  if (!raw) return ''

  const parsed = new Date(raw)
  return Number.isNaN(parsed.getTime()) ? '' : parsed.toISOString()
}

function formatDateTime(value, timeZone) {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  }
}

function formatTime(value, timeZone) {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed)
  }
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
  margin: 0;
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
}

.content {
  display: grid;
  gap: 0.28rem;
}

.heroRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.metricGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.24rem;
}

.metricItem {
  border: 1px solid var(--divider-color);
  border-radius: 0.56rem;
  background: rgb(var(--color-bg-rgb) / 0.22);
  padding: 0.34rem 0.4rem;
  min-width: 0;
}

.metricLabel,
.metricValue,
.heroValue,
.detailLine,
.sourceLine {
  margin: 0;
}

.metricLabel {
  font-size: 0.64rem;
  color: var(--color-text-secondary);
}

.metricValue,
.heroValue {
  margin-top: 0.08rem;
  color: var(--color-surface);
  font-weight: 700;
  line-height: 1.2;
}

.metricValue {
  font-size: 0.77rem;
}

.heroValue {
  font-size: 1rem;
}

.scoreBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 3.5rem;
  padding: 0.22rem 0.44rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.4);
  color: var(--color-surface);
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1.1;
  border-radius: 999px;
}

.scoreBadge.is-high {
  background: rgb(72 187 120 / 0.18);
  border-color: rgb(72 187 120 / 0.45);
}

.scoreBadge.is-medium {
  background: rgb(245 158 11 / 0.18);
  border-color: rgb(245 158 11 / 0.45);
}

.scoreBadge.is-low {
  background: rgb(96 165 250 / 0.18);
  border-color: rgb(96 165 250 / 0.45);
}

.scoreBadge.is-muted {
  background: rgb(var(--color-primary-rgb) / 0.14);
  border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.detailLine,
.sourceLine {
  font-size: 0.68rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
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
.w-full { width: 100%; }
</style>
