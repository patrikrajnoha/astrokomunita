import axios from 'axios'
import { useToast } from '@/composables/useToast'

const rawApiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
const normalizedApiBaseUrl = rawApiBaseUrl.replace(/\/api\/?$/i, '')

const api = axios.create({
  baseURL: `${normalizedApiBaseUrl}/api`,
  timeout: 15000,
  withCredentials: true,

  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  withXSRFToken: true,

  headers: {
    Accept: 'application/json',
  },
})

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
    pathname.startsWith('/profile') ||
    pathname.startsWith('/admin')
  )
}

function shouldRedirectToLogin(error) {
  if (error?.config?.meta?.requiresAuth === true) return true
  if (typeof window === 'undefined') return false
  return isProtectedPath(window.location.pathname || '')
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
    return 'Server error. Skus to neskor.'
  }

  return String(error?.response?.data?.message || message || 'Request failed.')
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status
    const suppressToast = Boolean(error?.config?.meta?.skipErrorToast || error?.config?.skipErrorToast)

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
      } else if (status === 401 || status === 419) {
        if (shouldRedirectToLogin(error)) {
          toast.warn(error?.response?.data?.message || 'Relacia vyprsala. Prihlas sa znova.')
          redirectToLoginIfNeeded()
        }
      } else if (status >= 500 || isTimeoutOrNetwork) {
        if (shouldShowErrorToast(normalizedMessage, status)) {
          toast.error(normalizedMessage)
        }
      }
    }

    return Promise.reject(error)
  },
)

export default api
