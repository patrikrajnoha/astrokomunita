import { defineStore } from 'pinia'
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { disconnectEcho, getEcho, initEcho } from '@/realtime/echo'

let activePrivateChannel = ''
let restrictionFlowActive = false

function normalizeNotificationPayload(payload) {
  if (!payload || typeof payload !== 'object') return null
  if (!payload.id) return null

  return {
    id: payload.id,
    type: payload.type || 'notification',
    data: payload.data && typeof payload.data === 'object' ? payload.data : {},
    read_at: payload.read_at ?? null,
    created_at: payload.created_at ?? new Date().toISOString(),
    created_human: payload.created_human ?? null,
    target: payload.target && typeof payload.target === 'object' ? payload.target : null,
  }
}

function toastMessageFor(notification) {
  if (notification.type === 'event_invite') {
    const inviter = notification.data?.actor_name || notification.data?.actor_username || 'Someone'
    return `${inviter} sent you an event invite.`
  }

  if (notification.type === 'contest_winner') {
    return 'You won a contest.'
  }

  if (notification.type === 'account_restricted') {
    return 'Your account has been restricted.'
  }

  return 'New notification'
}

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [],
    unreadCount: 0,
    loading: false,
    error: '',
    page: 1,
    lastPage: 1,
    realtimeReady: false,
    realtimeChannel: '',
  }),

  getters: {
    unreadBadge: (state) => {
      if (!state.unreadCount) return ''
      return state.unreadCount > 99 ? '99+' : String(state.unreadCount)
    },
  },

  actions: {
    resetState() {
      this.items = []
      this.unreadCount = 0
      this.loading = false
      this.error = ''
      this.page = 1
      this.lastPage = 1
    },

    async fetchList(page = 1) {
      const auth = useAuthStore()
      if (!auth.isAuthed) {
        this.resetState()
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

    applyRealtimeNotification(payload, options = {}) {
      const notification = normalizeNotificationPayload(payload)
      if (!notification) return

      const existingIndex = this.items.findIndex((item) => Number(item.id) === Number(notification.id))
      if (existingIndex === -1) {
        this.items = [notification, ...this.items]
        if (!notification.read_at) {
          this.unreadCount += 1
        }
      } else {
        const existing = this.items[existingIndex]
        const wasUnread = !existing.read_at
        const isUnread = !notification.read_at

        this.items.splice(existingIndex, 1, {
          ...existing,
          ...notification,
        })

        if (!wasUnread && isUnread) {
          this.unreadCount += 1
        } else if (wasUnread && !isUnread) {
          this.unreadCount = Math.max(0, this.unreadCount - 1)
        }
      }

      if (options.toast !== false) {
        const { info } = useToast()
        info(toastMessageFor(notification), { duration: 2600 })
      }

      if (notification.type === 'account_restricted') {
        this.handleAccountRestricted(notification)
      }
    },

    async handleAccountRestricted(notification) {
      if (restrictionFlowActive) return
      restrictionFlowActive = true

      const auth = useAuthStore()

      try {
        await auth.logout()
      } catch {
        // Local cleanup below is still enough.
      } finally {
        this.stopRealtime({ disconnect: true, clearState: true })

        if (typeof window !== 'undefined') {
          const params = new URLSearchParams()
          params.set('restricted', '1')

          const reason = String(notification?.data?.reason || '').trim()
          if (reason) {
            params.set('reason', reason)
          }

          window.location.assign(`/login?${params.toString()}`)
        }
      }
    },

    async startRealtime(options = {}) {
      const auth = useAuthStore()
      const userId = Number(auth.user?.id || 0)

      if (!auth.isAuthed || !userId) {
        this.stopRealtime({ disconnect: options.disconnectOnGuest === true })
        return
      }

      const channelName = `users.${userId}`
      if (this.realtimeReady && activePrivateChannel === channelName) {
        return
      }

      this.stopRealtime({ disconnect: false })

      const echo = initEcho()
      if (!echo) {
        this.realtimeReady = false
        this.realtimeChannel = ''
        return
      }

      activePrivateChannel = channelName
      this.realtimeReady = true
      this.realtimeChannel = channelName

      echo.private(channelName).listen('.notification.created', (eventPayload) => {
        const payload = eventPayload?.notification ?? eventPayload
        this.applyRealtimeNotification(payload, {
          toast: typeof window !== 'undefined' && window.location.pathname !== '/notifications',
        })
      })
    },

    stopRealtime(options = {}) {
      const echo = getEcho()
      if (echo && activePrivateChannel) {
        echo.leaveChannel(`private-${activePrivateChannel}`)
      }

      activePrivateChannel = ''
      this.realtimeReady = false
      this.realtimeChannel = ''
      restrictionFlowActive = false

      if (options.disconnect) {
        disconnectEcho()
      }

      if (options.clearState) {
        this.resetState()
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
