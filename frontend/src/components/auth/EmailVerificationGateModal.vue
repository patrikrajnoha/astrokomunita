<template>
  <teleport to="body">
    <transition name="vgate">
      <div
        v-if="open"
        class="vgate-overlay fixed inset-0 z-[1400] flex items-center justify-center p-4"
        data-testid="email-verification-gate"
      >
        <section
          ref="cardRef"
          class="vgate-card w-full max-w-[420px]"
          role="dialog"
          aria-modal="true"
          aria-labelledby="email-gate-title"
          tabindex="-1"
        >
          <div class="vgate-icon" aria-hidden="true">
            <svg
              class="vgate-icon__glyph"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.75"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <rect x="2" y="4" width="20" height="16" rx="2.5" />
              <path d="m2 7 10 6.5L22 7" />
            </svg>
          </div>

          <h2 id="email-gate-title" class="vgate-title">
            Potvrď svoju identitu ✨
          </h2>
          <p class="vgate-description">
            Overovací kód sme poslali na
            <span class="vgate-email">{{ account.email || 'tvoj e-mail' }}</span>
          </p>
          <p class="vgate-description vgate-description--secondary">
            Zadaj ho a vstúp do Astrokomunity.
          </p>

          <transition name="vgate-alert">
            <div
              v-if="state.success"
              class="vgate-pill vgate-pill--success"
              role="status"
            >
              {{ state.success }}
            </div>
          </transition>

          <transition name="vgate-alert">
            <div
              v-if="state.error"
              class="vgate-pill vgate-pill--error"
              role="alert"
            >
              {{ state.error }}
            </div>
          </transition>

          <div class="vgate-field">
            <label class="vgate-label" for="email-gate-code">
              Overovací kód
            </label>
            <input
              id="email-gate-code"
              v-model.trim="form.code"
              class="vgate-input"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              placeholder="12345-67890"
              :disabled="state.loading || state.confirming"
            />
            <p v-if="state.fieldError" class="vgate-fieldError">{{ state.fieldError }}</p>
          </div>

          <button
            type="button"
            class="vgate-button vgate-button--primary"
            :disabled="state.loading || state.sending || state.confirming || !form.code"
            @click="confirmCode"
          >
            Pokračovať
          </button>

          <button
            type="button"
            class="vgate-button vgate-button--secondary"
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

function formatResendTime(seconds) {
  const clamped = Math.max(0, Number(seconds || 0))
  return `${clamped}s`
}

const sendButtonLabel = computed(() => {
  if (state.loading) return 'Načítavam...'
  if (state.sending) return 'Odosielam...'
  if (account.secondsToResend > 0) return `Znova vyslať kód o ${formatResendTime(account.secondsToResend)}`
  return 'Znova vyslať kód'
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
    state.success = 'Kód bol odoslaný ✉️'
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
    const status = Number(error?.response?.status || 0)
    state.error = status === 422 ? 'Neplatný kód. Skús znova.' : resolved.message
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
.vgate-overlay {
  background: rgb(4 8 14 / 82%);
  backdrop-filter: blur(8px);
}

.vgate-card {
  background: #151d28;
  border: 0;
  border-radius: 24px;
  box-shadow: none;
  padding: 24px;
}

.vgate-icon {
  display: flex;
  width: 44px;
  height: 44px;
  margin-bottom: 16px;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: rgb(15 115 255 / 14%);
  color: #0f73ff;
}

.vgate-icon__glyph {
  width: 20px;
  height: 20px;
}

.vgate-title {
  margin: 0;
  color: #ffffff;
  font-size: 1.42rem;
  font-weight: 700;
  line-height: 1.22;
  letter-spacing: -0.01em;
}

.vgate-description {
  margin: 10px 0 0;
  color: #abb8c9;
  font-size: 0.95rem;
  line-height: 1.5;
}

.vgate-description--secondary {
  margin-top: 8px;
}

.vgate-email {
  color: #0f73ff;
  font-weight: 600;
  word-break: break-all;
}

.vgate-pill {
  margin-top: 14px;
  padding: 8px 12px;
  border-radius: 999px;
  font-size: 0.81rem;
  line-height: 1.35;
}

.vgate-pill--success {
  background: rgb(115 223 132 / 14%);
  color: #73df84;
}

.vgate-pill--error {
  background: rgb(245 84 84 / 14%);
  color: #eb2452;
}

.vgate-field {
  margin-top: 18px;
}

.vgate-label {
  display: block;
  margin-bottom: 8px;
  color: #abb8c9;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.vgate-fieldError {
  margin-top: 7px;
  color: #eb2452;
  font-size: 0.76rem;
  line-height: 1.35;
}

.vgate-enter-active,
.vgate-leave-active {
  transition: opacity 220ms ease;
}

.vgate-enter-from,
.vgate-leave-to {
  opacity: 0;
}

.vgate-enter-active .vgate-card {
  animation: vgateIn 280ms ease both;
}

.vgate-leave-active .vgate-card {
  animation: vgateOut 180ms ease forwards;
}

@keyframes vgateIn {
  from {
    transform: scale(0.96) translateY(12px);
    opacity: 0;
  }

  to {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}

@keyframes vgateOut {
  to {
    transform: scale(0.97) translateY(8px);
    opacity: 0;
  }
}

.vgate-alert-enter-active,
.vgate-alert-leave-active {
  transition: opacity 160ms ease, transform 160ms ease;
}

.vgate-alert-enter-from,
.vgate-alert-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.vgate-input {
  width: 100%;
  padding: 12px 14px;
  border: 0;
  border-radius: 16px;
  background: #1c2736;
  color: #ffffff;
  font-size: 1rem;
  letter-spacing: 0.12em;
  line-height: 1.4;
  outline: none;
  transition: box-shadow 140ms ease, background-color 140ms ease, opacity 140ms ease;
}

.vgate-input::placeholder {
  color: rgb(171 184 201 / 62%);
}

.vgate-input:focus {
  box-shadow: 0 0 0 3px rgb(15 115 255 / 34%);
}

.vgate-input:disabled {
  opacity: 0.54;
  cursor: not-allowed;
}

.vgate-button {
  width: 100%;
  margin-top: 10px;
  padding: 12px 16px;
  border: 0;
  border-radius: 999px;
  box-shadow: none;
  font-size: 0.94rem;
  font-weight: 600;
  line-height: 1.2;
  transition: background-color 140ms ease, color 140ms ease, opacity 140ms ease;
}

.vgate-button--primary {
  background: #0f73ff;
  color: #ffffff;
}

.vgate-button--primary:hover {
  background: #0d65e6;
}

.vgate-button--secondary {
  background: #222e3f;
  color: #abb8c9;
}

.vgate-button--secondary:hover {
  background: #1c2736;
  color: #abb8c9;
}

.vgate-button:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgb(15 115 255 / 34%);
}

.vgate-button:disabled {
  opacity: 0.52;
  cursor: not-allowed;
}

@media (max-width: 480px) {
  .vgate-overlay {
    padding: 16px;
    align-items: flex-end;
  }

  .vgate-card {
    border-radius: 20px;
    padding: 20px 16px 16px;
  }

  .vgate-title {
    font-size: 1.32rem;
  }

  .vgate-description {
    font-size: 0.91rem;
  }
}
</style>
