<template>
  <teleport to="body">
    <transition name="email-gate-fade">
      <div v-if="open" class="emailGateBackdrop" data-testid="email-verification-gate">
        <section
          ref="cardRef"
          class="emailGateCard"
          role="dialog"
          aria-modal="true"
          aria-labelledby="email-gate-title"
          tabindex="-1"
        >
          <p class="emailGateEyebrow">Povinne overenie</p>
          <h2 id="email-gate-title" class="emailGateTitle">Over svoj e-mail pred pokracovanim</h2>
          <p class="emailGateCopy">
            Posleme overovaci kod na
            <strong>{{ account.email || 'vas e-mail' }}</strong>
            . Kym kod nepotvrdite, aplikaciu nie je mozne pouzivat.
          </p>

          <div v-if="state.success" class="emailGateAlert isSuccess" role="status">
            {{ state.success }}
          </div>
          <div v-if="state.error" class="emailGateAlert isError" role="alert">
            {{ state.error }}
          </div>

          <button
            type="button"
            class="emailGateBtn emailGateBtnPrimary"
            :disabled="state.loading || state.sending || state.confirming || account.secondsToResend > 0"
            @click="sendCode"
          >
            {{ sendButtonLabel }}
          </button>

          <label class="emailGateLabel" for="email-gate-code">Overovaci kod</label>
          <input
            id="email-gate-code"
            v-model.trim="form.code"
            class="emailGateInput"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            placeholder="12345-67890"
            :disabled="state.loading || state.confirming"
          />
          <p v-if="state.fieldError" class="emailGateFieldError">{{ state.fieldError }}</p>

          <button
            type="button"
            class="emailGateBtn emailGateBtnConfirm"
            :disabled="state.loading || state.sending || state.confirming || !form.code"
            @click="confirmCode"
          >
            {{ state.confirming ? 'Potvrdzujem...' : 'Potvrdit kod' }}
          </button>
        </section>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['verified'])

const auth = useAuthStore()
const cardRef = ref(null)

const form = reactive({
  code: '',
})

const state = reactive({
  loading: false,
  sending: false,
  confirming: false,
  success: '',
  error: '',
  fieldError: '',
})

const account = reactive({
  email: '',
  verified: false,
  emailVerifiedAt: null,
  secondsToResend: 0,
})

let resendCountdownInterval = null
let previousBodyOverflow = ''
let previousActiveElement = null
let initialSendTriggered = false

const sendButtonLabel = computed(() => {
  if (state.loading) return 'Nacitavam...'
  if (state.sending) return 'Odosielam...'
  if (account.secondsToResend > 0) return `Opakovat o ${account.secondsToResend}s`
  return 'Poslat overovaci email'
})

function extractFirstError(errorsObject, field) {
  const value = errorsObject?.[field]
  return Array.isArray(value) && value.length > 0 ? String(value[0]) : ''
}

function resetFeedback() {
  state.success = ''
  state.error = ''
  state.fieldError = ''
}

function clearResendCountdown() {
  if (resendCountdownInterval !== null) {
    clearInterval(resendCountdownInterval)
    resendCountdownInterval = null
  }
}

function startResendCountdown() {
  clearResendCountdown()

  if (Number(account.secondsToResend) <= 0) {
    return
  }

  resendCountdownInterval = setInterval(() => {
    if (account.secondsToResend > 0) {
      account.secondsToResend -= 1
    }

    if (account.secondsToResend <= 0) {
      clearResendCountdown()
    }
  }, 1000)
}

function applyStatus(payload = {}) {
  account.email = String(payload?.email || auth.user?.email || '')
  account.verified = Boolean(payload?.verified ?? payload?.email_verified_at)
  account.emailVerifiedAt = payload?.email_verified_at || null
  account.secondsToResend = Math.max(0, Number(payload?.seconds_to_resend || 0))

  if (auth.user) {
    auth.user = {
      ...auth.user,
      email: account.email || auth.user.email,
      email_verified_at: account.emailVerifiedAt,
    }
  }

  startResendCountdown()
}

async function loadStatus() {
  if (!auth.user) return

  state.loading = true
  try {
    const response = await http.get('/account/email', {
      meta: { skipErrorToast: true },
    })
    applyStatus(response?.data?.data || {})
  } catch {
    applyStatus()
  } finally {
    state.loading = false
  }
}

function resolveRequestError(error, fallbackMessage) {
  const data = error?.response?.data
  const status = Number(error?.response?.status || 0)
  const fieldError = extractFirstError(data?.errors, 'code')

  if (status === 422 && fieldError) {
    return {
      message: data?.message || fallbackMessage,
      fieldError,
    }
  }

  return {
    message: data?.message || fallbackMessage,
    fieldError: fieldError || '',
  }
}

async function sendCode() {
  if (!auth.user || state.loading || state.sending || state.confirming) return

  resetFeedback()
  state.sending = true

  try {
    await auth.csrf()
    const response = await http.post('/account/email/verification/send', {})
    applyStatus(response?.data?.data || {})
    state.success = response?.data?.message || 'Overovaci kod bol odoslany.'
    initialSendTriggered = true
  } catch (error) {
    const resolved = resolveRequestError(error, 'Nepodarilo sa odoslat overovaci kod.')
    state.error = resolved.message
    state.fieldError = resolved.fieldError
  } finally {
    state.sending = false
  }
}

async function confirmCode() {
  if (!auth.user || state.loading || state.sending || state.confirming) return

  resetFeedback()

  if (!form.code) {
    state.fieldError = 'Overovaci kod je povinny.'
    return
  }

  state.confirming = true

  try {
    await auth.csrf()
    const response = await http.post('/account/email/verification/confirm', {
      code: form.code,
    })

    applyStatus(response?.data?.data || {})
    form.code = ''
    state.success = response?.data?.message || 'E-mail bol uspesne overeny.'

    if (account.verified || account.emailVerifiedAt) {
      emit('verified')
    }
  } catch (error) {
    const resolved = resolveRequestError(error, 'Nepodarilo sa overit kod.')
    state.error = resolved.message
    state.fieldError = resolved.fieldError
  } finally {
    state.confirming = false
  }
}

function getFocusableNodes() {
  if (!cardRef.value) return []

  const selector = [
    'button:not([disabled])',
    '[href]',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(',')

  return Array.from(cardRef.value.querySelectorAll(selector))
}

function handleOpenKeydown(event) {
  if (!props.open) return

  if (event.key === 'Escape') {
    event.preventDefault()
    return
  }

  if (event.key !== 'Tab') return

  const nodes = getFocusableNodes()
  if (!nodes.length) return

  const first = nodes[0]
  const last = nodes[nodes.length - 1]
  const active = document.activeElement

  if (event.shiftKey) {
    if (active === first || !cardRef.value?.contains(active)) {
      event.preventDefault()
      last.focus()
    }
    return
  }

  if (active === last) {
    event.preventDefault()
    first.focus()
  }
}

function restoreFocusAndScrollState() {
  if (typeof document === 'undefined') return

  document.removeEventListener('keydown', handleOpenKeydown)
  document.body.style.overflow = previousBodyOverflow

  if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
    previousActiveElement.focus()
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) {
      form.code = ''
      resetFeedback()
      restoreFocusAndScrollState()
      return
    }

    if (typeof document !== 'undefined') {
      previousBodyOverflow = document.body.style.overflow
      previousActiveElement = document.activeElement
      document.body.style.overflow = 'hidden'
      document.addEventListener('keydown', handleOpenKeydown)
    }

    await loadStatus()

    if (!account.verified && !initialSendTriggered && account.email && account.secondsToResend <= 0) {
      await sendCode()
    }

    await nextTick()
    const [firstFocusable] = getFocusableNodes()
    if (firstFocusable && typeof firstFocusable.focus === 'function') {
      firstFocusable.focus()
      return
    }

    if (cardRef.value && typeof cardRef.value.focus === 'function') {
      cardRef.value.focus()
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  clearResendCountdown()
  restoreFocusAndScrollState()
})
</script>

<style scoped>
.emailGateBackdrop {
  position: fixed;
  inset: 0;
  z-index: 1400;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(1 6 15 / 0.82);
  backdrop-filter: blur(3px);
}

.emailGateCard {
  width: min(460px, 100%);
  border-radius: 1rem;
  border: 1px solid rgb(var(--border-rgb) / 0.58);
  background:
    linear-gradient(170deg, rgb(var(--bg-surface-rgb) / 0.98) 0%, rgb(var(--bg-surface-2-rgb) / 0.98) 100%);
  box-shadow: 0 30px 80px rgb(2 10 24 / 0.52);
  padding: 1rem;
  color: var(--text-primary);
}

.emailGateEyebrow {
  margin: 0;
  font-size: 0.74rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgb(var(--color-primary-rgb) / 0.86);
  font-weight: 700;
}

.emailGateTitle {
  margin: 0.28rem 0 0;
  font-size: 1.18rem;
  line-height: 1.25;
  font-weight: 780;
  color: rgb(var(--text-primary-rgb));
}

.emailGateCopy {
  margin: 0.7rem 0 0;
  font-size: 0.93rem;
  line-height: 1.5;
  color: rgb(var(--text-primary-rgb) / 0.86);
}

.emailGateCopy strong {
  color: rgb(var(--text-primary-rgb));
  font-weight: 700;
}

.emailGateAlert {
  margin-top: 0.75rem;
  border-radius: 0.65rem;
  border: 1px solid;
  padding: 0.54rem 0.62rem;
  font-size: 0.84rem;
  line-height: 1.35;
}

.emailGateAlert.isSuccess {
  border-color: rgb(var(--color-success-rgb) / 0.6);
  background: rgb(var(--color-success-rgb) / 0.12);
  color: rgb(var(--color-success-rgb));
}

.emailGateAlert.isError {
  border-color: rgb(var(--color-danger-rgb) / 0.68);
  background: rgb(var(--color-danger-rgb) / 0.13);
  color: rgb(var(--color-danger-rgb));
}

.emailGateBtn {
  width: 100%;
  min-height: 2.45rem;
  margin-top: 0.72rem;
  border-radius: 0.7rem;
  font-size: 0.9rem;
  font-weight: 700;
  transition: transform 120ms ease, box-shadow 120ms ease, opacity 120ms ease;
}

.emailGateBtn:hover:not(:disabled) {
  transform: translateY(-1px);
}

.emailGateBtn:disabled {
  opacity: 0.68;
  cursor: not-allowed;
}

.emailGateBtnPrimary,
.emailGateBtnConfirm {
  border: 1px solid transparent;
  color: rgb(var(--bg-app-rgb));
  background: linear-gradient(135deg, rgb(var(--primary-rgb)) 0%, rgb(var(--primary-hover-rgb)) 100%);
  box-shadow: 0 14px 28px rgb(var(--primary-rgb) / 0.26);
}

.emailGateLabel {
  display: block;
  margin-top: 0.78rem;
  font-size: 0.82rem;
  font-weight: 620;
  color: rgb(var(--color-text-muted-rgb));
}

.emailGateInput {
  width: 100%;
  margin-top: 0.4rem;
  border-radius: 0.7rem;
  border: 1px solid rgb(var(--border-rgb) / 0.72);
  background: rgb(var(--bg-app-rgb) / 0.62);
  color: rgb(var(--text-primary-rgb));
  padding: 0.68rem 0.74rem;
  font-size: 0.95rem;
}

.emailGateInput:focus-visible {
  outline: 2px solid rgb(var(--primary-rgb) / 0.55);
  outline-offset: 1px;
}

.emailGateFieldError {
  margin: 0.36rem 0 0;
  font-size: 0.8rem;
  color: rgb(var(--color-danger-rgb));
}

.email-gate-fade-enter-active,
.email-gate-fade-leave-active {
  transition: opacity 180ms ease;
}

.email-gate-fade-enter-from,
.email-gate-fade-leave-to {
  opacity: 0;
}
</style>
