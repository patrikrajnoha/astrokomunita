import { defineStore } from 'pinia'
import { http } from '@/lib/http'
import axios from 'axios'

// Separate axios instance for CSRF (no baseURL)
const csrfHttp = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

function getCookie(name) {
  const row = document.cookie.split('; ').find((r) => r.startsWith(name + '='))
  return row ? decodeURIComponent(row.split('=')[1]) : null
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
    initialized: false,
  }),

  getters: {
    isAuthed: (s) => !!s.user,
    isAdmin: (s) => s.user?.role === 'admin' || s.user?.is_admin,
  },

  actions: {
    reset() {
      this.user = null
      this.loading = false
      this.initialized = true
    },

    async csrf() {
      await csrfHttp.get('/sanctum/csrf-cookie')

      // Edge/Windows: natvrdo nastav X-XSRF-TOKEN header z cookie
      const xsrf = getCookie('XSRF-TOKEN')
      if (xsrf) {
        http.defaults.headers.common['X-XSRF-TOKEN'] = xsrf
      }
    },

    async fetchUser() {
      try {
        const { data } = await http.get('/auth/me', { timeout: 8000, meta: { skipErrorToast: true } })
        this.user = data
      } catch (e) {
        if (e?.response?.status !== 401) {
          const details = e?.response?.data || e?.message || e
          console.error('[AUTH] fetchUser failed:', details)
        }
        this.user = null
      } finally {
        this.initialized = true
      }
    },

    async login(payload) {
      this.loading = true
      try {
        await this.csrf()
        await http.post('/auth/login', payload)
        await this.fetchUser()
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      this.loading = true
      try {
        await this.csrf()
        await http.post('/auth/register', payload)
        await this.fetchUser()
      } finally {
        this.loading = false
      }
    },

    async logout() {
      this.loading = true
      try {
        await this.csrf()
        await http.post('/auth/logout')
      } catch {
        // 401/419 nevadí – aj tak čistíme lokálny stav
      } finally {
        this.user = null
        this.loading = false
        this.initialized = true
      }
    },
  },
})
