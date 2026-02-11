import axios from 'axios'
import { useToast } from '@/composables/useToast'

const api = axios.create({
  // base URL berieme z env, fallback nechávame pre istotu
  baseURL: import.meta.env.VITE_API_BASE_URL
    ? `${import.meta.env.VITE_API_BASE_URL}/api`
    : 'http://127.0.0.1:8000/api',

  withCredentials: true,

  // ✅ Sanctum: cookie -> header
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  withXSRFToken: true,

  headers: {
    Accept: 'application/json',
  },
})

const toast = useToast()

function redirectToLoginIfNeeded() {
  if (typeof window === 'undefined') return
  const pathname = window.location.pathname || ''
  if (pathname === '/login') return
  const redirect = encodeURIComponent(window.location.pathname + window.location.search)
  window.location.assign(`/login?redirect=${redirect}`)
}

api.interceptors.response.use(
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

export default api
