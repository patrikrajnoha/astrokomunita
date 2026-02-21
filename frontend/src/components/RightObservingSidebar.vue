<template>
  <section class="card">
    <header class="head">
      <h3>Astronomické podmienky</h3>
      <p class="location">{{ locationLabel }}</p>

      <div v-if="showWeatherNowCard" class="weatherNow">
        <img v-if="weatherIconSrc" :src="weatherIconSrc" alt="" class="weatherIcon" />
        <span v-else class="weatherEmoji">{{ weatherEmoji }}</span>
        <div>
          <p class="miniTitle">Počasie teraz</p>
          <p class="temp">{{ weatherNowTemperature }}</p>
          <p class="meta">{{ weatherNowLabel }}<span v-if="weatherNowApparent"> · Pocitovo {{ weatherNowApparent }}</span><span v-if="weatherNowWind"> · Vietor {{ weatherNowWind }}</span></p>
        </div>
      </div>
    </header>

    <div v-if="authPending" class="state">Načítavam...</div>
    <div v-else-if="!isAuthenticated" class="state">
      <p>Widget je dostupný po prihlásení.</p>
      <button type="button" @click="goToLogin">Prihlásiť sa</button>
    </div>
    <div v-else-if="!hasLocationCoords" class="state">
      <p>Chýbajú súradnice lokality.</p>
      <button type="button" @click="goToProfileLocation">Nastaviť lokalitu</button>
    </div>
    <div v-else-if="loading" class="state">Načítavam dáta...</div>

    <div v-else class="body">
      <div class="modes">
        <button v-for="option in modeOptions" :key="option.key" class="modeBtn" :class="{ isActive: selectedMode === option.key }" @click="setMode(option.key)">
          {{ option.label }}
        </button>
      </div>

      <div v-if="error" class="notice">
        <p>{{ error }}</p>
        <button type="button" @click="fetchSummary">Skúsiť znova</button>
      </div>
      <p v-if="skyWarning" class="infoNotice">{{ skyWarning }}</p>

      <section class="panel">
        <p class="row"><span>Observing Index</span><strong>{{ observingIndexLabel }}</strong></p>
        <div class="bar"><span class="fill" :style="indexProgressStyle"></span></div>
        <p class="row"><span class="chip" :class="badgeFromLabel(overallStatusLabel)">{{ overallStatusLabel }}</span><span>{{ primaryReason }}</span></p>
      </section>

      <section class="panel">
        <p class="row"><span>Najlepší čas dnes</span><strong>{{ bestTimeLine }}</strong></p>
        <p v-if="bestTimeIsWeak" class="row"><span class="chip isWarn">Dnes celkovo slabšie podmienky</span><span>Relatívne najlepšie v rámci dneška</span></p>
      </section>

      <section class="chips">
        <span class="chip" :class="humidityChipClass">Vlhkosť: {{ humidityChipValue }}</span>
        <span class="chip" :class="cloudChipClass">Oblačnosť: {{ cloudChipValue }}</span>
        <span class="chip" :class="moonBadge">Mesiac: {{ moonChipValue }}</span>
        <span class="chip" :class="badgeForSun(observeSummary?.sun?.status)">Tma: {{ sunStatusLabel(observeSummary?.sun?.status) }}</span>
      </section>

      <details class="panel">
        <summary>Upozornenia ({{ additionalAlerts.length }})</summary>
        <ul v-if="additionalAlerts.length > 0" class="alerts">
          <li v-for="(alert, index) in additionalAlerts" :key="`${alert.code || 'alert'}-${index}`">
            <span class="chip" :class="badgeFromAlertLevel(alert.level)">{{ alertLevelLabel(alert.level) }}</span>
            <span>{{ alert.message || 'Nedostupné' }}</span>
          </li>
        </ul>
        <p v-else>Žiadne ďalšie upozornenia.</p>
      </details>

      <details class="panel">
        <summary>24h trend ({{ hasGraphData ? 'dostupný' : 'nedostupný' }})</summary>
        <div v-if="hasGraphData" class="graphWrap">
          <svg class="graph" viewBox="0 0 100 100" preserveAspectRatio="none">
            <line v-if="sunsetMarkerX !== null" :x1="sunsetMarkerX" y1="6" :x2="sunsetMarkerX" y2="94" class="marker" />
            <line v-if="twilightMarkerX !== null" :x1="twilightMarkerX" y1="6" :x2="twilightMarkerX" y2="94" class="marker marker2" />
            <line v-if="sunriseMarkerX !== null" :x1="sunriseMarkerX" y1="6" :x2="sunriseMarkerX" y2="94" class="marker marker3" />
            <path :d="humidityPath" class="line h" />
            <path :d="cloudPath" class="line c" />
            <path :d="moonPath" class="line m" />
          </svg>
          <p class="legend">Vlhkosť (%) · Oblačnosť (%) · Mesiac (alt.)</p>
          <p class="legend" v-if="timelineMarkers.length > 0">
            <span v-for="marker in timelineMarkers" :key="marker.label">{{ marker.label }}: {{ marker.time }}</span>
          </p>
        </div>
        <p v-else>24h trend je dočasne nedostupný.</p>
        <p class="row"><span>Seeing</span><strong>{{ seeingDisplay }}</strong></p>
        <p class="row"><span>PM2.5 / PM10</span><strong>{{ pmDisplay }}</strong></p>
      </details>

      <section class="panel">
        <h4>Detaily</h4>
        <p class="row"><span>Moonrise / Moonset</span><strong>{{ moonRiseSetText }}</strong></p>
        <p v-if="moonRiseSetReason" class="row"><span>Dôvod</span><strong>{{ moonRiseSetReason }}</strong></p>
        <p class="row"><span>Fáza</span><strong>{{ moonPhaseLabel }}</strong></p>
        <p class="row"><span>Osvietenie</span><strong>{{ moonIllumination }}</strong></p>
        <p class="row"><span>PM2.5 / PM10</span><strong>{{ pmDisplay }}</strong></p>
      </section>

      <section v-if="planets.length > 0" class="panel">
        <h4>Planéty</h4>
        <article v-for="planet in planets.slice(0, 3)" :key="planet.key" class="item">
          <p class="row"><strong>{{ planet.name }}</strong><span>{{ localizeDirection(planet.direction) }}</span></p>
          <p class="row"><span>Najlepšie</span><strong>{{ planet.best_from }} - {{ planet.best_to }}</strong></p>
        </article>
      </section>

      <section v-if="meteors.length > 0" class="panel">
        <h4>Aktívne meteory</h4>
        <article v-for="shower in meteors" :key="shower.id" class="item">
          <p class="row"><strong>{{ shower.name }}</strong><span class="chip isOk">aktívny</span></p>
          <p class="row"><span>Peak</span><strong>{{ shower.peak_date }} ({{ signedDays(shower.peak_in_days) }})</strong></p>
        </article>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import iconClear from '@/assets/weather-icons/clear.svg'
import iconPartlyCloudy from '@/assets/weather-icons/partly-cloudy.svg'
import iconOvercast from '@/assets/weather-icons/overcast.svg'
import iconFog from '@/assets/weather-icons/fog.svg'
import iconDrizzle from '@/assets/weather-icons/drizzle.svg'
import iconRain from '@/assets/weather-icons/rain.svg'
import iconSnow from '@/assets/weather-icons/snow.svg'
import iconThunderstorm from '@/assets/weather-icons/thunderstorm.svg'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  date: { type: String, default: '' },
  tz: { type: String, default: 'Europe/Bratislava' },
  locationName: { type: String, default: '' },
})

const modeOptions = [
  { key: 'deep_sky', label: 'Deep Sky' },
  { key: 'planets', label: 'Planéty' },
  { key: 'meteors', label: 'Meteory' },
]

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const observeSummary = ref(null)
const skySummary = ref(null)
const loading = ref(false)
const error = ref('')
const skyWarning = ref('')
const selectedMode = ref('deep_sky')
let debounceTimer = null
let requestCounter = 0

const numericLat = computed(() => toNumber(props.lat))
const numericLon = computed(() => toNumber(props.lon))
const hasLocationCoords = computed(() => Number.isFinite(numericLat.value) && Number.isFinite(numericLon.value))
const isAuthenticated = computed(() => auth.isAuthed)
const authPending = computed(() => !auth.initialized)
const locationLabel = computed(() => (props.locationName || 'Nezvolená lokalita'))
const planets = computed(() => (Array.isArray(skySummary.value?.planets) ? skySummary.value.planets : []))
const meteors = computed(() => (Array.isArray(skySummary.value?.meteors) ? skySummary.value.meteors : []))
const alerts = computed(() => (Array.isArray(observeSummary.value?.alerts) ? observeSummary.value.alerts : []))

const observingIndex = computed(() => {
  const raw = asFiniteNumber(observeSummary.value?.observing_index)
  return raw === null ? null : Math.max(0, Math.min(100, Math.round(raw)))
})
const observingIndexLabel = computed(() => (observingIndex.value === null ? '-' : String(observingIndex.value)))
const indexProgressStyle = computed(() => ({ width: `${observingIndex.value ?? 0}%` }))

const overallStatusLabel = computed(() => normalizeStatusLabel(observeSummary.value?.overall?.label))
const primaryReason = computed(() => cleanString(observeSummary.value?.overall?.reason) || cleanString(alerts.value[0]?.message) || 'Bez doplňujúceho dôvodu.')
const additionalAlerts = computed(() => {
  const primary = normalizeMessage(primaryReason.value)
  const seen = new Set()
  return alerts.value.filter((alert) => {
    const message = cleanString(alert?.message)
    if (!message) return false
    const key = normalizeMessage(message)
    if (key === primary || seen.has(key)) return false
    seen.add(key)
    return true
  })
})

const bestTimeLine = computed(() => {
  const time = cleanString(observeSummary.value?.best_time_local)
  if (!time) return 'Nedostupné'
  const index = asFiniteNumber(observeSummary.value?.best_time_index)
  const reason = cleanString(observeSummary.value?.best_time_reason)
  const suffix = index !== null && index < 60
    ? `Relatívne najlepšie: ${reason || 'nižšia oblačnosť, viac tmy.'}`
    : (reason || 'Najlepšia dostupná kombinácia faktorov.')
  return `${time}${index === null ? '' : ` (${Math.round(index)}/100)`} – ${suffix}`
})
const bestTimeIsWeak = computed(() => {
  const index = asFiniteNumber(observeSummary.value?.best_time_index)
  return index !== null && index < 60
})

const moonPhaseLabel = computed(() => translateMoonPhase(skySummary.value?.moon?.phase_name || observeSummary.value?.moon?.phase_name) || 'Nedostupné')
const moonIllumination = computed(() => {
  const value = asFiniteNumber(skySummary.value?.moon?.illumination ?? observeSummary.value?.moon?.illumination_pct)
  return value === null ? 'Nedostupné' : `${Math.round(value)}%`
})
const moonChipValue = computed(() => `${moonPhaseLabel.value}${moonIllumination.value === 'Nedostupné' ? '' : ` (${moonIllumination.value})`}`)
const moonBadge = computed(() => {
  const value = asFiniteNumber(skySummary.value?.moon?.illumination ?? observeSummary.value?.moon?.illumination_pct)
  if (value === null) return 'isUnknown'
  return value >= 90 ? 'isWarn' : 'isOk'
})
const moonRiseSetText = computed(() => {
  const rise = cleanString(skySummary.value?.moon?.rise_local)
  const set = cleanString(skySummary.value?.moon?.set_local)
  return rise && set ? `${rise} / ${set}` : 'Nedostupné'
})
const moonRiseSetReason = computed(() => (moonRiseSetText.value === 'Nedostupné'
  ? (skySummary.value?.moon ? 'nevychádza/nezapadá v sledovanom okne' : 'chýbajú dáta')
  : ''))

const humidityChipValue = computed(() => pct(observeSummary.value?.atmosphere?.humidity?.evening_pct ?? observeSummary.value?.atmosphere?.humidity?.current_pct))
const cloudChipValue = computed(() => pct(observeSummary.value?.atmosphere?.cloud_cover?.evening_pct ?? observeSummary.value?.atmosphere?.cloud_cover?.current_pct))
const humidityChipClass = computed(() => badgeFromLabel(observeSummary.value?.atmosphere?.humidity?.label))
const cloudChipClass = computed(() => badgeFromLabel(observeSummary.value?.atmosphere?.cloud_cover?.label))
const seeingDisplay = computed(() => {
  const score = asFiniteNumber(observeSummary.value?.atmosphere?.seeing?.score)
  const status = observeSummary.value?.atmosphere?.seeing?.status
  return status === 'unavailable' || score === null ? 'Nedostupné' : `${Math.round(score)} / 100`
})
const pmDisplay = computed(() => {
  const pm25 = asFiniteNumber(observeSummary.value?.atmosphere?.air_quality?.pm25)
  const pm10 = asFiniteNumber(observeSummary.value?.atmosphere?.air_quality?.pm10)
  if (pm25 === null && pm10 === null) return 'Nedostupné (chýbajú dáta)'
  return `${pm25 === null ? 'Nedostupné' : pm25.toFixed(1)} / ${pm10 === null ? 'Nedostupné' : pm10.toFixed(1)} µg/m³`
})

const graphSeries = computed(() => {
  const hourly = Array.isArray(observeSummary.value?.timeline?.hourly) ? observeSummary.value.timeline.hourly : []
  const moonHourly = Array.isArray(skySummary.value?.moon?.altitude_hourly) ? skySummary.value.moon.altitude_hourly : []
  const moonMap = new Map(moonHourly.filter((row) => typeof row?.local_time === 'string').map((row) => [row.local_time, asFiniteNumber(row.altitude_deg)]))
  return hourly.filter((row) => typeof row?.local_time === 'string').map((row) => ({
    local_time: row.local_time,
    humidity_pct: asFiniteNumber(row.humidity_pct),
    cloud_cover_pct: asFiniteNumber(row.cloud_cover_pct),
    moon_altitude_deg: moonMap.get(row.local_time),
  }))
})
const hasGraphData = computed(() => graphSeries.value.length > 1)
const humidityPath = computed(() => buildLinePath(graphSeries.value, 'humidity_pct', 'percent'))
const cloudPath = computed(() => buildLinePath(graphSeries.value, 'cloud_cover_pct', 'percent'))
const moonPath = computed(() => buildLinePath(graphSeries.value, 'moon_altitude_deg', 'moon'))
const sunsetMarkerX = computed(() => markerX(observeSummary.value?.timeline?.sunset))
const twilightMarkerX = computed(() => markerX(observeSummary.value?.timeline?.civil_twilight_end))
const sunriseMarkerX = computed(() => markerX(observeSummary.value?.timeline?.sunrise))
const timelineMarkers = computed(() => [
  { label: 'Západ Slnka', time: cleanString(observeSummary.value?.timeline?.sunset) },
  { label: 'Tma od', time: cleanString(observeSummary.value?.timeline?.civil_twilight_end) },
  { label: 'Východ Slnka', time: cleanString(observeSummary.value?.timeline?.sunrise) },
].filter((row) => row.time))

const showWeatherNowCard = computed(() => Boolean(observeSummary.value))
const weatherNowCode = computed(() => {
  const code = asFiniteNumber(observeSummary.value?.weather_now?.weather_code)
  return code === null ? null : Math.round(code)
})
const weatherNowLabel = computed(() => cleanString(observeSummary.value?.weather_now?.weather_label_sk) || weatherLabelFromCode(weatherNowCode.value))
const weatherNowTemperature = computed(() => {
  const value = asFiniteNumber(observeSummary.value?.weather_now?.temperature_c)
  return value === null ? 'Nedostupné' : `${value.toFixed(1)} °C`
})
const weatherNowApparent = computed(() => {
  const value = asFiniteNumber(observeSummary.value?.weather_now?.apparent_temperature_c)
  return value === null ? '' : `${value.toFixed(1)} °C`
})
const weatherNowWind = computed(() => {
  const value = asFiniteNumber(observeSummary.value?.weather_now?.wind_speed)
  return value === null ? '' : `${value.toFixed(1)} km/h`
})
const weatherIconSrc = computed(() => weatherIconByCode(weatherNowCode.value))
const weatherEmoji = computed(() => weatherEmojiByCode(weatherNowCode.value))

watch(
  () => [props.lat, props.lon, props.date, props.tz, auth.initialized, auth.isAuthed, selectedMode.value],
  queueFetch,
  { immediate: true },
)

onBeforeUnmount(() => {
  if (debounceTimer) window.clearTimeout(debounceTimer)
})

function queueFetch() {
  if (debounceTimer) window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(fetchSummary, 220)
}

function setMode(mode) {
  selectedMode.value = mode === 'planets' || mode === 'meteors' ? mode : 'deep_sky'
}

async function fetchSummary() {
  if (!isAuthenticated.value || !hasLocationCoords.value) {
    observeSummary.value = null
    skySummary.value = null
    error.value = ''
    skyWarning.value = ''
    return
  }

  const runId = ++requestCounter
  loading.value = true
  error.value = ''
  skyWarning.value = ''

  const params = {
    lat: numericLat.value,
    lon: numericLon.value,
    date: safeDate(props.date),
    tz: safeTz(props.tz),
    mode: selectedMode.value,
  }

  const [observeResult, skyResult] = await Promise.allSettled([
    api.get('/observe/summary', { params }),
    api.get('/observing/sky-summary', { params }),
  ])
  if (runId !== requestCounter) return

  observeSummary.value = observeResult.status === 'fulfilled' ? observeResult.value?.data ?? null : null
  skySummary.value = skyResult.status === 'fulfilled' ? skyResult.value?.data ?? null : null
  const skyMetaError = cleanString(skySummary.value?.meta?.error)

  if (observeResult.status === 'rejected' && skyResult.status === 'rejected') {
    error.value = 'Nepodarilo sa načítať astronomické podmienky ani planéty/meteory.'
  } else if (observeResult.status === 'rejected') {
    error.value = 'Nepodarilo sa načítať astronomické podmienky.'
  } else if (skyResult.status === 'rejected' || skyMetaError) {
    skyWarning.value = 'Nepodarilo sa načítať planéty/meteory (sky microservice nedostupná).'
  }

  loading.value = false
}

function goToLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } })
}

function goToProfileLocation() {
  router.push({ name: 'profile.edit', hash: '#location' })
}

function safeDate(value) {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return value
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

function safeTz(value) {
  const candidate = typeof value === 'string' ? value.trim() : ''
  if (!candidate) return 'UTC'
  try {
    Intl.DateTimeFormat('en-US', { timeZone: candidate }).format(new Date())
    return candidate
  } catch {
    return 'UTC'
  }
}

function toNumber(value) {
  if (typeof value === 'number') return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : NaN
  }
  return NaN
}

function asFiniteNumber(value) {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function cleanString(value) {
  return typeof value === 'string' ? value.trim() : ''
}

function normalizeMessage(value) {
  return cleanString(value).toLowerCase().replace(/\s+/g, ' ')
}

function normalizeLabelKey(label) {
  if (typeof label !== 'string') return ''
  return label.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z]/g, '')
}

function normalizeStatusLabel(label) {
  const key = normalizeLabelKey(label)
  if (key === 'ok') return 'OK'
  if (key.startsWith('pozor') || key === 'warn') return 'Pozor'
  if (key.startsWith('zle') || key === 'severe') return 'Zlé'
  return 'Nedostupné'
}

function badgeFromLabel(label) {
  const normalized = normalizeStatusLabel(label)
  if (normalized === 'OK') return 'isOk'
  if (normalized === 'Pozor') return 'isWarn'
  if (normalized === 'Zlé') return 'isBad'
  return 'isUnknown'
}

function alertLevelLabel(level) {
  const key = normalizeLabelKey(level)
  if (key === 'warn') return 'Pozor'
  if (key === 'severe') return 'Zlé'
  if (key === 'info') return 'Info'
  return 'Nedostupné'
}

function badgeFromAlertLevel(level) {
  return badgeFromLabel(alertLevelLabel(level))
}

function badgeForSun(status) {
  if (status === 'ok') return 'isOk'
  if (status === 'continuous_day' || status === 'continuous_night') return 'isWarn'
  return 'isUnknown'
}

function sunStatusLabel(status) {
  if (status === 'ok') return 'OK'
  if (status === 'continuous_day') return 'Polárny deň'
  if (status === 'continuous_night') return 'Polárna noc'
  return 'Nedostupné'
}

function pct(value) {
  const parsed = asFiniteNumber(value)
  return parsed === null ? 'Nedostupné' : `${Math.round(parsed)}%`
}

function signedDays(value) {
  const parsed = asFiniteNumber(value)
  if (parsed === null) return 'Nedostupné'
  if (parsed === 0) return 'dnes'
  return parsed > 0 ? `+${parsed} d` : `${parsed} d`
}

function localizeDirection(value) {
  const dictionary = { N: 'S', NE: 'SV', E: 'V', SE: 'JV', S: 'J', SW: 'JZ', W: 'Z', NW: 'SZ' }
  const normalized = cleanString(value).toUpperCase()
  return dictionary[normalized] || 'Nedostupné'
}

function translateMoonPhase(value) {
  const normalized = cleanString(value).toLowerCase().replace(/\s+/g, ' ')
  const dictionary = {
    'new moon': 'Nov',
    'waxing crescent': 'Dorastajúci kosáčik',
    'first quarter': 'Prvá štvrť',
    'waxing gibbous': 'Dorastajúci Mesiac',
    'full moon': 'Spln',
    'waning gibbous': 'Ubúdajúci Mesiac',
    'last quarter': 'Posledná štvrť',
    'third quarter': 'Posledná štvrť',
    'waning crescent': 'Ubúdajúci kosáčik',
  }
  return dictionary[normalized] || cleanString(value)
}

function buildLinePath(series, field, kind) {
  if (!Array.isArray(series) || series.length < 2) return ''
  let path = ''
  let started = false
  series.forEach((point, index) => {
    const raw = asFiniteNumber(point?.[field])
    if (raw === null) {
      started = false
      return
    }
    const x = xPoint(index, series.length)
    const y = yPoint(raw, kind)
    if (!Number.isFinite(y)) {
      started = false
      return
    }
    path += started ? ` L ${x} ${y}` : `M ${x} ${y}`
    started = true
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
  if (!/^\d{2}:\d{2}$/.test(cleanString(time))) return null
  const points = graphSeries.value
  if (!Array.isArray(points) || points.length < 2) return null
  const matched = points.findIndex((row) => row.local_time === time)
  if (matched >= 0) return xPoint(matched, points.length)
  const hour = Number(time.slice(0, 2))
  return Number.isFinite(hour) ? Number(((hour / 23) * 100).toFixed(2)) : null
}

function weatherLabelFromCode(code) {
  if (!Number.isInteger(code)) return 'Neznáme'
  if (code === 0) return 'Jasno'
  if (code === 1) return 'Prevažne jasno'
  if (code === 2) return 'Polojasno'
  if (code === 3) return 'Zamračené'
  if ([45, 48].includes(code)) return 'Hmla'
  if ([51, 53, 55, 56, 57].includes(code)) return 'Mrholenie'
  if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return 'Dážď'
  if ([71, 73, 75, 77, 85, 86].includes(code)) return 'Sneženie'
  if ([95, 96, 99].includes(code)) return 'Búrka'
  return 'Neznáme'
}

function weatherIconByCode(code) {
  if (!Number.isInteger(code)) return null
  if (code === 0) return iconClear
  if ([1, 2].includes(code)) return iconPartlyCloudy
  if (code === 3) return iconOvercast
  if ([45, 48].includes(code)) return iconFog
  if ([51, 53, 55, 56, 57].includes(code)) return iconDrizzle
  if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return iconRain
  if ([71, 73, 75, 77, 85, 86].includes(code)) return iconSnow
  if ([95, 96, 99].includes(code)) return iconThunderstorm
  return iconOvercast
}

function weatherEmojiByCode(code) {
  if (!Number.isInteger(code)) return '☁️'
  if (code === 0) return '☀️'
  if ([1, 2].includes(code)) return '⛅'
  if (code === 3) return '☁️'
  if ([45, 48].includes(code)) return '🌫️'
  if ([51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return '🌧️'
  if ([71, 73, 75, 77, 85, 86].includes(code)) return '❄️'
  if ([95, 96, 99].includes(code)) return '⚡'
  return '☁️'
}
</script>

<style scoped>
.card { border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25); border-radius: 1rem; padding: 0.9rem; display: grid; gap: 0.7rem; }
.head { display: grid; gap: 0.2rem; }
h3 { margin: 0; font-size: 1rem; }
.location { margin: 0; font-size: 0.78rem; color: rgb(var(--color-text-secondary-rgb) / 0.9); }
.weatherNow { border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25); border-radius: 0.8rem; padding: 0.45rem; display: grid; grid-template-columns: auto 1fr; gap: 0.45rem; }
.weatherIcon { width: 1.4rem; height: 1.4rem; }
.weatherEmoji { font-size: 1.2rem; line-height: 1.2rem; }
.miniTitle { margin: 0; font-size: 0.66rem; text-transform: uppercase; color: rgb(var(--color-text-secondary-rgb) / 0.9); }
.temp { margin: 0; font-size: 0.9rem; font-weight: 700; }
.meta { margin: 0; font-size: 0.72rem; color: rgb(var(--color-text-secondary-rgb) / 0.95); }
.state, .body { display: grid; gap: 0.6rem; }
.modes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.3rem; }
.modeBtn { border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35); border-radius: 0.6rem; padding: 0.3rem; font-size: 0.7rem; background: transparent; }
.modeBtn.isActive { border-color: rgb(var(--color-primary-rgb) / 0.7); }
.panel { border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.22); border-radius: 0.85rem; padding: 0.6rem; display: grid; gap: 0.36rem; }
.row { margin: 0; display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.78rem; }
.bar { width: 100%; height: 0.6rem; border-radius: 999px; overflow: hidden; background: rgb(var(--color-text-secondary-rgb) / 0.15); }
.fill { display: block; height: 100%; background: linear-gradient(90deg, rgb(34 197 94 / 0.8), rgb(59 130 246 / 0.8)); }
.chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.chip { border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32); border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.68rem; }
.alerts { margin: 0; padding: 0; list-style: none; display: grid; gap: 0.3rem; }
.alerts li { display: flex; gap: 0.4rem; align-items: flex-start; }
.graph { width: 100%; height: 90px; background: rgb(var(--color-text-secondary-rgb) / 0.08); border-radius: 0.5rem; }
.line { fill: none; stroke-width: 2; }
.line.h { stroke: rgb(45 212 191 / 0.95); }
.line.c { stroke: rgb(251 191 36 / 0.95); }
.line.m { stroke: rgb(167 139 250 / 0.95); }
.marker { stroke: rgb(244 114 182 / 0.9); stroke-width: 1.4; stroke-dasharray: 2 2; }
.marker2 { stroke: rgb(34 197 94 / 0.9); }
.marker3 { stroke: rgb(96 165 250 / 0.9); }
.legend { margin: 0; display: flex; flex-wrap: wrap; gap: 0.3rem 0.6rem; font-size: 0.68rem; color: rgb(var(--color-text-secondary-rgb) / 0.9); }
.item { border-top: 1px solid rgb(var(--color-text-secondary-rgb) / 0.15); padding-top: 0.35rem; display: grid; gap: 0.2rem; }
.notice p, .infoNotice { margin: 0; font-size: 0.74rem; }
.notice p { color: rgb(251 113 133 / 0.95); }
.infoNotice { color: rgb(186 230 253 / 0.98); border: 1px solid rgb(56 189 248 / 0.3); border-radius: 0.6rem; padding: 0.4rem 0.5rem; }
.isOk { color: rgb(167 243 208); border-color: rgb(34 197 94 / 0.55); background: rgb(22 163 74 / 0.18); }
.isWarn { color: rgb(254 240 138); border-color: rgb(234 179 8 / 0.58); background: rgb(202 138 4 / 0.18); }
.isBad { color: rgb(254 202 202); border-color: rgb(244 63 94 / 0.62); background: rgb(190 24 93 / 0.2); }
.isUnknown { color: rgb(var(--color-text-secondary-rgb) / 0.96); border-color: rgb(var(--color-text-secondary-rgb) / 0.3); background: rgb(var(--color-bg-rgb) / 0.45); }
</style>
