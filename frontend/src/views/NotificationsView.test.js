import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import NotificationsView from './NotificationsView.vue'

const pushMock = vi.hoisted(() => vi.fn())
const replaceMock = vi.hoisted(() => vi.fn())
const getMock = vi.hoisted(() => vi.fn())
const postMock = vi.hoisted(() => vi.fn())

const notificationsStoreMock = vi.hoisted(() => ({
  items: [],
  loading: false,
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

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: replaceMock,
  }),
  useRoute: () => ({
    hash: '',
  }),
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
  },
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('NotificationsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    notificationsStoreMock.items = []
    notificationsStoreMock.loading = false
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

      return { data: {} }
    })

    postMock.mockImplementation(async (_url, payload) => ({
      data: payload,
    }))

    if (!HTMLElement.prototype.scrollIntoView) {
      HTMLElement.prototype.scrollIntoView = () => {}
    }
  })

  it('renders notification settings section with both sky alert toggles', async () => {
    const wrapper = mount(NotificationsView, {
      attachTo: document.body,
    })

    await flush()

    expect(notificationsStoreMock.fetchList).toHaveBeenCalledWith(1)
    expect(notificationsStoreMock.fetchUnreadCount).toHaveBeenCalledTimes(1)
    expect(getMock).toHaveBeenCalledWith('/me/notifications/preferences', {
      meta: { requiresAuth: true, skipErrorToast: true },
    })

    expect(wrapper.text()).toContain('Nastavenia notifikácií')
    expect(wrapper.text()).toContain('Upozorniť ma pri výborných podmienkach')
    expect(wrapper.text()).toContain('Upozorniť ma na ISS prelet')

    wrapper.unmount()
  })
})
