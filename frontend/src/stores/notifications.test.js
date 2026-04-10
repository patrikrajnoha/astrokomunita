import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useNotificationsStore } from '@/stores/notifications'
import http from '@/services/api'

const authState = vi.hoisted(() => ({
  bootstrapDone: true,
  isAuthed: true,
  user: { id: 7 },
  waitForBootstrap: vi.fn(async () => {}),
  csrf: vi.fn(async () => {}),
  logout: vi.fn(async () => {}),
}))

const infoToast = vi.hoisted(() => vi.fn())
const realtimeState = vi.hoisted(() => ({
  initEcho: vi.fn(() => null),
  getEcho: vi.fn(() => null),
  disconnectEcho: vi.fn(),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    defaults: { baseURL: 'http://127.0.0.1:8000/api' },
  },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

vi.mock('@/realtime/echo', () => ({
  initEcho: realtimeState.initEcho,
  getEcho: realtimeState.getEcho,
  disconnectEcho: realtimeState.disconnectEcho,
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    info: infoToast,
  }),
}))

describe('notifications store realtime handler', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    infoToast.mockClear()
    http.get.mockReset()
    http.post.mockReset()
    authState.bootstrapDone = true
    authState.isAuthed = true
    authState.user = { id: 7 }
    authState.waitForBootstrap.mockClear()
    authState.csrf.mockClear()
    authState.logout.mockClear()
    realtimeState.initEcho.mockReset()
    realtimeState.getEcho.mockReset()
    realtimeState.disconnectEcho.mockReset()
    realtimeState.initEcho.mockReturnValue(null)
    realtimeState.getEcho.mockReturnValue(null)
    useNotificationsStore().stopRealtime({ disconnect: true, clearState: true })
  })

  it('prepends realtime notification and increments unread count', () => {
    const store = useNotificationsStore()
    expect(store.items).toEqual([])
    expect(store.unreadCount).toBe(0)

    store.applyRealtimeNotification(
      {
        id: 101,
        type: 'contest_winner',
        data: { contest_name: 'March contest' },
        read_at: null,
        created_at: '2026-02-20T10:00:00Z',
      },
      { toast: false },
    )

    expect(store.items).toHaveLength(1)
    expect(store.items[0].id).toBe(101)
    expect(store.items[0].type).toBe('contest_winner')
    expect(store.unreadCount).toBe(1)
  })

  it('updates existing notification without duplicating and keeps unread count stable', () => {
    const store = useNotificationsStore()

    store.items = [
      {
        id: 102,
        type: 'event_invite',
        data: { event_title: 'Meteor shower' },
        read_at: null,
        created_at: '2026-02-20T10:00:00Z',
      },
    ]
    store.unreadCount = 1

    store.applyRealtimeNotification(
      {
        id: 102,
        type: 'event_invite',
        data: { event_title: 'Meteor shower - updated' },
        read_at: null,
        created_at: '2026-02-20T10:01:00Z',
      },
      { toast: false },
    )

    expect(store.items).toHaveLength(1)
    expect(store.items[0].data.event_title).toContain('updated')
    expect(store.unreadCount).toBe(1)
  })

  it('subscribes to users.{id} and handles realtime notification.created payload', async () => {
    authState.bootstrapDone = false
    authState.waitForBootstrap.mockImplementationOnce(async () => {
      authState.bootstrapDone = true
    })

    const handlers = {}
    const channel = {
      listen: vi.fn((eventName, callback) => {
        handlers[eventName] = callback
        return channel
      }),
    }
    const echoClient = {
      private: vi.fn(() => channel),
      leaveChannel: vi.fn(),
      disconnect: vi.fn(),
    }

    realtimeState.initEcho.mockReturnValue(echoClient)
    realtimeState.getEcho.mockReturnValue(echoClient)

    const store = useNotificationsStore()
    await store.startRealtime()

    expect(authState.waitForBootstrap).toHaveBeenCalledTimes(1)
    expect(authState.csrf).toHaveBeenCalledTimes(1)
    expect(echoClient.private).toHaveBeenCalledWith('users.7')
    expect(channel.listen).toHaveBeenCalledWith('.notification.created', expect.any(Function))
    expect(channel.listen).toHaveBeenCalledWith('NotificationCreated', expect.any(Function))

    handlers['.notification.created']({
      notification: {
        id: 203,
        type: 'event_invite',
        data: { event_title: 'Lunar eclipse' },
        read_at: null,
        created_at: '2026-03-04T20:10:00Z',
      },
    })

    expect(store.items[0].id).toBe(203)
    expect(store.items[0].type).toBe('event_invite')
    expect(store.unreadCount).toBe(1)
    store.stopRealtime({ disconnect: true })
  })

  it('does not re-subscribe when realtime channel is already active', async () => {
    const channel = {
      listen: vi.fn(() => channel),
    }
    const echoClient = {
      private: vi.fn(() => channel),
      leaveChannel: vi.fn(),
      disconnect: vi.fn(),
    }

    realtimeState.initEcho.mockReturnValue(echoClient)
    realtimeState.getEcho.mockReturnValue(echoClient)

    const store = useNotificationsStore()

    await store.startRealtime()
    await store.startRealtime()

    expect(echoClient.private).toHaveBeenCalledTimes(1)
    store.stopRealtime({ disconnect: true })
  })

  it('dedupes notifications by id and keeps newest notifications first after list fetch', async () => {
    const store = useNotificationsStore()

    store.applyRealtimeNotification(
      {
        id: 901,
        type: 'event_invite',
        data: { event_title: 'Realtime invite' },
        read_at: null,
        created_at: '2026-03-05T10:00:00Z',
      },
      { toast: false },
    )

    http.get.mockImplementation(async (url) => {
      if (url === '/notifications') {
        return {
          data: {
            data: [
              {
                id: 901,
                type: 'event_invite',
                data: { event_title: 'Server invite title' },
                read_at: null,
                created_at: '2026-03-05T10:01:00Z',
              },
              {
                id: 902,
                type: 'contest_winner',
                data: { contest_name: 'March contest' },
                read_at: null,
                created_at: '2026-03-05T10:02:00Z',
              },
            ],
            meta: {
              current_page: 1,
              last_page: 1,
            },
          },
        }
      }

      return { data: { count: 2 } }
    })

    await store.fetchList(1, { refreshUnread: false })

    expect(store.items).toHaveLength(2)
    expect(store.items.map((item) => item.id)).toEqual([902, 901])
    expect(store.items.find((item) => item.id === 901)?.data?.event_title).toBe('Server invite title')
  })

  it('keeps realtime notification at top when fetch result does not include it yet', async () => {
    const store = useNotificationsStore()

    store.applyRealtimeNotification(
      {
        id: 990,
        type: 'event_invite',
        data: { event_title: 'Realtime first' },
        read_at: null,
        created_at: '2026-03-05T10:03:00Z',
      },
      { toast: false },
    )

    http.get.mockImplementation(async (url) => {
      if (url === '/notifications') {
        return {
          data: {
            data: [
              {
                id: 902,
                type: 'contest_winner',
                data: { contest_name: 'March contest' },
                read_at: null,
                created_at: '2026-03-05T10:02:00Z',
              },
              {
                id: 901,
                type: 'event_invite',
                data: { event_title: 'Server invite title' },
                read_at: null,
                created_at: '2026-03-05T10:01:00Z',
              },
            ],
            meta: {
              current_page: 1,
              last_page: 1,
            },
          },
        }
      }

      return { data: { count: 3 } }
    })

    await store.fetchList(1, { refreshUnread: false })

    expect(store.items.map((item) => item.id)).toEqual([990, 902, 901])
  })

  it('markAllRead keeps unread count at zero after backend sync and refetch', async () => {
    const store = useNotificationsStore()
    store.items = [
      {
        id: 1101,
        type: 'event_invite',
        data: { event_title: 'A' },
        read_at: null,
        created_at: '2026-03-05T10:00:00Z',
      },
      {
        id: 1102,
        type: 'contest_winner',
        data: { contest_name: 'B' },
        read_at: null,
        created_at: '2026-03-05T09:00:00Z',
      },
    ]
    store.latestItems = [...store.items]
    store.unreadCount = 2

    http.post.mockResolvedValue({ data: { updated: 2 } })
    http.get.mockResolvedValue({ data: { count: 0 } })

    await store.markAllRead()
    await store.fetchUnreadCount()

    expect(authState.csrf).toHaveBeenCalled()
    expect(http.post).toHaveBeenCalledWith('/notifications/read-all', null, {
      meta: { skipErrorToast: true },
    })
    expect(http.get).toHaveBeenCalledWith('/notifications/unread-count', {
      meta: { skipErrorToast: true, skipAuthRedirect: true },
    })
    expect(store.unreadCount).toBe(0)
    expect(store.unreadBadge).toBe('')
    expect(store.items.every((item) => Boolean(item.read_at))).toBe(true)
    expect(store.latestItems.every((item) => Boolean(item.read_at))).toBe(true)
  })

  it('reuses a fresh unread count without issuing another request', async () => {
    const store = useNotificationsStore()

    http.get.mockResolvedValueOnce({ data: { count: 4 } })

    const first = await store.fetchUnreadCount()
    const second = await store.fetchUnreadCount()

    expect(first).toBe(4)
    expect(second).toBe(4)
    expect(http.get).toHaveBeenCalledTimes(1)
    expect(store.unreadCount).toBe(4)
    expect(store.unreadCountHydrated).toBe(true)
  })

  it('waits for auth bootstrap before fetching unread count', async () => {
    const store = useNotificationsStore()
    authState.bootstrapDone = false
    authState.waitForBootstrap.mockImplementationOnce(async () => {
      authState.bootstrapDone = true
    })

    http.get.mockResolvedValueOnce({ data: { count: 6 } })

    await store.fetchUnreadCount()

    expect(authState.waitForBootstrap).toHaveBeenCalledTimes(1)
    expect(http.get).toHaveBeenCalledWith('/notifications/unread-count', {
      meta: { skipErrorToast: true, skipAuthRedirect: true },
    })
  })
})
