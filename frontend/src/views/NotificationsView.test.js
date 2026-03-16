import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { buildNotificationPreferenceMap } from '@/constants/notificationPreferences'
import NotificationsView from './NotificationsView.vue'

const getMock = vi.hoisted(() => vi.fn())
const postMock = vi.hoisted(() => vi.fn())
const putMock = vi.hoisted(() => vi.fn())

const notificationsStoreMock = vi.hoisted(() => ({
  items: [],
  loading: false,
  loadingMore: false,
  error: '',
  page: 1,
  lastPage: 1,
  fetchList: vi.fn(),
  fetchUnreadCount: vi.fn(),
  markAllRead: vi.fn(),
  markRead: vi.fn(),
}))

const authMock = vi.hoisted(() => ({
  isAuthed: true,
}))

vi.mock('@/stores/notifications', () => ({
  useNotificationsStore: () => notificationsStoreMock,
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
    post: postMock,
    put: putMock,
  },
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

async function mountView(initialPath = '/notifications') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/notifications',
        name: 'notifications',
        component: NotificationsView,
      },
    ],
  })

  const replaceSpy = vi.spyOn(router, 'replace')

  await router.push(initialPath)
  await router.isReady()

  const wrapper = mount(NotificationsView, {
    attachTo: document.body,
    global: {
      plugins: [router],
    },
  })

  await flush()

  return { wrapper, router, replaceSpy }
}

describe('NotificationsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    notificationsStoreMock.items = []
    notificationsStoreMock.loading = false
    notificationsStoreMock.loadingMore = false
    notificationsStoreMock.error = ''
    notificationsStoreMock.page = 1
    notificationsStoreMock.lastPage = 1
    authMock.isAuthed = true

    getMock.mockImplementation(async (url) => {
      if (url === '/me/notifications/preferences') {
        return {
          data: {
            good_conditions_alerts: true,
            iss_alerts: false,
          },
        }
      }

      if (url === '/notification-preferences') {
        return {
          data: {
            in_app: buildNotificationPreferenceMap(true),
            email_enabled: false,
            email: buildNotificationPreferenceMap(false),
          },
        }
      }

      return { data: {} }
    })

    postMock.mockImplementation(async (_url, payload) => ({
      data: payload,
    }))
    putMock.mockImplementation(async (_url, payload) => ({
      data: payload,
    }))
  })

  it('click on settings button opens modal', async () => {
    const { wrapper, replaceSpy } = await mountView()

    expect(getMock).not.toHaveBeenCalled()

    await wrapper.get('[data-testid="open-notification-settings"]').trigger('click')
    await flush()

    expect(document.body.querySelector('[data-testid="notification-settings-modal"]')).not.toBeNull()
    expect(document.body.textContent).toContain('Nastavenia notifikacii')
    expect(getMock).toHaveBeenCalledWith('/me/notifications/preferences', {
      meta: { requiresAuth: true, skipErrorToast: true },
    })
    expect(getMock).toHaveBeenCalledWith('/notification-preferences', {
      meta: { requiresAuth: true },
    })
    expect(replaceSpy).toHaveBeenCalledWith({
      path: '/notifications',
      query: {},
      hash: '#notification-settings',
    })

    wrapper.unmount()
  })

  it('close button closes modal', async () => {
    const { wrapper, router, replaceSpy } = await mountView()

    await wrapper.get('[data-testid="open-notification-settings"]').trigger('click')
    await flush()
    await router.push('/notifications#notification-settings')
    await flush()

    await document.body.querySelector('[data-testid="close-notification-settings"]').click()
    await flush()

    expect(replaceSpy).toHaveBeenCalledWith({
      path: '/notifications',
      query: {},
      hash: '',
    })
    expect(document.body.querySelector('[data-testid="notification-settings-modal"]')).toBeNull()

    wrapper.unmount()
  })

  it('opens modal when route hash is present on load', async () => {
    const { wrapper, replaceSpy } = await mountView('/notifications#notification-settings')
    await flush()

    expect(document.body.querySelector('[data-testid="notification-settings-modal"]')).not.toBeNull()
    expect(document.body.textContent).toContain('Nastavenia notifikacii')
    expect(getMock).toHaveBeenCalledWith('/me/notifications/preferences', {
      meta: { requiresAuth: true, skipErrorToast: true },
    })
    expect(getMock).toHaveBeenCalledWith('/notification-preferences', {
      meta: { requiresAuth: true },
    })
    expect(replaceSpy).not.toHaveBeenCalledWith({
      path: '/notifications',
      query: {},
      hash: '#notification-settings',
    })

    wrapper.unmount()
  })

  it('loads the notifications list without an extra unread-count fetch on mount', async () => {
    const { wrapper } = await mountView()

    expect(notificationsStoreMock.fetchList).toHaveBeenCalledWith(1)
    expect(notificationsStoreMock.fetchUnreadCount).not.toHaveBeenCalled()
    expect(getMock).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('shows event reminder categories and saves email toggles for them', async () => {
    const { wrapper } = await mountView()

    await wrapper.get('[data-testid="open-notification-settings"]').trigger('click')
    await flush()
    await flush()

    expect(document.body.textContent).toContain('Pripomienky udalosti')
    expect(document.body.textContent).toContain('Meteory a roje')
    expect(document.body.textContent).toContain('Zatmenia')

    await document.body.querySelector('[data-testid="delivery-email-event_reminder_meteors"]').click()
    await flush()

    expect(putMock).toHaveBeenCalledWith('/notification-preferences', expect.objectContaining({
      email_enabled: true,
      email: expect.objectContaining({
        event_reminder_meteors: true,
      }),
      in_app: expect.objectContaining({
        event_reminder: true,
      }),
    }), {
      meta: { requiresAuth: true },
    })

    wrapper.unmount()
  })
})
