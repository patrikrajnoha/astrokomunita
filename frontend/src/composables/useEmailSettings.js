import { onBeforeUnmount, onMounted, reactive } from 'vue'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/api'

export function useEmailSettings() {
  const auth = useAuthStore()

  const emailForm = reactive({
    code: '',
    newEmail: '',
    currentCode: '',
  })

  const emailState = reactive({
    loading: false,
    sending: false,
    confirming: false,
    requestingChange: false,
    confirmingCurrent: false,
    applyingNew: false,
    success: '',
    error: '',
    fieldError: '',
  })

  const emailAccount = reactive({
    email: '',
    verified: false,
    emailVerifiedAt: null,
    requiresEmailVerification: false,
    secondsToResend: 0,
    pendingEmailChange: null,
  })

  let emailResendCountdownInterval = null

  const extractFirstError = (errorsObj, field) => {
    const value = errorsObj?.[field]
    return Array.isArray(value) && value.length ? String(value[0]) : ''
  }

  const resetEmailState = () => {
    emailState.success = ''
    emailState.error = ''
    emailState.fieldError = ''
  }

  const clearEmailResendCountdown = () => {
    if (emailResendCountdownInterval !== null) {
      clearInterval(emailResendCountdownInterval)
      emailResendCountdownInterval = null
    }
  }

  const startEmailResendCountdown = () => {
    clearEmailResendCountdown()

    if (
      Number(emailAccount.secondsToResend) <= 0 &&
      Number(emailAccount.pendingEmailChange?.seconds_to_resend_current || 0) <= 0
    ) {
      return
    }

    emailResendCountdownInterval = setInterval(() => {
      if (emailAccount.secondsToResend > 0) {
        emailAccount.secondsToResend -= 1
      }

      const pending = emailAccount.pendingEmailChange
      if (pending && pending.seconds_to_resend_current > 0) {
        pending.seconds_to_resend_current -= 1
      }

      if (
        Number(emailAccount.secondsToResend) <= 0 &&
        Number(emailAccount.pendingEmailChange?.seconds_to_resend_current || 0) <= 0
      ) {
        clearEmailResendCountdown()
      }
    }, 1000)
  }

  const normalizePendingEmailChange = (payload) => {
    if (!payload || typeof payload !== 'object') return null

    return {
      id: Number(payload.id || 0) || 0,
      current_email: String(payload.current_email || ''),
      new_email: String(payload.new_email || ''),
      current_email_confirmed_at: payload.current_email_confirmed_at || null,
      new_email_applied_at: payload.new_email_applied_at || null,
      expires_at: payload.expires_at || null,
      seconds_to_resend_current: Math.max(0, Number(payload.seconds_to_resend_current || 0)),
    }
  }

  const applyEmailStatus = (payload = {}) => {
    emailAccount.email = String(payload.email || auth.user?.email || '')
    emailAccount.verified = Boolean(payload.verified ?? payload.email_verified_at)
    emailAccount.emailVerifiedAt = payload.email_verified_at || null
    emailAccount.requiresEmailVerification = Boolean(
      payload.requires_email_verification ?? auth.user?.requires_email_verification,
    )
    emailAccount.secondsToResend = Math.max(0, Number(payload.seconds_to_resend || 0))
    emailAccount.pendingEmailChange = normalizePendingEmailChange(payload.pending_email_change)

    if (auth.user) {
      auth.user = {
        ...auth.user,
        email: emailAccount.email || auth.user.email,
        email_verified_at: emailAccount.emailVerifiedAt,
        requires_email_verification: emailAccount.requiresEmailVerification,
      }
    }

    startEmailResendCountdown()
  }

  const loadEmailStatus = async () => {
    if (!auth.user) return

    emailState.loading = true
    try {
      const response = await http.get('/account/email', {
        meta: { skipErrorToast: true },
      })
      const payload = response?.data?.data
      if (payload && typeof payload === 'object') {
        applyEmailStatus(payload)
      } else {
        applyEmailStatus()
      }
    } catch {
      applyEmailStatus()
    } finally {
      emailState.loading = false
    }
  }

  const resolveEmailError = (error, fallbackMessage) => {
    const data = error?.response?.data
    const status = Number(error?.response?.status || 0)

    if (status === 422 && data?.errors) {
      return {
        message:
          extractFirstError(data.errors, 'new_email') ||
          extractFirstError(data.errors, 'code') ||
          'Skontrolujte zvýraznené pole.',
        fieldError:
          extractFirstError(data.errors, 'new_email') ||
          extractFirstError(data.errors, 'code') ||
          '',
      }
    }

    return {
      message: data?.message || error?.userMessage || fallbackMessage,
      fieldError: '',
    }
  }

  const sendEmailCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlásený.'
      return
    }

    emailState.sending = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/verification/send', {})
      applyEmailStatus(response?.data?.data || {})
      emailState.success = response?.data?.message || 'Overovací kód bol odoslaný.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa odoslať overovací kód.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.sending = false
    }
  }

  const confirmEmailCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlásený.'
      return
    }

    if (!emailForm.code) {
      emailState.fieldError = 'Overovací kód je povinný.'
      return
    }

    emailState.confirming = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/verification/confirm', {
        code: emailForm.code,
      })

      applyEmailStatus(response?.data?.data || {})
      emailForm.code = ''
      emailState.success = response?.data?.message || 'E-mail bol úspešne overený.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa overiť overovací kód.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirming = false
    }
  }

  const requestEmailChange = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlásený.'
      return
    }

    if (!emailForm.newEmail) {
      emailState.fieldError = 'Nový e-mail je povinný.'
      return
    }

    emailState.requestingChange = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/change/request', {
        new_email: emailForm.newEmail,
      })

      applyEmailStatus(response?.data?.data || {})
      emailForm.newEmail = ''
      emailState.success = response?.data?.message || 'Zmena e-mailu bola vyžiadaná.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Požiadavka na zmenu e-mailu zlyhala.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.requestingChange = false
    }
  }

  const sendCurrentEmailChangeCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlásený.'
      return
    }

    emailState.confirmingCurrent = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/change/confirm-current', {})
      applyEmailStatus(response?.data?.data || {})
      emailState.success = response?.data?.message || 'Kód bol odoslaný na aktuálny e-mail.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa odoslať kód na potvrdenie aktuálneho e-mailu.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirmingCurrent = false
    }
  }

  const confirmCurrentEmailChangeCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlásený.'
      return
    }

    if (!emailForm.currentCode) {
      emailState.fieldError = 'Potvrdzovací kód z aktuálneho e-mailu je povinný.'
      return
    }

    emailState.confirmingCurrent = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/change/confirm-current', {
        code: emailForm.currentCode,
      })

      applyEmailStatus(response?.data?.data || {})
      emailForm.currentCode = ''
      emailState.success = response?.data?.message || 'Aktuálny e-mail bol potvrdený.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa potvrdiť kód z aktuálneho e-mailu.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirmingCurrent = false
    }
  }

  const applyNewEmailChange = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlásený.'
      return
    }

    emailState.applyingNew = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/change/confirm-new', {})
      applyEmailStatus(response?.data?.data || {})
      emailForm.code = ''
      emailState.success =
        response?.data?.message || 'Nový e-mail bol použitý. Overte ho kódom odoslaným na nový e-mail.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa použiť nový e-mail.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.applyingNew = false
    }
  }

  onMounted(async () => {
    if (!auth.initialized) {
      await auth.fetchUser()
    }

    if (auth.user) {
      applyEmailStatus()
      await loadEmailStatus()
    }
  })

  onBeforeUnmount(() => {
    clearEmailResendCountdown()
  })

  return {
    emailForm,
    emailState,
    emailAccount,
    applyNewEmailChange,
    confirmCurrentEmailChangeCode,
    confirmEmailCode,
    requestEmailChange,
    sendCurrentEmailChangeCode,
    sendEmailCode,
  }
}
