<template>
  <section class="card panel">
    <h3 class="panelTitle sidebarSection__header">Aurora watch</h3>

    <AsyncState
      v-if="showMissingLocation"
      mode="empty"
      title="Poloha nie je nastavená"
      message="Nastav polohu pre lokálny aurora watch."
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
      title="Aurora watch je nedostupný"
      message="NOAA OVATION forecast sa momentálne nepodarilo načítať."
      compact
    />

    <div v-else class="aurora-content">
      <div class="aurora-headline" :class="scoreToneClass">
        <svg class="aurora-icon" viewBox="0 0 22 12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
          <path d="M1 6 Q5.5 1 11 6 Q16.5 11 21 6"/>
          <path d="M3 9.5 Q7 5.5 11 9 Q15 12.5 19 9.5" stroke-width="1" opacity="0.5"/>
          <path d="M2 2.5 Q6 -0.5 11 2.5 Q16 5.5 20 2.5" stroke-width="1" opacity="0.3"/>
        </svg>
        <span>{{ watchLabel }}</span>
      </div>

      <p class="aurora-score">{{ watchScoreLabel }}</p>

      <p v-if="contextLabel" class="aurora-context">{{ contextLabel }}</p>

      <p v-if="updatedLabel !== '-'" class="widget-footer">Aktualizované {{ updatedLabel }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
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
let pendingAbort = null

const numericLat = computed(() => toFiniteCoordinate(props.lat, -90, 90))
const numericLon = computed(() => toFiniteCoordinate(props.lon, -180, 180))
const effectiveTz = computed(() => {
  const candidate = String(props.tz || '').trim()
  return candidate || 'Europe/Bratislava'
})
const showMissingLocation = computed(() => numericLat.value === null || numericLon.value === null)
const watchLabel = computed(() => String(payload.value?.watch_label || 'Bez dát').trim() || 'Bez dát')
const watchScore = computed(() => toFiniteNumber(payload.value?.watch_score))
const watchScoreLabel = computed(() => {
  const score = watchScore.value
  return score === null ? '— / 100' : `${Math.round(score)} / 100`
})
const scoreToneClass = computed(() => {
  const score = watchScore.value

  if (score === null) return 'is-muted'
  if (score >= 70) return 'is-high'
  if (score >= 40) return 'is-medium'
  if (score >= 15) return 'is-low'
  return 'is-muted'
})
const updatedDateTime = computed(() => normalizeTimestamp(payload.value?.updated_at || payload.value?.observed_at))
const updatedLabel = computed(() => formatTime(updatedDateTime.value, effectiveTz.value))
const contextLabel = computed(() => {
  const inference = String(payload.value?.inference || '').trim()
  if (inference === 'poleward_corridor_peak') return 'Severný koridor'
  return ''
})

async function fetchPayload() {
  if (showMissingLocation.value) {
    payload.value = null
    loading.value = false
    error.value = ''
    return
  }

  pendingAbort?.abort()
  const controller = new AbortController()
  pendingAbort = controller

  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/sky/aurora', {
      params: {
        lat: numericLat.value,
        lon: numericLon.value,
        tz: effectiveTz.value,
      },
      signal: controller.signal,
      meta: { skipErrorToast: true },
    })

    payload.value = response?.data || null
  } catch (requestError) {
    if (requestError?.name === 'AbortError' || requestError?.code === 'ERR_CANCELED') return
    payload.value = null
    error.value = (
      requestError?.response?.data?.message
      || requestError?.message
      || 'Nepodarilo sa načítať aurora watch.'
    )
  } finally {
    if (pendingAbort === controller) {
      loading.value = false
    }
  }
}

onBeforeUnmount(() => {
  pendingAbort?.abort()
})

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
  gap: 0.5rem;
  min-width: 0;
}

.panelTitle {
  margin: 0;
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
}

/* ── Content ── */
.aurora-content {
  display: grid;
  gap: 0.22rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  background: rgb(var(--color-bg-rgb) / 0.18);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
}

/* ── Headline ── */
.aurora-headline {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--color-surface);
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.2;
}

.aurora-headline.is-high  { color: rgb(72 187 120); }
.aurora-headline.is-medium { color: rgb(245 158 11); }
.aurora-headline.is-low   { color: var(--color-surface); }
.aurora-headline.is-muted { color: var(--color-text-secondary); }

.aurora-icon {
  flex-shrink: 0;
  width: 1.15rem;
  height: 0.65rem;
  opacity: 0.85;
}

/* ── Score ── */
.aurora-score {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}

/* ── Context ── */
.aurora-context {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.25;
  opacity: 0.75;
}

/* ── Footer ── */
.widget-footer {
  margin: 0.18rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.62rem;
  line-height: 1.2;
  opacity: 0.55;
  text-align: right;
}

/* ── Loading skeleton ── */
.panelLoading {
  display: grid;
  gap: 0.22rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.08);
}

.skeleton {
  border-radius: 0.25rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.07),
    rgb(var(--color-text-secondary-rgb) / 0.14),
    rgb(var(--color-text-secondary-rgb) / 0.07)
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.h-8   { height: 1.1rem; }
.w-full { width: 100%; }
</style>
