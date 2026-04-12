import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/services/api'
import {
  getBortlePresentation,
  isPlanetNight,
  getScorePresentation,
  getVisiblePlanets,
} from '@/utils/skyWidget'
import { calculateAstronomyScore } from '@/utils/astronomyScore'
import {
  SKY_PHASE,
  SKY_PHASE_LABELS,
  classifySkyPhase,
  classifySkyPhaseFromTimeline,
  isTwilightSkyPhase,
  parseDate,
  resolveNightTiming,
  toFiniteNumber,
} from './skyWidget/skyPhase'
import {
  SHARED_SKY_CACHE_TTL_MS,
  EMPTY_PLANETS_PAYLOAD,
  EMPTY_ISS_PREVIEW,
  EMPTY_EPHEMERIS_PAYLOAD,
  fetchWithSharedSkyCache,
} from './skyWidget/skyCache'
import {
  formatTime,
  formatIsoShort,
  formatTimeOrDash,
  resolvePayloadTimestamp,
  normalizeSourceLabel,
  formatPercent,
  formatTemperature,
  formatWind,
  formatDurationMinutes,
  sanitizeLabel,
  formatFreshness,
  toFriendlyError,
} from './skyWidget/formatters'

export { SKY_PHASE, classifySkyPhase, classifySkyPhaseFromTimeline }

const CHEAP_REFRESH_MS = 10 * 60 * 1000
const ASTRONOMY_REFRESH_MS = 60 * 60 * 1000
const FRESHNESS_TICK_MS = 60 * 1000
const SKY_ALTITUDE_STALE_MINUTES = 120
const DEFERRED_SKY_FETCH_MS = 250

export function useSkyWidget(options = {}) {
  const lat = options.lat
  const lon = options.lon
  const tz = options.tz
  const initialPayloadRef = options.initialPayload
  const bundlePendingRef = options.bundlePending
  const includeWeather = options.includeWeather !== false
  const includeAstronomy = options.includeAstronomy !== false
  const includePlanets = options.includePlanets !== false
  const includeIss = options.includeIss !== false
  const includeLightPollution = options.includeLightPollution !== false
  const includeEphemeris = options.includeEphemeris === true

  const weather = ref(null)
  const astronomy = ref(null)
  const planetsPayload = ref({ ...EMPTY_PLANETS_PAYLOAD })
  const issPreview = ref({ ...EMPTY_ISS_PREVIEW })
  const lightPollution = ref(null)
  const ephemeris = ref({ ...EMPTY_EPHEMERIS_PAYLOAD })

  const weatherLoading = ref(false)
  const astronomyLoading = ref(false)
  const planetsLoading = ref(false)
  const issLoading = ref(false)
  const lightPollutionLoading = ref(false)
  const ephemerisLoading = ref(false)

  const weatherError = ref('')
  const astronomyError = ref('')
  const planetsError = ref('')
  const issError = ref('')
  const lightPollutionError = ref('')
  const ephemerisError = ref('')
  const weatherFetchedAt = ref(null)
  const astronomyFetchedAt = ref(null)
  const planetsFetchedAt = ref(null)
  const issFetchedAt = ref(null)
  const lightPollutionFetchedAt = ref(null)
  const ephemerisFetchedAt = ref(null)

  const isMounted = ref(false)
  const nowTick = ref(Date.now())

  let cheapRefreshTimer = null
  let astronomyRefreshTimer = null
  let freshnessTimer = null
  let deferredTimer = null
  let hydratedBundleBlocks = new Set()

  const requestTokens = {
    weather: 0,
    astronomy: 0,
    planets: 0,
    iss: 0,
    light: 0,
    ephemeris: 0,
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

  const hasLocationCoords = computed(() => (
    Number.isFinite(numericLat.value)
    && Number.isFinite(numericLon.value)
    && numericLat.value >= -90
    && numericLat.value <= 90
    && numericLon.value >= -180
    && numericLon.value <= 180
  ))
  const sunAltitudeDeg = computed(() => {
    // Astronomy is more reliable around midnight transitions, so it has priority.
    const fromAstronomy = toFiniteNumber(astronomy.value?.sun_altitude_deg)
    if (fromAstronomy !== null) return fromAstronomy

    const fromPlanets = toFiniteNumber(planetsPayload.value?.sun_altitude_deg)
    if (fromPlanets !== null) return fromPlanets

    return null
  })
  const sunAltitudeSampleAt = computed(() => (
    parseDate(astronomy.value?.sample_at) || parseDate(planetsPayload.value?.sample_at)
  ))
  const sunAltitudeAgeMinutes = computed(() => {
    if (!(sunAltitudeSampleAt.value instanceof Date) || Number.isNaN(sunAltitudeSampleAt.value.getTime())) {
      return null
    }

    return Math.max(0, Math.round((nowTick.value - sunAltitudeSampleAt.value.getTime()) / 60000))
  })
  const sunAltitudeIsStale = computed(() => {
    if (sunAltitudeDeg.value === null) return false
    if (sunAltitudeAgeMinutes.value === null) return true
    return sunAltitudeAgeMinutes.value > SKY_ALTITUDE_STALE_MINUTES
  })
  const skyPhaseFromAltitude = computed(() => classifySkyPhase({
    hasLocationCoords: hasLocationCoords.value,
    sunAltitudeDeg: sunAltitudeDeg.value,
  }))
  const skyPhaseFromTimeline = computed(() => classifySkyPhaseFromTimeline({
    hasLocationCoords: hasLocationCoords.value,
    nowTs: nowTick.value,
    sunriseAt: astronomy.value?.sunrise_at,
    sunsetAt: astronomy.value?.sunset_at,
    civilTwilightEndAt: astronomy.value?.civil_twilight_end_at,
  }))
  const shouldPreferTimelinePhase = computed(() => (
    sunAltitudeDeg.value === null || sunAltitudeIsStale.value
  ))
  const skyPhase = computed(() => {
    if (shouldPreferTimelinePhase.value && skyPhaseFromTimeline.value !== SKY_PHASE.UNKNOWN) {
      return skyPhaseFromTimeline.value
    }
    return skyPhaseFromAltitude.value
  })
  const skyPhaseLabel = computed(() => SKY_PHASE_LABELS[skyPhase.value] || SKY_PHASE_LABELS[SKY_PHASE.UNKNOWN])
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

  const isDaylight = computed(() => {
    const daylightByScore = scoreModel.value.phase === 'daylight'
    if (!daylightByScore) return false
    if (!shouldPreferTimelinePhase.value) return true
    return skyPhase.value === SKY_PHASE.DAY
  })
  const isAstronomicalNight = computed(() => (
    scoreModel.value.phase === 'astronomical_night'
    || (shouldPreferTimelinePhase.value && skyPhase.value === SKY_PHASE.ASTRONOMICAL_NIGHT)
  ))
  const isTwilightLimited = computed(() => (
    scoreModel.value.phase === 'twilight'
    || (shouldPreferTimelinePhase.value && isTwilightSkyPhase(skyPhase.value))
  ))

  const observingScore = computed(() => {
    if (rawObservingScore.value !== null) return rawObservingScore.value
    if (isDaylight.value) return null

    const adjustedScore = toFiniteNumber(scoreModel.value?.components?.adjustedScore)
    if (adjustedScore === null) return null

    return Math.max(0, Math.min(100, Math.round(adjustedScore)))
  })

  const scorePresentation = computed(() => {
    if (skyPhase.value === SKY_PHASE.LOCATION_REQUIRED) {
      return { label: 'Poloha nenastavená', tone: 'neutral' }
    }

    if (skyPhase.value === SKY_PHASE.UNKNOWN) {
      return { label: 'Neznáme', tone: 'neutral' }
    }

    if (isDaylight.value) {
      return { label: 'Denné svetlo', tone: 'day' }
    }

    if (skyPhase.value === SKY_PHASE.CIVIL_TWILIGHT || skyPhase.value === SKY_PHASE.NAUTICAL_TWILIGHT) {
      return { label: 'Sumrak', tone: 'twilight' }
    }

    if (skyPhase.value === SKY_PHASE.ASTRONOMICAL_TWILIGHT) {
      return { label: 'Astronomicky súmrak', tone: 'twilight' }
    }

    return getScorePresentation(observingScore.value)
  })

  const scoreLabel = computed(() => scorePresentation.value.label)
  const scoreTone = computed(() => scorePresentation.value.tone || 'neutral')
  const scoreEmoji = computed(() => scorePresentation.value.emoji || '')

  const scoreColorClass = computed(() => {
    if (observingScore.value === null) return 'bg-slate-500/60'
    if (observingScore.value < 40) return 'bg-rose-500/70'
    if (observingScore.value < 65) return 'bg-amber-500/70'
    if (observingScore.value < 85) return 'bg-sky-500/70'
    return 'bg-emerald-500/70'
  })

  const nightTiming = computed(() => resolveNightTiming({
    nowTs: nowTick.value,
    sunriseAt: astronomy.value?.sunrise_at,
    sunsetAt: astronomy.value?.sunset_at,
    civilTwilightEndAt: astronomy.value?.civil_twilight_end_at,
  }))
  const isDark = computed(() => (
    typeof nightTiming.value?.isNightNow === 'boolean' ? nightTiming.value.isNightNow : null
  ))
  const nightStartsAt = computed(() => nightTiming.value?.upcomingNightStart || null)

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
        : 'Najlepšie okno v rámci dneska.',
    }
  })

  const bestTimeLabel = computed(() => {
    if (skyPhase.value === SKY_PHASE.LOCATION_REQUIRED) {
      return 'Nastav polohu pre výpočet.'
    }

    if (skyPhase.value === SKY_PHASE.UNKNOWN) {
      return 'Najlepšie okno sa nepodarilo určiť.'
    }

    if (isDark.value === true) {
      return 'Prave prebieha'
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

  const heroTitle = computed(() => (isDaylight.value ? 'Denné podmienky' : 'Pozorovanie dnes'))
  const heroSubtitle = computed(() => {
    if (skyPhase.value === SKY_PHASE.LOCATION_REQUIRED) {
      return 'Poloha nie je nastavená. Nastav ju pre presný výpočet oblohy.'
    }

    if (skyPhase.value === SKY_PHASE.UNKNOWN) {
      return 'Stav oblohy je dočasne neznámy.'
    }

    if (isDaylight.value) {
      return 'Momentalne je den. Astronomicke pozorovanie nie je možné.'
    }

    if (!isAstronomicalNight.value) {
      return 'Obloha ešte nie je v plnej astronomickej tme.'
    }

    if (!bestTimeToday.value?.window) return 'Najlepšie pozorovanie dnes nie je dostupné.'
    const note = sanitizeLabel(bestTimeToday.value?.note)
    return note ? `${bestTimeToday.value.window} | ${note}` : bestTimeToday.value.window
  })

  const countdownToNightLabel = computed(() => {
    if (skyPhase.value === SKY_PHASE.LOCATION_REQUIRED || skyPhase.value === SKY_PHASE.UNKNOWN) return ''
    if (skyPhase.value === SKY_PHASE.ASTRONOMICAL_NIGHT) return 'Astronomicka noc prebieha.'
    if (isDark.value === true) return 'Tma už prebieha.'

    if (!(nightStartsAt.value instanceof Date)) return ''
    const deltaMs = nightStartsAt.value.getTime() - nowTick.value
    if (deltaMs <= 0) return ''

    const totalMinutes = Math.max(1, Math.round(deltaMs / 60000))
    const hours = Math.floor(totalMinutes / 60)
    const minutes = totalMinutes % 60
    if (hours <= 0) return `Do najtmavsej casti: ${minutes} min`
    if (minutes <= 0) return `Do najtmavsej casti: ${hours} h`
    return `Do najtmavsej casti: ${hours} h ${minutes} min`
  })

  const recommendationLine = computed(() => {
    if (skyPhase.value === SKY_PHASE.LOCATION_REQUIRED) {
      return 'Nastav polohu, aby sme vedeli vyhodnotiť podmienky.'
    }
    if (skyPhase.value === SKY_PHASE.UNKNOWN) {
      return 'Údaje sú neúplné, skús obnoviť widget.'
    }
    if (isDaylight.value) {
      return 'Vhodné skôr na planéty pri západe alebo ranné pozorovanie.'
    }
    if (isTwilightLimited.value) {
      return 'Počkaj na hlbšiu tmu. Teraz sú najlepšie jasné objekty a planéty.'
    }

    const moonIllumination = toFiniteNumber(astronomy.value?.moon_illumination_percent)
    const moonAltitude = toFiniteNumber(astronomy.value?.moon_altitude_deg)
    const moonInterferes = moonIllumination !== null && moonIllumination >= 60 && moonAltitude !== null && moonAltitude > 0

    if ((observingScore.value ?? 0) >= 80) {
      return moonInterferes
        ? 'Podmienky sú výborné, ale Mesiac môže rušiť deep-sky.'
        : 'Výborné podmienky na deep-sky aj planéty.'
    }
    if ((observingScore.value ?? 0) >= 60) {
      return moonInterferes
        ? 'Dobré na planéty, deep-sky je čiastočne rušené Mesiacom.'
        : 'Dobré podmienky na väčšinu jasných objektov.'
    }
    if ((observingScore.value ?? 0) >= 40) {
      return 'Priemerné podmienky. Zameraj sa na jasné objekty.'
    }
    return 'Slabé podmienky. Vhodné skôr na krátke orientačné pozorovanie.'
  })

  const scoreFactors = computed(() => {
    const cloud = toFiniteNumber(weather.value?.cloud_percent)
    const humidity = toFiniteNumber(weather.value?.humidity_percent)
    const wind = toFiniteNumber(weather.value?.wind_speed)
    const moonIllumination = toFiniteNumber(astronomy.value?.moon_illumination_percent)
    const moonAltitude = toFiniteNumber(astronomy.value?.moon_altitude_deg)
    const bortleClass = toFiniteNumber(lightPollution.value?.bortle_class)

    const factors = [
      {
        key: 'cloud',
        label: 'Oblacnost',
        value: cloud === null ? '-' : `${Math.round(cloud)}%`,
        hint: cloud === null ? 'Údaje nie sú dostupné.' : cloud <= 35 ? 'Nízka oblačnosť pomáha pozorovaniu.' : cloud <= 65 ? 'Stredná oblačnosť obmedzuje časť oblohy.' : 'Vysoká oblačnosť silno obmedzuje pozorovanie.',
        tone: cloud === null ? 'neutral' : cloud <= 35 ? 'positive' : cloud <= 65 ? 'neutral' : 'negative',
      },
      {
        key: 'humidity',
        label: 'Vlhkost',
        value: humidity === null ? '-' : `${Math.round(humidity)}%`,
        hint: humidity === null ? 'Údaje nie sú dostupné.' : humidity <= 70 ? 'Dobry stav pre ostrejsi obraz.' : 'Vyssia vlhkost môže zhoršiť kontrast.',
        tone: humidity === null ? 'neutral' : humidity <= 70 ? 'positive' : 'negative',
      },
      {
        key: 'wind',
        label: 'Vietor',
        value: wind === null ? '-' : `${wind.toFixed(1)} km/h`,
        hint: wind === null ? 'Údaje nie sú dostupné.' : wind <= 15 ? 'Pokojný vzduch je vhodný na pozorovanie.' : wind <= 30 ? 'Mierny vietor môže zhoršiť stabilitu obrazu.' : 'Silný vietor zhorsuje seeing aj komfort.',
        tone: wind === null ? 'neutral' : wind <= 15 ? 'positive' : wind <= 30 ? 'neutral' : 'negative',
      },
      {
        key: 'moon',
        label: 'Mesiac',
        value: moonIllumination === null ? '-' : moonAltitude !== null && moonAltitude <= 0 ? `${Math.round(moonIllumination)}% | pod obzorom` : `${Math.round(moonIllumination)}%`,
        hint: moonIllumination === null || moonAltitude === null ? 'Vplyv Mesiaca je nejasný.' : moonAltitude <= 0 ? 'Mesiac teraz neruší tmavú oblohu.' : moonIllumination >= 70 ? 'Silné osvetlenie oblohy od Mesiaca.' : 'Mesiac má iba mierny vplyv.',
        tone: moonIllumination === null || moonAltitude === null ? 'neutral' : moonAltitude <= 0 ? 'positive' : moonIllumination >= 70 ? 'negative' : 'neutral',
      },
      {
        key: 'bortle',
        label: 'Svetelne znecistenie',
        value: bortleClass === null ? '-' : `Bortle ${Math.round(Math.max(1, Math.min(9, bortleClass)))}`,
        hint: bortleClass === null ? 'Trieda oblohy nie je dostupná.' : bortleClass <= 4 ? 'Tmavšia obloha pomáha deep-sky.' : bortleClass <= 6 ? 'Stredná miera znečistenia svetlom.' : 'Silne svetelne znecistenie obmedzuje slabé objekty.',
        tone: bortleClass === null ? 'neutral' : bortleClass <= 4 ? 'positive' : bortleClass <= 6 ? 'neutral' : 'negative',
      },
    ]

    if (isDaylight.value) {
      factors.unshift({
        key: 'phase',
        label: 'Stav oblohy',
        value: 'Denné svetlo',
        hint: 'Astronomické pozorovanie je cez deň limitované.',
        tone: 'negative',
      })
    } else if (isTwilightLimited.value) {
      factors.unshift({
        key: 'phase',
        label: 'Stav oblohy',
        value: 'Sumrak',
        hint: 'Na plne tmavu oblohu sa ešte caka.',
        tone: 'neutral',
      })
    }

    return factors
  })

  const issPrimaryLine = computed(() => {
    if (!hasLocationCoords.value) {
      return 'Nastav polohu pre ISS prehľad.'
    }

    const passAt = formatIsoShort(issPreview.value?.next_pass_at, effectiveTz.value)
    if (passAt !== '-' && issPreview.value?.available) {
      return `Najblizsi prelet: ${passAt}`
    }

    if (passAt !== '-') {
      return `Dnes bez vyrazneho preletu. Najblizsi znamy prelet: ${passAt}`
    }

    return 'Dnes pravdepodobne bez viditeľného preletu ISS.'
  })

  const issSecondaryLine = computed(() => {
    const passAt = formatIsoShort(issPreview.value?.next_pass_at, effectiveTz.value)
    const durationMin = formatDurationMinutes(issPreview.value?.duration_sec)
    const maxAltitude = toFiniteNumber(issPreview.value?.max_altitude_deg)
    const directionStart = sanitizeLabel(issPreview.value?.direction_start)
    const directionEnd = sanitizeLabel(issPreview.value?.direction_end)

    if (passAt !== '-') {
      const parts = []
      if (durationMin !== '-') parts.push(durationMin)
      if (Number.isFinite(maxAltitude)) parts.push(`max ${Math.round(maxAltitude)} deg`)
      if (directionStart && directionEnd) parts.push(`${directionStart} -> ${directionEnd}`)
      return parts.join(' | ')
    }

    return 'Skús to znova zajtra po zotmení alebo obnov dáta neskôr.'
  })

  const issLine = computed(() => {
    if (!issSecondaryLine.value) return issPrimaryLine.value
    return `${issPrimaryLine.value} (${issSecondaryLine.value})`
  })

  const bortlePresentation = computed(() => getBortlePresentation(lightPollution.value?.bortle_class))
  const isLightPollutionEstimate = computed(() => {
    const reason = sanitizeLabel(lightPollution.value?.reason).toLowerCase()
    const source = sanitizeLabel(lightPollution.value?.source).toLowerCase()
    return reason.includes('estimated_from_location') || source === 'light_pollution_fallback'
  })

  const lightPollutionLine = computed(() => {
    if (!bortlePresentation.value) return ''
    return bortlePresentation.value.levelText
  })

  const lightPollutionMetaLine = computed(() => {
    if (!bortlePresentation.value) return ''

    const context = String(bortlePresentation.value.contextText || '')
    const capitalizedContext = context ? context.charAt(0).toUpperCase() + context.slice(1) : ''
    return `${capitalizedContext} - Bortle ${bortlePresentation.value.bortle}`
  })

  const lightPollutionEstimateLine = computed(() => (
    isLightPollutionEstimate.value ? 'Pouzivame odhad svetelneho znečistenia podľa lokality' : ''
  ))
  const lightPollutionImpactLine = computed(() => (
    sanitizeLabel(bortlePresentation.value?.impactText)
  ))

  const planetCandidates = computed(() => (
    Array.isArray(planetsPayload.value?.planets) ? planetsPayload.value.planets : []
  ))
  const planetsSourceLine = computed(() => {
    const source = String(planetsPayload.value?.source || '').trim().toLowerCase()
    if (source === 'jpl_horizons') return 'Zdroj: JPL Horizons'
    if (source === 'sky_microservice') return 'Zdroj: sky microservice'
    return 'Zdroj: výpočet polohy planét'
  })

  const planetsNightV15 = computed(() => (
    hasLocationCoords.value && isPlanetNight(sunAltitudeDeg.value)
  ))
  const planetsDisplayListV15 = computed(() => getVisiblePlanets({
    ...planetsPayload.value,
    sun_altitude_deg: sunAltitudeDeg.value ?? planetsPayload.value?.sun_altitude_deg ?? null,
  }))
  const shouldShowPlanetsListV15 = computed(() => planetsDisplayListV15.value.length > 0)
  const planetsContextLineV15 = computed(() => {
    if (!hasLocationCoords.value) return ''
    if (isDaylight.value) return 'Aktuálne je deň. Planety budú čitateľnejšie po zotmení.'
    if (isTwilightLimited.value) return 'Aktuálne je súmrak. Najlepšie po úplnom zotmení.'
    if (!shouldShowPlanetsListV15.value) return ''

    const count = planetsDisplayListV15.value.length
    if (count === 1) return '1 objekt je vhodný na pozorovanie.'
    if (count <= 4) return `${count} objekty sú vhodné na pozorovanie.`
    return `${count} objektov je vhodných na pozorovanie.`
  })
  const planetsMessageV15 = computed(() => {
    if (!hasLocationCoords.value) {
      return 'Nastav polohu pre výpočet planét.'
    }

    const reason = sanitizeLabel(planetsPayload.value?.reason)

    if (reason === 'sky_service_unavailable') {
      return 'Planety sú teraz nedostupne.'
    }

    if (reason === 'degraded_contract') {
      return 'Planety sú dočasne nedostupne.'
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
  const issUpdatedAt = computed(() => (
    resolvePayloadTimestamp(issPreview.value?.tracker, ['sample_at']) || issFetchedAt.value
  ))
  const issUpdatedLabel = computed(() => formatTimeOrDash(issUpdatedAt.value, effectiveTz.value))

  const weatherFreshness = computed(() => formatFreshness(weatherFetchedAt.value, nowTick.value))
  const astronomyFreshness = computed(() => formatFreshness(astronomyFetchedAt.value, nowTick.value))
  const planetsFreshness = computed(() => formatFreshness(planetsFetchedAt.value, nowTick.value))
  const issFreshness = computed(() => formatFreshness(issFetchedAt.value, nowTick.value))
  const lightPollutionFreshness = computed(() => formatFreshness(lightPollutionFetchedAt.value, nowTick.value))
  const ephemerisFreshness = computed(() => formatFreshness(ephemerisFetchedAt.value, nowTick.value))

  const contextKey = computed(() => {
    const latKey = hasLocationCoords.value ? numericLat.value.toFixed(4) : 'auto'
    const lonKey = hasLocationCoords.value ? numericLon.value.toFixed(4) : 'auto'
    return `${latKey}:${lonKey}:${effectiveTz.value}`
  })

  function readOptionValue(source) {
    if (source && typeof source === 'object' && 'value' in source) {
      return source.value
    }

    return source
  }

  function readInitialPayload() {
    const payload = readOptionValue(initialPayloadRef)
    return payload && typeof payload === 'object' ? payload : undefined
  }

  function isBundlePending() {
    return Boolean(readOptionValue(bundlePendingRef))
  }

  function resetPlanetsPayload() {
    planetsPayload.value = { ...EMPTY_PLANETS_PAYLOAD }
  }

  function resetIssPreview() {
    issPreview.value = { ...EMPTY_ISS_PREVIEW }
  }

  function resetEphemerisPayload() {
    ephemeris.value = { ...EMPTY_EPHEMERIS_PAYLOAD }
  }

  function hydrateBundleBlock(blockName, payload) {
    if (!payload || typeof payload !== 'object') {
      return
    }

    if (blockName === 'weather' && includeWeather) {
      weather.value = payload
      weatherError.value = ''
      weatherLoading.value = false
      weatherFetchedAt.value = resolvePayloadTimestamp(payload, ['updated_at', 'as_of']) || new Date()
      hydratedBundleBlocks.add('weather')
      return
    }

    if (blockName === 'astronomy' && includeAstronomy) {
      astronomy.value = payload
      astronomyError.value = ''
      astronomyLoading.value = false
      astronomyFetchedAt.value = resolvePayloadTimestamp(payload, ['sample_at']) || new Date()
      hydratedBundleBlocks.add('astronomy')
      return
    }

    if (blockName === 'visible_planets' && includePlanets) {
      planetsPayload.value = payload
      planetsError.value = ''
      planetsLoading.value = false
      planetsFetchedAt.value = resolvePayloadTimestamp(payload, ['sample_at']) || new Date()
      hydratedBundleBlocks.add('visible_planets')
      return
    }

    if (blockName === 'iss_preview' && includeIss) {
      issPreview.value = payload
      issError.value = ''
      issLoading.value = false
      issFetchedAt.value = resolvePayloadTimestamp(payload?.tracker, ['sample_at']) || new Date()
      hydratedBundleBlocks.add('iss_preview')
      return
    }

    if (blockName === 'light_pollution' && includeLightPollution) {
      lightPollution.value = payload
      lightPollutionError.value = ''
      lightPollutionLoading.value = false
      lightPollutionFetchedAt.value = resolvePayloadTimestamp(payload, ['sample_at']) || new Date()
      hydratedBundleBlocks.add('light_pollution')
      return
    }

    if (blockName === 'ephemeris' && includeEphemeris) {
      ephemeris.value = payload
      ephemerisError.value = ''
      ephemerisLoading.value = false
      ephemerisFetchedAt.value = resolvePayloadTimestamp(payload, ['sample_at']) || new Date()
      hydratedBundleBlocks.add('ephemeris')
    }
  }

  function applyInitialPayload(payload) {
    if (!payload || typeof payload !== 'object') {
      return
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'weather')) {
      hydrateBundleBlock('weather', payload.weather)
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'astronomy')) {
      hydrateBundleBlock('astronomy', payload.astronomy)
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'visible_planets')) {
      hydrateBundleBlock('visible_planets', payload.visible_planets)
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'iss_preview')) {
      hydrateBundleBlock('iss_preview', payload.iss_preview)
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'light_pollution')) {
      hydrateBundleBlock('light_pollution', payload.light_pollution)
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'ephemeris')) {
      hydrateBundleBlock('ephemeris', payload.ephemeris)
    }
  }

  function prepareForPendingBundle() {
    if (includeWeather) {
      weather.value = null
      weatherError.value = ''
      weatherLoading.value = true
      weatherFetchedAt.value = null
    }

    if (includeAstronomy) {
      astronomy.value = null
      astronomyError.value = ''
      astronomyLoading.value = true
      astronomyFetchedAt.value = null
    }

    if (includePlanets) {
      resetPlanetsPayload()
      planetsError.value = ''
      planetsLoading.value = true
      planetsFetchedAt.value = null
    }

    if (includeIss) {
      resetIssPreview()
      issError.value = ''
      issLoading.value = true
      issFetchedAt.value = null
    }

    if (includeLightPollution) {
      lightPollution.value = null
      lightPollutionError.value = ''
      lightPollutionLoading.value = true
      lightPollutionFetchedAt.value = null
    }

    if (includeEphemeris) {
      resetEphemerisPayload()
      ephemerisError.value = ''
      ephemerisLoading.value = true
      ephemerisFetchedAt.value = null
    }
  }

  function shouldFetchEssentialBlock(blockName, options = {}) {
    if (!options.onlyMissingBundleBlocks) {
      return true
    }

    return !hydratedBundleBlocks.has(blockName)
  }

  function shouldFetchDeferredBlock(blockName, options = {}) {
    if (!options.onlyMissingBundleBlocks) {
      return true
    }

    return !hydratedBundleBlocks.has(blockName)
  }

  function buildRequestParams(includeTz = true) {
    const params = {}
    if (hasLocationCoords.value) {
      params.lat = numericLat.value
      params.lon = numericLon.value
    }
    if (includeTz && effectiveTz.value) {
      params.tz = effectiveTz.value
    }
    return params
  }

  async function fetchWeather(options = {}) {
    if (!includeWeather) {
      weatherLoading.value = false
      weatherError.value = ''
      weather.value = null
      weatherFetchedAt.value = null
      return
    }

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
      const cacheKey = `weather:${contextKey.value}`
      const payload = await fetchWithSharedSkyCache(
        cacheKey,
        SHARED_SKY_CACHE_TTL_MS.weather,
        async () => {
          const response = await api.get('/sky/weather', {
            params: buildRequestParams(true),
            meta: { skipErrorToast: true },
          })
          return response?.data || null
        },
      )
      if (token !== requestTokens.weather) return
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
    if (!includeAstronomy) {
      astronomyLoading.value = false
      astronomyError.value = ''
      astronomy.value = null
      astronomyFetchedAt.value = null
      return
    }

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
      const cacheKey = `astronomy:${contextKey.value}`
      const payload = await fetchWithSharedSkyCache(
        cacheKey,
        SHARED_SKY_CACHE_TTL_MS.astronomy,
        async () => {
          const response = await api.get('/sky/astronomy', {
            params: buildRequestParams(true),
            meta: { skipErrorToast: true },
          })
          return response?.data || null
        },
      )
      if (token !== requestTokens.astronomy) return
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
    if (!includePlanets) {
      planetsLoading.value = false
      planetsError.value = ''
      resetPlanetsPayload()
      planetsFetchedAt.value = null
      return
    }

    const token = nextToken('planets')
    if (!options.silent) planetsLoading.value = true
    planetsError.value = ''

    if (!hasLocationCoords.value) {
      resetPlanetsPayload()
      planetsFetchedAt.value = null
      planetsLoading.value = false
      return
    }

    try {
      const cacheKey = `planets:${contextKey.value}`
      const payload = await fetchWithSharedSkyCache(
        cacheKey,
        SHARED_SKY_CACHE_TTL_MS.planets,
        async () => {
          const response = await api.get('/sky/visible-planets', {
            params: buildRequestParams(true),
            meta: { skipErrorToast: true },
          })
          return response?.data || { ...EMPTY_PLANETS_PAYLOAD }
        },
      )
      if (token !== requestTokens.planets) return
      planetsPayload.value = payload
      planetsFetchedAt.value = resolvePayloadTimestamp(payload, ['sample_at']) || new Date()
    } catch (error) {
      if (token !== requestTokens.planets) return
      resetPlanetsPayload()
      planetsError.value = toFriendlyError(error, 'Nepodarilo sa načítať planéty.')
    } finally {
      if (token === requestTokens.planets && !options.silent) {
        planetsLoading.value = false
      }
    }
  }

  async function fetchIssPreview(options = {}) {
    if (!includeIss) {
      issLoading.value = false
      issError.value = ''
      resetIssPreview()
      issFetchedAt.value = null
      return
    }

    const token = nextToken('iss')
    if (!options.silent) issLoading.value = true
    issError.value = ''

    if (!hasLocationCoords.value) {
      resetIssPreview()
      issFetchedAt.value = null
      issLoading.value = false
      return
    }

    try {
      const cacheKey = `iss:${contextKey.value}`
      const payload = await fetchWithSharedSkyCache(
        cacheKey,
        SHARED_SKY_CACHE_TTL_MS.iss,
        async () => {
          const response = await api.get('/sky/iss-preview', {
            params: buildRequestParams(true),
            meta: { skipErrorToast: true },
          })
          return response?.data || { ...EMPTY_ISS_PREVIEW }
        },
      )
      if (token !== requestTokens.iss) return
      issPreview.value = payload
      issFetchedAt.value = new Date()
    } catch {
      if (token !== requestTokens.iss) return
      issError.value = 'Údaje o ISS sú dočasne nedostupne.'
    } finally {
      if (token === requestTokens.iss && !options.silent) {
        issLoading.value = false
      }
    }
  }

  async function fetchLightPollution(options = {}) {
    if (!includeLightPollution) {
      lightPollutionLoading.value = false
      lightPollutionError.value = ''
      lightPollution.value = null
      lightPollutionFetchedAt.value = null
      return
    }

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
      const cacheKey = `light:${contextKey.value}`
      const payload = await fetchWithSharedSkyCache(
        cacheKey,
        SHARED_SKY_CACHE_TTL_MS.light,
        async () => {
          const response = await api.get('/sky/light-pollution', {
            params: buildRequestParams(false),
            meta: { skipErrorToast: true },
          })
          return response?.data || null
        },
      )
      if (token !== requestTokens.light) return
      lightPollution.value = payload
      lightPollutionFetchedAt.value = resolvePayloadTimestamp(lightPollution.value, ['sample_at']) || new Date()
    } catch (error) {
      if (token !== requestTokens.light) return
      lightPollution.value = null
      lightPollutionFetchedAt.value = null
      lightPollutionError.value = toFriendlyError(error, 'Svetelne znecistenie je dočasne nedostupne.')
    } finally {
      if (token === requestTokens.light && !options.silent) {
        lightPollutionLoading.value = false
      }
    }
  }
  async function fetchEphemeris(options = {}) {
    if (!includeEphemeris) {
      ephemerisLoading.value = false
      ephemerisError.value = ''
      resetEphemerisPayload()
      ephemerisFetchedAt.value = null
      return
    }

    const token = nextToken('ephemeris')
    if (!options.silent) ephemerisLoading.value = true
    ephemerisError.value = ''

    if (!hasLocationCoords.value) {
      resetEphemerisPayload()
      ephemerisFetchedAt.value = null
      ephemerisLoading.value = false
      return
    }

    try {
      const cacheKey = `ephemeris:${contextKey.value}`
      const payload = await fetchWithSharedSkyCache(
        cacheKey,
        SHARED_SKY_CACHE_TTL_MS.ephemeris,
        async () => {
          const response = await api.get('/sky/ephemeris', {
            params: buildRequestParams(true),
            meta: { skipErrorToast: true },
          })
          return response?.data || { ...EMPTY_EPHEMERIS_PAYLOAD }
        },
      )
      if (token !== requestTokens.ephemeris) return
      ephemeris.value = payload
      ephemerisFetchedAt.value = resolvePayloadTimestamp(ephemeris.value, ['sample_at']) || new Date()
    } catch {
      if (token !== requestTokens.ephemeris) return
      resetEphemerisPayload()
      ephemerisError.value = 'Efemeridy sú dočasne nedostupne.'
    } finally {
      if (token === requestTokens.ephemeris && !options.silent) {
        ephemerisLoading.value = false
      }
    }
  }

  function queueDeferredFetches(options = {}) {
    if (!includePlanets && !includeIss && !includeEphemeris) return
    if (isBundlePending()) return

    if (deferredTimer) {
      clearTimeout(deferredTimer)
      deferredTimer = null
    }

    deferredTimer = setTimeout(() => {
      if (includePlanets && shouldFetchDeferredBlock('visible_planets', options)) fetchPlanets(options)
      if (includeIss && shouldFetchDeferredBlock('iss_preview', options)) fetchIssPreview(options)
      if (includeEphemeris && shouldFetchDeferredBlock('ephemeris', options)) fetchEphemeris(options)
    }, DEFERRED_SKY_FETCH_MS)
  }

  async function fetchEssentialBlocks(options = {}) {
    const requests = []

    if (includeWeather && shouldFetchEssentialBlock('weather', options)) requests.push(fetchWeather(options))
    if (includeAstronomy && shouldFetchEssentialBlock('astronomy', options)) requests.push(fetchAstronomy(options))
    if (includeLightPollution && shouldFetchEssentialBlock('light_pollution', options)) requests.push(fetchLightPollution(options))

    if (requests.length === 0) return
    await Promise.all(requests)
  }

  async function initialize(options = {}) {
    const bundlePayload = readInitialPayload()
    if (bundlePayload) {
      applyInitialPayload(bundlePayload)
    }

    await fetchEssentialBlocks(options)
    if (isMounted.value) {
      queueDeferredFetches(options)
    }
  }

  async function syncFromBundleState(options = {}) {
    hydratedBundleBlocks = new Set()

    if (isBundlePending()) {
      prepareForPendingBundle()
      return
    }

    const bundlePayload = readInitialPayload()
    if (bundlePayload) {
      await initialize({
        ...options,
        onlyMissingBundleBlocks: true,
      })
      return
    }

    await initialize(options)
  }

  function refreshCheapBlocks(options = { silent: true }) {
    if (isBundlePending()) return
    if (includeWeather) fetchWeather(options)
    if (includePlanets) fetchPlanets(options)
    if (includeIss) fetchIssPreview(options)
    if (includeEphemeris) fetchEphemeris(options)
  }

  function refreshAstronomyBlock(options = { silent: true }) {
    if (isBundlePending()) return
    if (includeAstronomy) fetchAstronomy(options)
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
    if (blockName === 'ephemeris') return fetchEphemeris()
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
    [
      () => contextKey.value,
      () => isBundlePending(),
      () => readInitialPayload(),
    ],
    async () => {
      await syncFromBundleState({ silent: false })
    },
    { immediate: true },
  )

  onMounted(() => {
    isMounted.value = true

    if (isBundlePending()) {
      prepareForPendingBundle()
    } else if (readInitialPayload()) {
      applyInitialPayload(readInitialPayload())
      queueDeferredFetches({
        silent: false,
        onlyMissingBundleBlocks: true,
      })
    } else {
      queueDeferredFetches({ silent: false })
    }

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
    ephemeris,

    weatherLoading,
    astronomyLoading,
    planetsLoading,
    issLoading,
    lightPollutionLoading,
    ephemerisLoading,

    weatherError,
    astronomyError,
    planetsError,
    issError,
    lightPollutionError,
    ephemerisError,

    weatherFreshness,
    astronomyFreshness,
    planetsFreshness,
    issFreshness,
    lightPollutionFreshness,
    ephemerisFreshness,

    hasLocationCoords,
    skyPhase,
    skyPhaseLabel,
    observingScore,
    scoreReasons,
    scoreLabel,
    scoreTone,
    scoreEmoji,
    scoreColorClass,
    scoreFactors,
    heroTitle,
    heroSubtitle,
    bestTimeToday,
    bestTimeLabel,
    countdownToNightLabel,
    recommendationLine,
    formattedMetrics,
    issLine,
    issPrimaryLine,
    issSecondaryLine,
    lightPollutionLine,
    lightPollutionMetaLine,
    lightPollutionImpactLine,
    lightPollutionEstimateLine,
    isLightPollutionEstimate,
    weatherUpdatedLabel,
    weatherSourceLabel,
    issUpdatedLabel,
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
    fetchEphemeris,
    refreshAll,
    refreshBlock,
  }

  function nextToken(block) {
    requestTokens[block] += 1
    return requestTokens[block]
  }
}

