import { defineStore } from 'pinia'
import http from '@/services/api'
import axios from 'axios'
import { clearHomeFeedPrefetch } from '@/services/feedPrefetch'

const AUTH_TIMEOUTS_MS = [5000, 8000]
const configuredApiBaseUrl = import.meta.env.DEV
  ? ''
  : (import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_API_URL || '')
const csrfBaseUrl = String(configuredApiBaseUrl).replace(/\/api\/?$/i, '').replace(/\/+$/, '')
const authDebugEnabled =
  import.meta.env.DEV && String(import.meta.env.VITE_DEBUG_AUTH || '').trim() === '1'
let activeFetchUserPromise = null
let activeFetchUserKey = ''

// Separate axios instance for CSRF (no baseURL)
const csrfHttp = axios.create({
  baseURL: csrfBaseUrl || '',
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
    isEditor: (s) => s.user?.role === 'editor',
    isAdminOrEditor() {
      return Boolean(this.isAdmin || this.isEditor)
    },
  },

  actions: {
    reset() {
      clearHomeFeedPrefetch()
      this.user = null
      this.loading = false
      this.initialized = true
      this.status = 'guest'
      this.bootstrapDone = true
      this.loginSequence += 1
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

        if (authDebugEnabled) {
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
      const preserveStateOnError = options.preserveStateOnError === true
      const maxAttempts = shouldRetry ? 2 : 1
      const authSequenceAtStart = this.loginSequence
      const requestKey = JSON.stringify({
        shouldRetry,
        markBootstrap,
        preserveStateOnError,
      })

      if (activeFetchUserPromise && activeFetchUserKey === requestKey) {
        return activeFetchUserPromise
      }

      const requestPromise = (async () => {
        const isStaleAuthSnapshot = () => this.loginSequence !== authSequenceAtStart
        const applyBootstrapFlags = () => {
          if (markBootstrap) {
            this.bootstrapDone = true
            this.initialized = true
          }
        }

        this.loading = true
        this.status = 'loading'
        this.error = null

        const endpointDebug = getAuthEndpointDebug()
        try {
          for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
            const timeout = AUTH_TIMEOUTS_MS[Math.min(attempt - 1, AUTH_TIMEOUTS_MS.length - 1)]

            if (authDebugEnabled) {
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

              if (isStaleAuthSnapshot()) {
                if (authDebugEnabled) {
                  console.info('[AUTH] fetchUser stale success ignored', {
                    source,
                    attempt,
                    type: 'stale',
                  })
                }

                if (this.user) {
                  this.status = 'authenticated'
                  this.error = null
                }

                applyBootstrapFlags()
                return this.user
              }

              const payload = data && typeof data === 'object' ? data : null
              const hasAuthenticatedUser = Number(payload?.id || 0) > 0

              if (!hasAuthenticatedUser) {
                this.user = null
                this.status = 'guest'
                this.error = null

                applyBootstrapFlags()

                return null
              }

              this.user = payload
              this.status = 'authenticated'
              this.error = null

              applyBootstrapFlags()

              return payload
            } catch (error) {
              const classified = classifyFetchUserError(error)

              if (isStaleAuthSnapshot()) {
                if (authDebugEnabled) {
                  console.info('[AUTH] fetchUser stale failure ignored', {
                    source,
                    attempt,
                    type: 'stale',
                    status: classified.status,
                    code: classified.code,
                  })
                }

                if (this.user) {
                  this.status = 'authenticated'
                  this.error = null
                }

                applyBootstrapFlags()
                return this.user
              }

              if (authDebugEnabled) {
                if (classified.type === 'unauthorized') {
                  console.info('[AUTH] fetchUser failed', {
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
                } else {
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
              }

              const canRetry =
                shouldRetry &&
                attempt < maxAttempts &&
                (classified.type === 'timeout' || classified.type === 'network')

              if (canRetry) {
                continue
              }

              const isTransientFailure =
                classified.type === 'timeout' || classified.type === 'network' || classified.type === 'server'
              const shouldPreserveProfileSaveUnauthorized =
                source === 'profile-save' &&
                !!this.user &&
                classified.type === 'unauthorized'

              if (shouldPreserveProfileSaveUnauthorized || (this.user && (preserveStateOnError || isTransientFailure))) {
                this.status = 'authenticated'
                this.error = null
                if (authDebugEnabled) {
                  console.info('[AUTH] fetchUser failure ignored (preserve state)', {
                    source,
                    type: classified.type,
                    status: classified.status,
                    code: classified.code,
                  })
                }
                return this.user
              }

              this.user = null

              if (
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

              applyBootstrapFlags()

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

          applyBootstrapFlags()

          return null
        } finally {
          this.loading = false
        }
      })()

      activeFetchUserKey = requestKey
      const trackedPromise = requestPromise.finally(() => {
        if (activeFetchUserPromise === trackedPromise) {
          activeFetchUserPromise = null
          activeFetchUserKey = ''
        }
      })
      activeFetchUserPromise = trackedPromise

      return trackedPromise
    },

    async bootstrapAuth() {
      if (this.bootstrapDone) return this.user
      return this.fetchUser({ source: 'bootstrap', retry: true, markBootstrap: true })
    },

    async retryFetchUser() {
      return this.fetchUser({ source: 'retry', retry: true, markBootstrap: true })
    },

    async login(payload) {
      clearHomeFeedPrefetch()
      this.loading = true
      try {
        const requestPayload = payload && typeof payload === 'object'
          ? { ...payload, remember: payload.remember ?? true }
          : { remember: true }
        const response = await this.postWithCsrfRetry('/auth/login', requestPayload)
        const loginUser = response?.data || null

        if (!loginUser) {
          const fallbackMessage = this.error?.message || 'Prihlásenie zlyhalo.'
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
        this.fetchUser({
          source: 'login-bg-refresh',
          retry: false,
          markBootstrap: false,
          preserveStateOnError: true,
        }).catch(() => {})
        return loginUser
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      clearHomeFeedPrefetch()
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
          this.fetchUser({
            source: 'register-bg-refresh',
            retry: false,
            markBootstrap: false,
            preserveStateOnError: true,
          }).catch(() => {})
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
        clearHomeFeedPrefetch()
        this.user = null
        this.loading = false
        this.initialized = true
        this.status = 'guest'
        this.bootstrapDone = true
        this.loginSequence += 1
        this.error = null
      }
    },
  },
})
