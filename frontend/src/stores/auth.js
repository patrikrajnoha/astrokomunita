import { defineStore } from 'pinia'
import { http } from '@/lib/http'
import axios from 'axios'

const AUTH_TIMEOUTS_MS = [8000, 15000]

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

function getAuthEndpointDebug() {
  const baseURL = String(http?.defaults?.baseURL || '')
  const cleanBase = baseURL.replace(/\/+$/, '')
  return {
    baseURL,
    url: `${cleanBase}/auth/me`,
  }
}

function classifyFetchUserError(error) {
  const status = Number(error?.response?.status || 0)
  const code = String(error?.code || '')
  const message = String(error?.message || 'Request failed')

  if (status === 401 || status === 419) {
    return { type: 'unauthorized', status, code, message }
  }

  if (code === 'ECONNABORTED' || message.toLowerCase().includes('timeout')) {
    return { type: 'timeout', status, code, message }
  }

  if (!status && (code === 'ERR_NETWORK' || message.toLowerCase().includes('network'))) {
    return { type: 'network', status, code, message }
  }

  return { type: 'server', status, code, message }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
    initialized: false,
    status: 'idle', // idle|loading|authenticated|guest|error
    bootstrapDone: false,
    error: null,
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
      this.status = 'guest'
      this.bootstrapDone = true
      this.error = null
    },

    async csrf() {
      await csrfHttp.get('/sanctum/csrf-cookie')

      // Edge/Windows: set X-XSRF-TOKEN header from cookie
      const xsrf = getCookie('XSRF-TOKEN')
      if (xsrf) {
        http.defaults.headers.common['X-XSRF-TOKEN'] = xsrf
      }
    },

    async fetchUser(options = {}) {
      const source = String(options.source || 'manual')
      const shouldRetry = options.retry !== false
      const markBootstrap = options.markBootstrap !== false
      const maxAttempts = shouldRetry ? 2 : 1

      this.loading = true
      this.status = 'loading'
      this.error = null

      const endpointDebug = getAuthEndpointDebug()

      for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
        const timeout = AUTH_TIMEOUTS_MS[Math.min(attempt - 1, AUTH_TIMEOUTS_MS.length - 1)]

        if (import.meta.env.DEV) {
          console.info(
            `[AUTH] fetchUser start source=${source} attempt=${attempt}/${maxAttempts} url=${endpointDebug.url} baseURL=${endpointDebug.baseURL} timeout=${timeout}`,
          )
        }

        try {
          const { data } = await http.get('/auth/me', {
            timeout,
            withCredentials: true,
            meta: { skipErrorToast: true },
          })

          this.user = data
          this.status = 'authenticated'
          this.error = null

          if (markBootstrap) {
            this.bootstrapDone = true
            this.initialized = true
          }

          return data
        } catch (error) {
          const classified = classifyFetchUserError(error)

          if (import.meta.env.DEV) {
            console.warn('[AUTH] fetchUser failed', {
              source,
              attempt,
              ...endpointDebug,
              timeout,
              type: classified.type,
              status: classified.status,
              code: classified.code,
              message: classified.message,
              response: error?.response?.data || null,
            })
          }

          const canRetry =
            shouldRetry &&
            attempt < maxAttempts &&
            (classified.type === 'timeout' || classified.type === 'network')

          if (canRetry) {
            continue
          }

          this.user = null

          if (classified.type === 'timeout' || classified.type === 'network' || classified.type === 'unauthorized') {
            this.status = 'guest'
          } else {
            this.status = 'error'
          }

          this.error = {
            type: classified.type,
            message: classified.message,
            status: classified.status || null,
            code: classified.code || null,
          }

          if (markBootstrap) {
            this.bootstrapDone = true
            this.initialized = true
          }

          return null
        }
      }

      this.user = null
      this.status = 'guest'
      this.error = {
        type: 'unknown',
        message: 'fetchUser failed',
        status: null,
        code: null,
      }

      if (markBootstrap) {
        this.bootstrapDone = true
        this.initialized = true
      }

      return null
    },

    async bootstrapAuth() {
      if (this.loading || this.bootstrapDone) {
        return
      }

      await this.fetchUser({ source: 'bootstrap', retry: true, markBootstrap: true })
    },

    async retryFetchUser() {
      return this.fetchUser({ source: 'retry', retry: true, markBootstrap: true })
    },

    async login(payload) {
      this.loading = true
      try {
        await this.csrf()
        await http.post('/auth/login', payload)
        await this.fetchUser({ source: 'login', retry: false, markBootstrap: true })
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      this.loading = true
      try {
        await this.csrf()
        await http.post('/auth/register', payload)
        await this.fetchUser({ source: 'register', retry: false, markBootstrap: true })
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
        // 401/419 is fine, local cleanup still runs
      } finally {
        this.user = null
        this.loading = false
        this.initialized = true
        this.status = 'guest'
        this.bootstrapDone = true
        this.error = null
      }
    },
  },
})
