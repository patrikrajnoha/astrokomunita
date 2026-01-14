import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  withCredentials: true,

  // ✅ dôležité pre Sanctum (cookie -> header)
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',

  // ✅ axios v1+ : pošli XSRF aj pri cross-site (localhost:5173 -> localhost:8000)
  withXSRFToken: true,

  headers: { Accept: 'application/json' },
})

export default api
