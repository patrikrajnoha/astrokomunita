import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useOnboardingTourStore } from '@/stores/onboardingTour'
import http from '@/services/api'

export function useSettingsState() {
  const auth = useAuthStore()
  const router = useRouter()
  const onboardingTour = useOnboardingTourStore()

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

  const passwordForm = reactive({
    current: '',
    password: '',
    confirm: '',
  })

  const passwordState = reactive({
    loading: false,
    success: '',
    error: '',
    fieldError: '',
  })

  const deactivateForm = reactive({
    confirm: '',
  })

  const deactivateState = reactive({
    loading: false,
    error: '',
  })

  const logoutState = reactive({
    loading: false,
    error: '',
  })

  const newsletterSubscribed = ref(false)
  const newsletterState = reactive({
    loading: false,
    error: '',
    success: '',
  })

  const exportState = reactive({
    loading: false,
    error: '',
    success: '',
  })

  const activity = ref(null)
  const activityLoading = ref(false)
  const activityError = ref('')
  const activityExpanded = ref(false)

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
          'Check the highlighted field.',
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
      emailState.error = 'You are not signed in.'
      return
    }

    emailState.sending = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/verification/send', {})
      applyEmailStatus(response?.data?.data || {})
      emailState.success = response?.data?.message || 'Verification code sent.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Failed to send verification code.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.sending = false
    }
  }

  const confirmEmailCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'You are not signed in.'
      return
    }

    if (!emailForm.code) {
      emailState.fieldError = 'Verification code is required.'
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
      emailState.success = response?.data?.message || 'Email verified successfully.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Failed to verify email code.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirming = false
    }
  }

  const requestEmailChange = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'You are not signed in.'
      return
    }

    if (!emailForm.newEmail) {
      emailState.fieldError = 'New email is required.'
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
      emailState.success = response?.data?.message || 'Email change requested.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Email change request failed.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.requestingChange = false
    }
  }

  const sendCurrentEmailChangeCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'You are not signed in.'
      return
    }

    emailState.confirmingCurrent = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/change/confirm-current', {})
      applyEmailStatus(response?.data?.data || {})
      emailState.success = response?.data?.message || 'Code sent to current email.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Failed to send current email confirmation code.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirmingCurrent = false
    }
  }

  const confirmCurrentEmailChangeCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'You are not signed in.'
      return
    }

    if (!emailForm.currentCode) {
      emailState.fieldError = 'Current email confirmation code is required.'
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
      emailState.success = response?.data?.message || 'Current email confirmed.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Failed to confirm current email code.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirmingCurrent = false
    }
  }

  const applyNewEmailChange = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'You are not signed in.'
      return
    }

    emailState.applyingNew = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/change/confirm-new', {})
      applyEmailStatus(response?.data?.data || {})
      emailForm.code = ''
      emailState.success =
        response?.data?.message || 'New email applied. Verify it with the code sent to your new email.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Failed to apply new email change.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.applyingNew = false
    }
  }

  const resetPasswordState = () => {
    passwordState.success = ''
    passwordState.error = ''
    passwordState.fieldError = ''
  }

  const submitPassword = async () => {
    resetPasswordState()

    if (!auth.user) {
      passwordState.error = 'You are not signed in.'
      return
    }

    if (!passwordForm.current || !passwordForm.password || !passwordForm.confirm) {
      passwordState.fieldError = 'All fields are required.'
      return
    }

    if (passwordForm.password.length < 8) {
      passwordState.fieldError = 'Password must be at least 8 characters.'
      return
    }

    if (passwordForm.password !== passwordForm.confirm) {
      passwordState.fieldError = 'Passwords do not match.'
      return
    }

    passwordState.loading = true

    try {
      await auth.csrf()
      await http.patch('/profile/password', {
        current_password: passwordForm.current,
        password: passwordForm.password,
        password_confirmation: passwordForm.confirm,
      })

      passwordForm.current = ''
      passwordForm.password = ''
      passwordForm.confirm = ''
      passwordState.success = 'Password updated.'
    } catch (error) {
      const status = error?.response?.status
      const data = error?.response?.data

      if (status === 422 && data?.errors) {
        passwordState.fieldError =
          extractFirstError(data.errors, 'current_password') ||
          extractFirstError(data.errors, 'password') ||
          'Check the highlighted fields.'
      } else {
        passwordState.error = data?.message || 'Password update failed.'
      }
    } finally {
      passwordState.loading = false
    }
  }

  const submitDeactivate = async () => {
    deactivateState.error = ''
    deactivateState.loading = true

    try {
      if (!auth.user) {
        throw new Error('You are not signed in.')
      }

      await auth.csrf()
      await http.delete('/profile')
      await auth.logout()
      router.push({ name: 'login' })
    } catch (error) {
      const data = error?.response?.data
      deactivateState.error = data?.message || error?.message || 'Account deactivation failed.'
    } finally {
      deactivateState.loading = false
    }
  }

  const submitLogout = async () => {
    logoutState.error = ''
    logoutState.loading = true

    try {
      await auth.logout()
      await router.push({ name: 'login' })
    } catch (error) {
      logoutState.error = error?.message || 'Logout failed.'
    } finally {
      logoutState.loading = false
    }
  }

  const submitNewsletter = async (checked) => {
    newsletterState.error = ''
    newsletterState.success = ''
    newsletterState.loading = true

    try {
      if (!auth.user) {
        throw new Error('You are not signed in.')
      }

      await auth.csrf()
      const { data } = await http.patch('/me/newsletter', {
        newsletter_subscribed: Boolean(checked),
      })

      newsletterSubscribed.value = Boolean(data?.data?.newsletter_subscribed ?? checked)
      auth.user = {
        ...auth.user,
        newsletter_subscribed: newsletterSubscribed.value,
      }
      newsletterState.success = newsletterSubscribed.value
        ? 'Newsletter subscription enabled.'
        : 'Newsletter subscription disabled.'
    } catch (error) {
      newsletterState.error = error?.response?.data?.message || error?.message || 'Newsletter update failed.'
      newsletterSubscribed.value = Boolean(auth.user?.newsletter_subscribed)
    } finally {
      newsletterState.loading = false
    }
  }

  const resolveExportFilename = (contentDisposition) => {
    const header = String(contentDisposition || '')

    const utfMatch = header.match(/filename\*=UTF-8''([^;]+)/i)
    if (utfMatch?.[1]) {
      try {
        return decodeURIComponent(utfMatch[1].trim().replace(/^["']|["']$/g, ''))
      } catch {
        // Ignore malformed encodings and fallback to other filename formats.
      }
    }

    const defaultMatch = header.match(/filename="?([^";]+)"?/i)
    if (defaultMatch?.[1]) {
      return defaultMatch[1].trim()
    }

    const rawIdentifier = String(auth.user?.username || auth.user?.name || 'user')
    const safeIdentifier = rawIdentifier
      .toLowerCase()
      .replace(/[^a-z0-9_-]+/g, '-')
      .replace(/^-+|-+$/g, '')

    return `nebesky-sprievodca-export-${safeIdentifier || 'user'}-${new Date().toISOString().slice(0, 10)}.json`
  }

  const downloadProfileExport = async () => {
    if (exportState.loading) return

    exportState.error = ''
    exportState.success = ''
    exportState.loading = true

    try {
      if (!auth.user) {
        throw new Error('You are not signed in.')
      }

      const response = await http.get('/me/export', {
        responseType: 'blob',
        meta: { skipErrorToast: true },
      })

      const filename = resolveExportFilename(response?.headers?.['content-disposition'])
      const blob =
        response?.data instanceof Blob
          ? response.data
          : new Blob([JSON.stringify(response?.data || {})], { type: 'application/json' })

      const url = URL.createObjectURL(blob)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = filename
      document.body.appendChild(anchor)
      anchor.click()
      anchor.remove()
      URL.revokeObjectURL(url)

      exportState.success = 'Export downloaded.'
    } catch (error) {
      const status = Number(error?.response?.status || 0)

      if (status === 429) {
        exportState.error = 'Too many export requests. Try again in a minute.'
      } else {
        exportState.error =
          error?.response?.data?.message || error?.userMessage || error?.message || 'Data export failed.'
      }
    } finally {
      exportState.loading = false
    }
  }

  const startOnboardingTour = () => {
    onboardingTour.restartTour()
  }

  const normalizeCount = (value) => {
    const parsed = Number(value)
    if (!Number.isFinite(parsed) || parsed < 0) return 0
    return Math.floor(parsed)
  }

  const normalizeActivity = (payload) => {
    if (!payload || typeof payload !== 'object') return null

    return {
      last_login_at: payload.last_login_at || null,
      posts_count: normalizeCount(payload.posts_count),
      event_participations_count: normalizeCount(payload.event_participations_count),
    }
  }

  const loadUserActivity = async () => {
    if (!auth.user) return

    const fromAuth = normalizeActivity(auth.user.activity)
    if (fromAuth && !activity.value) {
      activity.value = fromAuth
    }

    activityLoading.value = true
    activityError.value = ''

    try {
      const { data } = await http.get('/me/activity', {
        meta: { skipErrorToast: true },
      })
      const normalized = normalizeActivity(data)
      activity.value = normalized
      if (auth.user && normalized) {
        auth.user = {
          ...auth.user,
          activity: normalized,
        }
      }
    } catch (error) {
      if (!activity.value && fromAuth) {
        activity.value = fromAuth
      } else if (!activity.value) {
        activityError.value = error?.response?.data?.message || 'Nacitavanie aktivity zlyhalo.'
      }
    } finally {
      activityLoading.value = false
    }
  }

  const toggleActivitySection = async () => {
    activityExpanded.value = !activityExpanded.value
    if (activityExpanded.value && !activity.value && !activityLoading.value) {
      await loadUserActivity()
    }
  }

  onBeforeUnmount(() => {
    clearEmailResendCountdown()
  })

  onMounted(async () => {
    if (!auth.initialized) {
      await auth.fetchUser()
    }

    if (auth.user) {
      applyEmailStatus()
      await loadEmailStatus()
      newsletterSubscribed.value = Boolean(auth.user.newsletter_subscribed)
      activity.value = normalizeActivity(auth.user.activity)
    }
  })

  return {
    activity,
    activityError,
    activityExpanded,
    activityLoading,
    applyNewEmailChange,
    confirmCurrentEmailChangeCode,
    confirmEmailCode,
    deactivateForm,
    deactivateState,
    downloadProfileExport,
    emailAccount,
    emailForm,
    emailState,
    exportState,
    logoutState,
    newsletterState,
    newsletterSubscribed,
    passwordForm,
    passwordState,
    requestEmailChange,
    sendCurrentEmailChangeCode,
    sendEmailCode,
    startOnboardingTour,
    submitDeactivate,
    submitLogout,
    submitNewsletter,
    submitPassword,
    toggleActivitySection,
  }
}
