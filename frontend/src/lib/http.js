import axios from 'axios'
import { useToast } from '@/composables/useToast'

const rawApiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
const normalizedApiBaseUrl = rawApiBaseUrl.replace(/\/api\/?$/i, '')

export const http = axios.create({
  baseURL: `${normalizedApiBaseUrl}/api`,
  timeout: 15000,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

const toast = useToast()

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

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status
    const dataMessage = error?.response?.data?.message
    const isNetworkError = !error?.response && (error?.message === 'Network Error' || error?.code === 'ERR_NETWORK')
    const suppressToast = Boolean(error?.config?.meta?.skipErrorToast || error?.config?.skipErrorToast)

    if (!suppressToast) {
      if (status === 422) {
        toast.warn('Skontroluj formular.')
      } else if (status === 401 || status === 419) {
        if (shouldRedirectToLogin(error)) {
          toast.warn(dataMessage || 'Relacia vyprsala. Prihlas sa znova.')
          redirectToLoginIfNeeded()
        }
      } else if (status >= 500 || isNetworkError) {
        toast.error(dataMessage || (isNetworkError ? 'Sietova chyba. Skus to znova.' : 'Server error. Skus to neskor.'))
      }
    }

    return Promise.reject(error)
  },
)
