import axios from 'axios'
import { useToast } from '@/composables/useToast'

const configuredApiBaseUrl = import.meta.env.DEV
  ? ''
  : (import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_API_URL || '')
const normalizedApiBaseUrl = String(configuredApiBaseUrl).replace(/\/api\/?$/i, '').replace(/\/+$/, '')
const apiBaseUrl = normalizedApiBaseUrl ? `${normalizedApiBaseUrl}/api` : '/api'

const api = axios.create({
  baseURL: apiBaseUrl,
  timeout: 15000,
  withCredentials: true,

  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  withXSRFToken: true,

  headers: {
    Accept: 'application/json',
  },
})

// Separate client for CSRF cookie — must hit the root (not /api)
const csrfClient = axios.create({
  baseURL: normalizedApiBaseUrl || '',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

function getCookie(name) {
  if (typeof document === 'undefined') return null
  const row = document.cookie.split('; ').find((r) => r.startsWith(name + '='))
  return row ? decodeURIComponent(row.split('=')[1]) : null
}

function syncXsrfHeaderFromCookie() {
  const xsrf = getCookie('XSRF-TOKEN')
  if (!xsrf) return
  api.defaults.headers.common['X-XSRF-TOKEN'] = xsrf
}

export async function refreshCsrfCookie() {
  await csrfClient.get('/sanctum/csrf-cookie')
  // Edge / Windows: axios doesn't always pick up the updated cookie automatically
  syncXsrfHeaderFromCookie()
}
let authProbePromise = null

async function probeActiveSession() {
  if (authProbePromise) {
    return authProbePromise
  }

  authProbePromise = api
    .get('/auth/me', {
      timeout: 6000,
      withCredentials: true,
      meta: { skipErrorToast: true, skipAuthRedirect: true },
    })
    .then(({ data }) => {
      const payload = data && typeof data === 'object' ? data : null
      return Number(payload?.id || 0) > 0
    })
    .catch((probeError) => {
      const status = Number(probeError?.response?.status || 0)
      if (status === 401 || status === 419) {
        return false
      }

      return null
    })
    .finally(() => {
      authProbePromise = null
    })

  return authProbePromise
}

function isLongRunningPath(url) {
  const normalized = String(url || '').toLowerCase()
  if (normalized === '') {
    return false
  }

  const isSingleCandidateRetranslate =
    normalized.includes('/admin/event-candidates/')
    && normalized.includes('/retranslate')
    && !normalized.includes('/retranslate-batch')
  const isSingleCandidateApprove =
    normalized.includes('/admin/event-candidates/')
    && normalized.includes('/approve')
    && !normalized.includes('/approve-batch')

  const isAdminEventAiGenerate =
    normalized.includes('/admin/events/') &&
    normalized.includes('/ai/generate-description')
  const isAdminBlogAiTagSuggest =
    normalized.includes('/admin/blog-posts/') &&
    normalized.includes('/ai/suggest-tags')

  return (
    isAdminEventAiGenerate ||
    isAdminBlogAiTagSuggest ||
    normalized.includes('/admin/bots/run/') ||
    normalized.includes('/admin/bots/quick-run') ||
    normalized.includes('/admin/event-sources/run') ||
    normalized.includes('/admin/event-sources/purge') ||
    normalized.includes('/admin/event-sources/translation-artifacts/repair') ||
    isSingleCandidateRetranslate ||
    isSingleCandidateApprove ||
    normalized.includes('/admin/event-candidates/approve-batch') ||
    normalized.includes('/admin/event-candidates/retranslate-batch') ||
    normalized.includes('/admin/manual-events/publish-batch') ||
    normalized.includes('/admin/performance-metrics/run')
  )
}

function isSlowSkyWidgetPath(url) {
  const normalized = String(url || '').toLowerCase()
  if (normalized === '') return false

  return (
    normalized.includes('/sky/moon-phases') ||
    normalized.includes('/sky/moon-overview') ||
    normalized.includes('/sky/moon-events')
  )
}

const toast = useToast()
let lastErrorToastKey = ''
let lastErrorToastAt = 0

function shouldShowErrorToast(message, status) {
  const now = Date.now()
  const key = `${status || 0}:${String(message || '')}`
  if (key === lastErrorToastKey && now - lastErrorToastAt < 1500) {
    return false
  }

  lastErrorToastKey = key
  lastErrorToastAt = now
  return true
}

export function shouldRedirectToLogin(error) {
  if (error?.config?.meta?.skipAuthRedirect === true || error?.config?.skipAuthRedirect === true) return false

  const requestUrl = String(error?.config?.url || '').toLowerCase()
  if (requestUrl.includes('/auth/me')) return true

  if (error?.config?.meta?.authCritical === true || error?.config?.authCritical === true) return true
  if (error?.config?.meta?.requiresAuth === true) return true
  return false
}

function resolveBackendErrorCode(error) {
  const direct = error?.response?.data?.error_code
  if (typeof direct === 'string' && direct.trim()) return direct.trim()

  const legacy = error?.response?.data?.code
  if (typeof legacy === 'string' && legacy.trim()) return legacy.trim()

  return ''
}

function resolveBackendAction(error) {
  const direct = error?.response?.data?.action
  if (typeof direct === 'string' && direct.trim()) return direct.trim()
  return ''
}

function isVerificationError(error, status, message) {
  const code = resolveBackendErrorCode(error)
  const action = resolveBackendAction(error)

  if (action === 'GO_TO_SETTINGS_EMAIL') return true
  if (code === 'EMAIL_NOT_VERIFIED' || code === 'EMAIL_VERIFY_DEPRECATED') return true
  if (status !== 403 && status !== 410) return false

  const normalized = String(message || '').toLowerCase()
  return normalized.includes('verified') || normalized.includes('verify') || normalized.includes('email address is not verified')
}

function redirectToEmailSettingsIfNeeded() {
  if (typeof window === 'undefined') return
  const pathname = window.location.pathname || ''
  if (pathname.startsWith('/settings')) return

  window.location.assign('/settings/email')
}

function redirectToLoginIfNeeded() {
  if (typeof window === 'undefined') return
  const pathname = window.location.pathname || ''
  if (pathname === '/login') return
  const redirect = encodeURIComponent(window.location.pathname + window.location.search)
  window.location.assign(`/login?redirect=${redirect}`)
}

function normalizeHttpErrorMessage(error) {
  const status = Number(error?.response?.status || 0)
  const code = String(error?.code || '')
  const message = String(error?.message || '')

  const isTimeoutError = code === 'ECONNABORTED' || message.toLowerCase().includes('timeout')
  if (isTimeoutError) {
    return 'Server neodpovedá. Skús to znova neskôr.'
  }

  if (status === 413) {
    return 'Nahravanie zlyhalo. Subor alebo cely upload je prilis velky. Zmensi subory alebo pocet priloh a skus to znova.'
  }

  const isNetworkError = !status && (code === 'ERR_NETWORK' || message === 'Network Error' || message.toLowerCase().includes('network'))
  if (isNetworkError) {
    if (isFormDataPayload(error?.config?.data)) {
      return 'Nahravanie zlyhalo. Upload bol odmietnuty alebo je prilis velky. Zmensi subor alebo pocet priloh a skus to znova.'
    }

    return 'Backend je nedostupný. Skontroluj, či beží API server.'
  }

  if (status >= 500) {
    return 'Chyba servera. Skús to neskôr.'
  }

  return String(error?.response?.data?.message || message || 'Požiadavka zlyhala.')
}

function isFormDataPayload(payload) {
  return typeof FormData !== 'undefined' && payload instanceof FormData
}

function logDevAuthDiagnostic(error, status) {
  if (!import.meta.env.DEV) return
  if (status !== 401 && status !== 419) return

  const config = error?.config || {}
  const method = String(config?.method || 'get').toUpperCase()
  const requestUrl = String(config?.url || '')
  const baseURL = String(config?.baseURL || api.defaults.baseURL || '')
  const pathname = typeof window !== 'undefined' ? String(window.location.pathname || '') : ''
  const hasXsrfCookie = typeof document !== 'undefined'
    ? document.cookie.includes('XSRF-TOKEN=')
    : false

  console.warn('[auth-flow] request failed', {
    status,
    method,
    requestUrl,
    baseURL,
    pathname,
    requiresAuth: config?.meta?.requiresAuth === true,
    withCredentials: config?.withCredentials !== false,
    hasXsrfCookie,
    response: error?.response?.data || null,
  })
}

api.interceptors.request.use(async (config) => {
  const url = String(config?.url ?? '')
  const isBootstrapRequest = url.includes('/auth/me') || url.includes('csrf-cookie')

  if (!isBootstrapRequest) {
    const gate = globalThis['__astrokomunitaBootstrapPromise__']
    console.log('[bootstrap-gate] interceptor', {
      url,
      hasGate: Boolean(gate),
    })

    if (gate) {
      console.log('[bootstrap-gate] waiting', { url })
      await gate
      console.log('[bootstrap-gate] released', { url })
    }
  }

  let nextConfig = config

  if (isLongRunningPath(nextConfig?.url)) {
    const url = String(nextConfig?.url || '').toLowerCase()
    const isAdminEventAiGenerate =
      url.includes('/admin/events/') &&
      url.includes('/ai/generate-description')
    const isSingleCandidateRetranslate =
      url.includes('/admin/event-candidates/') &&
      url.includes('/retranslate') &&
      !url.includes('/retranslate-batch')
    const veryLongRunning =
      isAdminEventAiGenerate ||
      isSingleCandidateRetranslate ||
      url.includes('/admin/event-sources/run') ||
      url.includes('/admin/event-sources/purge') ||
      url.includes('/admin/event-sources/translation-artifacts/repair')

    nextConfig = {
      ...nextConfig,
      timeout: veryLongRunning ? 300000 : 120000,
    }
  }

  if (isSlowSkyWidgetPath(nextConfig?.url)) {
    nextConfig = {
      ...nextConfig,
      timeout: 45000,
    }
  }

  return nextConfig
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error?.response?.status
    const requestUrl = String(error?.config?.url || '')
    const suppressToast = Boolean(
      error?.config?.meta?.skipErrorToast ||
      error?.config?.skipErrorToast ||
      isSlowSkyWidgetPath(requestUrl),
    )

    const normalizedMessage = normalizeHttpErrorMessage(error)
    error.userMessage = normalizedMessage

    const isTimeoutOrNetwork =
      error?.code === 'ECONNABORTED' ||
      String(error?.message || '').toLowerCase().includes('timeout') ||
      (!status && (error?.code === 'ERR_NETWORK' || error?.message === 'Network Error'))

    if (isTimeoutOrNetwork) {
      error.message = normalizedMessage
    }

    logDevAuthDiagnostic(error, status)

    // Auto-refresh CSRF and retry once on 419 before showing any error
    if (status === 419 && !error?.config?._csrfRetried) {
      try {
        await refreshCsrfCookie()
        return api({ ...error.config, _csrfRetried: true })
      } catch {
        // refresh failed — fall through to toast
      }
    }

    if (!suppressToast) {
      if (status === 422) {
        toast.warn('Skontroluj formulár.')
      } else if (status === 413) {
        toast.warn(normalizedMessage)
      } else if (isVerificationError(error, status, normalizedMessage)) {
        const backendCode = resolveBackendErrorCode(error)
        const backendAction = resolveBackendAction(error)
        const shouldOfferSettingsLink =
          backendAction === 'GO_TO_SETTINGS_EMAIL' || !backendAction
        const verificationMessage =
          backendCode === 'EMAIL_VERIFY_DEPRECATED'
            ? 'Overenie cez odkaz už nie je podporované.'
            : 'Najprv over emailovú adresu.'

        toast.warn(verificationMessage, {
          action: shouldOfferSettingsLink
            ? {
                label: 'Otvoriť Nastavenia',
                onClick: () => redirectToEmailSettingsIfNeeded(),
              }
            : undefined,
        })
      } else if (status === 401) {
        if (shouldRedirectToLogin(error)) {
          const requestUrl = String(error?.config?.url || '').toLowerCase()
          const shouldProbeSession = requestUrl !== '' && !requestUrl.includes('/auth/me')
          let shouldRedirect = true

          if (shouldProbeSession) {
            const probeResult = await probeActiveSession()
            if (probeResult === true || probeResult === null) {
              shouldRedirect = false
            }
          }

          if (shouldRedirect) {
            toast.warn(error?.response?.data?.message || 'Relácia vypršala. Prihlás sa znova.')
            redirectToLoginIfNeeded()
          }
        }
      } else if (status === 419) {
        toast.warn(error?.response?.data?.message || 'Bezpečnostný token vypršal. Obnov stránku a skús to znova.')
      } else if (status >= 500 || isTimeoutOrNetwork) {
        if (shouldShowErrorToast(normalizedMessage, status)) {
          toast.error(normalizedMessage)
        }
      }
    }

    return Promise.reject(error)
  },
)

api.vote = (pollId, optionId, config = {}) =>
  api.post(`/polls/${pollId}/vote`, { option_id: optionId }, config)

api.fetchPoll = (pollId, config = {}) => api.get(`/polls/${pollId}`, config)

export default api
