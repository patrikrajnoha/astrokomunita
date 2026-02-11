<template>
  <section class="card shell">
    <header class="head">
      <h3 class="title">Astronomicke podmienky</h3>
      <p class="subtitle">Minimalny prehlad na dnesny vecer</p>
      <p class="location">{{ locationLabel }}</p>
    </header>

    <div v-if="authPending" class="state">
      <p class="stateTitle">Nacitavam...</p>
      <p class="stateText">Overujem lokalitu a data.</p>
    </div>

    <div v-else-if="!isAuthenticated" class="state">
      <p class="stateTitle">Widget je dostupny po prihlaseni.</p>
      <button class="btnPrimary" type="button" @click="goToLogin">Prihlasit sa</button>
    </div>

    <div v-else-if="!hasLocationCoords" class="state">
      <p class="stateTitle">Chybaju suradnice lokality.</p>
      <button class="btnPrimary" type="button" @click="goToProfileLocation">Nastavit lokalitu</button>
    </div>

    <div v-else-if="loading" class="loading">
      <div class="skeleton h14 w60"></div>
      <div class="skeleton h10 w100"></div>
      <div class="skeleton h10 w100"></div>
      <div class="skeleton h10 w85"></div>
    </div>

    <div v-else class="body">
      <div v-if="error" class="notice">
        <p class="noticeText">{{ error }}</p>
        <button class="btnGhost" type="button" @click="fetchSummary">Skusit znova</button>
      </div>

      <section class="chips">
        <span class="chip" :class="badgeForSun(observeSummary?.sun?.status)">
          Tma: {{ sunStatusLabel(observeSummary?.sun?.status) }}
        </span>
        <span class="chip" :class="moonBadge">Mesiac: {{ moonPhaseLabel }}</span>
        <span class="chip" :class="badgeFromLabel(observeSummary?.overall?.label)">
          Atmosfera: {{ observeSummary?.overall?.label || 'Nedostupne' }}
        </span>
      </section>

      <section class="panel">
        <p class="row"><span>Sunset</span><strong>{{ observeSummary?.sun?.sunset || '-' }}</strong></p>
        <p class="row"><span>Tma od</span><strong>{{ observeSummary?.sun?.civil_twilight_end || '-' }}</strong></p>
        <p class="row"><span>Moonrise / Moonset</span><strong>{{ moonRiseSet }}</strong></p>
      </section>

      <section class="panel">
        <p class="row"><span>Faza</span><strong>{{ moonPhaseLabel }}</strong></p>
        <p class="row"><span>Osvetlenie</span><strong>{{ moonIllumination }}</strong></p>
      </section>

      <section v-if="planets.length > 0" class="panel">
        <h4 class="sectionTitle">Planety</h4>
        <article v-for="planet in planets.slice(0, 3)" :key="planet.key" class="planet">
          <div class="planetTop">
            <strong>{{ planet.name }}</strong>
            <div class="tags">
              <span class="badge">{{ localizeDirection(planet.direction) }}</span>
              <span v-if="planet.is_low" class="badge isWarn">nizko</span>
            </div>
          </div>
          <p class="row compact"><span>Najlepsie</span><strong>{{ planet.best_from }} - {{ planet.best_to }}</strong></p>
          <p class="row compact"><span>Max vyska</span><strong>{{ deg(planet.alt_max_deg) }}</strong></p>
        </article>
      </section>

      <section v-if="meteors.length > 0" class="panel">
        <h4 class="sectionTitle">Aktivne meteory</h4>
        <article v-for="shower in meteors" :key="shower.id" class="meteor">
          <p class="row compact"><strong>{{ shower.name }}</strong><span class="badge isOk">aktivny</span></p>
          <p class="row compact"><span>Peak</span><strong>{{ shower.peak_date }} ({{ signedDays(shower.peak_in_days) }})</strong></p>
          <p class="row compact"><span>ZHR</span><strong>{{ shower.zhr ?? '-' }}</strong></p>
        </article>
      </section>

      <section class="panel">
        <h4 class="sectionTitle">Atmosfera</h4>
        <p class="row compact">
          <span>Vlhkost (vecer)</span>
          <strong>{{ pct(observeSummary?.atmosphere?.humidity?.evening_pct ?? observeSummary?.atmosphere?.humidity?.current_pct) }}</strong>
        </p>
        <p class="row compact">
          <span>Smog PM2.5 / PM10</span>
          <strong>{{ pm(observeSummary?.atmosphere?.air_quality?.pm25) }} / {{ pm(observeSummary?.atmosphere?.air_quality?.pm10) }}</strong>
        </p>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  date: { type: String, default: '' },
  tz: { type: String, default: 'Europe/Bratislava' },
  locationName: { type: String, default: '' },
})

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const observeSummary = ref(null)
const skySummary = ref(null)
const loading = ref(false)
const error = ref('')
let debounceTimer = null
let requestCounter = 0

const numericLat = computed(() => toNumber(props.lat))
const numericLon = computed(() => toNumber(props.lon))
const hasLocationCoords = computed(() => Number.isFinite(numericLat.value) && Number.isFinite(numericLon.value))
const isAuthenticated = computed(() => auth.isAuthed)
const authPending = computed(() => !auth.initialized)

const locationLabel = computed(() => {
  if (authPending.value) return 'Nacitavam...'
  if (!isAuthenticated.value) return 'Neprihlaseny pouzivatel'

  const raw = typeof props.locationName === 'string' ? props.locationName.trim() : ''
  return raw || 'Nezvolena lokalita'
})

const safeDate = computed(() => {
  if (typeof props.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(props.date)) return props.date
  return toLocalIsoDate(new Date())
})

const safeTimezone = computed(() => {
  const candidate = typeof props.tz === 'string' ? props.tz.trim() : ''
  if (candidate && isValidIana(candidate)) return candidate

  const browser = Intl.DateTimeFormat().resolvedOptions().timeZone
  if (browser && isValidIana(browser)) return browser

  return 'UTC'
})

const planets = computed(() => (Array.isArray(skySummary.value?.planets) ? skySummary.value.planets : []))
const meteors = computed(() => (Array.isArray(skySummary.value?.meteors) ? skySummary.value.meteors : []))

const moonPhaseLabel = computed(() => {
  const raw = skySummary.value?.moon?.phase_name || observeSummary.value?.moon?.phase_name || ''
  return translateMoonPhase(raw) || 'Nedostupne'
})

const moonBadge = computed(() => {
  const pctValue = Number(skySummary.value?.moon?.illumination ?? observeSummary.value?.moon?.illumination_pct)
  if (!Number.isFinite(pctValue)) return 'isUnknown'
  if (pctValue >= 90) return 'isWarn'
  return 'isOk'
})

const moonRiseSet = computed(() => {
  const rise = skySummary.value?.moon?.rise_local || '-'
  const set = skySummary.value?.moon?.set_local || '-'
  return `${rise} / ${set}`
})

const moonIllumination = computed(() => {
  const fromSky = Number(skySummary.value?.moon?.illumination)
  if (Number.isFinite(fromSky)) return `${Math.round(fromSky)}%`

  const fromObserve = Number(observeSummary.value?.moon?.illumination_pct)
  if (Number.isFinite(fromObserve)) return `${Math.round(fromObserve)}%`

  return '-'
})

function queueFetch() {
  if (debounceTimer) window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(fetchSummary, 220)
}

async function fetchSummary() {
  if (!isAuthenticated.value || !hasLocationCoords.value) {
    observeSummary.value = null
    skySummary.value = null
    error.value = ''
    loading.value = false
    return
  }

  const runId = ++requestCounter
  loading.value = true
  error.value = ''

  const params = {
    lat: numericLat.value,
    lon: numericLon.value,
    date: safeDate.value,
    tz: safeTimezone.value,
  }

  const [observeResult, skyResult] = await Promise.allSettled([
    api.get('/observe/summary', { params }),
    api.get('/observing/sky-summary', { params }),
  ])

  if (runId !== requestCounter) return

  observeSummary.value = observeResult.status === 'fulfilled' ? (observeResult.value?.data ?? null) : null
  skySummary.value = skyResult.status === 'fulfilled' ? (skyResult.value?.data ?? null) : null

  if (observeResult.status === 'rejected' && skyResult.status === 'rejected') {
    error.value = 'Nepodarilo sa nacitat data pre pozorovanie. Skus to znovu.'
  } else if (skyResult.status === 'rejected') {
    error.value = 'Planetarne a meteoricke data su docasne nedostupne.'
  }

  loading.value = false
}

function goToLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } })
}

function goToProfileLocation() {
  router.push({ name: 'profile.edit', hash: '#location' })
}

watch(
  () => [props.lat, props.lon, props.date, props.tz, auth.initialized, auth.isAuthed],
  queueFetch,
  { immediate: true }
)

onBeforeUnmount(() => {
  if (debounceTimer) window.clearTimeout(debounceTimer)
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

function isValidIana(value) {
  try {
    Intl.DateTimeFormat('en-US', { timeZone: value }).format(new Date())
    return true
  } catch {
    return false
  }
}

function pct(value) {
  return Number.isFinite(Number(value)) ? `${Math.round(Number(value))}%` : '-'
}

function pm(value) {
  return Number.isFinite(Number(value)) ? Number(value).toFixed(1) : '-'
}

function deg(value) {
  return Number.isFinite(Number(value)) ? `${Number(value).toFixed(1)}deg` : '-'
}

function signedDays(value) {
  if (!Number.isFinite(Number(value))) return '-'
  const parsed = Number(value)
  if (parsed === 0) return 'dnes'
  return parsed > 0 ? `+${parsed} d` : `${parsed} d`
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

function sunStatusLabel(status) {
  if (status === 'ok') return 'OK'
  if (status === 'continuous_day') return 'Polarny den'
  if (status === 'continuous_night') return 'Polarna noc'
  return 'Nedostupne'
}

function localizeDirection(value) {
  if (typeof value !== 'string') return '-'

  const normalized = value.trim().toUpperCase()
  const dictionary = {
    N: 'S',
    NE: 'SV',
    E: 'V',
    SE: 'JV',
    S: 'J',
    SW: 'JZ',
    W: 'Z',
    NW: 'SZ',
  }

  return dictionary[normalized] || value.trim()
}

function translateMoonPhase(value) {
  if (typeof value !== 'string') return ''

  const normalized = value.trim().toLowerCase().replace(/\s+/g, ' ')
  const dictionary = {
    'new moon': 'Nov',
    'waxing crescent': 'Dorastajuci kosacik',
    'first quarter': 'Prva stvrt',
    'waxing gibbous': 'Dorastajuci mesiac',
    'full moon': 'Spln',
    'waning gibbous': 'Ubudajuci mesiac',
    'last quarter': 'Posledna stvrt',
    'third quarter': 'Posledna stvrt',
    'waning crescent': 'Ubudajuci kosacik',
  }

  return dictionary[normalized] || value.trim()
}
</script>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 1.1rem;
  padding: 0.95rem;
  background:
    radial-gradient(circle at 12% -18%, rgb(var(--color-primary-rgb) / 0.16), transparent 44%),
    linear-gradient(160deg, rgb(var(--color-bg-rgb) / 0.86), rgb(var(--color-bg-rgb) / 0.6));
}

.shell,
.body {
  display: grid;
  gap: 0.75rem;
}

.head {
  display: grid;
  gap: 0.22rem;
}

.title {
  margin: 0;
  font-size: 1.02rem;
  font-weight: 800;
  color: var(--color-surface);
}

.subtitle,
.location {
  margin: 0;
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.chip,
.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0.14rem 0.5rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  font-size: 0.68rem;
  font-weight: 700;
}

.panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 0.9rem;
  background: rgb(var(--color-bg-rgb) / 0.34);
  padding: 0.64rem;
  display: grid;
  gap: 0.36rem;
}

.sectionTitle {
  margin: 0 0 0.08rem;
  font-size: 0.76rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.96);
}

.row {
  margin: 0;
  display: flex;
  justify-content: space-between;
  gap: 0.55rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.98);
  font-size: 0.81rem;
}

.row strong {
  color: var(--color-surface);
  font-weight: 700;
}

.compact {
  font-size: 0.76rem;
}

.planet,
.meteor {
  display: grid;
  gap: 0.22rem;
  border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.15);
  padding-top: 0.42rem;
}

.planetTop {
  display: flex;
  justify-content: space-between;
  gap: 0.45rem;
}

.tags {
  display: inline-flex;
  gap: 0.3rem;
}

.state {
  display: grid;
  gap: 0.45rem;
}

.stateTitle {
  margin: 0;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--color-surface);
}

.stateText {
  margin: 0;
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.btnPrimary,
.btnGhost {
  width: fit-content;
  border-radius: 0.68rem;
  padding: 0.42rem 0.68rem;
  font-size: 0.76rem;
  font-weight: 600;
}

.btnPrimary {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.56);
  background: rgb(var(--color-primary-rgb) / 0.15);
  color: var(--color-surface);
}

.btnGhost {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.38);
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-surface);
}

.notice {
  display: grid;
  gap: 0.4rem;
}

.noticeText {
  margin: 0;
  font-size: 0.76rem;
  color: rgb(251 113 133 / 0.95);
  border: 1px solid rgb(251 113 133 / 0.33);
  border-radius: 0.62rem;
  background: rgb(190 24 93 / 0.12);
  padding: 0.4rem 0.5rem;
}

.loading {
  display: grid;
  gap: 0.44rem;
}

.skeleton {
  border-radius: 0.56rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.18),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 220% 100%;
  animation: shimmer 1.2s infinite linear;
}

.h14 { height: 0.85rem; }
.h10 { height: 0.62rem; }
.w60 { width: 60%; }
.w85 { width: 85%; }
.w100 { width: 100%; }

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

@keyframes shimmer {
  from { background-position: 220% 0; }
  to { background-position: -220% 0; }
}
</style>
