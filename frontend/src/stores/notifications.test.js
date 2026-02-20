import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useNotificationsStore } from '@/stores/notifications'

const authState = vi.hoisted(() => ({
  isAuthed: true,
  user: { id: 7 },
  csrf: vi.fn(async () => {}),
  logout: vi.fn(async () => {}),
}))

const infoToast = vi.hoisted(() => vi.fn())

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
  initEcho: vi.fn(() => null),
  getEcho: vi.fn(() => null),
  disconnectEcho: vi.fn(),
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
    authState.logout.mockClear()
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
})
