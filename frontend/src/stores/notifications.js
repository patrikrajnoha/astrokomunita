import { defineStore } from 'pinia'
import http from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { disconnectEcho, getEcho, initEcho } from '@/realtime/echo'

let activePrivateChannel = ''
let restrictionFlowActive = false
const UNREAD_COUNT_FRESH_MS = 30 * 1000

function normalizeNotificationId(value) {
  const id = Number(value || 0)
  return Number.isInteger(id) && id > 0 ? id : 0
}

function normalizeNotificationPayload(payload) {
  if (!payload || typeof payload !== 'object') return null
  const id = normalizeNotificationId(payload.id)
  if (!id) return null

  return {
    id,
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
    const inviter = notification.data?.actor_name || notification.data?.actor_username || 'Niekto'
    return `${inviter} ta pozval na astronomicke podujatie.`
  }

  if (notification.type === 'event_invite_response') {
    const actor = notification.data?.actor_name || notification.data?.actor_username || 'Niekto'
    const status = String(notification.data?.response_status || '').toLowerCase()
    if (status === 'accepted') return `${actor} prijal tvoju pozvanku.`
    if (status === 'declined') return `${actor} odmietol tvoju pozvanku.`
    return `${actor} odpovedal na tvoju pozvanku.`
  }

  if (notification.type === 'contest_winner') {
    return 'Vyhral si sutaz.'
  }

  if (notification.type === 'account_restricted') {
    return 'Tvoj ucet bol obmedzeny.'
  }

  return 'Nova notifikacia'
}

function notificationCreatedAtTimestamp(item) {
  const ts = Date.parse(String(item?.created_at || ''))
  return Number.isFinite(ts) ? ts : 0
}

function mergeNotificationRecords(existing, incoming) {
  const existingTs = notificationCreatedAtTimestamp(existing)
  const incomingTs = notificationCreatedAtTimestamp(incoming)
  const newer = incomingTs >= existingTs ? incoming : existing
  const older = newer === incoming ? existing : incoming
  const olderData = older?.data && typeof older.data === 'object' ? older.data : {}
  const newerData = newer?.data && typeof newer.data === 'object' ? newer.data : {}

  return {
    ...older,
    ...newer,
    data: {
      ...olderData,
      ...newerData,
    },
    read_at: newer.read_at ?? older.read_at ?? null,
    created_at: newer.created_at ?? older.created_at ?? null,
    created_human: newer.created_human ?? older.created_human ?? null,
    target: newer.target ?? older.target ?? null,
  }
}

function mergeNotificationsById(...collections) {
  const byId = new Map()

  collections.forEach((collection) => {
    if (!Array.isArray(collection)) return

    collection.forEach((rawItem) => {
      const item = normalizeNotificationPayload(rawItem)
      if (!item) return

      const id = item.id
      if (!byId.has(id)) {
        byId.set(id, item)
        return
      }

      byId.set(id, mergeNotificationRecords(byId.get(id), item))
    })
  })

  return Array.from(byId.values())
    .filter(Boolean)
    .sort((left, right) => {
      const leftTs = notificationCreatedAtTimestamp(left)
      const rightTs = notificationCreatedAtTimestamp(right)
      if (leftTs !== rightTs) {
        return rightTs - leftTs
      }

      return normalizeNotificationId(right?.id) - normalizeNotificationId(left?.id)
    })
}

function applyReadState(list, id, readAt) {
  const normalizedId = normalizeNotificationId(id)
  if (!normalizedId || !Array.isArray(list) || list.length === 0) return list

  let changed = false
  const next = list.map((item) => {
    const itemId = normalizeNotificationId(item?.id)
    if (itemId !== normalizedId) return item
    changed = true
    return {
      ...item,
      read_at: readAt,
    }
  })

  return changed ? next : list
}

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [],
    latestItems: [],
    latestLimit: 10,
    unreadCount: 0,
    unreadCountHydrated: false,
    loading: false,
    loadingMore: false,
    error: '',
    latestLoading: false,
    latestError: '',
    latestLoaded: false,
    unreadCountLoading: false,
    unreadCountFetchedAt: 0,
    unreadCountFetchSeq: 0,
    markAllReading: false,
    fetchingPages: [],
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
      this.latestItems = []
      this.unreadCount = 0
      this.unreadCountHydrated = false
      this.latestLimit = 10
      this.loading = false
      this.loadingMore = false
      this.error = ''
      this.latestLoading = false
      this.latestError = ''
      this.latestLoaded = false
      this.unreadCountLoading = false
      this.unreadCountFetchedAt = 0
      this.unreadCountFetchSeq = 0
      this.markAllReading = false
      this.fetchingPages = []
      this.page = 1
      this.lastPage = 1
    },

    isPageFetching(page) {
      const target = Number(page || 0)
      if (!Number.isInteger(target) || target <= 0) return false
      return this.fetchingPages.includes(target)
    },

    async fetchList(page = 1, options = {}) {
      const auth = useAuthStore()
      if (!auth.isAuthed) {
        this.resetState()
        return
      }

      const normalizedPage = Number(page || 1)
      if (!Number.isInteger(normalizedPage) || normalizedPage <= 0) return

      const perPage = Math.max(1, Math.min(50, Number(options?.perPage || 20)))
      if (this.isPageFetching(normalizedPage)) return

      this.fetchingPages = [...this.fetchingPages, normalizedPage]
      if (normalizedPage > 1) {
        this.loadingMore = true
      } else {
        this.loading = true
        this.error = ''
      }

      try {
        const res = await http.get('/notifications', {
          params: { page: normalizedPage, per_page: perPage },
          meta: { skipErrorToast: true },
        })
        const payload = res?.data || {}
        const rows = Array.isArray(payload.data) ? payload.data : []
        this.page = payload.meta?.current_page || normalizedPage
        this.lastPage = payload.meta?.last_page || normalizedPage

        this.items = normalizedPage > 1
          ? mergeNotificationsById(this.items, rows)
          : mergeNotificationsById(rows, this.items)
        this.latestItems = this.items.slice(0, this.latestLimit)
        this.latestLoaded = true

        if (normalizedPage === 1 && options?.refreshUnread !== false) {
          void this.fetchUnreadCount()
        }
      } catch (err) {
        const status = err?.response?.status
        if (status === 401) {
          this.error = 'Relacia vyprsala. Prihlas sa znova.'
        } else if (status === 403) {
          this.error = 'Tento ucet nema pristup k notifikaciam.'
        } else {
          this.error = err?.response?.data?.message || 'Nepodarilo sa nacitat notifikacie.'
        }
        console.warn('Notifications fetch failed:', err?.message || err)
      } finally {
        this.fetchingPages = this.fetchingPages.filter((entry) => entry !== normalizedPage)
        this.loading = this.fetchingPages.includes(1)
        this.loadingMore = this.fetchingPages.some((entry) => entry > 1)
      }
    },

    async fetchLatest(limit = 10, options = {}) {
      const auth = useAuthStore()
      if (!auth.isAuthed) {
        this.latestItems = []
        this.latestError = ''
        this.latestLoaded = true
        return
      }

      const normalizedLimit = Math.max(1, Math.min(50, Number(limit || this.latestLimit || 10)))
      this.latestLimit = normalizedLimit

      if (this.latestLoading) return
      if (this.latestLoaded && options?.force !== true && this.latestItems.length > 0) return

      this.latestLoading = true
      this.latestError = ''

      try {
        const res = await http.get('/notifications', {
          params: { page: 1, per_page: normalizedLimit },
          meta: { skipErrorToast: true },
        })
        const payload = res?.data || {}
        const rows = Array.isArray(payload.data) ? payload.data : []

        this.latestItems = mergeNotificationsById(rows, this.latestItems).slice(0, normalizedLimit)
        this.items = mergeNotificationsById(this.latestItems, this.items)
        this.latestLoaded = true

        if (options?.refreshUnread !== false) {
          void this.fetchUnreadCount()
        }
      } catch (err) {
        const status = err?.response?.status
        if (status === 401) {
          this.latestError = 'Relacia vyprsala. Prihlas sa znova.'
        } else if (status === 403) {
          this.latestError = 'Tento ucet nema pristup k notifikaciam.'
        } else {
          this.latestError = err?.response?.data?.message || 'Nepodarilo sa nacitat notifikacie.'
        }
        console.warn('Latest notifications fetch failed:', err?.message || err)
      } finally {
        this.latestLoading = false
      }
    },

    async fetchUnreadCount(options = {}) {
      const auth = useAuthStore()
      if (!auth.isAuthed) {
        this.unreadCountFetchSeq += 1
        this.unreadCount = 0
        this.unreadCountLoading = false
        this.unreadCountFetchedAt = 0
        this.unreadCountHydrated = true
        return
      }

      const hasFreshUnreadCount =
        this.unreadCountHydrated &&
        this.unreadCountFetchedAt > 0 &&
        Date.now() - this.unreadCountFetchedAt < UNREAD_COUNT_FRESH_MS

      if (hasFreshUnreadCount && options?.force !== true) {
        return this.unreadCount
      }

      if (this.unreadCountLoading && options?.force !== true) return

      const fetchSeq = this.unreadCountFetchSeq + 1
      this.unreadCountFetchSeq = fetchSeq
      this.unreadCountLoading = true

      try {
        const res = await http.get('/notifications/unread-count', {
          meta: { skipErrorToast: true },
        })
        if (fetchSeq !== this.unreadCountFetchSeq) return
        this.unreadCount = res?.data?.count ?? 0
        this.unreadCountFetchedAt = Date.now()
        return this.unreadCount
      } catch (err) {
        if (fetchSeq !== this.unreadCountFetchSeq) return
        const status = err?.response?.status
        if (status === 403 && !this.error) {
          this.error = 'Tento ucet nema pristup k notifikaciam.'
        }
        console.warn('Unread count fetch failed:', err?.message || err)
      } finally {
        if (fetchSeq === this.unreadCountFetchSeq) {
          this.unreadCountLoading = false
          this.unreadCountHydrated = true
        }
      }
    },

    applyRealtimeNotification(payload, options = {}) {
      const notification = normalizeNotificationPayload(payload)
      if (!notification) return

      const existing = this.items.find((item) => normalizeNotificationId(item?.id) === notification.id)
      const wasUnread = existing ? !existing.read_at : false
      const isUnread = !notification.read_at

      this.items = mergeNotificationsById([notification], this.items)
      this.latestItems = mergeNotificationsById([notification], this.latestItems).slice(0, this.latestLimit)

      if (!existing && isUnread) {
        this.unreadCount += 1
      } else if (!wasUnread && isUnread) {
        this.unreadCount += 1
      } else if (wasUnread && !isUnread) {
        this.unreadCount = Math.max(0, this.unreadCount - 1)
      }

      this.unreadCountHydrated = true
      this.unreadCountFetchedAt = Date.now()

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

      const echo = await initEcho()
      if (!echo) {
        this.realtimeReady = false
        this.realtimeChannel = ''
        return
      }

      activePrivateChannel = channelName
      this.realtimeReady = true
      this.realtimeChannel = channelName

      const onCreated = (eventPayload) => {
        const payload = eventPayload?.notification ?? eventPayload
        this.applyRealtimeNotification(payload, {
          toast: typeof window !== 'undefined' && window.location.pathname !== '/notifications',
        })
      }

      const channel = echo.private(channelName)
      channel.listen('.notification.created', onCreated)
      channel.listen('NotificationCreated', onCreated)
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

      const normalizedId = normalizeNotificationId(id)
      if (!normalizedId) return

      const target = this.items.find((item) => normalizeNotificationId(item?.id) === normalizedId)
        || this.latestItems.find((item) => normalizeNotificationId(item?.id) === normalizedId)
      const previousReadAt = target?.read_at ?? null

      if (target && !target.read_at) {
        const nowIso = new Date().toISOString()
        this.items = applyReadState(this.items, normalizedId, nowIso)
        this.latestItems = applyReadState(this.latestItems, normalizedId, nowIso)
        this.unreadCount = Math.max(0, this.unreadCount - 1)
        this.unreadCountHydrated = true
        this.unreadCountFetchedAt = Date.now()
      }

      try {
        await auth.csrf()
        await http.post(`/notifications/${normalizedId}/read`, null, {
          meta: { skipErrorToast: true },
        })
      } catch (err) {
        console.warn('Mark read failed:', err?.message || err)
        if (target && !previousReadAt) {
          this.items = applyReadState(this.items, normalizedId, null)
          this.latestItems = applyReadState(this.latestItems, normalizedId, null)
          this.unreadCount += 1
        }
      }
    },

    async markAllRead() {
      const auth = useAuthStore()
      if (!auth.isAuthed) return
      if (this.markAllReading) return
      this.markAllReading = true
      const hadUnread = this.items.filter((item) => !item.read_at)
      const prevCount = this.unreadCount
      const nowIso = new Date().toISOString()
      const unreadIds = hadUnread.map((item) => normalizeNotificationId(item?.id)).filter((id) => id > 0)
      this.items = this.items.map((item) => (item.read_at ? item : { ...item, read_at: nowIso }))
      this.latestItems = this.latestItems.map((item) => (item.read_at ? item : { ...item, read_at: nowIso }))
      this.unreadCount = 0
      this.unreadCountHydrated = true
      this.unreadCountFetchedAt = Date.now()

      try {
        await auth.csrf()
        await http.post('/notifications/read-all', null, {
          meta: { skipErrorToast: true },
        })
        await this.fetchUnreadCount({ force: true })
      } catch (err) {
        console.warn('Mark all read failed:', err?.message || err)
        unreadIds.forEach((unreadId) => {
          this.items = applyReadState(this.items, unreadId, null)
          this.latestItems = applyReadState(this.latestItems, unreadId, null)
        })
        this.unreadCount = prevCount
      } finally {
        this.markAllReading = false
      }
    },
  },
})
