import axios from 'axios'
import { useToast } from '@/composables/useToast'

export const http = axios.create({
  baseURL: (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api',
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
        toast.warn(dataMessage || 'Relacia vyprsala. Prihlas sa znova.')
        redirectToLoginIfNeeded()
      } else if (status >= 500 || isNetworkError) {
        toast.error(dataMessage || (isNetworkError ? 'Sietova chyba. Skus to znova.' : 'Server error. Skus to neskor.'))
      }
    }

    return Promise.reject(error)
  },
)
