import { defineStore } from 'pinia'
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [],
    unreadCount: 0,
    loading: false,
    error: '',
    page: 1,
    lastPage: 1,
  }),

  getters: {
    unreadBadge: (state) => {
      if (!state.unreadCount) return ''
      return state.unreadCount > 99 ? '99+' : String(state.unreadCount)
    },
  },

  actions: {
    async fetchList(page = 1) {
      const auth = useAuthStore()
      if (!auth.isAuthed) {
        this.items = []
        this.page = 1
        this.lastPage = 1
        this.error = ''
        return
      }

      if (this.loading) return
      this.loading = true
      this.error = ''
      try {
        const res = await http.get('/notifications', {
          params: { page, per_page: 20 },
        })
        const payload = res?.data || {}
        const data = payload.data || []
        this.page = payload.meta?.current_page || page
        this.lastPage = payload.meta?.last_page || page
        this.items = page > 1 ? [...this.items, ...data] : data
      } catch (err) {
        const status = err?.response?.status
        if (status === 401) {
          this.error = 'Session expired. Please sign in again.'
        } else if (status === 403) {
          this.error = 'Account does not have access to notifications.'
        } else {
          this.error = err?.response?.data?.message || 'Failed to load notifications.'
        }
        console.warn('Notifications fetch failed:', err?.message || err)
      } finally {
        this.loading = false
      }
    },

    async fetchUnreadCount() {
      const auth = useAuthStore()
      if (!auth.isAuthed) {
        this.unreadCount = 0
        return
      }

      try {
        const res = await http.get('/notifications/unread-count')
        this.unreadCount = res?.data?.count ?? 0
      } catch (err) {
        const status = err?.response?.status
        if (status === 403 && !this.error) {
          this.error = 'Account does not have access to notifications.'
        }
        console.warn('Unread count fetch failed:', err?.message || err)
      }
    },

    async markRead(id) {
      const auth = useAuthStore()
      if (!auth.isAuthed) return
      const target = this.items.find((item) => item.id === id)
      if (target && !target.read_at) {
        target.read_at = new Date().toISOString()
        this.unreadCount = Math.max(0, this.unreadCount - 1)
      }

      try {
        await auth.csrf()
        await http.post(`/notifications/${id}/read`)
      } catch (err) {
        console.warn('Mark read failed:', err?.message || err)
        if (target && target.read_at) {
          target.read_at = null
          this.unreadCount += 1
        }
      }
    },

    async markAllRead() {
      const auth = useAuthStore()
      if (!auth.isAuthed) return
      const hadUnread = this.items.filter((item) => !item.read_at)
      const prevCount = this.unreadCount
      const nowIso = new Date().toISOString()
      this.items = this.items.map((item) => ({
        ...item,
        read_at: item.read_at || nowIso,
      }))
      this.unreadCount = 0

      try {
        await auth.csrf()
        await http.post('/notifications/read-all')
      } catch (err) {
        console.warn('Mark all read failed:', err?.message || err)
        hadUnread.forEach((item) => {
          const target = this.items.find((entry) => entry.id === item.id)
          if (target) target.read_at = null
        })
        this.unreadCount = prevCount
      }
    },
  },
})
