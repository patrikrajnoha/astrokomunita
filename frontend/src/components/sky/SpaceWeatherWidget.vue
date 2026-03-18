<template>
  <section class="card panel">
    <h3 class="panelTitle sidebarSection__header">Vesmírne počasie</h3>

    <AsyncState
      v-if="showMissingLocation"
      mode="empty"
      title="Poloha nie je nastavená"
      message="Nastav polohu pre lokálne vesmírne počasie."
      compact
    />

    <div v-else-if="loading" class="panelLoading">
      <div class="skeleton h-8 w-full"></div>
      <div class="skeleton h-5 w-full"></div>
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
      title="Vesmírne počasie je nedostupné"
      message="NOAA SWPC dáta sa momentálne nepodarilo načítať."
      compact
    />

    <div v-else class="sw-content">
      <div class="sw-headline" :class="verdictToneClass">
        <svg class="sw-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
          <circle cx="8" cy="8" r="2.8"/>
          <line x1="8" y1="1" x2="8" y2="3.2"/>
          <line x1="8" y1="12.8" x2="8" y2="15"/>
          <line x1="1" y1="8" x2="3.2" y2="8"/>
          <line x1="12.8" y1="8" x2="15" y2="8"/>
          <line x1="3.05" y1="3.05" x2="4.6" y2="4.6"/>
          <line x1="11.4" y1="11.4" x2="12.95" y2="12.95"/>
          <line x1="12.95" y1="3.05" x2="11.4" y2="4.6"/>
          <line x1="4.6" y1="11.4" x2="3.05" y2="12.95"/>
        </svg>
        <span>{{ verdict }}</span>
      </div>

      <p class="sw-detail">
        <abbr
          v-if="kpRounded !== null"
          class="detail-kp"
          title="Kp index = geomagnetická aktivita (0–9)"
        >Kp {{ kpRounded }}</abbr>
        <span v-else>—</span>
      </p>

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

const kpRounded = computed(() => {
  const kpIndex = toFiniteNumber(payload.value?.kp_index)
  const estimated = toFiniteNumber(payload.value?.estimated_kp)
  const value = kpIndex ?? estimated
  return value === null ? null : Math.round(value)
})

const verdict = computed(() => {
  const raw = String(payload.value?.geomagnetic_level || '').trim()
  const normalized = raw.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  if (normalized.includes('extrem')) return 'Extrémna búrka'
  if (normalized.includes('velmi silna')) return 'Veľmi silná búrka'
  if (normalized.includes('silna')) return 'Silná búrka'
  if (normalized.includes('stredna')) return 'Stredná búrka'
  if (normalized.includes('mensia') || normalized.includes('menša')) return 'Menšia búrka'
  if (normalized.includes('aktiv')) return 'Mierna aktivita'
  if (normalized.includes('pokoj')) return 'Pokojné podmienky'
  return raw || 'Bez dát'
})

const verdictToneClass = computed(() => {
  const v = verdict.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  if (v.includes('extrem') || v.includes('velmi silna')) return 'is-intense'
  if (v.includes('silna') || v.includes('stredna')) return 'is-strong'
  if (v.includes('mensia') || v.includes('aktiv')) return 'is-moderate'
  return 'is-calm'
})


const updatedLabel = computed(() => formatTime(payload.value?.updated_at, effectiveTz.value))

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
    const response = await api.get('/sky/space-weather', {
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
      || 'Nepodarilo sa načítať vesmírne počasie.'
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

/* ── Content card ── */
.sw-content {
  display: grid;
  gap: 0.22rem;
  padding: 0.56rem 0.6rem;
  border-radius: 0.64rem;
  background: rgb(var(--color-bg-rgb) / 0.18);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.12);
}

/* ── Verdict headline ── */
.sw-headline {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.2;
  color: var(--color-surface);
}

.sw-headline.is-calm     { color: rgb(96 165 250); }
.sw-headline.is-moderate { color: rgb(245 158 11); }
.sw-headline.is-strong   { color: rgb(249 115 22); }
.sw-headline.is-intense  { color: rgb(167 139 250); }

/* ── Sun icon ── */
.sw-icon {
  flex-shrink: 0;
  width: 0.95rem;
  height: 0.95rem;
  opacity: 0.85;
}

/* ── Compact data line ── */
.sw-detail {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.2;
}

.detail-kp {
  font-size: 0.66rem;
  font-weight: 400;
  opacity: 0.52;
  text-decoration: none;
  cursor: help;
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
.h-5   { height: 0.75rem; }
.w-full { width: 100%; }
</style>
