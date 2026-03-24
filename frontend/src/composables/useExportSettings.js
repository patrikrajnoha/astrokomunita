import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useOnboardingTourStore } from '@/stores/onboardingTour'
import http from '@/services/api'

export function useExportSettings() {
  const auth = useAuthStore()
  const onboardingTour = useOnboardingTourStore()

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

  let exportRetryCountdownInterval = null

  const toPositiveInt = (value, fallback = 0) => {
    const parsed = Number(value)
    if (!Number.isFinite(parsed) || parsed < 0) return fallback
    return Math.floor(parsed)
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

  const submitNewsletter = async (checked) => {
    newsletterState.error = ''
    newsletterState.success = ''
    newsletterState.loading = true

    try {
      if (!auth.user) {
        throw new Error('Nie ste prihlásený.')
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
        ? 'Odber newslettera bol zapnutý.'
        : 'Odber newslettera bol vypnutý.'
    } catch (error) {
      newsletterState.error = error?.response?.data?.message || error?.message || 'Aktualizácia newslettera zlyhala.'
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
        error?.response?.data?.message || error?.userMessage || error?.message || 'Načítavanie sumarizácie exportu zlyhalo.'
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
      const error = new Error('Aktuálne heslo je povinné pre export dát.')
      error.userMessage = 'Zadajte aktuálne heslo pre potvrdenie exportu.'
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
      throw new Error('Export job nebol vytvorený.')
    }

    const maxAttempts = 60
    for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
      if (job?.status === 'ready' && job?.download_url) {
        exportState.phase = 'Sťahujem súbor...'
        return http.get(job.download_url, {
          responseType: 'blob',
          meta: { skipErrorToast: true },
        })
      }

      if (job?.status === 'failed') {
        const error = new Error(job?.error_message || 'Export sa nepodaril.')
        error.userMessage = job?.error_message || 'Export sa nepodaril.'
        throw error
      }

      exportState.phase = 'Exportujem...'
      const statusResponse = await http.get(`/me/export/jobs/${jobId}`, {
        meta: { skipErrorToast: true },
      })
      job = statusResponse?.data && typeof statusResponse.data === 'object' ? statusResponse.data : null
      jobId = Number(job?.id || jobId || 0)

      if (job?.status !== 'ready') {
        await sleep(1000)
      }
    }

    const timeoutError = new Error('Export trvá dlhšie. Skúste to znova o chvíľu.')
    timeoutError.userMessage = 'Export trvá dlhšie. Skúste to znova o chvíľu.'
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
      exportState.error = `Príliš veľa požiadaviek na export. Skúste to znova o ${formatRetryAfterLabel(exportState.retryAfterSeconds)}.`
      return
    }

    exportState.error = ''
    exportState.success = ''
    exportState.loading = true
    exportState.phase = ''

    try {
      if (!auth.user) {
        throw new Error('Nie ste prihlásený.')
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

      exportState.success = 'Hotovo, sťahovanie sa začalo.'
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
        exportState.error = `Príliš veľa požiadaviek na export. Skúste to znova o ${formatRetryAfterLabel(retryAfterSeconds)}.`
      } else {
        exportState.error =
          error?.response?.data?.message || error?.userMessage || error?.message || 'Export sa nepodaril. Skúste to znova.'
      }
    } finally {
      exportState.loading = false
      exportState.phase = ''
    }
  }

  const startOnboardingTour = () => {
    onboardingTour.restartTour()
  }

  onMounted(async () => {
    if (!auth.initialized) {
      await auth.fetchUser()
    }

    if (auth.user) {
      newsletterSubscribed.value = Boolean(auth.user.newsletter_subscribed)
    }
  })

  onBeforeUnmount(() => {
    clearExportRetryCountdown()
  })

  return {
    newsletterSubscribed,
    newsletterState,
    exportForm,
    exportState,
    exportSummaryState,
    downloadProfileExport,
    formatExportBytes,
    loadExportSummary,
    startOnboardingTour,
    submitNewsletter,
  }
}
