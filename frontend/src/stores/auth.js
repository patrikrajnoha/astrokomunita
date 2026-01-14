import { defineStore } from 'pinia'
import { http } from '@/lib/http'

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
  },

  actions: {
    reset() {
      this.user = null
      this.loading = false
      this.initialized = true
    },

    async csrf() {
      await http.get('/sanctum/csrf-cookie')

      // Edge/Windows: natvrdo nastav X-XSRF-TOKEN header z cookie
      const xsrf = getCookie('XSRF-TOKEN')
      if (xsrf) {
        http.defaults.headers.common['X-XSRF-TOKEN'] = xsrf
      }
    },

    async fetchUser() {
      try {
        const { data } = await http.get('/api/auth/me')
        this.user = data
      } catch (e) {
        if (e?.response?.status !== 401) console.error('fetchUser error:', e)
        this.user = null
      } finally {
        this.initialized = true
      }
    },

    async login(payload) {
      this.loading = true
      try {
        await this.csrf()
        await http.post('/api/auth/login', payload)
        await this.fetchUser()
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      this.loading = true
      try {
        await this.csrf()
        await http.post('/api/auth/register', payload)
        await this.fetchUser()
      } finally {
        this.loading = false
      }
    },

    async logout() {
      this.loading = true
      try {
        await this.csrf()
        await http.post('/api/auth/logout')
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
