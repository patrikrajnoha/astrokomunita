<template>
  <section class="card panel">
    <header class="panelHead">
      <h3 class="panelTitle">Observing conditions</h3>
      <p class="panelSub">Rychly prehlad pre pozorovanie</p>
    </header>

    <div v-if="!hasLocation" class="state">
      <p class="stateTitle">Zvol lokalitu</p>
      <p class="stateText">Dopln lat/lon do vyberu lokality, aby sa nacitali podmienky.</p>
    </div>

    <div v-else-if="loading" class="loadingGrid">
      <div class="skeleton h12 w70"></div>
      <div class="skeleton h10 w100"></div>
      <div class="skeleton h10 w90"></div>
      <div class="skeleton h10 w100"></div>
      <div class="skeleton h10 w80"></div>
    </div>

    <div v-else-if="error" class="state stateError">
      <p class="stateTitle">Nepodarilo sa nacitat podmienky</p>
      <p class="stateText">{{ error }}</p>
      <button class="ghostBtn" type="button" @click="fetchSummary">Skusit znova</button>
    </div>

    <div v-else-if="summary" class="content">
      <section class="group">
        <div class="groupHead">
          <h4>Tma</h4>
          <span class="badge" :class="badgeForSun(summary.sun.status)">
            {{ sunStatusLabel(summary.sun.status) }}
          </span>
        </div>

        <p v-if="summary.sun.status === 'continuous_day'" class="line muted">
          Slnko je cely den nad horizontom.
        </p>
        <p v-else-if="summary.sun.status === 'continuous_night'" class="line muted">
          Slnko je cely den pod horizontom.
        </p>
        <template v-else>
          <p class="line"><span>Sunrise</span><strong>{{ summary.sun.sunrise || '-' }}</strong></p>
          <p class="line"><span>Sunset</span><strong>{{ summary.sun.sunset || '-' }}</strong></p>
          <p class="line">
            <span>Tma priblizne od</span>
            <strong>{{ summary.sun.civil_twilight_end || '-' }}</strong>
          </p>
        </template>
      </section>

      <section class="group">
        <div class="groupHead">
          <h4>Mesiac</h4>
          <span class="badge" :class="badgeForMoon(summary.moon.status, summary.moon.warning)">
            {{ moonLabel(summary.moon.status, summary.moon.warning) }}
          </span>
        </div>

        <p class="line"><span>Faza</span><strong>{{ summary.moon.phase_name || '-' }}</strong></p>
        <p class="line"><span>Osvetlenie</span><strong>{{ pct(summary.moon.illumination_pct) }}</strong></p>
        <p v-if="summary.moon.warning" class="warn">{{ summary.moon.warning }}</p>
      </section>

      <section class="group">
        <div class="groupHead">
          <h4>Atmosfera</h4>
          <span class="badge" :class="badgeFromLabel(summary.overall?.label)">
            {{ summary.overall?.label || 'Nedostupne' }}
          </span>
        </div>

        <p class="line">
          <span>Vlhkost (vecer)</span>
          <strong>{{ pct(summary.atmosphere.humidity.evening_pct ?? summary.atmosphere.humidity.current_pct) }}</strong>
        </p>
        <p class="subline">
          <span class="badge" :class="badgeFromLabel(summary.atmosphere.humidity.label)">
            {{ summary.atmosphere.humidity.label }}
          </span>
          <span>{{ summary.atmosphere.humidity.note || '-' }}</span>
        </p>

        <p class="line">
          <span>Smog (PM2.5 / PM10)</span>
          <strong>{{ pm(summary.atmosphere.air_quality.pm25) }} / {{ pm(summary.atmosphere.air_quality.pm10) }}</strong>
        </p>
        <p class="subline">
          <span class="badge" :class="badgeFromLabel(summary.atmosphere.air_quality.label)">
            {{ summary.atmosphere.air_quality.label }}
          </span>
          <span>{{ summary.atmosphere.air_quality.note || '-' }}</span>
        </p>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import api from '@/services/api'

const props = defineProps({
  lat: {
    type: [Number, String],
    default: null,
  },
  lon: {
    type: [Number, String],
    default: null,
  },
  date: {
    type: String,
    default: '',
  },
  tz: {
    type: String,
    default: 'Europe/Bratislava',
  },
})

const summary = ref(null)
const loading = ref(false)
const error = ref('')
let debounceTimer = null
let requestCounter = 0

const numericLat = computed(() => toNumber(props.lat))
const numericLon = computed(() => toNumber(props.lon))
const hasLocation = computed(() => Number.isFinite(numericLat.value) && Number.isFinite(numericLon.value))

const safeDate = computed(() => {
  if (typeof props.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(props.date)) {
    return props.date
  }
  return toLocalIsoDate(new Date())
})

const safeTimezone = computed(() => {
  const value = typeof props.tz === 'string' ? props.tz.trim() : ''
  if (value) return value
  return 'Europe/Bratislava'
})

function toNumber(value) {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : NaN
  }
  return NaN
}

function toLocalIsoDate(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function queueFetch() {
  if (debounceTimer) {
    window.clearTimeout(debounceTimer)
  }

  debounceTimer = window.setTimeout(() => {
    fetchSummary()
  }, 220)
}

async function fetchSummary() {
  if (!hasLocation.value) {
    summary.value = null
    error.value = ''
    loading.value = false
    return
  }

  const runId = ++requestCounter
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/observe/summary', {
      params: {
        lat: numericLat.value,
        lon: numericLon.value,
        date: safeDate.value,
        tz: safeTimezone.value,
      },
    })

    if (runId !== requestCounter) return
    summary.value = response?.data ?? null
  } catch (err) {
    if (runId !== requestCounter) return
    summary.value = null
    error.value = err?.response?.data?.message || err?.message || 'Neznama chyba.'
  } finally {
    if (runId === requestCounter) {
      loading.value = false
    }
  }
}

watch(
  () => [props.lat, props.lon, props.date, props.tz],
  () => {
    queueFetch()
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  if (debounceTimer) {
    window.clearTimeout(debounceTimer)
  }
})

function pct(value) {
  return Number.isFinite(value) ? `${Math.round(Number(value))}%` : '-'
}

function pm(value) {
  if (!Number.isFinite(value)) return '-'
  return Number(value).toFixed(1)
}

function badgeFromLabel(label) {
  if (label === 'OK') return 'isOk'
  if (label === 'Pozor') return 'isWarn'
  if (label === 'Zle') return 'isBad'
  return 'isUnknown'
}

function badgeForSun(status) {
  if (status === 'ok') return 'isOk'
  if (status === 'continuous_day' || status === 'continuous_night') return 'isWarn'
  return 'isUnknown'
}

function badgeForMoon(status, warning) {
  if (status !== 'ok') return 'isUnknown'
  if (warning) return 'isWarn'
  return 'isOk'
}

function moonLabel(status, warning) {
  if (status !== 'ok') return 'Nedostupne'
  return warning ? 'Pozor' : 'OK'
}

function sunStatusLabel(status) {
  if (status === 'ok') return 'OK'
  if (status === 'continuous_day') return 'Polarny den'
  if (status === 'continuous_night') return 'Polarna noc'
  return 'Nedostupne'
}
</script>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 1.1rem;
  background: linear-gradient(160deg, rgb(var(--color-bg-rgb) / 0.82), rgb(var(--color-bg-rgb) / 0.6));
  padding: 0.95rem;
}

.panel {
  display: grid;
  gap: 0.8rem;
}

.panelHead {
  display: grid;
  gap: 0.2rem;
}

.panelTitle {
  margin: 0;
  color: var(--color-surface);
  font-size: 0.95rem;
  font-weight: 800;
}

.panelSub {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-size: 0.76rem;
}

.content {
  display: grid;
  gap: 0.85rem;
}

.group {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 0.9rem;
  padding: 0.7rem;
  display: grid;
  gap: 0.45rem;
  background: rgb(var(--color-bg-rgb) / 0.36);
}

.groupHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.groupHead h4 {
  margin: 0;
  font-size: 0.82rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.line {
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.55rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.98);
  font-size: 0.82rem;
}

.line strong {
  color: var(--color-surface);
  font-weight: 700;
}

.subline {
  margin: 0;
  display: flex;
  gap: 0.4rem;
  align-items: center;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 0.74rem;
}

.muted {
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.warn {
  margin: 0;
  font-size: 0.74rem;
  color: rgb(251 113 133 / 0.95);
  background: rgb(190 24 93 / 0.12);
  border: 1px solid rgb(251 113 133 / 0.35);
  border-radius: 0.6rem;
  padding: 0.35rem 0.45rem;
}

.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0.1rem 0.48rem;
  font-size: 0.68rem;
  font-weight: 700;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
}

.isOk {
  color: rgb(167 243 208);
  border-color: rgb(34 197 94 / 0.55);
  background: rgb(22 163 74 / 0.18);
}

.isWarn {
  color: rgb(254 240 138);
  border-color: rgb(234 179 8 / 0.58);
  background: rgb(202 138 4 / 0.18);
}

.isBad {
  color: rgb(254 202 202);
  border-color: rgb(244 63 94 / 0.62);
  background: rgb(190 24 93 / 0.2);
}

.isUnknown {
  color: rgb(var(--color-text-secondary-rgb) / 0.96);
  border-color: rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.45);
}

.loadingGrid {
  display: grid;
  gap: 0.45rem;
}

.skeleton {
  border-radius: 0.6rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.18),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 220% 100%;
  animation: shimmer 1.2s infinite linear;
}

.h12 { height: 0.75rem; }
.h10 { height: 0.62rem; }
.w70 { width: 70%; }
.w80 { width: 80%; }
.w90 { width: 90%; }
.w100 { width: 100%; }

.state {
  display: grid;
  gap: 0.45rem;
}

.stateTitle {
  margin: 0;
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--color-surface);
}

.stateText {
  margin: 0;
  font-size: 0.8rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.stateError .stateTitle,
.stateError .stateText {
  color: rgb(251 113 133 / 0.95);
}

.ghostBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  border-radius: 0.7rem;
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
  padding: 0.45rem 0.7rem;
  font-size: 0.76rem;
  font-weight: 600;
}

.ghostBtn:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.6);
}

@keyframes shimmer {
  from { background-position: 220% 0; }
  to { background-position: -220% 0; }
}
</style>

