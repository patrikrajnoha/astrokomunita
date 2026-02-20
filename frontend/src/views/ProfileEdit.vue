<template>
  <div class="wrap">
    <header class="top">
      <button class="iconBtn" @click="back">←</button>
      <div>
        <div class="title">Upravit profil</div>
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

          <div id="location" class="field">
            <label>Poloha (label)</label>
            <select class="input" v-model="form.location">
              <option value="">— Vyber polohu —</option>
              <option v-for="x in locations" :key="x" :value="x">{{ x }}</option>
            </select>
            <p v-if="fieldErr.location" class="fieldErr">{{ fieldErr.location }}</p>
          </div>

          <div class="fieldGrid">
            <div class="field">
              <label>Latitude</label>
              <input class="input" v-model="form.latitude" type="number" step="0.0000001" />
              <p v-if="fieldErr.latitude" class="fieldErr">{{ fieldErr.latitude }}</p>
            </div>
            <div class="field">
              <label>Longitude</label>
              <input class="input" v-model="form.longitude" type="number" step="0.0000001" />
              <p v-if="fieldErr.longitude" class="fieldErr">{{ fieldErr.longitude }}</p>
            </div>
          </div>

          <div class="field">
            <label>Timezone</label>
            <input class="input" v-model.trim="form.timezone" type="text" placeholder="Europe/Bratislava" />
            <p v-if="fieldErr.timezone" class="fieldErr">{{ fieldErr.timezone }}</p>
          </div>

          <div class="actions left">
            <button class="btn ghost" type="button" @click="useMyLocation" :disabled="saving || geolocating">
              {{ geolocating ? 'Ziskavam GPS...' : 'Pouzit moju polohu' }}
            </button>
          </div>

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
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'

const router = useRouter()
const auth = useAuthStore()

const locations = [
  'Bratislava', 'Kosice', 'Presov', 'Zilina', 'Nitra', 'Banska Bystrica', 'Trnava', 'Trencin',
  'Slovensko (ine)', 'Cesko', 'Europa', 'Mimo Europy',
]

const form = reactive({
  name: '',
  email: '',
  bio: '',
  location: '',
  latitude: '',
  longitude: '',
  timezone: '',
})

const saving = ref(false)
const geolocating = ref(false)
const msg = ref('')
const err = ref('')

const fieldErr = reactive({
  name: '',
  email: '',
  bio: '',
  location: '',
  latitude: '',
  longitude: '',
  timezone: '',
})

function back() {
  router.push({ name: 'profile' })
}

function clearErrors() {
  err.value = ''
  msg.value = ''
  fieldErr.name = ''
  fieldErr.email = ''
  fieldErr.bio = ''
  fieldErr.location = ''
  fieldErr.latitude = ''
  fieldErr.longitude = ''
  fieldErr.timezone = ''
}

function resetForm() {
  if (!auth.user) return
  clearErrors()
  form.name = auth.user.name || ''
  form.email = auth.user.email || ''
  form.bio = auth.user.bio || ''
  form.location = auth.user.location || ''

  const meta = auth.user.location_meta || null
  const lat = Number(meta?.lat ?? auth.user.latitude)
  const lon = Number(meta?.lon ?? auth.user.longitude)
  form.latitude = Number.isFinite(lat) ? String(lat) : ''
  form.longitude = Number.isFinite(lon) ? String(lon) : ''
  form.timezone = String(meta?.tz || auth.user.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC')
}

function extractFirstError(errorsObj, field) {
  const v = errorsObj?.[field]
  return Array.isArray(v) && v.length ? String(v[0]) : ''
}

function parseCoordinate(value) {
  if (typeof value !== 'string' || value.trim() === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

function hasPreciseLocation() {
  return parseCoordinate(form.latitude) !== null && parseCoordinate(form.longitude) !== null
}

async function save() {
  clearErrors()
  saving.value = true

  try {
    await auth.csrf()

    const profileResponse = await http.patch('/profile', {
      name: form.name,
      email: form.email,
      bio: form.bio,
      location: form.location,
    })

    let userPayload = profileResponse?.data || null

    if (hasPreciseLocation()) {
      const locationResponse = await http.put('/me/location', {
        latitude: parseCoordinate(form.latitude),
        longitude: parseCoordinate(form.longitude),
        timezone: form.timezone || null,
        location: form.location || null,
      })
      userPayload = locationResponse?.data || userPayload
    }

    auth.user = userPayload
    msg.value = 'Profil ulozeny.'
  } catch (e) {
    const status = e?.response?.status
    const data = e?.response?.data

    if (status === 422 && data?.errors) {
      fieldErr.name = extractFirstError(data.errors, 'name')
      fieldErr.email = extractFirstError(data.errors, 'email')
      fieldErr.bio = extractFirstError(data.errors, 'bio')
      fieldErr.location = extractFirstError(data.errors, 'location')
      fieldErr.latitude = extractFirstError(data.errors, 'latitude')
      fieldErr.longitude = extractFirstError(data.errors, 'longitude')
      fieldErr.timezone = extractFirstError(data.errors, 'timezone')

      err.value =
        fieldErr.name ||
        fieldErr.email ||
        fieldErr.bio ||
        fieldErr.location ||
        fieldErr.latitude ||
        fieldErr.longitude ||
        fieldErr.timezone ||
        'Skontroluj oznacene polia.'
    } else {
      err.value = data?.message || 'Ulozenie zlyhalo.'
    }
  } finally {
    saving.value = false
  }
}

async function useMyLocation() {
  clearErrors()
  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    err.value = 'Geolokacia nie je podporovana v tomto prehliadaci.'
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
      err.value = 'Nepodarilo sa ziskat suradnice.'
      return
    }

    form.latitude = lat.toFixed(7)
    form.longitude = lon.toFixed(7)
    if (!form.timezone) {
      form.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
    }
  } catch {
    err.value = 'Nepodarilo sa ziskat GPS polohu.'
  } finally {
    geolocating.value = false
  }
}

onMounted(async () => {
  if (!auth.initialized) await auth.fetchUser()
  resetForm()
})
</script>

<style scoped>
.wrap {
  max-width: 720px;
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

.title { font-weight: 950; color: var(--color-surface); }
.subtitle { color: var(--color-text-secondary); font-size: 0.9rem; }

.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.85);
  background: rgb(var(--color-bg-rgb) / 0.55);
  border-radius: 1.25rem;
  padding: 1.1rem;
}

.form { margin-top: 0.75rem; display: grid; gap: 0.9rem; }

.field label {
  display: block;
  font-size: 0.8rem;
  color: var(--color-surface);
  margin-bottom: 0.35rem;
}

.fieldGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem;
}

.input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  outline: none;
}

.input:focus { border-color: rgb(var(--color-primary-rgb) / 0.9); }
.textarea { resize: vertical; }

.hint {
  margin-top: 0.35rem;
  color: var(--color-text-secondary);
  font-size: 0.85rem;
  text-align: right;
}

.fieldErr {
  margin-top: 0.35rem;
  font-size: 0.85rem;
  color: var(--color-danger);
}

.actions {
  display: flex;
  gap: 0.5rem;
  padding-top: 0.25rem;
  justify-content: flex-end;
}

.actions.left {
  justify-content: flex-start;
}

.btn {
  padding: 0.6rem 0.95rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.85);
  background: rgb(var(--color-primary-rgb) / 0.15);
  color: var(--color-surface);
  font-weight: 800;
}

.btn:hover { background: rgb(var(--color-primary-rgb) / 0.25); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn.ghost {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.95);
  background: rgb(var(--color-bg-rgb) / 0.2);
  color: var(--color-surface);
}

.btn.ghost:hover { border-color: rgb(var(--color-primary-rgb) / 0.85); color: var(--color-surface); }

.msg { margin-bottom: 0.75rem; padding: 0.6rem 0.8rem; border-radius: 1rem; font-size: 0.95rem; }
.msg.ok { border: 1px solid rgb(var(--color-success-rgb) / 0.45); background: rgb(var(--color-success-rgb) / 0.1); color: var(--color-success); }
.msg.err { border: 1px solid rgb(var(--color-danger-rgb) / 0.45); background: rgb(var(--color-danger-rgb) / 0.1); color: var(--color-danger); }

.muted { color: var(--color-text-secondary); }
.err { color: var(--color-danger); }
</style>
