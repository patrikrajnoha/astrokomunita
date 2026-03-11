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
  let exportRetryCountdownInterval = null

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
    phase: '',
    retryAfterSeconds: 0,
  })

  const exportForm = reactive({
    currentPassword: '',
  })

  const createDefaultExportCounts = () => ({
    posts_count: 0,
    invites_received_count: 0,
    invites_sent_count: 0,
    reminders_count: 0,
    followed_events_count: 0,
    bookmarks_count: 0,
  })

  const exportSummaryState = reactive({
    loading: false,
    loaded: false,
    error: '',
    estimatedBytes: 0,
    generatedAt: null,
    counts: createDefaultExportCounts(),
    sections: [],
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

  const clearExportRetryCountdown = () => {
    if (exportRetryCountdownInterval !== null) {
      clearInterval(exportRetryCountdownInterval)
      exportRetryCountdownInterval = null
    }
  }

  const startExportRetryCountdown = (seconds) => {
    const initialSeconds = Math.max(1, toPositiveInt(seconds, 60))
    exportState.retryAfterSeconds = initialSeconds
    clearExportRetryCountdown()

    exportRetryCountdownInterval = setInterval(() => {
      if (exportState.retryAfterSeconds > 0) {
        exportState.retryAfterSeconds -= 1
      }

      if (exportState.retryAfterSeconds <= 0) {
        clearExportRetryCountdown()
      }
    }, 1000)
  }

  const parseRetryAfterSeconds = (headers) => {
    const value = headers?.['retry-after'] ?? headers?.['Retry-After']
    const numeric = Number(value)
    if (Number.isFinite(numeric) && numeric > 0) {
      return Math.ceil(numeric)
    }

    if (typeof value === 'string') {
      const parsedAt = Date.parse(value)
      if (Number.isFinite(parsedAt)) {
        const remainingSeconds = Math.ceil((parsedAt - Date.now()) / 1000)
        if (remainingSeconds > 0) {
          return remainingSeconds
        }
      }
    }

    return 60
  }

  const formatRetryAfterLabel = (seconds) => {
    const total = Math.max(1, toPositiveInt(seconds, 1))
    if (total < 60) {
      return `${total} s`
    }

    const minutes = Math.floor(total / 60)
    const remainder = total % 60
    if (remainder === 0) {
      return `${minutes} min`
    }

    return `${minutes} min ${remainder} s`
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
          'Skontrolujte zvyraznene pole.',
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
      emailState.error = 'Nie ste prihlaseny.'
      return
    }

    emailState.sending = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/verification/send', {})
      applyEmailStatus(response?.data?.data || {})
      emailState.success = response?.data?.message || 'Overovaci kod bol odoslany.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa odoslat overovaci kod.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.sending = false
    }
  }

  const confirmEmailCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlaseny.'
      return
    }

    if (!emailForm.code) {
      emailState.fieldError = 'Overovaci kod je povinny.'
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
      emailState.success = response?.data?.message || 'E-mail bol uspesne overeny.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa overit overovaci kod.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirming = false
    }
  }

  const requestEmailChange = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlaseny.'
      return
    }

    if (!emailForm.newEmail) {
      emailState.fieldError = 'Novy e-mail je povinny.'
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
      emailState.success = response?.data?.message || 'Zmena e-mailu bola vyziadana.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Poziadavka na zmenu e-mailu zlyhala.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.requestingChange = false
    }
  }

  const sendCurrentEmailChangeCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlaseny.'
      return
    }

    emailState.confirmingCurrent = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/change/confirm-current', {})
      applyEmailStatus(response?.data?.data || {})
      emailState.success = response?.data?.message || 'Kod bol odoslany na aktualny e-mail.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa odoslat kod na potvrdenie aktualneho e-mailu.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirmingCurrent = false
    }
  }

  const confirmCurrentEmailChangeCode = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlaseny.'
      return
    }

    if (!emailForm.currentCode) {
      emailState.fieldError = 'Potvrdzovaci kod z aktualneho e-mailu je povinny.'
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
      emailState.success = response?.data?.message || 'Aktualny e-mail bol potvrdeny.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa potvrdit kod z aktualneho e-mailu.')
      emailState.error = resolved.message
      emailState.fieldError = resolved.fieldError
    } finally {
      emailState.confirmingCurrent = false
    }
  }

  const applyNewEmailChange = async () => {
    resetEmailState()

    if (!auth.user) {
      emailState.error = 'Nie ste prihlaseny.'
      return
    }

    emailState.applyingNew = true

    try {
      await auth.csrf()
      const response = await http.post('/account/email/change/confirm-new', {})
      applyEmailStatus(response?.data?.data || {})
      emailForm.code = ''
      emailState.success =
        response?.data?.message || 'Novy e-mail bol pouzity. Overte ho kodom odoslanym na novy e-mail.'
    } catch (error) {
      const resolved = resolveEmailError(error, 'Nepodarilo sa pouzit novy e-mail.')
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
      passwordState.error = 'Nie ste prihlaseny.'
      return
    }

    if (!passwordForm.current || !passwordForm.password || !passwordForm.confirm) {
      passwordState.fieldError = 'Vsetky polia su povinne.'
      return
    }

    if (passwordForm.password.length < 8) {
      passwordState.fieldError = 'Heslo musi mat aspon 8 znakov.'
      return
    }

    if (passwordForm.password !== passwordForm.confirm) {
      passwordState.fieldError = 'Hesla sa nezhoduju.'
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
      passwordState.success = 'Heslo bolo zmenene.'
    } catch (error) {
      const status = error?.response?.status
      const data = error?.response?.data

      if (status === 422 && data?.errors) {
        passwordState.fieldError =
          extractFirstError(data.errors, 'current_password') ||
          extractFirstError(data.errors, 'password') ||
          'Skontrolujte zvyraznene polia.'
      } else {
        passwordState.error = data?.message || 'Zmena hesla zlyhala.'
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
        throw new Error('Nie ste prihlaseny.')
      }

      await auth.csrf()
      await http.delete('/profile')
      await auth.logout()
      router.push({ name: 'login' })
    } catch (error) {
      const data = error?.response?.data
      deactivateState.error = data?.message || error?.message || 'Deaktivacia uctu zlyhala.'
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
      logoutState.error = error?.message || 'Odhlasenie zlyhalo.'
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
        throw new Error('Nie ste prihlaseny.')
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
        ? 'Odber newslettera bol zapnuty.'
        : 'Odber newslettera bol vypnuty.'
    } catch (error) {
      newsletterState.error = error?.response?.data?.message || error?.message || 'Aktualizacia newslettera zlyhala.'
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

  const toPositiveInt = (value, fallback = 0) => {
    const parsed = Number(value)
    if (!Number.isFinite(parsed) || parsed < 0) return fallback
    return Math.floor(parsed)
  }

  const formatExportBytes = (bytes) => {
    const value = Number(bytes || 0)
    if (!Number.isFinite(value) || value <= 0) return '0 B'
    if (value < 1024) return `${Math.round(value)} B`
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`
    return `${(value / (1024 * 1024)).toFixed(2)} MB`
  }

  const normalizeExportSummary = (payload) => {
    const counts = payload?.counts && typeof payload.counts === 'object' ? payload.counts : {}
    const sections = Array.isArray(payload?.sections)
      ? payload.sections.map((item) => String(item || '')).filter(Boolean)
      : []

    return {
      estimatedBytes: toPositiveInt(payload?.estimated_bytes, 0),
      generatedAt: payload?.generated_at || null,
      counts: {
        posts_count: toPositiveInt(counts.posts_count, 0),
        invites_received_count: toPositiveInt(counts.invites_received_count, 0),
        invites_sent_count: toPositiveInt(counts.invites_sent_count, 0),
        reminders_count: toPositiveInt(counts.reminders_count, 0),
        followed_events_count: toPositiveInt(counts.followed_events_count, 0),
        bookmarks_count: toPositiveInt(counts.bookmarks_count, 0),
      },
      sections,
    }
  }

  const applyExportSummary = (summary) => {
    exportSummaryState.loaded = true
    exportSummaryState.error = ''
    exportSummaryState.estimatedBytes = summary.estimatedBytes
    exportSummaryState.generatedAt = summary.generatedAt
    exportSummaryState.counts = summary.counts
    exportSummaryState.sections = summary.sections
  }

  const loadExportSummary = async () => {
    if (!auth.user || exportSummaryState.loading) return

    exportSummaryState.loading = true
    exportSummaryState.error = ''

    try {
      const { data } = await http.get('/me/export/summary', {
        meta: { skipErrorToast: true },
      })
      applyExportSummary(normalizeExportSummary(data))
    } catch (error) {
      exportSummaryState.error =
        error?.response?.data?.message || error?.userMessage || error?.message || 'Nacitavanie sumarizacie exportu zlyhalo.'
    } finally {
      exportSummaryState.loading = false
    }
  }

  const parseExportBlobPayload = async (blob) => {
    if (!(blob instanceof Blob)) return null
    const mime = String(blob.type || '').toLowerCase()
    if (mime && !mime.includes('json')) {
      return null
    }

    try {
      const text = await blob.text()
      const parsed = JSON.parse(text)
      return parsed && typeof parsed === 'object' ? parsed : null
    } catch {
      return null
    }
  }

  const summarizeDownloadedExport = (payload, fallbackBytes) => {
    const summary = payload?.data_summary && typeof payload.data_summary === 'object' ? payload.data_summary : {}
    const postsCount = toPositiveInt(summary.posts_count, 0)
    const invitesReceivedCount = toPositiveInt(
      summary.invites_received_count ?? summary.invites_count,
      0,
    )
    const invitesSentCount = toPositiveInt(summary.invites_sent_count, 0)
    const remindersCount = toPositiveInt(summary.reminders_count, 0)
    const followedEventsCount = toPositiveInt(summary.followed_events_count, 0)
    const bookmarksCount = toPositiveInt(summary.bookmarks_count, 0)
    const estimatedBytes = toPositiveInt(summary.estimated_bytes, fallbackBytes)

    return {
      estimatedBytes,
      generatedAt: payload?.exported_at || null,
      counts: {
        posts_count: postsCount,
        invites_received_count: invitesReceivedCount,
        invites_sent_count: invitesSentCount,
        reminders_count: remindersCount,
        followed_events_count: followedEventsCount,
        bookmarks_count: bookmarksCount,
      },
      sections: [
        'user',
        'newsletter',
        'notification_preferences',
        'activity',
        'posts',
        'invites_received',
        'invites_sent',
        'reminders',
        'followed_events',
        'bookmarks',
      ],
    }
  }

  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

  const downloadViaAsyncJob = async () => {
    if (!String(exportForm.currentPassword || '').trim()) {
      const error = new Error('Aktualne heslo je povinne pre export dat.')
      error.userMessage = 'Zadajte aktualne heslo pre potvrdenie exportu.'
      throw error
    }

    exportState.phase = 'Overujem heslo...'
    const created = await http.post(
      '/me/export/jobs',
      {
        current_password: exportForm.currentPassword,
      },
      {
        meta: { skipErrorToast: true },
      },
    )

    let job = created?.data && typeof created.data === 'object' ? created.data : null
    let jobId = Number(job?.id || 0)
    if (jobId <= 0) {
      throw new Error('Export job nebol vytvoreny.')
    }

    const maxAttempts = 60
    for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
      if (job?.status === 'ready' && job?.download_url) {
        exportState.phase = 'Stahujem subor...'
        return http.get(job.download_url, {
          responseType: 'blob',
          meta: { skipErrorToast: true },
        })
      }

      if (job?.status === 'failed') {
        const error = new Error(job?.error_message || 'Generovanie exportu zlyhalo.')
        error.userMessage = job?.error_message || 'Generovanie exportu zlyhalo.'
        throw error
      }

      exportState.phase = 'Generujem export...'
      const statusResponse = await http.get(`/me/export/jobs/${jobId}`, {
        meta: { skipErrorToast: true },
      })
      job = statusResponse?.data && typeof statusResponse.data === 'object' ? statusResponse.data : null
      jobId = Number(job?.id || jobId || 0)

      if (job?.status !== 'ready') {
        await sleep(1000)
      }
    }

    const timeoutError = new Error('Generovanie exportu trva dlhsie, skuste to o chvilu znova.')
    timeoutError.userMessage = 'Generovanie exportu trva dlhsie, skuste to o chvilu znova.'
    throw timeoutError
  }

  const downloadViaLegacyEndpoint = async () => {
    exportState.phase = 'Pripravujem export...'
    return http.get('/me/export', {
      responseType: 'blob',
      meta: { skipErrorToast: true },
    })
  }

  const downloadProfileExport = async () => {
    if (exportState.loading) return
    if (exportState.retryAfterSeconds > 0) {
      exportState.error = `Prilis vela poziadaviek na export. Skuste to znova o ${formatRetryAfterLabel(exportState.retryAfterSeconds)}.`
      return
    }

    exportState.error = ''
    exportState.success = ''
    exportState.loading = true
    exportState.phase = ''

    try {
      if (!auth.user) {
        throw new Error('Nie ste prihlaseny.')
      }

      let response = null
      try {
        response = await downloadViaAsyncJob()
      } catch (error) {
        const status = Number(error?.response?.status || 0)
        if (status === 404 || status === 405 || status === 501) {
          response = await downloadViaLegacyEndpoint()
        } else {
          throw error
        }
      }

      const filename = resolveExportFilename(response?.headers?.['content-disposition'])
      const blob =
        response?.data instanceof Blob
          ? response.data
          : new Blob([JSON.stringify(response?.data || {})], { type: 'application/json' })

      const parsedPayload = await parseExportBlobPayload(blob)
      if (parsedPayload) {
        applyExportSummary(summarizeDownloadedExport(parsedPayload, blob.size))
      }

      const url = URL.createObjectURL(blob)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = filename
      document.body.appendChild(anchor)
      anchor.click()
      anchor.remove()
      URL.revokeObjectURL(url)

      const sizeLabel = formatExportBytes(blob.size)
      const postsCount = exportSummaryState.counts.posts_count
      const invitesTotal =
        exportSummaryState.counts.invites_received_count + exportSummaryState.counts.invites_sent_count
      exportState.success = `Export bol stiahnuty (${sizeLabel}, ${postsCount} prispevkov, ${invitesTotal} pozvanok).`
      exportForm.currentPassword = ''
      exportState.retryAfterSeconds = 0
      clearExportRetryCountdown()
    } catch (error) {
      const status = Number(error?.response?.status || 0)

      if (status === 422 && error?.response?.data?.errors?.current_password?.[0]) {
        exportState.error = String(error.response.data.errors.current_password[0])
      } else if (status === 429) {
        const retryAfterSeconds = parseRetryAfterSeconds(error?.response?.headers)
        startExportRetryCountdown(retryAfterSeconds)
        exportState.error = `Prilis vela poziadaviek na export. Skuste to znova o ${formatRetryAfterLabel(retryAfterSeconds)}.`
      } else {
        exportState.error =
          error?.response?.data?.message || error?.userMessage || error?.message || 'Export dat zlyhal.'
      }
    } finally {
      exportState.loading = false
      exportState.phase = ''
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
    clearExportRetryCountdown()
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
    exportForm,
    exportState,
    exportSummaryState,
    formatExportBytes,
    loadExportSummary,
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
