import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import {
  getBortlePresentation,
  getScorePresentation,
  getVisiblePlanets,
} from '@/utils/skyWidget'

const CHEAP_REFRESH_MS = 10 * 60 * 1000
const ASTRONOMY_REFRESH_MS = 60 * 60 * 1000
const FRESHNESS_TICK_MS = 60 * 1000

export function useSkyWidget(options = {}) {
  const lat = options.lat
  const lon = options.lon
  const tz = options.tz

  const weather = ref(null)
  const astronomy = ref(null)
  const planetsPayload = ref({ planets: [] })
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
  const currentTime = computed(() => getZonedComparableTimestamp(nowTick.value, effectiveTz.value))
  const rawObservingScore = computed(() => {
    const value = toFiniteNumber(weather.value?.observing_score)
    if (value === null) return null
    return Math.max(0, Math.min(100, Math.round(value)))
  })

  const isDaylight = computed(() => {
    const sunrise = getDateComparableTimestamp(astronomy.value?.sunrise_at, effectiveTz.value)
    const sunset = getDateComparableTimestamp(astronomy.value?.sunset_at, effectiveTz.value)
    if (sunrise === null || sunset === null || currentTime.value === null) return false
    return currentTime.value >= sunrise && currentTime.value <= sunset
  })

  const isAstronomicalNight = computed(() => {
    if (isDaylight.value || currentTime.value === null) return false

    const civilTwilightEnd = getDateComparableTimestamp(astronomy.value?.civil_twilight_end_at, effectiveTz.value)
    if (civilTwilightEnd !== null) {
      return currentTime.value >= civilTwilightEnd
    }

    const sunset = parseDate(astronomy.value?.sunset_at)
    if (!(sunset instanceof Date)) return false

    const fallbackNightStart = new Date(sunset.getTime() + (90 * 60 * 1000))
    return currentTime.value > getZonedComparableTimestamp(fallbackNightStart, effectiveTz.value)
  })

  const isTwilightLimited = computed(() => {
    if (isDaylight.value || currentTime.value === null) return false

    const civilTwilightEnd = getDateComparableTimestamp(astronomy.value?.civil_twilight_end_at, effectiveTz.value)
    if (civilTwilightEnd === null) return false

    return currentTime.value < civilTwilightEnd
  })

  const observingScore = computed(() => {
    if (isDaylight.value) return 0
    return rawObservingScore.value
  })

  const scorePresentation = computed(() => {
    if (isDaylight.value) {
      return { label: 'Denné svetlo', emoji: '☀️' }
    }

    if (!isAstronomicalNight.value) {
      return { label: 'Súmrak', emoji: '🌇' }
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

  const planetsDisplayList = computed(() => {
    if (isDaylight.value) return []
    return getVisiblePlanets(planetCandidates.value)
  })

  const shouldShowPlanetsList = computed(() => planetsDisplayList.value.length > 0)
  const planetsContextLine = computed(() => (
    isTwilightLimited.value ? 'Súmrak: viditeľnosť môže byť slabšia.' : ''
  ))
  const planetsSourceLine = computed(() => 'Zdroj: výpočet polohy planét')

  const planetsMessage = computed(() => {
    const reason = sanitizeLabel(planetsPayload.value?.reason)

    if (reason === 'sky_service_unavailable') {
      return 'Planéty sú teraz nedostupné.'
    }

    if (isDaylight.value) {
      return 'Planéty: zobrazíme po zotmení.'
    }

    if (planetsDisplayList.value.length === 0) {
      return 'Teraz nevidno žiadnu planétu dosť vysoko.'
    }

    return ''
  })

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

    try {
      const response = await api.get('/sky/weather', {
        params: buildRequestParams(true),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.weather) return
      weather.value = response?.data || null
      weatherFetchedAt.value = new Date()
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

    try {
      const response = await api.get('/sky/astronomy', {
        params: buildRequestParams(true),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.astronomy) return
      astronomy.value = response?.data || null
      astronomyFetchedAt.value = new Date()
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

    try {
      const response = await api.get('/sky/visible-planets', {
        params: buildRequestParams(true),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.planets) return
      planetsPayload.value = response?.data || { planets: [] }
      planetsFetchedAt.value = new Date()
    } catch (error) {
      if (token !== requestTokens.planets) return
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

    try {
      const response = await api.get('/sky/iss-preview', {
        params: buildRequestParams(true),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.iss) return
      issPreview.value = response?.data || { available: false }
      issFetchedAt.value = new Date()
    } catch (error) {
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

    try {
      const response = await api.get('/sky/light-pollution', {
        params: buildRequestParams(false),
        meta: { skipErrorToast: true },
      })
      if (token !== requestTokens.light) return
      lightPollution.value = response?.data || null
      lightPollutionFetchedAt.value = new Date()
    } catch (error) {
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
    planetCandidates,
    planetsDisplayList,
    planetsMessage,
    planetsContextLine,
    planetsSourceLine,
    shouldShowPlanetsList,
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

function getDateComparableTimestamp(value, timeZone) {
  const date = parseDate(value)
  if (!(date instanceof Date)) return null
  return getZonedComparableTimestamp(date, timeZone)
}

function getZonedComparableTimestamp(value, timeZone) {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return null

  try {
    const formatter = new Intl.DateTimeFormat('en-CA', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23',
    })
    const parts = formatter.formatToParts(date)
    const lookup = Object.fromEntries(parts.map(({ type, value: partValue }) => [type, partValue]))

    return Date.UTC(
      Number(lookup.year),
      Number(lookup.month) - 1,
      Number(lookup.day),
      Number(lookup.hour),
      Number(lookup.minute),
      Number(lookup.second),
    )
  } catch {
    return date.getTime()
  }
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
