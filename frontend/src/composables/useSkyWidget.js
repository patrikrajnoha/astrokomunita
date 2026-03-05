import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import {
  getBortlePresentation,
  isPlanetNight,
  getScorePresentation,
  getVisiblePlanets,
} from '@/utils/skyWidget'
import { calculateAstronomyScore } from '@/utils/astronomyScore'

const CHEAP_REFRESH_MS = 10 * 60 * 1000
const ASTRONOMY_REFRESH_MS = 60 * 60 * 1000
const FRESHNESS_TICK_MS = 60 * 1000

export const SKY_PHASE = Object.freeze({
  LOCATION_REQUIRED: 'location_required',
  UNKNOWN: 'unknown',
  DAY: 'day',
  CIVIL_TWILIGHT: 'civil_twilight',
  NAUTICAL_TWILIGHT: 'nautical_twilight',
  ASTRONOMICAL_TWILIGHT: 'astronomical_twilight',
  ASTRONOMICAL_NIGHT: 'astronomical_night',
})

export function useSkyWidget(options = {}) {
  const lat = options.lat
  const lon = options.lon
  const tz = options.tz

  const weather = ref(null)
  const astronomy = ref(null)
  const planetsPayload = ref({ planets: [], sample_at: null, sun_altitude_deg: null })
  const issPreview = ref({ available: false })
  const lightPollution = ref(null)

  const weatherLoading = ref(false)
  const astronomyLoading = ref(false)
  const planetsLoading = ref(false)
  const issLoading = ref(false)
  const lightPollutionLoading = ref(false)

  const weatherError = ref('')
  const astronomyError = ref('')
  const planetsError = ref('')
  const issError = ref('')
  const lightPollutionError = ref('')
  const weatherFetchedAt = ref(null)
  const astronomyFetchedAt = ref(null)
  const planetsFetchedAt = ref(null)
  const issFetchedAt = ref(null)
  const lightPollutionFetchedAt = ref(null)

  const isMounted = ref(false)
  const nowTick = ref(Date.now())

  let cheapRefreshTimer = null
  let astronomyRefreshTimer = null
  let freshnessTimer = null
  let deferredTimer = null

  const requestTokens = {
    weather: 0,
    astronomy: 0,
    planets: 0,
    iss: 0,
    light: 0,
  }

  const numericLat = computed(() => toFiniteNumber(lat?.value))
  const numericLon = computed(() => toFiniteNumber(lon?.value))
  const effectiveTz = computed(() => {
    const candidate = String(tz?.value || '').trim()
    if (!candidate) return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Bratislava'

    try {
      Intl.DateTimeFormat('en-US', { timeZone: candidate }).format(new Date())
      return candidate
    } catch {
      return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Bratislava'
    }
  })

  const hasLocationCoords = computed(() => Number.isFinite(numericLat.value) && Number.isFinite(numericLon.value))
  const sunAltitudeDeg = computed(() => {
    const fromPlanets = toFiniteNumber(planetsPayload.value?.sun_altitude_deg)
    if (fromPlanets !== null) return fromPlanets
    return toFiniteNumber(astronomy.value?.sun_altitude_deg)
  })
  const skyPhase = computed(() => classifySkyPhase({
    hasLocationCoords: hasLocationCoords.value,
    sunAltitudeDeg: sunAltitudeDeg.value,
  }))
  const scoreModel = computed(() => calculateAstronomyScore({
    sunAltitudeDeg: sunAltitudeDeg.value,
    cloudPercent: weather.value?.cloud_percent,
    humidityPercent: weather.value?.humidity_percent,
    windKmh: weather.value?.wind_speed,
    moonIlluminationPercent: astronomy.value?.moon_illumination_percent,
    moonAltitudeDeg: astronomy.value?.moon_altitude_deg,
    bortleClass: lightPollution.value?.bortle_class,
  }))
  const rawObservingScore = computed(() => scoreModel.value.score)
  const scoreReasons = computed(() => (Array.isArray(scoreModel.value.reasons) ? scoreModel.value.reasons : []))

  const isDaylight = computed(() => scoreModel.value.phase === 'daylight')
  const isAstronomicalNight = computed(() => scoreModel.value.phase === 'astronomical_night')
  const isTwilightLimited = computed(() => scoreModel.value.phase === 'twilight')

  const observingScore = computed(() => rawObservingScore.value)

  const scorePresentation = computed(() => {
    if (skyPhase.value === SKY_PHASE.LOCATION_REQUIRED) {
      return { label: 'Poloha nenastavena', emoji: '📍' }
    }

    if (skyPhase.value === SKY_PHASE.UNKNOWN) {
      return { label: 'Neznáme', emoji: '❔' }
    }

    if (isDaylight.value) {
      return { label: 'Denné svetlo', emoji: '☀️' }
    }

    if (skyPhase.value === SKY_PHASE.CIVIL_TWILIGHT || skyPhase.value === SKY_PHASE.NAUTICAL_TWILIGHT) {
      return { label: 'Namorny sumrak', emoji: '🌆' }
    }

    if (skyPhase.value === SKY_PHASE.ASTRONOMICAL_TWILIGHT) {
      return { label: 'Astronomicky sumrak', emoji: '🌌' }
    }

    return getScorePresentation(observingScore.value)
  })

  const scoreLabel = computed(() => scorePresentation.value.label)
  const scoreEmoji = computed(() => scorePresentation.value.emoji)

  const scoreColorClass = computed(() => {
    if (observingScore.value === null) return 'bg-slate-500/60'
    if (observingScore.value < 40) return 'bg-rose-500/70'
    if (observingScore.value < 65) return 'bg-amber-500/70'
    if (observingScore.value < 85) return 'bg-sky-500/70'
    return 'bg-emerald-500/70'
  })

  const darkWindow = computed(() => {
    const nauticalEnd = parseDate(astronomy.value?.nautical_twilight_end_at)
    const nauticalStart = parseDate(astronomy.value?.nautical_twilight_start_at)
    if (nauticalEnd && nauticalStart) {
      return { start: nauticalEnd, end: nauticalStart, source: 'nautical' }
    }

    const sunset = parseDate(astronomy.value?.sunset_at)
    const sunrise = parseDate(astronomy.value?.sunrise_at)
    if (sunset && sunrise) {
      return { start: sunset, end: sunrise, source: 'sun' }
    }

    return null
  })

  const isDark = computed(() => {
    if (!darkWindow.value) return null

    const now = Date.now()
    const start = darkWindow.value.start.getTime()
    const end = darkWindow.value.end.getTime()

    if (start <= end) {
      return now >= start && now < end
    }

    return now >= start || now < end
  })

  const nightStartsAt = computed(() => darkWindow.value?.start || null)

  const bestTimeToday = computed(() => {
    const sunset = parseDate(astronomy.value?.sunset_at)
    if (!(sunset instanceof Date)) return null

    const twilightEnd = parseDate(astronomy.value?.nautical_twilight_end_at)
      || parseDate(astronomy.value?.civil_twilight_end_at)
    const twilightSpanMs = twilightEnd instanceof Date && twilightEnd > sunset
      ? twilightEnd.getTime() - sunset.getTime()
      : 45 * 60 * 1000

    const start = new Date(sunset.getTime() + (twilightSpanMs * 2))
    const end = new Date(start.getTime() + (90 * 60 * 1000))

    return {
      window: `${formatTime(start, effectiveTz.value)} - ${formatTime(end, effectiveTz.value)}`,
      note: (observingScore.value ?? 0) >= 65
        ? 'Najtmavšie okno po súmraku.'
        : 'Najlepšie okno v rámci dneška.',
    }
  })

  const bestTimeLabel = computed(() => {
    if (skyPhase.value === SKY_PHASE.LOCATION_REQUIRED) {
      return 'Nastav polohu pre vypocet.'
    }

    if (skyPhase.value === SKY_PHASE.UNKNOWN) {
      return 'Najlepsie okno sa nepodarilo urcit.'
    }

    if (isDaylight.value) {
      const nightStart = nightStartsAt.value ? formatTime(nightStartsAt.value, effectiveTz.value) : ''
      return nightStart ? `Noc začne: ${nightStart}` : 'Najlepšie dnes: po zotmení'
    }

    if (!bestTimeToday.value?.window) return 'Najlepšie dnes nie je dostupné.'
    return bestTimeToday.value.window
  })

  const formattedMetrics = computed(() => {
    const cloud = formatPercent(weather.value?.cloud_percent)
    const humidity = formatPercent(weather.value?.humidity_percent)
    const temperature = formatTemperature(weather.value?.temperature_c)
    const wind = formatWind(weather.value?.wind_speed, weather.value?.wind_unit)
    const conditionLabel = sanitizeLabel(weather.value?.weather_label) || 'Bez popisu'

    return {
      cloud,
      humidity,
      wind,
      temp: temperature,
      conditionLabel,
    }
  })

  const heroTitle = computed(() => (isDaylight.value ? 'Denné podmienky' : 'Astronomické podmienky'))
  const heroSubtitle = computed(() => {
    if (skyPhase.value === SKY_PHASE.LOCATION_REQUIRED) {
      return 'Poloha nie je nastavena. Nastav ju pre presny vypocet oblohy.'
    }

    if (skyPhase.value === SKY_PHASE.UNKNOWN) {
      return 'Stav oblohy je dočasne neznámy.'
    }

    if (isDaylight.value) {
      return 'Momentálne je deň. Astronomické pozorovanie nie je možné.'
    }

    if (!isAstronomicalNight.value) {
      return 'Obloha ešte nie je v plnej astronomickej tme.'
    }

    if (!bestTimeToday.value?.window) return 'Najlepšie pozorovanie dnes nie je dostupné.'
    const note = sanitizeLabel(bestTimeToday.value?.note)
    return note ? `${bestTimeToday.value.window} · ${note}` : bestTimeToday.value.window
  })

  const issLine = computed(() => {
    if (!issPreview.value?.available) return 'ISS dnes neuvidíš.'

    const passAt = formatIsoShort(issPreview.value?.next_pass_at, effectiveTz.value)
    const durationMin = formatDurationMinutes(issPreview.value?.duration_sec)
    if (passAt === '-') return 'ISS dnes neuvidíš.'

    return `Najbližší prelet: ${passAt} (${durationMin})`
  })

  const bortlePresentation = computed(() => getBortlePresentation(lightPollution.value?.bortle_class))
  const isLightPollutionEstimate = computed(() => {
    const confidence = sanitizeLabel(lightPollution.value?.confidence).toLowerCase()
    const reason = sanitizeLabel(lightPollution.value?.reason).toLowerCase()
    const fallbackReason = sanitizeLabel(lightPollution.value?.fallback_reason)
    return confidence === 'low' || reason === 'fallback' || fallbackReason !== ''
  })

  const lightPollutionLine = computed(() => {
    if (!bortlePresentation.value) return ''
    return `Svetelné znečistenie: ${bortlePresentation.value.levelText}`
  })

  const lightPollutionMetaLine = computed(() => {
    if (!bortlePresentation.value) return ''

    const context = String(bortlePresentation.value.contextText || '')
    const capitalizedContext = context ? context.charAt(0).toUpperCase() + context.slice(1) : ''
    return `${capitalizedContext} (Bortle ${bortlePresentation.value.bortle})`
  })

  const lightPollutionEstimateLine = computed(() => (
    isLightPollutionEstimate.value ? 'Odhad podľa polohy' : ''
  ))

  const planetCandidates = computed(() => (
    Array.isArray(planetsPayload.value?.planets) ? planetsPayload.value.planets : []
  ))
  const planetsSourceLine = computed(() => 'Zdroj: výpočet polohy planét')

  const planetsNightV15 = computed(() => (
    hasLocationCoords.value && isPlanetNight(planetsPayload.value?.sun_altitude_deg)
  ))
  const planetsDisplayListV15 = computed(() => getVisiblePlanets(planetsPayload.value))
  const shouldShowPlanetsListV15 = computed(() => planetsDisplayListV15.value.length > 0)
  const planetsContextLineV15 = computed(() => '')
  const planetsMessageV15 = computed(() => {
    if (!hasLocationCoords.value) {
      return 'Nastav polohu pre vypocet planet.'
    }

    const reason = sanitizeLabel(planetsPayload.value?.reason)

    if (reason === 'sky_service_unavailable') {
      return 'Planéty sú teraz nedostupné.'
    }

    if (reason === 'degraded_contract') {
      return 'Planéty sú dočasne nedostupné.'
    }

    if (!planetsNightV15.value) {
      return 'Zobrazíme po zotmení.'
    }

    if (planetsDisplayListV15.value.length === 0) {
      return 'Teraz nevidno žiadnu planétu dosť vysoko.'
    }

    return ''
  })

  const weatherUpdatedAt = computed(() => (
    resolvePayloadTimestamp(weather.value, ['updated_at', 'as_of']) || weatherFetchedAt.value
  ))
  const weatherUpdatedLabel = computed(() => formatTimeOrDash(weatherUpdatedAt.value, effectiveTz.value))
  const weatherSourceLabel = computed(() => normalizeSourceLabel(weather.value?.source))

  const weatherFreshness = computed(() => formatFreshness(weatherFetchedAt.value, nowTick.value))
  const astronomyFreshness = computed(() => formatFreshness(astronomyFetchedAt.value, nowTick.value))
  const planetsFreshness = computed(() => formatFreshness(planetsFetchedAt.value, nowTick.value))
  const issFreshness = computed(() => formatFreshness(issFetchedAt.value, nowTick.value))
  const lightPollutionFreshness = computed(() => formatFreshness(lightPollutionFetchedAt.value, nowTick.value))

  const contextKey = computed(() => {
    const latKey = Number.isFinite(numericLat.value) ? numericLat.value.toFixed(4) : 'auto'
    const lonKey = Number.isFinite(numericLon.value) ? numericLon.value.toFixed(4) : 'auto'
    return `${latKey}:${lonKey}:${effectiveTz.value}`
  })

  function buildRequestParams(includeTz = true) {
    const params = {}
    if (Number.isFinite(numericLat.value) && Number.isFinite(numericLon.value)) {
      params.lat = numericLat.value
      params.lon = numericLon.value
    }
    if (includeTz && effectiveTz.value) {
      params.tz = effectiveTz.value
    }
    return params
  }

  async function fetchWeather(options = {}) {
    const token = nextToken('weather')
    if (!options.silent) weatherLoading.value = true
    weatherError.value = ''

    if (!hasLocationCoords.value) {
      weather.value = null
      weatherFetchedAt.value = null
      weatherLoading.value = false
      return
    }

    try {
      const response = await api.get('/sky/weather', {
        params: buildRequestParams(true),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.weather) return
      const payload = response?.data || null
      weather.value = payload
      weatherFetchedAt.value = resolvePayloadTimestamp(payload, ['updated_at', 'as_of']) || new Date()
    } catch (error) {
      if (token !== requestTokens.weather) return
      weatherError.value = toFriendlyError(error, 'Nepodarilo sa načítať počasie.')
    } finally {
      if (token === requestTokens.weather && !options.silent) {
        weatherLoading.value = false
      }
    }
  }

  async function fetchAstronomy(options = {}) {
    const token = nextToken('astronomy')
    if (!options.silent) astronomyLoading.value = true
    astronomyError.value = ''

    if (!hasLocationCoords.value) {
      astronomy.value = null
      astronomyFetchedAt.value = null
      astronomyLoading.value = false
      return
    }

    try {
      const response = await api.get('/sky/astronomy', {
        params: buildRequestParams(true),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.astronomy) return
      const payload = response?.data || null
      astronomy.value = payload
      astronomyFetchedAt.value = resolvePayloadTimestamp(payload, ['sample_at']) || new Date()
    } catch (error) {
      if (token !== requestTokens.astronomy) return
      astronomyError.value = toFriendlyError(error, 'Nepodarilo sa načítať astronómiu.')
    } finally {
      if (token === requestTokens.astronomy && !options.silent) {
        astronomyLoading.value = false
      }
    }
  }

  async function fetchPlanets(options = {}) {
    const token = nextToken('planets')
    if (!options.silent) planetsLoading.value = true
    planetsError.value = ''

    if (!hasLocationCoords.value) {
      planetsPayload.value = { planets: [], sample_at: null, sun_altitude_deg: null }
      planetsFetchedAt.value = null
      planetsLoading.value = false
      return
    }

    try {
      const response = await api.get('/sky/visible-planets', {
        params: buildRequestParams(true),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.planets) return
      const payload = response?.data || { planets: [] }
      planetsPayload.value = payload
      planetsFetchedAt.value = resolvePayloadTimestamp(payload, ['sample_at']) || new Date()
    } catch (error) {
      if (token !== requestTokens.planets) return
      planetsPayload.value = { planets: [], sample_at: null, sun_altitude_deg: null }
      planetsError.value = toFriendlyError(error, 'Nepodarilo sa načítať planéty.')
    } finally {
      if (token === requestTokens.planets && !options.silent) {
        planetsLoading.value = false
      }
    }
  }

  async function fetchIssPreview(options = {}) {
    const token = nextToken('iss')
    if (!options.silent) issLoading.value = true
    issError.value = ''

    if (!hasLocationCoords.value) {
      issPreview.value = { available: false }
      issFetchedAt.value = null
      issLoading.value = false
      return
    }

    try {
      const response = await api.get('/sky/iss-preview', {
        params: buildRequestParams(true),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.iss) return
      issPreview.value = response?.data || { available: false }
      issFetchedAt.value = new Date()
    } catch {
      if (token !== requestTokens.iss) return
      issError.value = 'Údaje o ISS sú dočasne nedostupné.'
    } finally {
      if (token === requestTokens.iss && !options.silent) {
        issLoading.value = false
      }
    }
  }

  async function fetchLightPollution(options = {}) {
    const token = nextToken('light')
    if (!options.silent) lightPollutionLoading.value = true
    lightPollutionError.value = ''

    if (!hasLocationCoords.value) {
      lightPollution.value = null
      lightPollutionFetchedAt.value = null
      lightPollutionLoading.value = false
      return
    }

    try {
      const response = await api.get('/sky/light-pollution', {
        params: buildRequestParams(false),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.light) return
      lightPollution.value = response?.data || null
      lightPollutionFetchedAt.value = new Date()
    } catch {
      if (token !== requestTokens.light) return
      lightPollutionError.value = 'Svetelné znečistenie je dočasne nedostupné.'
    } finally {
      if (token === requestTokens.light && !options.silent) {
        lightPollutionLoading.value = false
      }
    }
  }

  function queueDeferredFetches(options = {}) {
    if (deferredTimer) {
      clearTimeout(deferredTimer)
      deferredTimer = null
    }

    deferredTimer = setTimeout(() => {
      fetchPlanets(options)
      fetchIssPreview(options)
    }, 25)
  }

  async function fetchEssentialBlocks(options = {}) {
    await Promise.all([
      fetchWeather(options),
      fetchAstronomy(options),
      fetchLightPollution(options),
    ])
  }

  async function initialize(options = {}) {
    await fetchEssentialBlocks(options)
    if (isMounted.value) {
      queueDeferredFetches(options)
    }
  }

  function refreshCheapBlocks(options = { silent: true }) {
    fetchWeather(options)
    fetchPlanets(options)
    fetchIssPreview(options)
  }

  function refreshAstronomyBlock(options = { silent: true }) {
    fetchAstronomy(options)
  }

  function refreshAll(options = {}) {
    return initialize(options)
  }

  function refreshBlock(blockName) {
    if (blockName === 'weather') return fetchWeather()
    if (blockName === 'astronomy') return fetchAstronomy()
    if (blockName === 'planets') return fetchPlanets()
    if (blockName === 'iss') return fetchIssPreview()
    if (blockName === 'lightPollution') return fetchLightPollution()
    return Promise.resolve()
  }

  function startAutoRefresh() {
    stopAutoRefresh()

    cheapRefreshTimer = setInterval(() => {
      refreshCheapBlocks({ silent: true })
    }, CHEAP_REFRESH_MS)

    astronomyRefreshTimer = setInterval(() => {
      refreshAstronomyBlock({ silent: true })
    }, ASTRONOMY_REFRESH_MS)

    freshnessTimer = setInterval(() => {
      nowTick.value = Date.now()
    }, FRESHNESS_TICK_MS)
  }

  function stopAutoRefresh() {
    if (cheapRefreshTimer) {
      clearInterval(cheapRefreshTimer)
      cheapRefreshTimer = null
    }
    if (astronomyRefreshTimer) {
      clearInterval(astronomyRefreshTimer)
      astronomyRefreshTimer = null
    }
    if (freshnessTimer) {
      clearInterval(freshnessTimer)
      freshnessTimer = null
    }
    if (deferredTimer) {
      clearTimeout(deferredTimer)
      deferredTimer = null
    }
  }

  watch(
    () => contextKey.value,
    () => {
      initialize({ silent: false })
    },
    { immediate: true },
  )

  onMounted(() => {
    isMounted.value = true
    queueDeferredFetches({ silent: false })
    startAutoRefresh()
  })

  onBeforeUnmount(() => {
    stopAutoRefresh()
  })

  return {
    weather,
    astronomy,
    planetsPayload,
    issPreview,
    lightPollution,

    weatherLoading,
    astronomyLoading,
    planetsLoading,
    issLoading,
    lightPollutionLoading,

    weatherError,
    astronomyError,
    planetsError,
    issError,
    lightPollutionError,

    weatherFreshness,
    astronomyFreshness,
    planetsFreshness,
    issFreshness,
    lightPollutionFreshness,

    hasLocationCoords,
    observingScore,
    scoreReasons,
    scoreLabel,
    scoreEmoji,
    scoreColorClass,
    heroTitle,
    heroSubtitle,
    bestTimeToday,
    bestTimeLabel,
    formattedMetrics,
    issLine,
    lightPollutionLine,
    lightPollutionMetaLine,
    lightPollutionEstimateLine,
    isLightPollutionEstimate,
    weatherUpdatedLabel,
    weatherSourceLabel,
    planetCandidates,
    planetsDisplayList: planetsDisplayListV15,
    planetsMessage: planetsMessageV15,
    planetsContextLine: planetsContextLineV15,
    planetsSourceLine,
    shouldShowPlanetsList: shouldShowPlanetsListV15,
    isDaylight,
    isAstronomicalNight,
    isTwilightLimited,
    isDark,
    nightStartsAt,
    effectiveTz,

    fetchWeather,
    fetchAstronomy,
    fetchPlanets,
    fetchIssPreview,
    fetchLightPollution,
    refreshAll,
    refreshBlock,
  }

  function nextToken(block) {
    requestTokens[block] += 1
    return requestTokens[block]
  }
}

export function classifySkyPhase({ hasLocationCoords, sunAltitudeDeg }) {
  if (!hasLocationCoords) {
    return SKY_PHASE.LOCATION_REQUIRED
  }

  const altitude = toFiniteNumber(sunAltitudeDeg)
  if (altitude === null) {
    return SKY_PHASE.UNKNOWN
  }

  if (altitude > 0) {
    return SKY_PHASE.DAY
  }

  if (altitude > -6) {
    return SKY_PHASE.CIVIL_TWILIGHT
  }

  if (altitude > -12) {
    return SKY_PHASE.NAUTICAL_TWILIGHT
  }

  if (altitude > -18) {
    return SKY_PHASE.ASTRONOMICAL_TWILIGHT
  }

  return SKY_PHASE.ASTRONOMICAL_NIGHT
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function parseDate(value) {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

function formatTime(date, timeZone) {
  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  }
}

function formatIsoShort(value, timeZone) {
  if (!value) return '-'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date)
  }
}

function formatTimeOrDash(value, timeZone) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return '-'
  return formatTime(value, timeZone)
}

function resolvePayloadTimestamp(payload, keys) {
  if (!payload || typeof payload !== 'object') return null
  const fields = Array.isArray(keys) ? keys : []

  for (const key of fields) {
    const raw = typeof payload[key] === 'string' ? payload[key].trim() : ''
    if (!raw) continue
    const parsed = new Date(raw)
    if (!Number.isNaN(parsed.getTime())) return parsed
  }

  return null
}

function normalizeSourceLabel(value) {
  const normalized = sanitizeLabel(value)
  if (!normalized) return 'neznamy'
  return normalized.replace(/_/g, '-')
}

function formatPercent(value) {
  const numeric = toFiniteNumber(value)
  return numeric === null ? '-' : `${Math.round(numeric)}%`
}

function formatTemperature(value) {
  const numeric = toFiniteNumber(value)
  return numeric === null ? '-' : `${numeric.toFixed(1)} °C`
}

function formatWind(speed, unit) {
  const numeric = toFiniteNumber(speed)
  const normalizedUnit = String(unit || 'km/h')
  return numeric === null ? '-' : `${numeric.toFixed(1)} ${normalizedUnit}`
}

function formatDurationMinutes(value) {
  const numeric = toFiniteNumber(value)
  if (numeric === null) return '-'
  const minutes = Math.max(1, Math.round(numeric / 60))
  return `${minutes} min`
}

function sanitizeLabel(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

function formatFreshness(value, tick) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return ''
  const minutes = Math.max(0, Math.round((tick - value.getTime()) / 60000))
  if (minutes <= 0) return 'Aktualizované práve teraz'
  return `Aktualizované pred ${minutes} min`
}

function toFriendlyError(_error, fallback) {
  return fallback
}
