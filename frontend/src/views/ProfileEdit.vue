<template src="./profileEdit/ProfileEdit.template.html"></template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'
import { searchOnboardingLocations } from '@/services/events'
import InlineStatus from '@/components/ui/InlineStatus.vue'

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
  { key: 'ivanka-pri-nitre', label: 'Ivanka pri Nitre', latitude: 48.2930, longitude: 18.1889, timezone: 'Europe/Bratislava' },
  { key: 'banska-bystrica', label: 'Banska Bystrica', latitude: 48.7363, longitude: 19.1462, timezone: 'Europe/Bratislava' },
  { key: 'trnava', label: 'Trnava', latitude: 48.3774, longitude: 17.5872, timezone: 'Europe/Bratislava' },
  { key: 'trencin', label: 'Trencin', latitude: 48.8945, longitude: 18.0444, timezone: 'Europe/Bratislava' },
]

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
const resolvingLocation = ref(false)
const msg = ref('')
const err = ref('')
const savedState = ref(null)
const locationSectionRef = ref(null)
const locationHighlightActive = ref(false)
const locationAutocompleteRef = ref(null)
const locationSuggestions = ref([])
const openLocationSuggestions = ref(false)

const LOCATION_FOCUS_MAX_ATTEMPTS = 30
const LOCATION_FOCUS_RETRY_DELAY_MS = 80
const LOCATION_HIGHLIGHT_DURATION_MS = 2000

let locationFocusRetryTimer = null
let locationHighlightTimer = null
let locationSuggestionsDebounceTimer = null
let locationSuggestionsRequestId = 0

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

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value !== 'string' || value.trim() === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
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
  return ['preset', 'manual'].includes(raw) ? raw : null
}

function sanitizeTimezone(value, fallback = browserTimezone()) {
  const candidate = String(value || '').trim()
  if (!candidate) return fallback

  try {
    Intl.DateTimeFormat('en-US', { timeZone: candidate }).format(new Date())
    return candidate
  } catch {
    return fallback
  }
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

function buildStateFromUser(user) {
  const location = resolveLocationData(user)
  const latitude = Number(location?.latitude)
  const longitude = Number(location?.longitude)
  const hasCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude)
  const label = String(location?.label || user?.location_label || user?.location || '').trim()
  const timezone = String(location?.timezone || browserTimezone()).trim() || browserTimezone()
  const presetByLabel = findPresetByLabel(label)
  const presetByCoordinates = hasCoordinates ? findPresetByCoordinates(latitude, longitude) : null
  const preset = presetByLabel || presetByCoordinates

  const source = 'manual'
  const normalizedLabel = presetByLabel ? presetByLabel.label : label

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

function setLocationMode() {
  form.locationMode = 'manual'
  fieldErr.locationSource = ''
  if (form.locationMode === 'manual') {
    form.presetKey = ''
    if (!form.timezone) {
      form.timezone = browserTimezone()
    }
  }
}

function applyPreset(presetKey) {
  const preset = findPresetByKey(presetKey)
  if (!preset) return
  form.presetKey = preset.key
  form.locationMode = 'manual'
  form.latitude = formatCoordinate(preset.latitude)
  form.longitude = formatCoordinate(preset.longitude)
  form.timezone = preset.timezone
  form.locationLabel = preset.label
}

function applyManualResolvedLocation(location) {
  setLocationMode('manual')
  form.presetKey = ''
  form.locationLabel = String(location?.label || form.locationLabel || '').trim()
  form.latitude = formatCoordinate(Number(location?.latitude))
  form.longitude = formatCoordinate(Number(location?.longitude))
  form.timezone = sanitizeTimezone(location?.timezone, form.timezone || browserTimezone())
}

function selectBestGeoCandidate(rows, query) {
  const normalizedQuery = normalizeText(query)
  if (!normalizedQuery) return null

  let best = null
  let bestScore = -1

  for (const row of rows) {
    if (!row || typeof row !== 'object') continue

    const latitude = toFiniteNumber(row.lat ?? row.latitude)
    const longitude = toFiniteNumber(row.lon ?? row.longitude)
    if (latitude === null || longitude === null) continue

    const label = String(row.label || '').trim()
    const normalizedLabel = normalizeText(label)
    if (!normalizedLabel) continue

    let score = 0
    if (normalizedLabel === normalizedQuery) score = 1000
    else if (normalizedLabel.startsWith(`${normalizedQuery} `) || normalizedLabel.startsWith(`${normalizedQuery},`)) score = 900
    else if (normalizedLabel.startsWith(normalizedQuery)) score = 800
    else if (normalizedLabel.includes(` ${normalizedQuery} `) || normalizedLabel.includes(` ${normalizedQuery},`)) score = 700
    else if (normalizedLabel.includes(normalizedQuery)) score = 600 - normalizedLabel.indexOf(normalizedQuery)

    if (score > bestScore) {
      best = {
        label: label || String(query || '').trim(),
        latitude,
        longitude,
        timezone: sanitizeTimezone(row.timezone, form.timezone || browserTimezone()),
      }
      bestScore = score
    }
  }

  return best
}

async function resolveManualLocation(query) {
  const normalizedQuery = String(query || '').trim()
  if (!normalizedQuery) return null

  try {
    const response = await searchOnboardingLocations(normalizedQuery, 8)
    const rows = Array.isArray(response?.data?.data) ? response.data.data : []
    const resolved = selectBestGeoCandidate(rows, normalizedQuery)
    if (resolved) return resolved
  } catch {
    // Fallback to local preset map below.
  }

  const preset = findPresetByQuery(normalizedQuery)
  if (!preset) return null

  return {
    label: preset.label,
    latitude: preset.latitude,
    longitude: preset.longitude,
    timezone: preset.timezone,
  }
}

function clearManualCoordinates() {
  form.latitude = ''
  form.longitude = ''
  form.timezone = ''
  fieldErr.latitude = ''
  fieldErr.longitude = ''
  fieldErr.timezone = ''
}

function clearLocationSuggestions() {
  locationSuggestions.value = []
  openLocationSuggestions.value = false
}

function normalizeLocationSuggestion(row) {
  if (!row || typeof row !== 'object') return null

  const latitude = toFiniteNumber(row.lat ?? row.latitude)
  const longitude = toFiniteNumber(row.lon ?? row.longitude)
  if (latitude === null || longitude === null) return null

  const label = String(row.label || '').trim()
  if (!label) return null

  return {
    place_id: String(row.place_id || `${label}:${latitude}:${longitude}`),
    label,
    lat: latitude,
    lon: longitude,
    timezone: sanitizeTimezone(row.timezone, form.timezone || browserTimezone()),
    country: String(row.country || '').trim(),
  }
}

function queueLocationSuggestions(query) {
  const normalizedQuery = String(query || '').trim()

  if (locationSuggestionsDebounceTimer) {
    clearTimeout(locationSuggestionsDebounceTimer)
    locationSuggestionsDebounceTimer = null
  }

  if (normalizedQuery.length < 2) {
    clearLocationSuggestions()
    return
  }

  locationSuggestionsDebounceTimer = setTimeout(async () => {
    const requestId = ++locationSuggestionsRequestId
    try {
      const response = await searchOnboardingLocations(normalizedQuery, 8)
      if (requestId !== locationSuggestionsRequestId) return

      const rows = Array.isArray(response?.data?.data) ? response.data.data : []
      const nextSuggestions = rows
        .map((row) => normalizeLocationSuggestion(row))
        .filter((row) => row !== null)

      locationSuggestions.value = nextSuggestions
      openLocationSuggestions.value = nextSuggestions.length > 0
    } catch {
      if (requestId !== locationSuggestionsRequestId) return
      clearLocationSuggestions()
    } finally {
      if (requestId === locationSuggestionsRequestId) {
        locationSuggestionsDebounceTimer = null
      }
    }
  }, 300)
}

function onLocationLabelFocus() {
  const query = String(form.locationLabel || '').trim()
  if (query.length < 2) return

  if (locationSuggestions.value.length > 0) {
    openLocationSuggestions.value = true
    return
  }

  queueLocationSuggestions(query)
}

function onLocationSuggestionSelect(option) {
  if (!option) return
  fieldErr.locationLabel = ''
  fieldErr.locationSource = ''
  err.value = ''

  applyManualResolvedLocation({
    label: option.label,
    latitude: option.lat,
    longitude: option.lon,
    timezone: option.timezone,
  })
  clearLocationSuggestions()
  msg.value = `Poloha nastavená: ${option.label}.`
}

function onLocationAutocompleteOutsidePointerDown(event) {
  if (!openLocationSuggestions.value) return
  const root = locationAutocompleteRef.value
  if (!root) return
  if (root === event.target || root.contains(event.target)) return
  openLocationSuggestions.value = false
}

function onLocationLabelInput() {
  const label = String(form.locationLabel || '').trim()
  fieldErr.locationLabel = ''
  fieldErr.locationSource = ''
  msg.value = ''
  err.value = ''

  if (!label) {
    setLocationMode('manual')
    form.presetKey = ''
    clearManualCoordinates()
    clearLocationSuggestions()
    return
  }

  const exactPreset = findPresetByLabel(label)
  if (exactPreset) {
    applyPreset(exactPreset.key)
    clearLocationSuggestions()
    return
  }

  setLocationMode('manual')
  form.presetKey = ''
  clearManualCoordinates()
  queueLocationSuggestions(label)
}

async function fillLocationFromLabel() {
  clearLocationSuggestions()
  fieldErr.locationLabel = ''
  fieldErr.latitude = ''
  fieldErr.longitude = ''
  fieldErr.timezone = ''
  err.value = ''

  const label = String(form.locationLabel || '').trim()
  if (!label) {
    clearManualCoordinates()
    fieldErr.locationLabel = 'Zadaj názov mesta.'
    return
  }

  resolvingLocation.value = true
  const resolved = await resolveManualLocation(label)
  resolvingLocation.value = false

  if (!resolved) {
    clearManualCoordinates()
    fieldErr.locationLabel = 'Nepodarilo sa doplniť súradnice pre zadané mesto. Skús presnejší názov.'
    return
  }

  applyManualResolvedLocation(resolved)
  msg.value = `Poloha nastavená: ${resolved.label}.`
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
  const locationSource = 'manual'
  const locationLabel = String(state?.locationLabel || '').trim() || null

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
  msg.value = 'Zmeny zrušené.'
}

async function validateLocationPayload() {
  if (form.locationMode === 'manual') {
    const locationLabel = String(form.locationLabel || '').trim()
    if (!locationLabel) {
      fieldErr.locationLabel = 'Zadaj názov mesta.'
      return null
    }

    const hasCoordinates = parseCoordinate(form.latitude) !== null && parseCoordinate(form.longitude) !== null
    if (!hasCoordinates) {
      resolvingLocation.value = true
      const resolved = await resolveManualLocation(locationLabel)
      resolvingLocation.value = false

      if (!resolved) {
        clearManualCoordinates()
        fieldErr.locationLabel = 'Nepodarilo sa doplniť súradnice pre zadané mesto. Skús presnejší názov.'
        return null
      }

      applyManualResolvedLocation(resolved)
    }
  }

  const payload = buildLocationPayload(form)

  let valid = true

  if (payload.latitude === null || payload.latitude < -90 || payload.latitude > 90) {
    fieldErr.latitude = 'Latitude musí byť v rozsahu -90 až 90.'
    valid = false
  }

  if (payload.longitude === null || payload.longitude < -180 || payload.longitude > 180) {
    fieldErr.longitude = 'Longitude musí byť v rozsahu -180 až 180.'
    valid = false
  }

  if (!payload.timezone || !payload.timezone.includes('/')) {
    fieldErr.timezone = 'Zadaj platné IANA timezone, napr. Europe/Bratislava.'
    valid = false
  }

  if (payload.location_label && payload.location_label.length > 80) {
    fieldErr.locationLabel = 'Názov polohy môže mať max 80 znakov.'
    valid = false
  }

  if (payload.location_source !== 'manual') {
    fieldErr.locationSource = 'Vyber validný spôsob polohy.'
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
    msg.value = 'Žiadne zmeny.'
    return
  }

  let locationPayload = currentLocationPayload
  if (locationDirty) {
    locationPayload = await validateLocationPayload()
    if (!locationPayload) {
      return
    }
  }

  saving.value = true

  try {
    await auth.csrf()

    let userPayload = null

    if (locationDirty) {
      try {
        const locationResponse = await http.patch('/me/location', locationPayload, { meta: { skipErrorToast: true } })
        userPayload = locationResponse?.data || userPayload
      } catch (locationError) {
        const fallback = profileDirty ? 'Uloženie polohy zlyhalo. Profil nebol uložený.' : 'Uloženie polohy zlyhalo.'
        applySaveError(locationError, fallback)
        return
      }
    }

    if (profileDirty) {
      try {
        const profileResponse = await http.patch('/profile', profilePayload, { meta: { skipErrorToast: true } })
        userPayload = profileResponse?.data || userPayload
      } catch (profileError) {
        const fallback = locationDirty ? 'Poloha bola uložená, ale profil sa nepodarilo uložiť.' : 'Uloženie profilu zlyhalo.'
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
      msg.value = 'Profil a poloha uložené.'
    } else if (profileDirty) {
      msg.value = 'Profil uložený.'
    } else {
      msg.value = 'Poloha uložená.'
    }
  } catch (saveError) {
    applySaveError(saveError, 'Uloženie zlyhalo.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  document.addEventListener('pointerdown', onLocationAutocompleteOutsidePointerDown)
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
  if (locationSuggestionsDebounceTimer) {
    clearTimeout(locationSuggestionsDebounceTimer)
    locationSuggestionsDebounceTimer = null
  }
  document.removeEventListener('pointerdown', onLocationAutocompleteOutsidePointerDown)
  clearLocationFocusRetryTimer()
  clearLocationHighlightTimer()
})
</script>

<style scoped src="./profileEdit/ProfileEdit.css"></style>
