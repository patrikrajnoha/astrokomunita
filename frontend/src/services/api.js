import axios from 'axios'

const api = axios.create({
  // base URL berieme z env, fallback nechávame pre istotu
  baseURL: import.meta.env.VITE_API_BASE_URL
    ? `${import.meta.env.VITE_API_BASE_URL}/api`
    : 'http://127.0.0.1:8000/api',

  withCredentials: true,

  // ✅ Sanctum: cookie -> header
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',

  // ✅ axios v1+: pošli XSRF aj pri cross-site (5173 → 8000)
  withXSRFToken: true,

  headers: {
    Accept: 'application/json',
  },
})

export default api
