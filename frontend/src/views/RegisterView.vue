<template src="./register/RegisterView.template.html"></template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue'
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
const stepItems = [
  { id: 1, label: 'Profil', subtitle: 'Zakladne informacie' },
  { id: 2, label: 'Konto', subtitle: 'Prihlasovacie udaje' },
  { id: 3, label: 'Overenie', subtitle: 'Finalne potvrdenie' },
]
const currentStep = ref(1)
const stars = createStars(80)

const redirect = computed(() => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/') ? r : '/'
})

const loginLink = computed(() => ({
  name: 'login',
  query: { redirect: redirect.value },
}))
const currentStepMeta = computed(() => stepItems[currentStep.value - 1] || stepItems[0])
const isLastStep = computed(() => currentStep.value === stepItems.length)
const stepProgress = computed(() => (currentStep.value / stepItems.length) * 100)
const turnstileEnabled = computed(() => turnstileSiteKey !== '')
const turnstileHint = computed(() => {
  if (turnstileState.value === 'error') return 'Overenie proti botom sa nepodarilo nacitat. Obnov stranku a skus to znova.'
  if (turnstileState.value === 'expired') return 'Overenie proti botom vyprsalo. Potvrd ho prosim znova.'
  return ''
})
const submitTurnstileMessage = computed(() => {
  if (!turnstileEnabled.value) return 'Bezpecnostne overenie nie je nastavene. Skus to prosim neskor.'
  if (turnstileToken.value) return ''
  if (turnstileState.value === 'loading' || turnstileState.value === 'idle') return 'Nacitavam overenie...'
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
  if (!turnstileEnabled.value || currentStep.value !== stepItems.length) {
    return
  }

  void mountTurnstileWidget()
})

watch(
  () => currentStep.value,
  async (nextStep) => {
    formError.value = null

    if (nextStep === stepItems.length && turnstileEnabled.value) {
      await nextTick()
      void mountTurnstileWidget()
    }
  }
)

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

function goToPreviousStep() {
  if (currentStep.value <= 1) {
    return
  }

  currentStep.value -= 1
}

function goToNextStep() {
  formError.value = null

  const currentStepError = validateCurrentStep(currentStep.value)
  if (currentStepError) {
    formError.value = currentStepError
    return
  }

  if (currentStep.value >= stepItems.length) {
    return
  }

  currentStep.value += 1
}

function validateCurrentStep(step) {
  if (step === 1) return validateStepOne()
  if (step === 2) return validateStepTwo()
  if (step === 3) return validateStepThree()
  return ''
}

function validateStepOne() {
  if (!name.value.trim()) {
    return 'Meno je povinne.'
  }

  const usernameError = validateUsername(username.value)
  if (usernameError) {
    return usernameError
  }

  const dobError = validateDateOfBirth(dateOfBirth.value)
  if (dobError) {
    return dobError
  }

  if (usernameCheckState.value === 'checking') {
    return 'Pockaj, kontrolujem dostupnost pouzivatelskeho mena.'
  }

  if (usernameReason.value === 'taken' || usernameReason.value === 'reserved' || usernameReason.value === 'invalid') {
    return usernameCheckReasonToMessage(usernameReason.value)
  }

  return ''
}

function validateStepTwo() {
  const emailError = validateEmail(email.value)
  if (emailError) {
    return emailError
  }

  if (!password.value) {
    return 'Heslo je povinne.'
  }

  if (password.value.length < 8) {
    return 'Heslo musi mat aspon 8 znakov.'
  }

  if (!passwordConfirmation.value) {
    return 'Potvrdenie hesla je povinne.'
  }

  if (password.value !== passwordConfirmation.value) {
    return 'Hesla sa nezhoduju.'
  }

  return ''
}

function validateStepThree() {
  if (!turnstileEnabled.value) {
    return 'Bezpecnostne overenie nie je nastavene. Skus to prosim neskor.'
  }

  if (turnstileEnabled.value && !turnstileToken.value) {
    return turnstileHint.value || 'Nacitavam overenie...'
  }

  return ''
}

const submit = async () => {
  formError.value = null

  if (!isLastStep.value) {
    goToNextStep()
    return
  }

  const registerError = validateStepOne() || validateStepTwo() || validateStepThree()
  if (registerError) {
    formError.value = registerError
    return
  }

  try {
    await auth.register({
      name: name.value.trim(),
      email: email.value.trim(),
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
          toast.warn(sendMessage || 'Overovaci kod bol odoslany nedavno. Dokonc overenie v Nastaveniach.')
        } else {
          toast.warn('Nepodarilo sa poslat overovaci kod automaticky. Pokracuj v Nastaveniach > Email.')
        }
      }

      await router.push({ name: 'settings.email', query: { redirect: redirect.value } })
      return
    }
    await router.push(redirect.value)
  } catch (e) {
    const msg = e?.response?.data?.message
    const errors = e?.response?.data?.errors
    if (errors?.turnstile_token?.length) {
      formError.value = 'Bezpecnostne overenie zlyhalo. Skus to prosim znova.'
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

function validateEmail(value) {
  const normalized = String(value || '').trim()
  if (!normalized) return 'E-mail je povinny.'
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized)) return 'E-mail ma neplatny format.'
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

function seededRandom(seed) {
  const value = Math.sin(seed * 9999.91) * 10000
  return value - Math.floor(value)
}

function createStars(count) {
  const generatedStars = []

  for (let i = 1; i <= count; i += 1) {
    const x = seededRandom(i * 1.37)
    const y = seededRandom(i * 2.17)
    const size = [1, 2, 3, 4][Math.floor(seededRandom(i * 3.31) * 4)]
    const delay = -(seededRandom(i * 4.13) * 4)

    generatedStars.push({
      id: i,
      style: {
        left: `${(x * 100).toFixed(2)}%`,
        top: `${(y * 100).toFixed(2)}%`,
        '--star-size': `${size}px`,
        '--blink-delay': `${delay.toFixed(2)}s`,
      },
    })
  }

  return generatedStars
}
</script>

<style scoped src="./register/RegisterView.css"></style>
