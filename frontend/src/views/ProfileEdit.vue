<template>
  <div class="wrap">
    <header class="top">
      <button class="iconBtn" @click="back">&larr;</button>
      <div>
        <div class="title">Upraviť profil</div>
        <div class="subtitle">Meno, bio a poloha</div>
      </div>
      <button class="btn ghost" @click="resetForm" :disabled="saving">Reset</button>
    </header>

    <div v-if="!auth.initialized" class="card muted">Nacitavam...</div>
    <div v-else-if="!auth.user" class="card err">Nie si prihlaseny.</div>

    <template v-else>
      <div class="card">
        <div v-if="msg" class="msg ok">{{ msg }}</div>
        <div v-if="err" class="msg err">{{ err }}</div>

        <div class="form">
          <div class="field">
            <label>Meno</label>
            <input class="input" v-model="form.name" type="text" />
            <p v-if="fieldErr.name" class="fieldErr">{{ fieldErr.name }}</p>
          </div>

          <div class="field">
            <label>O mne</label>
            <textarea class="input textarea" v-model="form.bio" rows="4" maxlength="160"></textarea>
            <div class="hint">{{ (form.bio || '').length }}/160</div>
            <p v-if="fieldErr.bio" class="fieldErr">{{ fieldErr.bio }}</p>
          </div>

          <section
            id="location"
            ref="locationSectionRef"
            class="locationCard"
            :class="{ locationHighlight: locationHighlightActive }"
          >
            <div class="locationHead">
              <h3 class="sectionTitle">Poloha</h3>
              <p class="sectionHint">Jedna kanonicka poloha pre observing a widgety.</p>
            </div>

            <div class="modeSwitch" role="tablist" aria-label="Sposob nastavenia polohy">
              <button
                v-for="option in modeOptions"
                :key="option.key"
                class="modeBtn"
                :class="{ active: form.locationMode === option.key }"
                type="button"
                role="tab"
                :aria-selected="form.locationMode === option.key ? 'true' : 'false'"
                @click="setLocationMode(option.key)"
              >
                {{ option.label }}
              </button>
            </div>

            <div class="quickGps">
              <button class="btn ghost quickGpsBtn" type="button" @click="useMyLocation" :disabled="saving || geolocating">
                {{ geolocating ? 'Ziskavam GPS...' : 'Pouzit moju polohu' }}
              </button>
            </div>

            <div class="field">
              <label>Nazov polohy</label>
              <input
                class="input"
                v-model.trim="form.locationLabel"
                type="text"
                maxlength="80"
                placeholder="Napriklad Bratislava"
              />
              <p v-if="fieldErr.locationLabel" class="fieldErr">{{ fieldErr.locationLabel }}</p>
            </div>

            <div v-if="form.locationMode === 'preset'" class="field">
              <label>Predvolba mesta</label>
              <select class="input" v-model="form.presetKey" @change="onPresetChanged">
                <option value="">-- Vyber predvolbu --</option>
                <option v-for="preset in locationPresets" :key="preset.key" :value="preset.key">
                  {{ preset.label }}
                </option>
              </select>
              <p class="hint">Pri predvolbe su suradnice iba na citanie.</p>
              <p v-if="fieldErr.locationSource" class="fieldErr">{{ fieldErr.locationSource }}</p>
            </div>

            <div v-if="form.locationMode === 'gps'" class="field">
              <label>GPS</label>
              <p class="hint">Ak GPS zlyha, prepni na manualny rezim.</p>
            </div>

            <div v-if="form.locationMode === 'manual'" class="field">
              <label>Manualne</label>
              <p class="hint">Zadaj suradnice a timezone rucne v casti Rozsirene.</p>
            </div>

            <details class="advanced" open>
              <summary>Rozsirene</summary>
              <div class="fieldGrid">
                <div class="field">
                  <label>Latitude</label>
                  <input
                    class="input"
                    v-model="form.latitude"
                    type="number"
                    step="0.0000001"
                    :readonly="isCoordinatesReadOnly"
                  />
                  <p v-if="fieldErr.latitude" class="fieldErr">{{ fieldErr.latitude }}</p>
                </div>
                <div class="field">
                  <label>Longitude</label>
                  <input
                    class="input"
                    v-model="form.longitude"
                    type="number"
                    step="0.0000001"
                    :readonly="isCoordinatesReadOnly"
                  />
                  <p v-if="fieldErr.longitude" class="fieldErr">{{ fieldErr.longitude }}</p>
                </div>
              </div>

              <div class="field">
                <label>Timezone</label>
                <input
                  class="input"
                  v-model.trim="form.timezone"
                  type="text"
                  placeholder="Europe/Bratislava"
                  :readonly="isCoordinatesReadOnly"
                />
                <p v-if="fieldErr.timezone" class="fieldErr">{{ fieldErr.timezone }}</p>
              </div>
            </details>
          </section>

          <div class="actions">
            <button class="btn" @click="save" :disabled="saving">
              {{ saving ? 'Ukladam...' : 'Ulozit' }}
            </button>
            <button class="btn ghost" @click="back" :disabled="saving">Zrusit</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const modeOptions = [
  { key: 'preset', label: 'Predvolba mesta' },
  { key: 'gps', label: 'Pouzit GPS' },
  { key: 'manual', label: 'Manualne' },
]

const locationPresets = [
  { key: 'bratislava', label: 'Bratislava', latitude: 48.1486, longitude: 17.1077, timezone: 'Europe/Bratislava' },
  { key: 'kosice', label: 'Kosice', latitude: 48.7164, longitude: 21.2611, timezone: 'Europe/Bratislava' },
  { key: 'presov', label: 'Presov', latitude: 48.9984, longitude: 21.2339, timezone: 'Europe/Bratislava' },
  { key: 'zilina', label: 'Zilina', latitude: 49.2231, longitude: 18.7394, timezone: 'Europe/Bratislava' },
  { key: 'nitra', label: 'Nitra', latitude: 48.3064, longitude: 18.0764, timezone: 'Europe/Bratislava' },
  { key: 'banska-bystrica', label: 'Banska Bystrica', latitude: 48.7363, longitude: 19.1462, timezone: 'Europe/Bratislava' },
  { key: 'trnava', label: 'Trnava', latitude: 48.3774, longitude: 17.5872, timezone: 'Europe/Bratislava' },
  { key: 'trencin', label: 'Trencin', latitude: 48.8945, longitude: 18.0444, timezone: 'Europe/Bratislava' },
  { key: 'slovensko-ine', label: 'Slovensko (ine)', latitude: 48.669, longitude: 19.699, timezone: 'Europe/Bratislava' },
  { key: 'cesko', label: 'Cesko', latitude: 49.8175, longitude: 15.473, timezone: 'Europe/Prague' },
  { key: 'europa', label: 'Europa', latitude: 50.1109, longitude: 8.6821, timezone: 'Europe/Berlin' },
  { key: 'mimo-europy', label: 'Mimo Europy', latitude: 40.7128, longitude: -74.006, timezone: 'America/New_York' },
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

const isCoordinatesReadOnly = computed(() => form.locationMode !== 'manual')

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
  const normalized = String(label || '').trim().toLowerCase()
  if (!normalized) return null
  return locationPresets.find((preset) => preset.label.toLowerCase() === normalized) || null
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

  let source = normalizeSource(location?.source || user?.location_source)
  if (!source) {
    source = preset ? 'preset' : hasCoordinates ? 'manual' : 'manual'
  }
  if (source === 'preset' && !preset) {
    source = hasCoordinates ? 'manual' : 'manual'
  }

  return {
    name: String(user?.name || ''),
    email: String(user?.email || ''),
    bio: String(user?.bio || ''),
    locationMode: source,
    locationLabel: label,
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

function resetForm() {
  clearErrors()
  if (savedState.value) {
    applyState(savedState.value)
    return
  }
  if (auth.user) {
    syncFromUser(auth.user)
  }
}

function setLocationMode(mode) {
  form.locationMode = mode
  fieldErr.locationSource = ''
  if (mode === 'manual' && !form.timezone) {
    form.timezone = browserTimezone()
  }
  if (mode === 'preset' && form.presetKey) {
    applyPreset(form.presetKey, true)
  }
}

function applyPreset(presetKey, keepLabel = false) {
  const preset = findPresetByKey(presetKey)
  if (!preset) return
  form.presetKey = preset.key
  form.locationMode = 'preset'
  form.latitude = formatCoordinate(preset.latitude)
  form.longitude = formatCoordinate(preset.longitude)
  form.timezone = preset.timezone
  if (!keepLabel || !form.locationLabel.trim()) {
    form.locationLabel = preset.label
  }
}

function onPresetChanged() {
  applyPreset(form.presetKey)
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
  return {
    latitude: parseCoordinate(state?.latitude),
    longitude: parseCoordinate(state?.longitude),
    timezone: String(state?.timezone || '').trim(),
    location_label: String(state?.locationLabel || '').trim() || null,
    location_source: normalizeSource(state?.locationMode) || 'manual',
  }
}

function isEqualPayload(left, right) {
  return JSON.stringify(left || {}) === JSON.stringify(right || {})
}

function validateLocationPayload() {
  const payload = buildLocationPayload(form)

  let valid = true

  if (form.locationMode === 'preset' && !form.presetKey) {
    fieldErr.locationSource = 'Vyber predvolbu mesta.'
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
    fieldErr.locationSource = 'Zvol jeden rezim polohy.'
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

  const locationPayload = validateLocationPayload()
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

  const profilePayload = buildProfilePayload(form)
  const savedProfilePayload = buildProfilePayload(savedState.value)
  const savedLocationPayload = buildLocationPayload(savedState.value)

  const profileDirty = !isEqualPayload(profilePayload, savedProfilePayload)
  const locationDirty = !isEqualPayload(locationPayload, savedLocationPayload)

  if (!profileDirty && !locationDirty) {
    msg.value = 'Žiadne zmeny.'
    return
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
    err.value = 'Geolokacia nie je podporovana. Pouzi manualny rezim.'
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
      err.value = 'Nepodarilo sa ziskat GPS suradnice. Pouzi manualny rezim.'
      return
    }

    form.locationMode = 'gps'
    form.presetKey = ''
    form.latitude = formatCoordinate(lat)
    form.longitude = formatCoordinate(lon)
    form.timezone = browserTimezone()
    if (!form.locationLabel.trim() || normalizeSource(savedState.value?.locationMode) === 'gps') {
      form.locationLabel = 'Moja poloha'
    }
  } catch {
    err.value = 'GPS zlyhalo. Skus manualny rezim.'
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

<style scoped>
.wrap {
  max-width: 760px;
  margin: 0 auto;
  padding: 1rem;
  display: grid;
  gap: 1rem;
}

.top {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.75rem;
}

.iconBtn {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.8);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
}

.title {
  font-weight: 900;
  color: var(--color-surface);
}

.subtitle {
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.8);
  background: rgb(var(--color-bg-rgb) / 0.55);
  border-radius: 1.25rem;
  padding: 1.1rem;
}

.form {
  margin-top: 0.75rem;
  display: grid;
  gap: 0.9rem;
}

.field label {
  display: block;
  font-size: 0.8rem;
  color: var(--color-surface);
  margin-bottom: 0.35rem;
}

.input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.85);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  outline: none;
}

.input[readonly] {
  opacity: 0.82;
}

.input:focus {
  border-color: rgb(var(--color-primary-rgb) / 0.9);
}

.textarea {
  resize: vertical;
}

.hint {
  margin-top: 0.35rem;
  color: var(--color-text-secondary);
  font-size: 0.84rem;
}

.sectionHint {
  margin: 0.15rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.84rem;
}

.fieldErr {
  margin-top: 0.35rem;
  font-size: 0.84rem;
  color: var(--color-danger);
}

.locationCard {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  border-radius: 1rem;
  padding: 0.85rem;
  display: grid;
  gap: 0.8rem;
  background: rgb(var(--color-bg-rgb) / 0.28);
  transition: border-color 220ms ease, box-shadow 220ms ease;
}

.locationCard.locationHighlight {
  border-color: rgb(var(--color-primary-rgb) / 0.85);
  box-shadow: 0 0 0 2px rgb(var(--color-primary-rgb) / 0.26);
}

.sectionTitle {
  margin: 0;
  font-size: 1rem;
  color: var(--color-surface);
}

.modeSwitch {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.45rem;
}

.quickGps {
  display: flex;
  justify-content: flex-start;
}

.quickGpsBtn {
  width: fit-content;
}

.modeBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.75);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: var(--color-surface);
  padding: 0.5rem 0.6rem;
  font-size: 0.82rem;
  font-weight: 700;
}

.modeBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.85);
  background: rgb(var(--color-primary-rgb) / 0.2);
}

.fieldGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.advanced {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.48);
  border-radius: 0.9rem;
  padding: 0.7rem;
  background: rgb(var(--color-bg-rgb) / 0.2);
}

.advanced summary {
  cursor: pointer;
  font-weight: 700;
  color: var(--color-surface);
}

.advanced .fieldGrid,
.advanced .field {
  margin-top: 0.65rem;
}

.actions {
  display: flex;
  gap: 0.5rem;
  padding-top: 0.3rem;
  justify-content: flex-end;
}

.btn {
  padding: 0.6rem 0.95rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.85);
  background: rgb(var(--color-primary-rgb) / 0.16);
  color: var(--color-surface);
  font-weight: 800;
}

.btn:hover {
  background: rgb(var(--color-primary-rgb) / 0.24);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn.ghost {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--color-bg-rgb) / 0.2);
}

.msg {
  margin-bottom: 0.75rem;
  padding: 0.6rem 0.8rem;
  border-radius: 1rem;
  font-size: 0.93rem;
}

.msg.ok {
  border: 1px solid rgb(var(--color-success-rgb) / 0.45);
  background: rgb(var(--color-success-rgb) / 0.12);
  color: var(--color-success);
}

.msg.err {
  border: 1px solid rgb(var(--color-danger-rgb) / 0.45);
  background: rgb(var(--color-danger-rgb) / 0.12);
  color: var(--color-danger);
}

.muted {
  color: var(--color-text-secondary);
}

.err {
  color: var(--color-danger);
}

@media (max-width: 640px) {
  .fieldGrid,
  .modeSwitch {
    grid-template-columns: 1fr;
  }
}
</style>
