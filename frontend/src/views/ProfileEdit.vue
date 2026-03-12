<template src="./profileEdit/ProfileEdit.template.html"></template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const locationPresets = [
  { key: 'bratislava', label: 'Bratislava', latitude: 48.1486, longitude: 17.1077, timezone: 'Europe/Bratislava' },
  { key: 'kosice', label: 'Kosice', latitude: 48.7164, longitude: 21.2611, timezone: 'Europe/Bratislava' },
  { key: 'presov', label: 'Presov', latitude: 48.9984, longitude: 21.2339, timezone: 'Europe/Bratislava' },
  { key: 'zilina', label: 'Zilina', latitude: 49.2231, longitude: 18.7394, timezone: 'Europe/Bratislava' },
  { key: 'nitra', label: 'Nitra', latitude: 48.3064, longitude: 18.0764, timezone: 'Europe/Bratislava' },
  { key: 'banska-bystrica', label: 'Banska Bystrica', latitude: 48.7363, longitude: 19.1462, timezone: 'Europe/Bratislava' },
  { key: 'trnava', label: 'Trnava', latitude: 48.3774, longitude: 17.5872, timezone: 'Europe/Bratislava' },
  { key: 'trencin', label: 'Trencin', latitude: 48.8945, longitude: 18.0444, timezone: 'Europe/Bratislava' },
]
const GPS_NEAREST_CITY_MAX_DISTANCE_KM = 80

const form = reactive({
  name: '',
  email: '',
  bio: '',
  locationMode: 'manual',
  locationLabel: '',
  presetKey: '',
  latitude: '',
  longitude: '',
  timezone: '',
})

const saving = ref(false)
const geolocating = ref(false)
const msg = ref('')
const err = ref('')
const savedState = ref(null)
const locationSectionRef = ref(null)
const locationHighlightActive = ref(false)

const LOCATION_FOCUS_MAX_ATTEMPTS = 30
const LOCATION_FOCUS_RETRY_DELAY_MS = 80
const LOCATION_HIGHLIGHT_DURATION_MS = 2000

let locationFocusRetryTimer = null
let locationHighlightTimer = null

const fieldErr = reactive({
  name: '',
  email: '',
  bio: '',
  locationLabel: '',
  locationSource: '',
  latitude: '',
  longitude: '',
  timezone: '',
})

const locationModeLabel = computed(() => {
  if (form.locationMode === 'preset') return 'Mesto'
  if (form.locationMode === 'gps') return 'GPS'
  return 'Rucne'
})

const embedded = computed(() => props.embedded)

function back() {
  router.push({ name: 'profile' })
}

function clearErrors() {
  msg.value = ''
  err.value = ''
  fieldErr.name = ''
  fieldErr.email = ''
  fieldErr.bio = ''
  fieldErr.locationLabel = ''
  fieldErr.locationSource = ''
  fieldErr.latitude = ''
  fieldErr.longitude = ''
  fieldErr.timezone = ''
}

function extractFirstError(errorsObj, field) {
  const value = errorsObj?.[field]
  return Array.isArray(value) && value.length ? String(value[0]) : ''
}

function browserTimezone() {
  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
}

function parseCoordinate(raw) {
  if (typeof raw === 'number') return Number.isFinite(raw) ? raw : null
  if (typeof raw !== 'string') return null
  const trimmed = raw.trim()
  if (trimmed === '') return null
  const parsed = Number(trimmed)
  return Number.isFinite(parsed) ? parsed : null
}

function formatCoordinate(value) {
  if (!Number.isFinite(value)) return ''
  return Number(value).toFixed(7)
}

function toRadians(value) {
  return (value * Math.PI) / 180
}

function haversineDistanceKm(latA, lonA, latB, lonB) {
  const earthRadiusKm = 6371
  const dLat = toRadians(latB - latA)
  const dLon = toRadians(lonB - lonA)
  const latARad = toRadians(latA)
  const latBRad = toRadians(latB)

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(latARad) * Math.cos(latBRad) * Math.sin(dLon / 2) * Math.sin(dLon / 2)

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return earthRadiusKm * c
}

function normalizeText(value) {
  const raw = String(value || '').trim().toLowerCase()
  if (!raw) return ''
  return raw
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()
}

function normalizeSource(value) {
  const raw = typeof value === 'string' ? value.trim().toLowerCase() : ''
  return ['preset', 'gps', 'manual'].includes(raw) ? raw : null
}

function resolveLocationData(user) {
  if (!user || typeof user !== 'object') return null
  const data = user.location_data
  if (data && typeof data === 'object') {
    return {
      latitude: Number(data.latitude),
      longitude: Number(data.longitude),
      timezone: String(data.timezone || ''),
      label: String(data.label || ''),
      source: normalizeSource(data.source),
    }
  }

  const meta = user.location_meta && typeof user.location_meta === 'object' ? user.location_meta : null
  return {
    latitude: Number(meta?.lat ?? user.latitude),
    longitude: Number(meta?.lon ?? user.longitude),
    timezone: String(meta?.tz || user.timezone || ''),
    label: String(meta?.label || user.location_label || user.location || ''),
    source: normalizeSource(meta?.source || user.location_source),
  }
}

function findPresetByKey(key) {
  return locationPresets.find((preset) => preset.key === key) || null
}

function findPresetByLabel(label) {
  const normalized = normalizeText(label)
  if (!normalized) return null
  return locationPresets.find((preset) => normalizeText(preset.label) === normalized) || null
}

function findPresetByQuery(query) {
  const normalized = normalizeText(query)
  if (!normalized || normalized.length < 2) return null

  let bestPreset = null
  let bestScore = -1
  let isAmbiguous = false

  for (const preset of locationPresets) {
    const normalizedLabel = normalizeText(preset.label)
    if (!normalizedLabel) continue

    let score = -1

    if (normalizedLabel === normalized) {
      score = 1000
    } else if (normalizedLabel.startsWith(normalized)) {
      score = 800 - Math.max(0, normalizedLabel.length - normalized.length)
    } else if (normalizedLabel.includes(normalized)) {
      score = 600 - normalizedLabel.indexOf(normalized)
    } else {
      const queryTokens = normalized.split(' ').filter(Boolean)
      const labelTokens = normalizedLabel.split(' ').filter(Boolean)
      const matchedTokens = queryTokens.filter((token) => labelTokens.some((labelToken) => labelToken.startsWith(token)))
      if (matchedTokens.length === queryTokens.length && queryTokens.length > 0) {
        score = 500 + matchedTokens.length
      }
    }

    if (score < 0) continue
    if (score > bestScore) {
      bestPreset = preset
      bestScore = score
      isAmbiguous = false
    } else if (score === bestScore) {
      isAmbiguous = true
    }
  }

  if (!bestPreset || isAmbiguous) return null
  return bestPreset
}

function findPresetByCoordinates(latitude, longitude) {
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null
  const tolerance = 0.0003
  return (
    locationPresets.find((preset) => {
      return Math.abs(preset.latitude - latitude) <= tolerance && Math.abs(preset.longitude - longitude) <= tolerance
    }) || null
  )
}

function findNearestPresetByCoordinates(latitude, longitude, maxDistanceKm = Number.POSITIVE_INFINITY) {
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null

  let nearest = null

  for (const preset of locationPresets) {
    const distanceKm = haversineDistanceKm(latitude, longitude, preset.latitude, preset.longitude)
    if (distanceKm > maxDistanceKm) continue
    if (!nearest || distanceKm < nearest.distanceKm) {
      nearest = { preset, distanceKm }
    }
  }

  return nearest
}

function buildStateFromUser(user) {
  const location = resolveLocationData(user)
  const latitude = Number(location?.latitude)
  const longitude = Number(location?.longitude)
  const hasCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude)
  const label = String(location?.label || user?.location_label || user?.location || '').trim()
  const timezone = String(location?.timezone || browserTimezone()).trim() || browserTimezone()
  const presetByLabel = findPresetByLabel(label)
  const presetByCoordinates = hasCoordinates
    ? (findPresetByCoordinates(latitude, longitude) ||
      findNearestPresetByCoordinates(latitude, longitude, GPS_NEAREST_CITY_MAX_DISTANCE_KM)?.preset ||
      null)
    : null
  const preset = presetByLabel || presetByCoordinates

  let source = normalizeSource(location?.source || user?.location_source)
  if (!source) {
    source = preset ? 'preset' : 'manual'
  }
  if (source === 'manual' && presetByLabel) {
    source = 'preset'
  }
  if (source === 'preset' && !preset) {
    source = 'manual'
  }

  const normalizedLabel = source === 'preset' && preset ? preset.label : label

  return {
    name: String(user?.name || ''),
    email: String(user?.email || ''),
    bio: String(user?.bio || ''),
    locationMode: source,
    locationLabel: normalizedLabel,
    presetKey: preset?.key || '',
    latitude: hasCoordinates ? formatCoordinate(latitude) : '',
    longitude: hasCoordinates ? formatCoordinate(longitude) : '',
    timezone,
  }
}

function applyState(state) {
  if (!state) return
  form.name = state.name
  form.email = state.email
  form.bio = state.bio
  form.locationMode = state.locationMode
  form.locationLabel = state.locationLabel
  form.presetKey = state.presetKey
  form.latitude = state.latitude
  form.longitude = state.longitude
  form.timezone = state.timezone
}

function syncFromUser(user) {
  const state = buildStateFromUser(user)
  savedState.value = { ...state }
  applyState(state)
}

function setLocationMode(mode) {
  form.locationMode = mode
  fieldErr.locationSource = ''
  if (mode === 'manual') {
    form.presetKey = ''
    if (!form.timezone) {
      form.timezone = browserTimezone()
    }
  }
  if (mode === 'preset' && form.presetKey) {
    applyPreset(form.presetKey)
  }
}

function applyPreset(presetKey) {
  const preset = findPresetByKey(presetKey)
  if (!preset) return
  form.presetKey = preset.key
  form.locationMode = 'preset'
  form.latitude = formatCoordinate(preset.latitude)
  form.longitude = formatCoordinate(preset.longitude)
  form.timezone = preset.timezone
  form.locationLabel = preset.label
}

function onPresetChanged() {
  if (!form.presetKey) {
    setLocationMode('manual')
    return
  }
  applyPreset(form.presetKey)
}

function clearManualCoordinates() {
  form.latitude = ''
  form.longitude = ''
  form.timezone = ''
  fieldErr.latitude = ''
  fieldErr.longitude = ''
  fieldErr.timezone = ''
}

function onLocationLabelInput() {
  const label = String(form.locationLabel || '').trim()
  fieldErr.locationLabel = ''
  msg.value = ''
  err.value = ''

  if (!label) {
    form.locationMode = 'manual'
    form.presetKey = ''
    clearManualCoordinates()
    return
  }

  const exactPreset = findPresetByLabel(label)
  if (exactPreset) {
    applyPreset(exactPreset.key)
    return
  }

  form.locationMode = 'manual'
  form.presetKey = ''
  clearManualCoordinates()
}

function fillLocationFromLabel() {
  fieldErr.locationLabel = ''
  fieldErr.latitude = ''
  fieldErr.longitude = ''
  fieldErr.timezone = ''
  err.value = ''

  const label = String(form.locationLabel || '').trim()
  if (!label) {
    clearManualCoordinates()
    fieldErr.locationLabel = 'Zadaj nazov mesta.'
    return
  }

  const preset = findPresetByQuery(label)
  if (!preset) {
    clearManualCoordinates()
    fieldErr.locationLabel = 'Vyber velke slovenske mesto zo zoznamu.'
    return
  }

  applyPreset(preset.key)
  msg.value = 'Mesto bolo nastavene.'
}

function buildProfilePayload(state) {
  const payload = {}
  const normalizedName = String(state?.name || '').trim()
  const normalizedBio = String(state?.bio || '')
  if (normalizedName) {
    payload.name = normalizedName
  }
  payload.bio = normalizedBio
  return payload
}

function buildLocationPayload(state) {
  const locationSource = normalizeSource(state?.locationMode) || 'manual'
  const preset = locationSource === 'preset' ? findPresetByKey(String(state?.presetKey || '')) : null
  const locationLabel =
    locationSource === 'preset'
      ? String(preset?.label || state?.locationLabel || '').trim() || null
      : String(state?.locationLabel || '').trim() || null

  return {
    latitude: parseCoordinate(state?.latitude),
    longitude: parseCoordinate(state?.longitude),
    timezone: String(state?.timezone || '').trim(),
    location_label: locationLabel,
    location_source: locationSource,
  }
}

function isEqualPayload(left, right) {
  return JSON.stringify(left || {}) === JSON.stringify(right || {})
}

const hasChanges = computed(() => {
  if (!savedState.value) return false

  const profilePayload = buildProfilePayload(form)
  const savedProfilePayload = buildProfilePayload(savedState.value)
  const locationPayload = buildLocationPayload(form)
  const savedLocationPayload = buildLocationPayload(savedState.value)

  return !isEqualPayload(profilePayload, savedProfilePayload) || !isEqualPayload(locationPayload, savedLocationPayload)
})

function resetForm() {
  if (!savedState.value) return
  clearErrors()
  applyState(savedState.value)
  msg.value = 'Zmeny zrusene.'
}

function validateLocationPayload() {
  if (form.locationMode === 'manual') {
    const resolvedPreset = findPresetByQuery(form.locationLabel)
    if (resolvedPreset) {
      applyPreset(resolvedPreset.key)
    } else {
      fieldErr.locationLabel = 'Vyber velke slovenske mesto alebo pouzi GPS.'
      return null
    }
  }

  const payload = buildLocationPayload(form)

  let valid = true

  if (form.locationMode === 'preset' && !form.presetKey) {
    fieldErr.locationSource = 'Vyber mesto.'
    valid = false
  }

  if (payload.latitude === null || payload.latitude < -90 || payload.latitude > 90) {
    fieldErr.latitude = 'Latitude musi byt v rozsahu -90 az 90.'
    valid = false
  }

  if (payload.longitude === null || payload.longitude < -180 || payload.longitude > 180) {
    fieldErr.longitude = 'Longitude musi byt v rozsahu -180 az 180.'
    valid = false
  }

  if (!payload.timezone || !payload.timezone.includes('/')) {
    fieldErr.timezone = 'Zadaj platne IANA timezone, napr. Europe/Bratislava.'
    valid = false
  }

  if (payload.location_label && payload.location_label.length > 80) {
    fieldErr.locationLabel = 'Nazov polohy moze mat max 80 znakov.'
    valid = false
  }

  if (!['preset', 'gps', 'manual'].includes(payload.location_source)) {
    fieldErr.locationSource = 'Vyber validny sposob polohy.'
    valid = false
  }

  return valid ? payload : null
}

function applyServerValidationErrors(errorsObj) {
  fieldErr.name = extractFirstError(errorsObj, 'name')
  fieldErr.email = extractFirstError(errorsObj, 'email')
  fieldErr.bio = extractFirstError(errorsObj, 'bio')
  fieldErr.locationLabel = extractFirstError(errorsObj, 'location_label')
  fieldErr.locationSource = extractFirstError(errorsObj, 'location_source')
  fieldErr.latitude = extractFirstError(errorsObj, 'latitude')
  fieldErr.longitude = extractFirstError(errorsObj, 'longitude')
  fieldErr.timezone = extractFirstError(errorsObj, 'timezone')
}

function firstFieldErrorMessage() {
  return (
    fieldErr.name ||
    fieldErr.email ||
    fieldErr.bio ||
    fieldErr.locationLabel ||
    fieldErr.locationSource ||
    fieldErr.latitude ||
    fieldErr.longitude ||
    fieldErr.timezone ||
    ''
  )
}

function applySaveError(error, fallbackMessage) {
  const status = error?.response?.status
  const data = error?.response?.data

  if (status === 422 && data?.errors) {
    applyServerValidationErrors(data.errors)
    const fieldMessage = firstFieldErrorMessage()
    err.value = fieldMessage ? `${fallbackMessage} ${fieldMessage}` : fallbackMessage
    return
  }

  err.value = data?.message || fallbackMessage
}

function clearLocationFocusRetryTimer() {
  if (locationFocusRetryTimer) {
    clearTimeout(locationFocusRetryTimer)
    locationFocusRetryTimer = null
  }
}

function clearLocationHighlightTimer() {
  if (locationHighlightTimer) {
    clearTimeout(locationHighlightTimer)
    locationHighlightTimer = null
  }
}

function triggerLocationHighlight() {
  clearLocationHighlightTimer()
  locationHighlightActive.value = true
  locationHighlightTimer = setTimeout(() => {
    locationHighlightActive.value = false
    locationHighlightTimer = null
  }, LOCATION_HIGHLIGHT_DURATION_MS)
}

function focusLocationSection(attempt = 0) {
  const section = locationSectionRef.value
  if (section) {
    if (typeof section.scrollIntoView === 'function') {
      section.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    }
    triggerLocationHighlight()
    return
  }

  if (attempt >= LOCATION_FOCUS_MAX_ATTEMPTS) return

  clearLocationFocusRetryTimer()
  locationFocusRetryTimer = setTimeout(() => {
    focusLocationSection(attempt + 1)
  }, LOCATION_FOCUS_RETRY_DELAY_MS)
}

async function maybeFocusLocationFromHash() {
  if (route.hash !== '#location') return
  await nextTick()
  focusLocationSection()
}

async function save() {
  clearErrors()

  const profilePayload = buildProfilePayload(form)
  const currentLocationPayload = buildLocationPayload(form)
  const savedProfilePayload = buildProfilePayload(savedState.value)
  const savedLocationPayload = buildLocationPayload(savedState.value)

  const profileDirty = !isEqualPayload(profilePayload, savedProfilePayload)
  const locationDirty = !isEqualPayload(currentLocationPayload, savedLocationPayload)

  if (!profileDirty && !locationDirty) {
    msg.value = 'Ziadne zmeny.'
    return
  }

  let locationPayload = currentLocationPayload
  if (locationDirty) {
    locationPayload = validateLocationPayload()
    if (!locationPayload) {
      err.value =
        fieldErr.locationSource ||
        fieldErr.locationLabel ||
        fieldErr.latitude ||
        fieldErr.longitude ||
        fieldErr.timezone ||
        'Skontroluj oznacene polia.'
      return
    }
  }

  saving.value = true

  try {
    await auth.csrf()

    let userPayload = null

    if (locationDirty) {
      try {
        const locationResponse = await http.patch('/me/location', locationPayload)
        userPayload = locationResponse?.data || userPayload
      } catch (locationError) {
        const fallback = profileDirty ? 'Ulozenie polohy zlyhalo. Profil nebol ulozeny.' : 'Ulozenie polohy zlyhalo.'
        applySaveError(locationError, fallback)
        return
      }
    }

    if (profileDirty) {
      try {
        const profileResponse = await http.patch('/profile', profilePayload)
        userPayload = profileResponse?.data || userPayload
      } catch (profileError) {
        const fallback = locationDirty ? 'Poloha bola ulozena, ale profil sa nepodarilo ulozit.' : 'Ulozenie profilu zlyhalo.'
        applySaveError(profileError, fallback)
        return
      }
    }

    try {
      const refreshed = await http.get('/auth/me', {
        meta: { skipErrorToast: true },
      })
      if (refreshed?.data) {
        userPayload = refreshed.data
      }
    } catch {
      // Keep latest successful payload if /auth/me refresh fails.
    }

    if (userPayload) {
      auth.user = userPayload
      syncFromUser(userPayload)
    }

    if (profileDirty && locationDirty) {
      msg.value = 'Profil a poloha ulozene.'
    } else if (profileDirty) {
      msg.value = 'Profil ulozeny.'
    } else {
      msg.value = 'Poloha ulozena.'
    }
  } catch (saveError) {
    applySaveError(saveError, 'Ulozenie zlyhalo.')
  } finally {
    saving.value = false
  }
}

async function useMyLocation() {
  clearErrors()

  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    err.value = 'Geolokacia nie je podporovana. Vyber mesto zo zoznamu.'
    return
  }

  geolocating.value = true
  try {
    const position = await new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000,
      })
    })

    const lat = Number(position?.coords?.latitude)
    const lon = Number(position?.coords?.longitude)
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
      err.value = 'Nepodarilo sa ziskat GPS suradnice. Vyber mesto zo zoznamu.'
      return
    }

    form.locationMode = 'gps'
    form.latitude = formatCoordinate(lat)
    form.longitude = formatCoordinate(lon)
    form.timezone = browserTimezone()

    const nearest = findNearestPresetByCoordinates(lat, lon, GPS_NEAREST_CITY_MAX_DISTANCE_KM)
    if (nearest?.preset) {
      form.presetKey = nearest.preset.key
      form.locationLabel = nearest.preset.label
      msg.value = `GPS nastavene. Najblizsie mesto: ${nearest.preset.label}.`
    } else {
      form.presetKey = ''
      if (!form.locationLabel.trim() || normalizeSource(savedState.value?.locationMode) === 'gps') {
        form.locationLabel = 'Moja poloha'
      }
      msg.value = 'GPS suradnice nastavene.'
    }
  } catch {
    err.value = 'GPS zlyhalo. Skus to znovu alebo vyber mesto zo zoznamu.'
  } finally {
    geolocating.value = false
  }
}

onMounted(async () => {
  if (!auth.initialized) {
    await auth.fetchUser()
  }
  syncFromUser(auth.user)
  maybeFocusLocationFromHash()
})

watch(
  () => route.hash,
  () => {
    maybeFocusLocationFromHash()
  },
)

watch(
  () => auth.user,
  (user) => {
    if (!user) return
    maybeFocusLocationFromHash()
  },
)

onBeforeUnmount(() => {
  clearLocationFocusRetryTimer()
  clearLocationHighlightTimer()
})
</script>

<style scoped src="./profileEdit/ProfileEdit.css"></style>
