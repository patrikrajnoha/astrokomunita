<template>
  <section class="card shell" data-tour="conditions">
    <header class="hero">
      <div class="heroLeft">
        <h3 class="title">Astronomické podmienky</h3>
        <p class="location">{{ locationLabel }}</p>
      </div>

      <section v-if="showWeatherNowCard" class="weatherNow">
        <div class="weatherNowIconWrap">
          <img v-if="weatherIconSrc" :src="weatherIconSrc" alt="" class="weatherNowIcon" />
          <span v-else class="weatherNowEmoji">{{ weatherEmoji }}</span>
        </div>
        <div class="weatherNowMeta">
          <p class="miniLabel">Počasie teraz</p>
          <p class="weatherTemp">{{ weatherNowTemperature }}</p>
          <p class="weatherText">
            {{ weatherNowLabel }}
            <span v-if="weatherNowApparent"> · Pocitovo {{ weatherNowApparent }}</span>
            <span v-if="weatherNowWind"> · Vietor {{ weatherNowWind }}</span>
          </p>
        </div>
      </section>
    </header>

    <div v-if="authPending" class="state">Načítavam...</div>
    <div v-else-if="!hasLocationCoords" class="state">
      <p>Chýbajú súradnice lokality.</p>
      <button class="btn" type="button" @click="goToProfileLocation">Nastaviť lokalitu</button>
    </div>
    <div v-else-if="loading" class="state">Načítavam dáta...</div>

    <div v-else class="body">
      <div v-if="error" class="notice error">
        <p>{{ error }}</p>
        <button class="btnGhost" type="button" @click="fetchSummary">Skúsiť znova</button>
      </div>

      <section class="primaryGrid">
        <article class="panel strong">
          <p class="miniLabel">Index pozorovania</p>
          <p class="indexValue">{{ observingIndexLabel }}</p>
          <div class="bar" role="progressbar" :aria-valuenow="observingIndexForAria" aria-valuemin="0" aria-valuemax="100">
            <span class="fill" :style="indexProgressStyle"></span>
          </div>
          <p class="statusRow">
            <span class="chip" :class="badgeFromLabel(overallStatusLabel)">{{ overallStatusLabel }}</span>
            <span>{{ primaryReason }}</span>
          </p>
        </article>

        <article class="panel">
          <p class="miniLabel">Najlepší čas dnes</p>
          <p class="bestLine">{{ bestTimeLine }}</p>
          <p v-if="bestTimeIsWeak" class="muted">Relatívne najlepšie v rámci dneška.</p>
          <p v-if="bestTimeIsWeak" class="chip isWarn weakChip">Dnes celkovo slabšie podmienky</p>
        </article>
      </section>

      <section class="metricsGrid">
        <article class="metric">
          <p class="metricLabel">Oblačnosť</p>
          <p class="metricValue">{{ cloudChipValue }}</p>
        </article>
        <article class="metric">
          <p class="metricLabel">Vlhkosť</p>
          <p class="metricValue">{{ humidityChipValue }}</p>
        </article>
        <article class="metric">
          <p class="metricLabel">Mesiac</p>
          <p class="metricValue"><span class="phaseIcon">{{ moonPhaseIcon }}</span> {{ moonChipValue }}</p>
        </article>
        <article class="metric">
          <p class="metricLabel">Seeing (vietor+vlhkosť)</p>
          <p class="metricValue">{{ seeingDisplay }}</p>
        </article>
      </section>

      <section class="panel">
        <p class="miniLabel">Bortle: {{ skyQualityLabel }}</p>
        <p class="detailRow">
          <span title="1 = tmavá obloha, 9 = silné svetelné znečistenie (mesto). Ovplyvňuje najmä deep-sky pozorovanie.">Bortle</span>
          <strong>{{ skyQualityLabel }}</strong>
        </p>
        <p class="muted">{{ skyQualityImpactNote }}</p>
        <label v-if="isAuthenticated" class="bortleControl">
          <span>Bortle: {{ selectedBortleClass }}/9</span>
          <input type="range" min="1" max="9" step="1" :value="selectedBortleClass" @input="onBortleInput" />
        </label>
      </section>

      <section v-if="additionalAlerts.length > 0" class="panel">
        <p class="miniLabel">Upozornenia</p>
        <ul class="alerts">
          <li v-for="(alert, index) in additionalAlerts" :key="`${alert.code || 'alert'}-${index}`">
            <span class="chip" :class="badgeFromAlertLevel(alert.level)">{{ alertLevelLabel(alert.level) }}</span>
            <span>{{ alert.message }}</span>
          </li>
        </ul>
      </section>

      <details class="panel">
        <summary>24h trend ({{ hasGraphData ? 'dostupný' : 'nedostupný' }})</summary>
        <div v-if="hasGraphData" class="graphWrap">
          <svg class="graph" viewBox="0 0 100 100" preserveAspectRatio="none">
            <line v-if="sunsetMarkerX !== null" :x1="sunsetMarkerX" y1="6" :x2="sunsetMarkerX" y2="94" class="marker markerSunset" />
            <line v-if="twilightMarkerX !== null" :x1="twilightMarkerX" y1="6" :x2="twilightMarkerX" y2="94" class="marker markerTwilight" />
            <line v-if="sunriseMarkerX !== null" :x1="sunriseMarkerX" y1="6" :x2="sunriseMarkerX" y2="94" class="marker markerSunrise" />
            <path :d="humidityPath" class="line humidityLine" />
            <path :d="cloudPath" class="line cloudLine" />
          </svg>
          <p class="legend">Vlhkosť (%) · Oblačnosť (%)</p>
          <p v-if="timelineMarkers.length > 0" class="legend">
            <span v-for="marker in timelineMarkers" :key="marker.label">{{ marker.label }}: {{ marker.time }}</span>
          </p>
        </div>
        <p v-else class="muted">24h trend je dočasne nedostupný.</p>
      </details>

      <section v-if="detailRows.length > 0" class="panel">
        <p class="miniLabel">Detaily</p>
        <p v-for="detail in detailRows" :key="detail.key" class="detailRow">
          <span>{{ detail.label }}</span>
          <strong>{{ detail.value }}</strong>
        </p>
      </section>

      <section class="panel">
        <p class="miniLabel">Fázy Mesiaca</p>
        <div v-if="moonPhaseCards.length > 0" class="moonPhaseRail">
          <button
            v-for="(phase, index) in moonPhaseCards"
            :key="`moon-phase-card-${index}-${phase.from_local || phase.at_local || 'now'}`"
            type="button"
            class="moonPhaseCard phaseLink"
            :class="{ isCurrent: isCurrentMoonPhase(phase, index) }"
            @click="openMoonPhaseEvent(phase)"
          >
            <div class="moonPhaseDiscWrap">
              <span class="moonPhaseDisc">{{ moonPhaseEmoji(phase.phase) }}</span>
            </div>
            <p class="moonPhaseTitle">{{ translateMoonPhase(phase.phase) }}</p>
            <p v-if="isCurrentMoonPhase(phase, index) && moonIllumination" class="moonPhaseIllum">{{ moonIllumination }}</p>
            <p class="moonPhaseDate">{{ formatMoonPhaseRange(phase.from_local, phase.to_local, phase.at_local) }}</p>
          </button>
        </div>
        <p v-else class="muted">Mesačný rozpis fáz zatiaľ nie je dostupný.</p>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
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

const auth = useAuthStore()
const preferences = useEventPreferencesStore()
const router = useRouter()

const observeSummary = ref(null)
const loading = ref(false)
const error = ref('')
let debounceTimer = null
let saveBortleTimer = null
let requestCounter = 0

const numericLat = computed(() => toNumber(props.lat))
const numericLon = computed(() => toNumber(props.lon))
const hasLocationCoords = computed(() => Number.isFinite(numericLat.value) && Number.isFinite(numericLon.value))
const isAuthenticated = computed(() => auth.isAuthed)
const authPending = computed(() => !auth.initialized)
const locationLabel = computed(() => cleanString(props.locationName) || 'Nezvolená lokalita')
const alerts = computed(() => (Array.isArray(observeSummary.value?.alerts) ? observeSummary.value.alerts : []))
const selectedBortleClass = ref(6)
const skyQualityClass = computed(() => {
  const value = Number(observeSummary.value?.sky_quality?.bortle_class ?? selectedBortleClass.value)
  if (!Number.isInteger(value)) return 6
  return Math.min(9, Math.max(1, value))
})
const skyQualityLabel = computed(() => `${skyQualityClass.value}/9`)
const skyQualityImpactNote = computed(() => cleanString(observeSummary.value?.sky_quality?.impact_note) || '1 = tmavá obloha, 9 = silné svetelné znečistenie (mesto). Ovplyvňuje najmä deep-sky pozorovanie.')

const observingIndex = computed(() => {
  const raw = asFiniteNumber(observeSummary.value?.observing_index)
  return raw === null ? null : Math.max(0, Math.min(100, Math.round(raw)))
})
const observingIndexLabel = computed(() => (observingIndex.value === null ? '-' : String(observingIndex.value)))
const observingIndexForAria = computed(() => observingIndex.value ?? 0)
const indexProgressStyle = computed(() => ({ width: `${observingIndexForAria.value}%` }))
const overallStatusLabel = computed(() => normalizeStatusLabel(observeSummary.value?.overall?.label))

const primaryReason = computed(() => {
  const reason = cleanString(observeSummary.value?.overall?.reason)
  if (reason) return reason
  return cleanString(alerts.value[0]?.message) || 'Bez doplňujúceho dôvodu.'
})

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
  const reasonRaw = cleanString(observeSummary.value?.best_time_reason).replace(/^relatívne najlepšie:\s*/i, '')
  const reason = reasonRaw || 'nižšia oblačnosť, viac tmy.'
  const summary = index !== null && index < 60 ? `Relatívne najlepšie: ${reason}` : reason
  return `${time}${index === null ? '' : ` (${Math.round(index)}/100)`} – ${summary}`
})

const bestTimeIsWeak = computed(() => {
  const index = asFiniteNumber(observeSummary.value?.best_time_index)
  return index !== null && index < 60
})

const moonPhaseLabel = computed(() => translateMoonPhase(observeSummary.value?.moon?.phase_name) || 'Nedostupné')
const moonPhaseIcon = computed(() => moonPhaseEmoji(observeSummary.value?.moon?.phase_name))
const moonIllumination = computed(() => {
  const value = asFiniteNumber(observeSummary.value?.moon?.illumination_pct)
  return value === null ? null : `${Math.round(value)}%`
})
const moonChipValue = computed(() => (moonIllumination.value ? `${moonPhaseLabel.value} (${moonIllumination.value})` : moonPhaseLabel.value))
const moonPhaseSchedule = computed(() => {
  const rows = Array.isArray(observeSummary.value?.moon?.phase_schedule) ? observeSummary.value.moon.phase_schedule : []
  return rows
    .filter((row) => cleanString(row?.phase) && cleanString(row?.at_local))
    .slice(0, 16)
})
const moonPhaseCards = computed(() => {
  const targetDate = safeDate(props.date)

  if (moonPhaseSchedule.value.length > 1) {
    const cards = buildMoonPhaseCards(moonPhaseSchedule.value, targetDate)
    return filterCurrentAndFutureMoonPhases(cards, targetDate, observeSummary.value?.moon?.phase_name)
  }
  if (moonPhaseSchedule.value.length === 1) {
    const one = moonPhaseSchedule.value[0]
    const synthetic = buildSyntheticMoonPhaseCards(targetDate, safeTz(props.tz))
    const merged = mergeSingleScheduledMoonPhase(one, synthetic)
    return filterCurrentAndFutureMoonPhases(merged, targetDate, observeSummary.value?.moon?.phase_name)
  }
  const synthetic = buildSyntheticMoonPhaseCards(targetDate, safeTz(props.tz))
  if (synthetic.length > 0) return filterCurrentAndFutureMoonPhases(synthetic, targetDate, observeSummary.value?.moon?.phase_name)
  if (cleanString(observeSummary.value?.moon?.phase_name)) {
    return [{
      phase: observeSummary.value.moon.phase_name,
      at_local: null,
      from_local: null,
      to_local: null,
      event_id: null,
    }]
  }
  return []
})
const currentMoonCardIndex = computed(() => {
  const currentPhase = normalizeMoonPhaseKey(observeSummary.value?.moon?.phase_name)
  if (!currentPhase || moonPhaseCards.value.length === 0) return -1

  const reference = localNowYmdHm(safeTz(props.tz)) || `${safeDate(props.date)} 00:00`
  let containingIndex = -1
  let earliestFutureIndex = -1
  let earliestFuturePoint = ''
  let latestPastIndex = -1
  let latestPastPoint = ''

  moonPhaseCards.value.forEach((card, index) => {
    const phase = normalizeMoonPhaseKey(card?.phase)
    if (phase !== currentPhase) return

    const fromLocal = cleanString(card?.from_local)
    const toLocal = cleanString(card?.to_local)
    const atLocal = cleanString(card?.at_local)
    const point = atLocal || fromLocal || toLocal

    if (fromLocal && toLocal && fromLocal <= reference && reference <= toLocal) {
      containingIndex = index
      return
    }

    if (!point) {
      if (latestPastIndex === -1) latestPastIndex = index
      return
    }

    if (point >= reference) {
      if (!earliestFuturePoint || point < earliestFuturePoint) {
        earliestFuturePoint = point
        earliestFutureIndex = index
      }
      return
    }

    if (!latestPastPoint || point > latestPastPoint) {
      latestPastPoint = point
      latestPastIndex = index
    }
  })

  if (containingIndex !== -1) return containingIndex
  if (earliestFutureIndex !== -1) return earliestFutureIndex
  return latestPastIndex
})

const humidityChipValue = computed(() => pct(observeSummary.value?.atmosphere?.humidity?.evening_pct ?? observeSummary.value?.atmosphere?.humidity?.current_pct))
const cloudChipValue = computed(() => pct(observeSummary.value?.atmosphere?.cloud_cover?.evening_pct ?? observeSummary.value?.atmosphere?.cloud_cover?.current_pct))

const seeingDisplay = computed(() => {
  const status = observeSummary.value?.atmosphere?.seeing?.status
  const score = asFiniteNumber(observeSummary.value?.atmosphere?.seeing?.score)
  if (status === 'unavailable' || score === null) return 'Nedostupné'
  const wind = asFiniteNumber(observeSummary.value?.atmosphere?.seeing?.wind_speed_kmh)
  const humidity = asFiniteNumber(observeSummary.value?.atmosphere?.seeing?.humidity_pct)
  const suffix = []
  if (wind !== null) suffix.push(`${wind.toFixed(1)} km/h`)
  if (humidity !== null) suffix.push(`${Math.round(humidity)}%`)
  return suffix.length > 0 ? `${Math.round(score)} / 100 · ${suffix.join(' · ')}` : `${Math.round(score)} / 100`
})

const pm25 = computed(() => asFiniteNumber(observeSummary.value?.atmosphere?.air_quality?.pm25))
const pm10 = computed(() => asFiniteNumber(observeSummary.value?.atmosphere?.air_quality?.pm10))
const showPm = computed(() => pm25.value !== null || pm10.value !== null)
const pmDisplay = computed(() => `${pm25.value === null ? 'Nedostupné' : pm25.value.toFixed(1)} / ${pm10.value === null ? 'Nedostupné' : pm10.value.toFixed(1)} µg/m³`)

const detailRows = computed(() => {
  const rows = []
  if (showPm.value) rows.push({ key: 'pm', label: 'PM2.5 / PM10', value: pmDisplay.value })
  return rows
})

const graphSeries = computed(() => {
  const hourly = Array.isArray(observeSummary.value?.timeline?.hourly) ? observeSummary.value.timeline.hourly : []
  return hourly
    .filter((row) => typeof row?.local_time === 'string')
    .map((row) => ({
      local_time: row.local_time,
      humidity_pct: asFiniteNumber(row.humidity_pct),
      cloud_cover_pct: asFiniteNumber(row.cloud_cover_pct),
      moon_altitude_deg: null,
    }))
})

const hasGraphData = computed(() => graphSeries.value.length > 1)
const humidityPath = computed(() => buildLinePath(graphSeries.value, 'humidity_pct', 'percent'))
const cloudPath = computed(() => buildLinePath(graphSeries.value, 'cloud_cover_pct', 'percent'))
const sunsetMarkerX = computed(() => markerX(observeSummary.value?.timeline?.sunset))
const twilightMarkerX = computed(() => markerX(observeSummary.value?.timeline?.civil_twilight_end))
const sunriseMarkerX = computed(() => markerX(observeSummary.value?.timeline?.sunrise))
const timelineMarkers = computed(() => ([
  { label: 'Západ Slnka', time: cleanString(observeSummary.value?.timeline?.sunset) },
  { label: 'Tma od', time: cleanString(observeSummary.value?.timeline?.civil_twilight_end) },
  { label: 'Východ Slnka', time: cleanString(observeSummary.value?.timeline?.sunrise) },
]).filter((marker) => marker.time))

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
const isNightNow = computed(() => {
  const sunset = parseClockTime(observeSummary.value?.sun?.sunset)
  const sunrise = parseClockTime(observeSummary.value?.sun?.sunrise)
  const nowMinutes = localNowMinutes(safeTz(props.tz))
  if (nowMinutes === null) return false
  if (sunset === null || sunrise === null) {
    // Fallback when sunrise/sunset are unavailable: treat late evening and night as dark hours.
    const hour = Math.floor(nowMinutes / 60)
    return hour >= 20 || hour < 6
  }
  return nowMinutes >= sunset || nowMinutes < sunrise
})
const weatherIconSrc = computed(() => weatherIconByCode(weatherNowCode.value, isNightNow.value))
const weatherEmoji = computed(() => weatherEmojiByCode(weatherNowCode.value, isNightNow.value))

watch(
  () => [props.lat, props.lon, props.date, props.tz, auth.initialized, auth.isAuthed],
  queueFetch,
  { immediate: true },
)

watch(
  () => [auth.isAuthed, preferences.bortleClass],
  ([isAuthed, bortle]) => {
    if (!isAuthed) {
      selectedBortleClass.value = 6
      return
    }
    const parsed = Number(bortle)
    selectedBortleClass.value = Number.isInteger(parsed) ? Math.min(9, Math.max(1, parsed)) : 6
    queueFetch()
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (debounceTimer) window.clearTimeout(debounceTimer)
  if (saveBortleTimer) window.clearTimeout(saveBortleTimer)
})

function queueFetch() {
  if (debounceTimer) window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(fetchSummary, 220)
}

async function fetchSummary() {
  if (!hasLocationCoords.value) {
    observeSummary.value = null
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
        date: safeDate(props.date),
        tz: safeTz(props.tz),
        mode: 'deep_sky',
        bortle_class: selectedBortleClass.value,
      },
    })

    if (runId !== requestCounter) return
    observeSummary.value = response?.data ?? null
  } catch {
    if (runId !== requestCounter) return
    observeSummary.value = null
    error.value = 'Nepodarilo sa načítať astronomické podmienky.'
  } finally {
    if (runId === requestCounter) loading.value = false
  }
}

function goToProfileLocation() {
  router.push({ name: 'profile', query: { edit: '1', section: 'location' } })
}

function openMoonPhaseEvent(phase) {
  const eventId = Number(phase?.event_id)
  if (Number.isInteger(eventId) && eventId > 0) {
    router.push(`/events/${eventId}`)
  }
}

function onBortleInput(event) {
  const value = Number(event?.target?.value)
  if (!Number.isInteger(value)) return
  selectedBortleClass.value = Math.min(9, Math.max(1, value))

  if (saveBortleTimer) window.clearTimeout(saveBortleTimer)
  saveBortleTimer = window.setTimeout(async () => {
    if (!isAuthenticated.value) {
      queueFetch()
      return
    }

    try {
      await preferences.savePreferences({ bortle_class: selectedBortleClass.value })
    } finally {
      queueFetch()
    }
  }, 260)
}

function normalizeMoonPhaseKey(value) {
  return cleanString(value).toLowerCase().replace(/\s+/g, ' ')
}

function isCurrentMoonPhase(card, index) {
  const candidate = normalizeMoonPhaseKey(card?.phase)
  const current = normalizeMoonPhaseKey(observeSummary.value?.moon?.phase_name)
  if (!candidate || !current || candidate !== current) return false
  return index === currentMoonCardIndex.value
}

function buildMoonPhaseCards(schedule, targetDate) {
  const targetMonth = cleanString(targetDate).slice(0, 7)
  const events = [...schedule]
    .map((row) => ({
      phase: normalizeMoonPhaseKey(row.phase),
      at_local: cleanString(row.at_local),
      event_id: Number.isInteger(Number(row.event_id)) ? Number(row.event_id) : null,
    }))
    .filter((row) => row.phase && row.at_local)
    .sort((a, b) => a.at_local.localeCompare(b.at_local))

  if (events.length === 0) return []
  const cards = []

  for (let i = 0; i < events.length; i += 1) {
    const current = events[i]
    const next = events[i + 1]

    cards.push({
      phase: current.phase,
      at_local: current.at_local,
      from_local: current.at_local,
      to_local: current.at_local,
      event_id: current.event_id,
    })

    if (!next) continue
    const between = intermediatePhase(current.phase, next.phase)
    if (!between) continue
    cards.push({
      phase: between,
      at_local: null,
      from_local: current.at_local,
      to_local: next.at_local,
      event_id: null,
    })
  }

  if (!targetMonth) return cards
  const inMonth = cards.filter((card) => {
    const fromMonth = cleanString(card.from_local).slice(0, 7)
    const toMonth = cleanString(card.to_local).slice(0, 7)
    const atMonth = cleanString(card.at_local).slice(0, 7)
    return fromMonth === targetMonth || toMonth === targetMonth || atMonth === targetMonth
  })

  return inMonth.length > 0 ? inMonth : cards
}

function intermediatePhase(fromPhase, toPhase) {
  const from = normalizeMoonPhaseKey(fromPhase)
  const to = normalizeMoonPhaseKey(toPhase)
  const key = `${from}->${to}`
  const map = {
    'new moon->first quarter': 'waxing crescent',
    'first quarter->full moon': 'waxing gibbous',
    'full moon->last quarter': 'waning gibbous',
    'last quarter->new moon': 'waning crescent',
  }
  return map[key] || null
}

function buildSyntheticMoonPhaseCards(dateYmd, tz) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(cleanString(dateYmd))) return []
  const [y, m] = dateYmd.split('-').map((v) => Number(v))
  if (!Number.isInteger(y) || !Number.isInteger(m)) return []

  const monthStartUtcMs = Date.UTC(y, m - 1, 1, 0, 0, 0)
  const monthEndUtcMs = Date.UTC(y, m, 0, 23, 59, 59)

  const synodicDays = 29.530588853
  const stepMs = (synodicDays / 8) * 86400000
  const epochMs = Date.UTC(2000, 0, 6, 18, 14, 0) // known new moon reference
  const phaseOrder = ['new moon', 'waxing crescent', 'first quarter', 'waxing gibbous', 'full moon', 'waning gibbous', 'last quarter', 'waning crescent']

  const startIndex = Math.floor((monthStartUtcMs - epochMs) / stepMs) - 2
  const endIndex = Math.ceil((monthEndUtcMs - epochMs) / stepMs) + 2
  const cards = []

  for (let i = startIndex; i < endIndex; i += 1) {
    const fromMs = epochMs + (i * stepMs)
    const toMs = epochMs + ((i + 1) * stepMs)
    if (toMs < monthStartUtcMs || fromMs > monthEndUtcMs) continue

    const phase = phaseOrder[((i % 8) + 8) % 8]
    cards.push({
      phase,
      at_local: null,
      from_local: formatUtcAsLocalYmdHm(fromMs, tz),
      to_local: formatUtcAsLocalYmdHm(toMs, tz),
      event_id: null,
    })
  }

  return cards
}

function mergeSingleScheduledMoonPhase(scheduled, syntheticCards) {
  const scheduledCard = {
    phase: normalizeMoonPhaseKey(scheduled?.phase),
    at_local: cleanString(scheduled?.at_local),
    from_local: cleanString(scheduled?.at_local),
    to_local: cleanString(scheduled?.at_local),
    event_id: Number.isInteger(Number(scheduled?.event_id)) ? Number(scheduled.event_id) : null,
  }
  if (!scheduledCard.phase || !scheduledCard.at_local) return syntheticCards
  if (!Array.isArray(syntheticCards) || syntheticCards.length === 0) return [scheduledCard]

  const merged = syntheticCards.map((card) => {
    const phase = normalizeMoonPhaseKey(card?.phase)
    const fromLocal = cleanString(card?.from_local)
    const toLocal = cleanString(card?.to_local)
    if (!phase || !fromLocal || !toLocal) return card

    const isSamePhase = phase === scheduledCard.phase
    const coversScheduledTime = fromLocal <= scheduledCard.at_local && scheduledCard.at_local <= toLocal
    if (!isSamePhase || !coversScheduledTime) return card

    return {
      ...card,
      at_local: scheduledCard.at_local,
      event_id: scheduledCard.event_id,
    }
  })

  const hasPlacedScheduled = merged.some((card) => Number(card?.event_id) === scheduledCard.event_id && cleanString(card?.at_local) === scheduledCard.at_local)
  return hasPlacedScheduled ? merged : [scheduledCard, ...merged]
}

function filterCurrentAndFutureMoonPhases(cards, targetDate, currentPhaseName) {
  if (!Array.isArray(cards) || cards.length === 0) return []
  const fallbackDate = cleanString(targetDate)
  const reference = localNowYmdHm(safeTz(props.tz)) || (/^\d{4}-\d{2}-\d{2}$/.test(fallbackDate) ? `${fallbackDate} 00:00` : '')
  if (!reference) return cards
  const currentPhase = normalizeMoonPhaseKey(currentPhaseName)
  const filtered = cards.filter((card) => {
    const atLocal = cleanString(card?.at_local)
    const fromLocal = cleanString(card?.from_local)
    const toLocal = cleanString(card?.to_local)
    const phase = normalizeMoonPhaseKey(card?.phase)

    if (phase && phase === currentPhase) return true

    if (atLocal) return atLocal >= reference
    if (toLocal) return toLocal >= reference
    if (fromLocal) return fromLocal >= reference

    return phase && phase === currentPhase
  })

  return filtered.length > 0 ? filtered : cards
}

function localNowYmdHm(timeZone) {
  try {
    const formatter = new Intl.DateTimeFormat('sv-SE', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    })
    return formatter.format(new Date())
  } catch {
    return ''
  }
}

function formatUtcAsLocalYmdHm(utcMs, tz) {
  const date = new Date(utcMs)
  try {
    const fmt = new Intl.DateTimeFormat('sv-SE', {
      timeZone: tz,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    })
    return fmt.format(date).replace(' ', ' ')
  } catch {
    const y = date.getUTCFullYear()
    const mo = String(date.getUTCMonth() + 1).padStart(2, '0')
    const d = String(date.getUTCDate()).padStart(2, '0')
    const h = String(date.getUTCHours()).padStart(2, '0')
    const mi = String(date.getUTCMinutes()).padStart(2, '0')
    return `${y}-${mo}-${d} ${h}:${mi}`
  }
}

function safeDate(value) {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return value
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

function safeTz(value) {
  const candidate = cleanString(value)
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

function pct(value) {
  const parsed = asFiniteNumber(value)
  return parsed === null ? 'Nedostupné' : `${Math.round(parsed)}%`
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

function moonPhaseEmoji(value) {
  const normalized = cleanString(value).toLowerCase().replace(/\s+/g, ' ')
  const dictionary = {
    'new moon': '\uD83C\uDF11',
    'waxing crescent': '\uD83C\uDF12',
    'first quarter': '\uD83C\uDF13',
    'waxing gibbous': '\uD83C\uDF14',
    'full moon': '\uD83C\uDF15',
    'waning gibbous': '\uD83C\uDF16',
    'last quarter': '\uD83C\uDF17',
    'third quarter': '\uD83C\uDF17',
    'waning crescent': '\uD83C\uDF18',
  }
  return dictionary[normalized] || '\uD83C\uDF19'
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

function parseClockTime(value) {
  const normalized = cleanString(value)
  if (!/^\d{2}:\d{2}$/.test(normalized)) return null
  const hours = Number(normalized.slice(0, 2))
  const minutes = Number(normalized.slice(3, 5))
  if (!Number.isInteger(hours) || !Number.isInteger(minutes)) return null
  if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null
  return (hours * 60) + minutes
}

function localNowMinutes(timeZone) {
  try {
    const formatter = new Intl.DateTimeFormat('en-GB', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    })
    const parts = formatter.formatToParts(new Date())
    const hours = Number(parts.find((part) => part.type === 'hour')?.value)
    const minutes = Number(parts.find((part) => part.type === 'minute')?.value)
    if (!Number.isInteger(hours) || !Number.isInteger(minutes)) return null
    return (hours * 60) + minutes
  } catch {
    return null
  }
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

function weatherIconByCode(code, isNight = false) {
  if (!Number.isInteger(code)) return null
  if (code === 0 && isNight) return null
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

function weatherEmojiByCode(code, isNight = false) {
  if (!Number.isInteger(code)) return '\u2601\uFE0F'
  if (code === 0 && isNight) return '\u{1F319}'
  if (code === 0) return '\u2600\uFE0F'
  if ([1, 2].includes(code)) return '\u26C5'
  if (code === 3) return '\u2601\uFE0F'
  if ([45, 48].includes(code)) return '\u{1F32B}\uFE0F'
  if ([51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return '\u{1F327}\uFE0F'
  if ([71, 73, 75, 77, 85, 86].includes(code)) return '\u2744\uFE0F'
  if ([95, 96, 99].includes(code)) return '\u26A1'
  return '\u2601\uFE0F'
}

function formatMoonPhaseDate(value) {
  const normalized = cleanString(value)
  if (!normalized) return 'Aktuálne'
  const candidate = normalized.includes('T') ? normalized : normalized.replace(' ', 'T')
  const date = new Date(candidate)
  if (Number.isNaN(date.getTime())) return normalized

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  } catch {
    return normalized
  }
}

function formatMoonPhaseRange(fromValue, toValue, pointValue) {
  const from = cleanString(fromValue)
  const to = cleanString(toValue)
  const point = cleanString(pointValue)

  if (from && to && from !== to) {
    return `${formatMoonPhaseDate(from)} - ${formatMoonPhaseDate(to)}`
  }
  if (point) return formatMoonPhaseDate(point)
  if (from) return formatMoonPhaseDate(from)
  if (to) return formatMoonPhaseDate(to)
  return 'Aktuálne'
}
</script>

<style scoped>
.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 1.2rem;
  padding: 0.95rem;
  background: linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.85), rgb(var(--color-bg-rgb) / 0.66));
  backdrop-filter: blur(8px);
  display: grid;
  gap: 0.75rem;
}

.hero {
  display: grid;
  grid-template-columns: 1fr minmax(170px, 1fr);
  gap: 0.55rem;
  align-items: start;
}

.title {
  margin: 0;
  font-size: 1.02rem;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.location {
  margin: 0.2rem 0 0;
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.weatherNow {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  border-radius: 0.95rem;
  padding: 0.48rem;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.45rem;
  background: rgb(var(--color-text-secondary-rgb) / 0.06);
}

.weatherNowIconWrap {
  width: 2rem;
  height: 2rem;
  border-radius: 0.6rem;
  background: rgb(var(--color-text-secondary-rgb) / 0.12);
  display: grid;
  place-items: center;
}

.weatherNowIcon {
  width: 1.35rem;
  height: 1.35rem;
}

.weatherNowEmoji {
  font-size: 1.2rem;
}

.miniLabel {
  margin: 0;
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
}

.weatherTemp {
  margin: 0.04rem 0 0;
  font-size: 0.9rem;
  font-weight: 700;
}

.weatherText {
  margin: 0.08rem 0 0;
  font-size: 0.72rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
}

.state,
.body {
  display: grid;
  gap: 0.55rem;
}

.btn,
.btnGhost {
  width: fit-content;
  border-radius: 0.75rem;
  padding: 0.42rem 0.68rem;
  font-size: 0.76rem;
}

.btn {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.15);
  color: var(--color-surface);
}

.btnGhost {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-text-secondary-rgb) / 0.08);
  color: var(--color-surface);
}

.notice {
  margin: 0;
  border-radius: 0.8rem;
  padding: 0.45rem 0.55rem;
  font-size: 0.75rem;
}

.notice.error {
  border: 1px solid rgb(251 113 133 / 0.35);
  color: rgb(251 113 133 / 0.95);
  background: rgb(190 24 93 / 0.1);
}

.primaryGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.55rem;
}

.panel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  border-radius: 0.95rem;
  padding: 0.62rem;
  display: grid;
  gap: 0.36rem;
  background: rgb(var(--color-text-secondary-rgb) / 0.045);
}

.panel.strong {
  border-color: rgb(var(--color-primary-rgb) / 0.28);
}

.indexValue {
  margin: 0;
  font-size: 1.42rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.bar {
  width: 100%;
  height: 0.56rem;
  border-radius: 999px;
  overflow: hidden;
  background: rgb(var(--color-text-secondary-rgb) / 0.15);
}

.fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, rgb(34 197 94 / 0.8), rgb(59 130 246 / 0.8));
}

.statusRow {
  margin: 0;
  display: flex;
  gap: 0.42rem;
  align-items: flex-start;
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.bestLine {
  margin: 0;
  font-size: 0.8rem;
  line-height: 1.38;
}

.weakChip {
  width: fit-content;
}

.metricsGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.42rem;
}

.metric {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  border-radius: 0.82rem;
  padding: 0.46rem;
  background: rgb(var(--color-text-secondary-rgb) / 0.04);
}

.metricLabel,
.metricValue {
  margin: 0;
}

.metricLabel {
  font-size: 0.68rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.84);
}

.metricValue {
  margin-top: 0.14rem;
  font-size: 0.8rem;
  font-weight: 600;
}

.alerts {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.3rem;
}

.alerts li {
  display: flex;
  gap: 0.42rem;
  align-items: flex-start;
  font-size: 0.74rem;
}

.chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0.12rem 0.5rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  font-size: 0.67rem;
  font-weight: 700;
}

.graphWrap {
  display: grid;
  gap: 0.35rem;
}

.graph {
  width: 100%;
  height: 94px;
  border-radius: 0.56rem;
  background: rgb(var(--color-text-secondary-rgb) / 0.08);
}

.line {
  fill: none;
  stroke-width: 2;
}

.humidityLine {
  stroke: rgb(45 212 191 / 0.95);
}

.cloudLine {
  stroke: rgb(251 191 36 / 0.95);
}

.marker {
  stroke-width: 1.4;
  stroke-dasharray: 2 2;
}

.markerSunset {
  stroke: rgb(244 114 182 / 0.9);
}

.markerTwilight {
  stroke: rgb(34 197 94 / 0.9);
}

.markerSunrise {
  stroke: rgb(96 165 250 / 0.9);
}

.legend {
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.28rem 0.6rem;
  font-size: 0.68rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}

.detailRow {
  margin: 0;
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.78rem;
}

.detailRow strong {
  text-align: right;
}

.bortleControl {
  display: grid;
  gap: 0.3rem;
  font-size: 0.74rem;
}

.bortleControl input[type='range'] {
  width: 100%;
}

.phaseLink {
  width: 100%;
  border: 0;
  background: transparent;
  padding: 0;
  text-align: left;
  cursor: pointer;
  color: inherit;
  font: inherit;
}

.phaseIcon {
  display: inline-block;
  min-width: 1.1rem;
  text-align: center;
}

.moonPhaseRail {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.4rem;
}

.moonPhaseCard {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 0.8rem;
  padding: 0.45rem 0.35rem;
  background: linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.62), rgb(var(--color-bg-rgb) / 0.35));
  display: grid;
  gap: 0.2rem;
  justify-items: center;
}

.moonPhaseCard.isCurrent {
  border-color: rgb(251 191 36 / 0.7);
  box-shadow: inset 0 0 0 1px rgb(251 191 36 / 0.35);
}

.moonPhaseDiscWrap {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgb(var(--color-text-secondary-rgb) / 0.12);
}

.moonPhaseDisc {
  font-size: 1.5rem;
  line-height: 1;
}

.moonPhaseTitle {
  margin: 0.05rem 0 0;
  text-align: center;
  font-size: 0.66rem;
  font-weight: 600;
}

.moonPhaseIllum {
  margin: 0;
  font-size: 0.66rem;
  color: rgb(251 191 36 / 0.95);
  font-weight: 700;
}

.moonPhaseDate {
  margin: 0;
  font-size: 0.62rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  text-align: center;
}

.muted {
  margin: 0;
  font-size: 0.74rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
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

@media (max-width: 900px) {
  .hero {
    grid-template-columns: 1fr;
  }

  .primaryGrid,
  .metricsGrid {
    grid-template-columns: 1fr;
  }

  .moonPhaseRail {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 560px) {
  .moonPhaseRail {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
