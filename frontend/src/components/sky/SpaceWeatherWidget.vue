<template>
  <section class="card panel">
    <h3 class="panelTitle sidebarSection__header">Vesmirne pocasie</h3>

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
        action-label="Skúsiť znova"
        @action="fetchPayload"
      />
    </section>

    <AsyncState
      v-else-if="!payload?.available"
      mode="empty"
      title="Space weather je nedostupne"
      message="NOAA SWPC data sa momentálne nepodarilo načítať."
      compact
    />

    <div v-else class="content">
      <div class="heroRow">
        <div>
          <p class="metricLabel">Planetarny Kp index</p>
          <p class="heroValue">{{ kpValueLabel }}</p>
        </div>
        <span class="scaleBadge">{{ noaaScaleLabel }}</span>
      </div>

      <div class="metricGrid">
        <article class="metricItem">
          <p class="metricLabel">Geomagneticka aktivita</p>
          <p class="metricValue">{{ geomagneticLevelLabel }}</p>
        </article>
        <article class="metricItem">
          <p class="metricLabel">Aurora watch</p>
          <p class="metricValue">{{ auroraLabel }}</p>
        </article>
      </div>

      <p v-if="auroraDetail" class="detailLine">{{ auroraDetail }}</p>
      <p class="sourceLine">Zdroj: NOAA SWPC | Aktualizovane: {{ updatedLabel }}</p>
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
const kpValueLabel = computed(() => {
  const kpIndex = toFiniteNumber(payload.value?.kp_index)
  const estimated = toFiniteNumber(payload.value?.estimated_kp)
  const value = kpIndex ?? estimated
  return value === null ? '-' : value.toFixed(1)
})
const noaaScaleLabel = computed(() => String(payload.value?.noaa_scale || 'Bez dat').trim() || 'Bez dat')
const geomagneticLevelLabel = computed(() => (
  String(payload.value?.geomagnetic_level || 'Nezname').trim() || 'Nezname'
))
const auroraLabel = computed(() => (
  String(payload.value?.aurora?.watch_label || 'Bez dat').trim() || 'Bez dat'
))
const auroraDetail = computed(() => {
  const score = toFiniteNumber(payload.value?.aurora?.watch_score)
  const forecastFor = formatTime(payload.value?.aurora?.forecast_for, effectiveTz.value)

  if (score === null) {
    return forecastFor === '-' ? '' : `Predikcia pre ${forecastFor}`
  }

  const base = `Skore severne od teba: ${Math.round(score)}/100`
  return forecastFor === '-' ? base : `${base} | Predikcia pre ${forecastFor}`
})
const updatedLabel = computed(() => formatTime(payload.value?.updated_at, effectiveTz.value))

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
    const response = await api.get('/sky/space-weather', {
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
      || 'Nepodarilo sa načítať vesmírne počasie.'
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

function formatTime(value, timeZone) {
  const raw = String(value || '').trim()
  if (!raw) return '-'

  const parsed = new Date(raw)
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

.scaleBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.5rem;
  padding: 0.22rem 0.44rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.4);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: var(--color-surface);
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1.1;
  border-radius: 999px;
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
