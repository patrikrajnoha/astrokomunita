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

const authProbeClient = axios.create({
  baseURL: apiBaseUrl,
  timeout: 6000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})

let authProbePromise = null

async function probeActiveSession() {
  if (authProbePromise) {
    return authProbePromise
  }

  authProbePromise = authProbeClient
    .get('/auth/me', {
      withCredentials: true,
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

  return (
    normalized.includes('/admin/bots/run/') ||
    normalized.includes('/admin/bots/quick-run') ||
    normalized.includes('/admin/event-sources/run') ||
    normalized.includes('/admin/event-sources/purge') ||
    normalized.includes('/admin/event-sources/translation-artifacts/repair') ||
    normalized.includes('/admin/event-candidates/approve-batch') ||
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

function isProtectedPath(pathname) {
  return (
    pathname.startsWith('/settings') ||
    pathname.startsWith('/creator-studio') ||
    pathname.startsWith('/notifications') ||
    pathname.startsWith('/bookmarks') ||
    pathname.startsWith('/profile') ||
    pathname.startsWith('/admin')
  )
}

function shouldRedirectToLogin(error) {
  if (error?.config?.meta?.requiresAuth === true) return true
  if (typeof window === 'undefined') return false
  return isProtectedPath(window.location.pathname || '')
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
    return 'Server neodpoveda. Skus to znova neskor.'
  }

  const isNetworkError = !status && (code === 'ERR_NETWORK' || message === 'Network Error' || message.toLowerCase().includes('network'))
  if (isNetworkError) {
    return 'Backend je nedostupny. Skontroluj, ci bezi API server.'
  }

  if (status >= 500) {
    return 'Chyba servera. Skus to neskor.'
  }

  return String(error?.response?.data?.message || message || 'Poziadavka zlyhala.')
}

api.interceptors.request.use((config) => {
  if (isLongRunningPath(config?.url)) {
    const url = String(config?.url || '').toLowerCase()
    const veryLongRunning =
      url.includes('/admin/event-sources/run') ||
      url.includes('/admin/event-sources/purge') ||
      url.includes('/admin/event-sources/translation-artifacts/repair')

    return {
      ...config,
      timeout: veryLongRunning ? 300000 : 120000,
    }
  }

  if (isSlowSkyWidgetPath(config?.url)) {
    return {
      ...config,
      timeout: 45000,
    }
  }

  return config
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

    if (!suppressToast) {
      if (status === 422) {
        toast.warn('Skontroluj formular.')
      } else if (isVerificationError(error, status, normalizedMessage)) {
        const backendCode = resolveBackendErrorCode(error)
        const backendAction = resolveBackendAction(error)
        const shouldOfferSettingsLink =
          backendAction === 'GO_TO_SETTINGS_EMAIL' || !backendAction
        const verificationMessage =
          backendCode === 'EMAIL_VERIFY_DEPRECATED'
            ? 'Overenie cez odkaz uz nie je podporovane.'
            : 'Najprv over emailovu adresu.'

        toast.warn(verificationMessage, {
          action: shouldOfferSettingsLink
            ? {
                label: 'Otvorit Nastavenia',
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
            toast.warn(error?.response?.data?.message || 'Relacia vyprsala. Prihlas sa znova.')
            redirectToLoginIfNeeded()
          }
        }
      } else if (status === 419) {
        toast.warn(error?.response?.data?.message || 'Bezpecnostny token vyprsal. Obnov stranku a skus to znova.')
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
