<template>
  <section class="card shell">
    <header class="head">
      <h3 class="title">Astronomicke podmienky</h3>
      <p class="subtitle">Observing Conditions</p>
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
      <div class="modeRow">
        <button
          v-for="option in modeOptions"
          :key="option.key"
          class="modeBtn"
          :class="{ isActive: selectedMode === option.key }"
          :title="option.description"
          type="button"
          @click="setMode(option.key)"
        >
          {{ option.label }}
        </button>
      </div>

      <div v-if="error" class="notice">
        <p class="noticeText">{{ error }}</p>
        <button class="btnGhost" type="button" @click="fetchSummary">Skusit znova</button>
      </div>

      <section class="panel indexPanel">
        <p class="indexLabel" :title="weightsTooltip">
          Observing Index
          <strong>{{ observingIndexLabel }}</strong>
        </p>
        <div class="progressTrack" role="progressbar" :aria-valuenow="observingIndexForAria" aria-valuemin="0" aria-valuemax="100">
          <div class="progressFill" :style="indexProgressStyle"></div>
        </div>
        <p class="indexMeta">
          <span class="chip" :class="badgeFromLabel(observeSummary?.overall?.label)">{{ observeSummary?.overall?.label || 'Nedostupne' }}</span>
          <span>{{ overallReason }}</span>
        </p>
      </section>

      <section v-if="bestTimeLine" class="panel">
        <p class="row compact">
          <span>Najlepsi cas dnes</span>
          <strong>{{ bestTimeLine }}</strong>
        </p>
      </section>

      <section v-if="alerts.length > 0" class="panel">
        <h4 class="sectionTitle">Alerts</h4>
        <ul class="alertList">
          <li v-for="(alert, index) in alerts" :key="`${alert.code || 'alert'}-${index}`" class="alertItem">
            <span class="badge" :class="badgeFromLevel(alert.level)">{{ alert.level || 'info' }}</span>
            <span>{{ alert.message || '-' }}</span>
          </li>
        </ul>
      </section>

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
        <h4 class="sectionTitle">24h trend</h4>
        <div v-if="hasGraphData" class="graphWrap">
          <svg class="graph" viewBox="0 0 100 100" preserveAspectRatio="none">
            <line v-if="sunsetMarkerX !== null" :x1="sunsetMarkerX" y1="6" :x2="sunsetMarkerX" y2="94" class="marker markerSunset" />
            <line v-if="sunriseMarkerX !== null" :x1="sunriseMarkerX" y1="6" :x2="sunriseMarkerX" y2="94" class="marker markerSunrise" />
            <path :d="humidityPath" class="line lineHumidity" />
            <path :d="cloudPath" class="line lineCloud" />
            <path :d="moonPath" class="line lineMoon" />
          </svg>
          <div class="legend">
            <span><i class="dot humidity"></i>Humidity</span>
            <span><i class="dot cloud"></i>Cloud</span>
            <span><i class="dot moon"></i>Moon alt</span>
            <span v-if="sunsetMarkerX !== null"><i class="dot sunset"></i>Sunset</span>
            <span v-if="sunriseMarkerX !== null"><i class="dot sunrise"></i>Sunrise</span>
          </div>
        </div>
        <p v-else class="stateText">24h graf je docasne nedostupny.</p>
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
          <span>Cloud cover (vecer)</span>
          <strong>{{ pct(observeSummary?.atmosphere?.cloud_cover?.evening_pct ?? observeSummary?.atmosphere?.cloud_cover?.current_pct) }}</strong>
        </p>
        <p class="row compact">
          <span>Seeing proxy</span>
          <strong>{{ pct(observeSummary?.atmosphere?.seeing?.score) }}</strong>
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

const modeOptions = [
  { key: 'deep_sky', label: 'Deep Sky', description: 'Priorita: cloud, humidity, moon glow.' },
  { key: 'planets', label: 'Planety', description: 'Priorita: seeing proxy + cloud.' },
  { key: 'meteors', label: 'Meteory', description: 'Priorita: darkness + cloud + moon.' },
]

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const observeSummary = ref(null)
const skySummary = ref(null)
const loading = ref(false)
const error = ref('')
const selectedMode = ref('deep_sky')
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

const observingIndex = computed(() => {
  const raw = Number(observeSummary.value?.observing_index)
  if (!Number.isFinite(raw)) return null
  return Math.max(0, Math.min(100, Math.round(raw)))
})

const observingIndexLabel = computed(() => (observingIndex.value === null ? '-' : String(observingIndex.value)))
const observingIndexForAria = computed(() => (observingIndex.value === null ? 0 : observingIndex.value))
const indexProgressStyle = computed(() => ({
  width: `${observingIndexForAria.value}%`,
}))

const overallReason = computed(() => {
  const reason = observeSummary.value?.overall?.reason
  if (typeof reason === 'string' && reason.trim() !== '') return reason.trim()
  return 'Bez detailneho dovodu.'
})

const weightsTooltip = computed(() => {
  const mode = observeSummary.value?.observing_mode || selectedMode.value
  const weights = observeSummary.value?.weights
  if (!weights || typeof weights !== 'object') return `Mode: ${mode}`

  const items = Object.entries(weights)
    .filter(([, value]) => Number.isFinite(Number(value)))
    .map(([key, value]) => `${key}: ${Math.round(Number(value) * 100)}%`)

  return `Mode ${mode} | ${items.join(', ')}`
})

const alerts = computed(() => (Array.isArray(observeSummary.value?.alerts) ? observeSummary.value.alerts : []))

const bestTimeLine = computed(() => {
  const time = observeSummary.value?.best_time_local
  const index = Number(observeSummary.value?.best_time_index)
  const reason = observeSummary.value?.best_time_reason
  if (typeof time !== 'string' || time.trim() === '') return ''

  const parts = [`${time}`]
  if (Number.isFinite(index)) parts.push(`${Math.round(index)}/100`)
  if (typeof reason === 'string' && reason.trim() !== '') parts.push(reason.trim())
  return parts.join(' - ')
})

const graphSeries = computed(() => {
  const hourly = Array.isArray(observeSummary.value?.timeline?.hourly) ? observeSummary.value.timeline.hourly : []
  const moonHourly = Array.isArray(skySummary.value?.moon?.altitude_hourly) ? skySummary.value.moon.altitude_hourly : []
  const moonMap = new Map(
    moonHourly
      .filter((item) => typeof item?.local_time === 'string')
      .map((item) => [item.local_time, Number(item.altitude_deg)]),
  )

  return hourly
    .filter((item) => typeof item?.local_time === 'string')
    .map((item) => ({
      local_time: item.local_time,
      humidity_pct: Number(item.humidity_pct),
      cloud_cover_pct: Number(item.cloud_cover_pct),
      moon_altitude_deg: moonMap.get(item.local_time),
    }))
})

const hasGraphData = computed(() => graphSeries.value.length > 1)

const humidityPath = computed(() => buildLinePath(graphSeries.value, 'humidity_pct', 'percent'))
const cloudPath = computed(() => buildLinePath(graphSeries.value, 'cloud_cover_pct', 'percent'))
const moonPath = computed(() => buildLinePath(graphSeries.value, 'moon_altitude_deg', 'moon'))

const sunsetMarkerX = computed(() => markerX(observeSummary.value?.timeline?.sunset))
const sunriseMarkerX = computed(() => markerX(observeSummary.value?.timeline?.sunrise))

function queueFetch() {
  if (debounceTimer) window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(fetchSummary, 220)
}

function setMode(mode) {
  if (typeof mode !== 'string') return
  selectedMode.value = normalizeMode(mode)
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
    mode: selectedMode.value,
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
  } else if (observeResult.status === 'rejected') {
    error.value = 'Observing index je docasne nedostupny. Planety a meteory su dostupne.'
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
  () => [props.lat, props.lon, props.date, props.tz, auth.initialized, auth.isAuthed, selectedMode.value],
  queueFetch,
  { immediate: true },
)

onBeforeUnmount(() => {
  if (debounceTimer) window.clearTimeout(debounceTimer)
})

function normalizeMode(value) {
  const candidate = typeof value === 'string' ? value.trim().toLowerCase() : ''
  if (candidate === 'planets' || candidate === 'meteors') return candidate
  return 'deep_sky'
}

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

function normalizeLabelKey(label) {
  if (typeof label !== 'string') return ''
  return label
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z]/g, '')
}

function badgeFromLabel(label) {
  const key = normalizeLabelKey(label)
  if (key === 'ok') return 'isOk'
  if (key.startsWith('pozor')) return 'isWarn'
  if (key.startsWith('zl')) return 'isBad'
  return 'isUnknown'
}

function badgeFromLevel(level) {
  const key = normalizeLabelKey(level)
  if (key === 'severe') return 'isBad'
  if (key === 'warn') return 'isWarn'
  if (key === 'info') return 'isInfo'
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

function buildLinePath(series, field, kind) {
  if (!Array.isArray(series) || series.length < 2) return ''

  let path = ''
  let started = false

  series.forEach((point, index) => {
    const raw = Number(point?.[field])
    if (!Number.isFinite(raw)) {
      started = false
      return
    }

    const x = xPoint(index, series.length)
    const y = yPoint(raw, kind)
    if (!Number.isFinite(y)) {
      started = false
      return
    }

    if (!started) {
      path += `M ${x} ${y}`
      started = true
    } else {
      path += ` L ${x} ${y}`
    }
  })

  return path
}

function xPoint(index, total) {
  if (!Number.isFinite(index) || !Number.isFinite(total) || total <= 1) return 0
  return Number(((index / (total - 1)) * 100).toFixed(2))
}

function yPoint(value, kind) {
  if (!Number.isFinite(value)) return NaN

  if (kind === 'moon') {
    const normalized = Math.max(0, Math.min(100, ((value + 20) / 100) * 100))
    return Number((90 - (normalized / 100) * 80).toFixed(2))
  }

  const normalized = Math.max(0, Math.min(100, value))
  return Number((90 - (normalized / 100) * 80).toFixed(2))
}

function markerX(time) {
  if (typeof time !== 'string' || !/^\d{2}:\d{2}$/.test(time)) return null
  const points = graphSeries.value
  if (!Array.isArray(points) || points.length < 2) return null

  const matchedIndex = points.findIndex((item) => item.local_time === time)
  if (matchedIndex >= 0) return xPoint(matchedIndex, points.length)

  const hour = Number(time.slice(0, 2))
  if (!Number.isFinite(hour)) return null
  return Number(((hour / 23) * 100).toFixed(2))
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

.modeRow {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.3rem;
}

.modeBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  border-radius: 0.62rem;
  padding: 0.34rem 0.45rem;
  font-size: 0.69rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  background: rgb(var(--color-bg-rgb) / 0.38);
}

.modeBtn.isActive {
  border-color: rgb(var(--color-primary-rgb) / 0.75);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.indexPanel {
  gap: 0.5rem;
}

.indexLabel {
  margin: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.indexLabel strong {
  font-size: 0.96rem;
  color: var(--color-surface);
}

.indexMeta {
  margin: 0;
  display: grid;
  gap: 0.35rem;
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.progressTrack {
  width: 100%;
  height: 0.62rem;
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.7);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.23);
  overflow: hidden;
}

.progressFill {
  height: 100%;
  background: linear-gradient(90deg, rgb(34 197 94 / 0.8), rgb(59 130 246 / 0.8));
  transition: width 180ms ease;
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

.alertList {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.35rem;
}

.alertItem {
  display: flex;
  gap: 0.45rem;
  align-items: flex-start;
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.98);
}

.graphWrap {
  display: grid;
  gap: 0.5rem;
}

.graph {
  width: 100%;
  height: 94px;
  border-radius: 0.52rem;
  background: rgb(var(--color-bg-rgb) / 0.48);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
}

.line {
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.lineHumidity {
  stroke: rgb(45 212 191 / 0.95);
}

.lineCloud {
  stroke: rgb(251 191 36 / 0.95);
}

.lineMoon {
  stroke: rgb(167 139 250 / 0.95);
}

.marker {
  stroke-width: 1.4;
  stroke-dasharray: 2 2;
}

.markerSunset {
  stroke: rgb(244 114 182 / 0.9);
}

.markerSunrise {
  stroke: rgb(96 165 250 / 0.9);
}

.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem 0.75rem;
  font-size: 0.66rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.legend span {
  display: inline-flex;
  align-items: center;
  gap: 0.26rem;
}

.dot {
  width: 0.46rem;
  height: 0.46rem;
  border-radius: 999px;
  display: inline-block;
}

.dot.humidity {
  background: rgb(45 212 191 / 0.95);
}

.dot.cloud {
  background: rgb(251 191 36 / 0.95);
}

.dot.moon {
  background: rgb(167 139 250 / 0.95);
}

.dot.sunset {
  background: rgb(244 114 182 / 0.9);
}

.dot.sunrise {
  background: rgb(96 165 250 / 0.9);
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

.isInfo {
  color: rgb(186 230 253);
  border-color: rgb(56 189 248 / 0.62);
  background: rgb(14 116 144 / 0.2);
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
