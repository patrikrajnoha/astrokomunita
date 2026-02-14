<template>
  <div class="registerPage">
    <div class="registerShell">
      <header class="registerHeader">
        <h1 class="registerTitle">Registracia</h1>
        <p class="registerSubtitle">Vytvor si ucet.</p>
      </header>

      <form class="card" @submit.prevent="submit">
        <div v-if="formError" class="alert">
          {{ formError }}
        </div>

        <label class="field">
          <span class="label">Meno</span>
          <input v-model.trim="name" type="text" class="input" autocomplete="name" />
        </label>

        <label class="field">
          <span class="label">Pouzivatelske meno</span>
          <input v-model="username" type="text" class="input" autocomplete="username" maxlength="20" />
          <span v-if="usernameHint" class="fieldHint" :class="usernameHintClass">{{ usernameHint }}</span>
        </label>

        <label class="field">
          <span class="label">Datum narodenia</span>
          <div class="dobPicker">
            <div class="dobPickerGrid">
              <label class="dobPickerField">
                <span>Den</span>
                <select v-model.number="dobDraftDay" class="dobSelect">
                  <option v-for="d in dayOptions" :key="d" :value="d">{{ d }}</option>
                </select>
              </label>
              <label class="dobPickerField">
                <span>Mesiac</span>
                <select v-model.number="dobDraftMonth" class="dobSelect">
                  <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
              </label>
              <label class="dobPickerField">
                <span>Rok</span>
                <select v-model.number="dobDraftYear" class="dobSelect">
                  <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                </select>
              </label>
            </div>
            <p v-if="dobDraftTooYoung" class="fieldHint isError">Musis mat aspon 13 rokov.</p>
          </div>
          <span v-if="dateOfBirthError" class="fieldHint isError">{{ dateOfBirthError }}</span>
        </label>

        <label class="field">
          <span class="label">Email</span>
          <input v-model.trim="email" type="email" class="input" autocomplete="email" />
        </label>

        <label class="field">
          <span class="label">Heslo</span>
          <input v-model="password" type="password" class="input" autocomplete="new-password" />
        </label>

        <label class="field">
          <span class="label">Potvrdenie hesla</span>
          <input v-model="passwordConfirmation" type="password" class="input" autocomplete="new-password" />
        </label>

        <button class="btn" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Registrujem...' : 'Zaregistrovat' }}
        </button>

        <p class="loginHint">
          Uz mas ucet?
          <router-link class="link" :to="loginLink">Prihlas sa</router-link>
        </p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const name = ref('')
const username = ref('')
const dateOfBirth = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const formError = ref(null)

const usernameCheckState = ref('idle')
const usernameReason = ref('')
let usernameCheckTimer = null
let usernameCheckRequestId = 0
const dobDraftDay = ref(1)
const dobDraftMonth = ref(1)
const dobDraftYear = ref(2000)

const redirect = computed(() => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/') ? r : '/'
})

const loginLink = computed(() => ({
  name: 'login',
  query: { redirect: redirect.value },
}))

const maxDateOfBirth = computed(() => {
  const d = new Date()
  d.setFullYear(d.getFullYear() - 13)
  return formatDateForInput(d)
})
const maxDobDateObj = computed(() => new Date(`${maxDateOfBirth.value}T00:00:00`))
const minDobYear = 1900
const maxDobYear = computed(() => maxDobDateObj.value.getFullYear())
const yearOptions = computed(() => {
  const years = []
  for (let y = maxDobYear.value; y >= minDobYear; y -= 1) years.push(y)
  return years
})
const monthOptions = [
  { value: 1, label: 'Januar' },
  { value: 2, label: 'Februar' },
  { value: 3, label: 'Marec' },
  { value: 4, label: 'April' },
  { value: 5, label: 'Maj' },
  { value: 6, label: 'Jun' },
  { value: 7, label: 'Jul' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'Oktober' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]
const dayOptions = computed(() => {
  const days = daysInMonth(dobDraftYear.value, dobDraftMonth.value)
  return Array.from({ length: days }, (_, i) => i + 1)
})
const dobDraftIso = computed(() => formatDateForInput(new Date(dobDraftYear.value, dobDraftMonth.value - 1, dobDraftDay.value)))
const dobDraftTooYoung = computed(() => dobDraftIso.value > maxDateOfBirth.value)

const dateOfBirthError = computed(() => validateDateOfBirth(dateOfBirth.value))

const usernameHint = computed(() => {
  const clientError = validateUsername(username.value)

  if (!username.value.trim()) return ''
  if (clientError) return clientError

  if (usernameCheckState.value === 'checking') return 'Kontrolujem...'
  if (usernameCheckReasonToMessage(usernameReason.value)) {
    return usernameCheckReasonToMessage(usernameReason.value)
  }

  return ''
})

const usernameHintClass = computed(() => {
  if (usernameCheckState.value === 'checking') return 'isMuted'
  if (usernameReason.value === 'ok') return 'isSuccess'
  return 'isError'
})

watch(
  () => username.value,
  (nextValue) => {
    if (usernameCheckTimer) {
      clearTimeout(usernameCheckTimer)
      usernameCheckTimer = null
    }

    const normalized = normalizeUsername(nextValue)

    if (!normalized) {
      usernameCheckState.value = 'idle'
      usernameReason.value = ''
      return
    }

    const clientError = validateUsername(normalized)
    if (clientError) {
      usernameCheckState.value = 'idle'
      usernameReason.value = 'invalid'
      return
    }

    usernameCheckState.value = 'checking'

    usernameCheckTimer = setTimeout(async () => {
      const requestId = ++usernameCheckRequestId

      try {
        const { data } = await http.get('/auth/username-available', {
          params: { username: normalized },
        })

        if (requestId !== usernameCheckRequestId) return

        usernameCheckState.value = 'done'
        usernameReason.value = data?.reason || 'invalid'
      } catch {
        if (requestId !== usernameCheckRequestId) return

        usernameCheckState.value = 'idle'
        usernameReason.value = 'invalid'
      }
    }, 400)
  }
)

onBeforeUnmount(() => {
  if (usernameCheckTimer) clearTimeout(usernameCheckTimer)
})

watch(
  () => [dobDraftYear.value, dobDraftMonth.value],
  () => {
    const maxDay = daysInMonth(dobDraftYear.value, dobDraftMonth.value)
    if (dobDraftDay.value > maxDay) dobDraftDay.value = maxDay
  }
)

watch(
  () => [dobDraftDay.value, dobDraftMonth.value, dobDraftYear.value],
  () => {
    if (!dobDraftTooYoung.value) {
      dateOfBirth.value = dobDraftIso.value
    }
  },
  { immediate: true }
)

const submit = async () => {
  formError.value = null

  const usernameError = validateUsername(username.value)
  if (usernameError) {
    formError.value = usernameError
    return
  }

  const dobError = validateDateOfBirth(dateOfBirth.value)
  if (dobError) {
    formError.value = dobError
    return
  }

  if (usernameReason.value === 'taken' || usernameReason.value === 'reserved' || usernameReason.value === 'invalid') {
    formError.value = usernameCheckReasonToMessage(usernameReason.value)
    return
  }

  try {
    await auth.register({
      name: name.value,
      email: email.value,
      username: normalizeUsername(username.value),
      date_of_birth: dateOfBirth.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await router.push(redirect.value)
  } catch (e) {
    const msg = e?.response?.data?.message
    const errors = e?.response?.data?.errors
    if (errors) {
      const firstKey = Object.keys(errors)[0]
      formError.value = errors[firstKey]?.[0] || msg || 'Registracia zlyhala.'
    } else {
      formError.value = msg || 'Registracia zlyhala.'
    }
  }
}

function normalizeUsername(value) {
  return String(value || '').trim().toLowerCase()
}

function validateUsername(value) {
  const normalized = normalizeUsername(value)

  if (!normalized) return 'Pouzivatelske meno je povinne.'
  if (normalized.length < 3 || normalized.length > 20) return 'Pouzivatelske meno musi mat 3 az 20 znakov.'
  if (!/^[a-z]/.test(normalized)) return 'Pouzivatelske meno musi zacinat pismenom.'
  if (!/^[a-z][a-z0-9_]*$/.test(normalized)) return 'Pouzivatelske meno moze obsahovat iba male pismena, cisla a podciarkovnik.'
  if (normalized.includes('__')) return 'Pouzivatelske meno nemoze obsahovat dvojite podciarkovniky.'

  return ''
}

function validateDateOfBirth(value) {
  if (!value) return 'Datum narodenia je povinny.'
  if (value > maxDateOfBirth.value) return 'Musis mat aspon 13 rokov.'
  return ''
}

function usernameCheckReasonToMessage(reason) {
  if (reason === 'ok') return 'Pouzivatelske meno je volne.'
  if (reason === 'taken') return 'Toto pouzivatelske meno je uz obsadene.'
  if (reason === 'reserved') return 'Toto pouzivatelske meno nie je povolene.'
  if (reason === 'invalid') return 'Pouzivatelske meno ma neplatny format.'
  return ''
}

function formatDateForInput(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function daysInMonth(year, month) {
  return new Date(year, month, 0).getDate()
}
</script>

<style scoped>
.registerPage {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 1rem;
  background:
    radial-gradient(1000px 500px at 10% -10%, rgb(var(--color-primary-rgb) / 0.16), transparent 60%),
    radial-gradient(900px 440px at 100% 120%, rgb(var(--color-success-rgb) / 0.1), transparent 65%);
}

.registerShell {
  width: min(100%, 408px);
  display: grid;
  gap: 0.7rem;
}

.registerHeader {
  text-align: center;
  display: grid;
  gap: 0.35rem;
}

.registerTitle {
  margin: 0;
  font-size: clamp(1.35rem, 2vw, 1.7rem);
  font-weight: 700;
  color: var(--color-surface);
}

.registerSubtitle {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.86rem;
}

.card {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: linear-gradient(180deg, rgb(var(--color-bg-rgb) / 0.8), rgb(var(--color-bg-rgb) / 0.64));
  border-radius: 0.9rem;
  padding: 0.95rem;
  box-shadow: 0 14px 28px rgb(var(--color-bg-rgb) / 0.3);
  backdrop-filter: blur(6px);
  display: grid;
  gap: 0.68rem;
}

.field {
  display: grid;
  gap: 0.25rem;
}

.label {
  color: var(--color-text-secondary);
  font-size: 0.75rem;
  font-weight: 600;
}

.input {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  background: rgb(var(--color-bg-rgb) / 0.42);
  color: var(--color-surface);
  outline: none;
  font-size: 0.92rem;
  transition: border-color 140ms ease, box-shadow 140ms ease, background-color 140ms ease;
}

.input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
  background: rgb(var(--color-bg-rgb) / 0.5);
}

.dobPicker {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  border-radius: 0.72rem;
  background: rgb(var(--color-bg-rgb) / 0.52);
  padding: 0.56rem;
  display: grid;
  gap: 0.4rem;
}

.dobPickerGrid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.36rem;
}

.dobPickerField {
  display: grid;
  gap: 0.2rem;
  font-size: 0.7rem;
  color: var(--color-text-secondary);
}

.dobSelect {
  width: 100%;
  padding: 0.42rem 0.4rem;
  border-radius: 0.58rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  background: rgb(var(--color-bg-rgb) / 0.65);
  color: var(--color-surface);
  font-size: 0.88rem;
}


.fieldHint {
  font-size: 0.75rem;
}

.fieldHint.isMuted {
  color: var(--color-text-secondary);
}

.fieldHint.isSuccess {
  color: var(--color-success);
}

.fieldHint.isError {
  color: var(--color-danger);
}

.btn {
  width: 100%;
  margin-top: 0.16rem;
  padding: 0.64rem 0.9rem;
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: linear-gradient(180deg, rgb(var(--color-primary-rgb) / 0.24), rgb(var(--color-primary-rgb) / 0.15));
  color: var(--color-surface);
  font-weight: 600;
  font-size: 0.9rem;
  transition: transform 120ms ease, background-color 120ms ease;
}

.btn:hover {
  background: linear-gradient(180deg, rgb(var(--color-primary-rgb) / 0.3), rgb(var(--color-primary-rgb) / 0.2));
}

.btn:active {
  transform: translateY(1px);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.loginHint {
  margin: 0.05rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.8rem;
  text-align: center;
}

.link {
  color: var(--color-primary);
  font-weight: 600;
}

.link:hover {
  color: var(--color-surface);
}

.alert {
  padding: 0.5rem 0.62rem;
  border-radius: 0.7rem;
  border: 1px solid rgb(var(--color-danger-rgb) / 0.35);
  background: rgb(var(--color-danger-rgb) / 0.12);
  color: var(--color-danger);
  font-size: 0.79rem;
}

@media (max-width: 480px) {
  .registerPage {
    padding: 0.55rem;
  }

  .card {
    padding: 0.82rem;
    border-radius: 0.8rem;
  }

  .dobPickerGrid {
    grid-template-columns: 1fr;
  }
}
</style>
