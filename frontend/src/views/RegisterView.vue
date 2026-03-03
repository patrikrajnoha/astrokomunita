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

        <div v-if="turnstileEnabled" class="field">
          <span class="label">Overenie proti botom</span>
          <div class="turnstileShell" :class="{ isError: turnstileState === 'error' || turnstileState === 'expired' }">
            <div ref="turnstileContainer"></div>
          </div>
          <span v-if="turnstileHint" class="fieldHint isError">{{ turnstileHint }}</span>
        </div>

        <p
          v-if="submitTurnstileMessage"
          class="submitHint"
          :class="{ isError: !turnstileEnabled, isMuted: turnstileEnabled }"
        >
          {{ submitTurnstileMessage }}
        </p>

        <button class="btn ui-pill ui-pill--primary ui-pill--full" type="submit" :disabled="isSubmitDisabled">
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const TURNSTILE_SCRIPT_ID = 'cf-turnstile-script'
const TURNSTILE_SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
let turnstileScriptPromise = null

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const toast = useToast()

const turnstileSiteKey = String(import.meta.env.VITE_TURNSTILE_SITE_KEY || '').trim()
const name = ref('')
const username = ref('')
const dateOfBirth = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const formError = ref(null)
const turnstileContainer = ref(null)
const turnstileToken = ref('')
const turnstileWidgetId = ref(null)
const turnstileState = ref(turnstileSiteKey ? 'idle' : 'disabled')

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
const turnstileEnabled = computed(() => turnstileSiteKey !== '')
const turnstileHint = computed(() => {
  if (turnstileState.value === 'error') return 'Overenie proti botom sa nepodarilo nacitat. Obnov stranku a skus to znova.'
  if (turnstileState.value === 'expired') return 'Overenie proti botom vyprsalo. Potvrd ho prosim znova.'
  return ''
})
const submitTurnstileMessage = computed(() => {
  if (!turnstileEnabled.value) return 'Bezpečnostné overenie nie je nastavené. Skús to prosím neskôr.'
  if (turnstileToken.value) return ''
  if (turnstileState.value === 'loading' || turnstileState.value === 'idle') return 'Načítavam overenie...'
  return ''
})
const isSubmitDisabled = computed(() => {
  if (auth.loading) return true
  if (!turnstileEnabled.value) return true
  return !turnstileToken.value
})

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

  if (turnstileWidgetId.value !== null && window.turnstile?.remove) {
    window.turnstile.remove(turnstileWidgetId.value)
  }
})

onMounted(() => {
  if (!turnstileEnabled.value) {
    return
  }

  void mountTurnstileWidget()
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

  if (!turnstileEnabled.value) {
    formError.value = 'Bezpečnostné overenie nie je nastavené. Skús to prosím neskôr.'
    return
  }

  if (turnstileEnabled.value && !turnstileToken.value) {
    formError.value = turnstileHint.value || 'Načítavam overenie...'
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
      turnstile_token: turnstileToken.value,
    })
    if (!auth.isAdmin && auth.user?.requires_email_verification && !auth.user?.email_verified_at) {
      try {
        if (typeof auth.csrf === 'function') {
          await auth.csrf()
        }

        await http.post(
          '/account/email/verification/send',
          {},
          { meta: { skipErrorToast: true } },
        )
        toast.success('Poslali sme ti overovaci kod.')
      } catch (sendError) {
        const sendStatus = Number(sendError?.response?.status || 0)
        const sendMessage = sendError?.response?.data?.message

        if (sendStatus === 429) {
          toast.warn(sendMessage || 'Overovaci kod bol odoslany nedavno. Dokonc overenie v Settings.')
        } else {
          toast.warn('Nepodarilo sa poslat overovaci kod automaticky. Pokracuj v Settings -> Email.')
        }
      }

      await router.push({ name: 'settings', query: { section: 'email', redirect: redirect.value } })
      return
    }
    await router.push(redirect.value)
  } catch (e) {
    const msg = e?.response?.data?.message
    const errors = e?.response?.data?.errors
    if (errors?.turnstile_token?.length) {
      formError.value = 'Bezpečnostné overenie zlyhalo. Skús to prosím znova.'
    } else if (errors) {
      const firstKey = Object.keys(errors)[0]
      formError.value = errors[firstKey]?.[0] || msg || 'Registracia zlyhala.'
    } else {
      formError.value = msg || 'Registracia zlyhala.'
    }

    if (turnstileWidgetId.value !== null && window.turnstile?.reset) {
      turnstileToken.value = ''
      turnstileState.value = 'idle'
      window.turnstile.reset(turnstileWidgetId.value)
    }
  }
}

async function mountTurnstileWidget() {
  if (!turnstileContainer.value) {
    return
  }

  turnstileState.value = 'loading'

  try {
    const api = await loadTurnstileApi()

    if (!turnstileContainer.value) {
      return
    }

    turnstileToken.value = ''

    if (turnstileWidgetId.value !== null && api.remove) {
      api.remove(turnstileWidgetId.value)
      turnstileWidgetId.value = null
    }

    turnstileWidgetId.value = api.render(turnstileContainer.value, {
      sitekey: turnstileSiteKey,
      theme: 'auto',
      callback: (token) => {
        turnstileToken.value = token
        turnstileState.value = 'ready'
      },
      'expired-callback': () => {
        turnstileToken.value = ''
        turnstileState.value = 'expired'
      },
      'error-callback': () => {
        turnstileToken.value = ''
        turnstileState.value = 'error'
      },
      'timeout-callback': () => {
        turnstileToken.value = ''
        turnstileState.value = 'expired'
      },
    })

  } catch {
    turnstileToken.value = ''
    turnstileState.value = 'error'
  }
}

function loadTurnstileApi() {
  if (window.turnstile?.render) {
    return Promise.resolve(window.turnstile)
  }

  if (turnstileScriptPromise) {
    return turnstileScriptPromise
  }

  turnstileScriptPromise = new Promise((resolve, reject) => {
    const existingScript = document.getElementById(TURNSTILE_SCRIPT_ID)
    if (existingScript) {
      existingScript.addEventListener('load', () => resolve(window.turnstile), { once: true })
      existingScript.addEventListener('error', reject, { once: true })
      return
    }

    const script = document.createElement('script')
    script.id = TURNSTILE_SCRIPT_ID
    script.src = TURNSTILE_SCRIPT_SRC
    script.async = true
    script.defer = true
    script.onload = () => {
      if (window.turnstile?.render) {
        resolve(window.turnstile)
        return
      }

      reject(new Error('Turnstile API unavailable'))
    }
    script.onerror = reject
    document.head.appendChild(script)
  }).catch((error) => {
    turnstileScriptPromise = null
    throw error
  })

  return turnstileScriptPromise
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
  background: transparent;
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
  color: var(--text-primary);
}

.registerSubtitle {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.86rem;
}

.card {
  border: 1px solid var(--border);
  background: var(--bg-surface);
  border-radius: 1rem;
  padding: 0.95rem;
  box-shadow: 0 14px 28px rgb(var(--bg-app-rgb) / 0.24);
  backdrop-filter: blur(6px);
  display: grid;
  gap: 0.68rem;
}

.field {
  display: grid;
  gap: 0.25rem;
}

.label {
  color: var(--text-secondary);
  font-size: 0.75rem;
  font-weight: 600;
}

.input {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border-radius: 0.72rem;
  border: 1px solid var(--border);
  background: rgb(var(--bg-app-rgb) / 0.42);
  color: var(--text-primary);
  outline: none;
  font-size: 0.92rem;
  transition: border-color 140ms ease, box-shadow 140ms ease, background-color 140ms ease;
}

.input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgb(var(--primary-rgb) / 0.2);
  background: rgb(var(--bg-app-rgb) / 0.5);
}

.dobPicker {
  border: 1px solid var(--border);
  border-radius: 0.72rem;
  background: rgb(var(--bg-app-rgb) / 0.52);
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
  color: var(--text-secondary);
}

.dobSelect {
  width: 100%;
  padding: 0.42rem 0.4rem;
  border-radius: 0.58rem;
  border: 1px solid var(--border);
  background: rgb(var(--bg-app-rgb) / 0.65);
  color: var(--text-primary);
  font-size: 0.88rem;
}


.fieldHint {
  font-size: 0.75rem;
}

.fieldHint.isMuted {
  color: var(--text-secondary);
}

.fieldHint.isSuccess {
  color: var(--primary);
}

.fieldHint.isError {
  color: var(--primary-active);
}

.turnstileShell {
  border: 1px solid var(--border);
  border-radius: 0.72rem;
  background: rgb(var(--bg-app-rgb) / 0.52);
  padding: 0.56rem;
  overflow-x: auto;
}

.turnstileShell.isError {
  border-color: var(--primary-active);
}

.btn {
  width: 100%;
  margin-top: 0.16rem;
}

.submitHint {
  margin: 0;
  font-size: 0.79rem;
}

.loginHint {
  margin: 0.05rem 0 0;
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-align: center;
}

.link {
  color: var(--primary);
  font-weight: 600;
}

.link:hover {
  color: var(--text-primary);
}

.alert {
  padding: 0.5rem 0.62rem;
  border-radius: 0.7rem;
  border: 1px solid var(--primary-active);
  background: rgb(var(--primary-active-rgb) / 0.12);
  color: var(--primary-active);
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
