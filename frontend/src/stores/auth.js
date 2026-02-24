import { defineStore } from 'pinia'
import http from '@/services/api'
import axios from 'axios'

const AUTH_TIMEOUTS_MS = [5000, 8000]
const rawApiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
const csrfBaseUrl = rawApiBaseUrl.replace(/\/api\/?$/i, '')

// Separate axios instance for CSRF (no baseURL)
const csrfHttp = axios.create({
  baseURL: csrfBaseUrl,
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
  const responseMessage = String(error?.response?.data?.message || '')
  const message = responseMessage || String(error?.message || 'Request failed')
  const backendCode = String(error?.response?.data?.code || '')
  const reason = error?.response?.data?.reason ?? null
  const bannedAt = error?.response?.data?.banned_at ?? null

  if (status === 401 || status === 419) {
    return { type: 'unauthorized', status, code, message, backendCode, reason: null, bannedAt: null }
  }

  if (status === 403 && backendCode === 'ACCOUNT_BANNED') {
    return { type: 'banned', status, code, message, backendCode, reason, bannedAt }
  }

  if (status === 403 && backendCode === 'ACCOUNT_INACTIVE') {
    return { type: 'inactive', status, code, message, backendCode, reason: null, bannedAt: null }
  }

  if (code === 'ECONNABORTED' || message.toLowerCase().includes('timeout')) {
    return { type: 'timeout', status, code, message, backendCode, reason: null, bannedAt: null }
  }

  if (!status && (code === 'ERR_NETWORK' || message.toLowerCase().includes('network'))) {
    return { type: 'network', status, code, message, backendCode, reason: null, bannedAt: null }
  }

  return { type: 'server', status, code, message, backendCode, reason: null, bannedAt: null }
}

function isCsrfMismatch(error) {
  const status = Number(error?.response?.status || 0)
  const message = String(error?.response?.data?.message || error?.message || '').toLowerCase()
  return status === 419 || message.includes('csrf token mismatch')
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
    initialized: false,
    status: 'idle', // idle|loading|authenticated|guest|error
    bootstrapDone: false,
    loginSequence: 0,
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

    async postWithCsrfRetry(url, payload) {
      try {
        await this.csrf()
        return await http.post(url, payload)
      } catch (error) {
        if (!isCsrfMismatch(error)) {
          throw error
        }

        if (import.meta.env.DEV) {
          console.warn(`[AUTH] CSRF retry for ${url}`)
        }

        await this.csrf()
        return http.post(url, payload)
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
      try {
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

            if (
              classified.type === 'timeout' ||
              classified.type === 'network' ||
              classified.type === 'unauthorized' ||
              classified.type === 'banned' ||
              classified.type === 'inactive'
            ) {
              this.status = 'guest'
            } else {
              this.status = 'error'
            }

            this.error = {
              type: classified.type,
              message: classified.message,
              status: classified.status || null,
              code: classified.code || null,
              backendCode: classified.backendCode || null,
              reason: classified.reason || null,
              bannedAt: classified.bannedAt || null,
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
          backendCode: null,
          reason: null,
          bannedAt: null,
        }

        if (markBootstrap) {
          this.bootstrapDone = true
          this.initialized = true
        }

        return null
      } finally {
        this.loading = false
      }
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
        const response = await this.postWithCsrfRetry('/auth/login', payload)
        const loginUser = response?.data || null

        if (!loginUser) {
          const fallbackMessage = this.error?.message || 'Prihlasenie zlyhalo.'
          const loginFailure = new Error(fallbackMessage)
          loginFailure.authError = this.error
          throw loginFailure
        }

        this.user = loginUser
        this.status = 'authenticated'
        this.error = null
        this.bootstrapDone = true
        this.initialized = true
        this.loginSequence += 1

        // Non-blocking refresh for enriched payload (/auth/me adds activity fields).
        this.fetchUser({ source: 'login-bg-refresh', retry: false, markBootstrap: false }).catch(() => {})
        return loginUser
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      this.loading = true
      try {
        const response = await this.postWithCsrfRetry('/auth/register', payload)
        const registerUser = response?.data || null

        if (registerUser) {
          this.user = registerUser
          this.status = 'authenticated'
          this.error = null
          this.bootstrapDone = true
          this.initialized = true
          this.loginSequence += 1
          this.fetchUser({ source: 'register-bg-refresh', retry: false, markBootstrap: false }).catch(() => {})
          return
        }

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
