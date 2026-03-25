<template>
  <teleport to="body">
    <transition name="vgate">
      <div
        v-if="open"
        class="fixed inset-0 z-[1400] flex items-center justify-center p-4"
        style="background:rgba(1,6,15,0.78);backdrop-filter:blur(12px) saturate(180%)"
        data-testid="email-verification-gate"
      >
        <section
          ref="cardRef"
          class="vgate-card w-full max-w-[420px] bg-app rounded-2xl px-6 pt-6 pb-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="email-gate-title"
          tabindex="-1"
        >
          <!-- Icon -->
          <div class="w-10 h-10 rounded-full bg-vivid/10 flex items-center justify-center mb-5">
            <svg class="w-[18px] h-[18px] text-vivid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="2" y="4" width="20" height="16" rx="2.5" />
              <path d="m2 7 10 6.5L22 7" />
            </svg>
          </div>

          <h2 id="email-gate-title" class="text-white text-[1.15rem] font-semibold leading-snug">
            Over svoj e-mail
          </h2>
          <p class="mt-2 text-muted text-sm leading-relaxed">
            Poslali sme overovací kód na
            <strong class="text-vivid font-medium">{{ account.email || 'tvoj e-mail' }}</strong>.
            Kým kód nepotvrdíš, aplikáciu nie je možné používať.
          </p>

          <!-- Alerts -->
          <transition name="vgate-alert">
            <div
              v-if="state.success"
              class="mt-4 rounded-xl bg-green-500/10 text-green-400 px-4 py-2.5 text-sm"
              role="status"
            >
              {{ state.success }}
            </div>
          </transition>
          <transition name="vgate-alert">
            <div
              v-if="state.error"
              class="mt-4 rounded-xl bg-danger/10 text-danger px-4 py-2.5 text-sm"
              role="alert"
            >
              {{ state.error }}
            </div>
          </transition>

          <!-- Input -->
          <div class="mt-5">
            <label class="block text-muted text-xs font-medium mb-1.5" for="email-gate-code">
              Overovací kód
            </label>
            <input
              id="email-gate-code"
              v-model.trim="form.code"
              class="vgate-input w-full bg-hover rounded-xl px-4 py-3 text-white text-base tracking-widest transition-shadow"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              placeholder="12345-67890"
              :disabled="state.loading || state.confirming"
            />
            <p v-if="state.fieldError" class="mt-1.5 text-danger text-xs">{{ state.fieldError }}</p>
          </div>

          <!-- Confirm -->
          <button
            type="button"
            class="mt-3 w-full rounded-2xl bg-vivid hover:bg-vivid-hover text-white font-medium py-3 text-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed active:scale-[0.98]"
            :disabled="state.loading || state.sending || state.confirming || !form.code"
            @click="confirmCode"
          >
            {{ state.confirming ? 'Potvrdzujem…' : 'Potvrdiť kód' }}
          </button>

          <!-- Resend -->
          <button
            type="button"
            class="mt-2 w-full rounded-2xl bg-secondary-btn hover:bg-secondary-btn-hover text-muted font-medium py-3 text-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed active:scale-[0.98]"
            :disabled="state.loading || state.sending || state.confirming || account.secondsToResend > 0"
            @click="sendCode"
          >
            {{ sendButtonLabel }}
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
  if (state.loading) return 'Načítavam…'
  if (state.sending) return 'Odosielam…'
  if (account.secondsToResend > 0) return `Opakovať za ${account.secondsToResend} s`
  return 'Poslať overovací e-mail'
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
    state.success = response?.data?.message || 'Overovací kód bol odoslaný.'
    initialSendTriggered = true
  } catch (error) {
    const resolved = resolveRequestError(error, 'Nepodarilo sa odoslať overovací kód.')
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
    state.fieldError = 'Overovací kód je povinný.'
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
    state.success = response?.data?.message || 'E-mail bol úspešne overený.'

    if (account.verified || account.emailVerifiedAt) {
      emit('verified')
    }
  } catch (error) {
    const resolved = resolveRequestError(error, 'Nepodarilo sa overiť kód.')
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
/* Backdrop fade */
.vgate-enter-active,
.vgate-leave-active {
  transition: opacity 220ms ease;
}
.vgate-enter-from,
.vgate-leave-to {
  opacity: 0;
}

/* Card scale */
.vgate-enter-active .vgate-card {
  animation: vgateIn 300ms cubic-bezier(0.34, 1.4, 0.64, 1) both;
}
.vgate-leave-active .vgate-card {
  animation: vgateOut 180ms ease forwards;
}

@keyframes vgateIn {
  from {
    transform: scale(0.92) translateY(14px);
    opacity: 0;
  }
  to {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}
@keyframes vgateOut {
  to {
    transform: scale(0.95) translateY(6px);
    opacity: 0;
  }
}

/* Alert slide */
.vgate-alert-enter-active,
.vgate-alert-leave-active {
  transition: opacity 160ms ease, transform 160ms ease;
}
.vgate-alert-enter-from,
.vgate-alert-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* Input */
.vgate-input {
  border: none;
  outline: none;
}
.vgate-input::placeholder {
  color: rgba(171, 184, 201, 0.3);
}
.vgate-input:focus {
  box-shadow: 0 0 0 2px rgba(15, 115, 255, 0.35);
}
.vgate-input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
