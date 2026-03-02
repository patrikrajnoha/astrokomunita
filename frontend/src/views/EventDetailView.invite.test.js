import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import EventDetailView from '@/views/EventDetailView.vue'

const apiGetMock = vi.hoisted(() => vi.fn())
const followStateMock = vi.hoisted(() => vi.fn())
const followEventMock = vi.hoisted(() => vi.fn())
const unfollowEventMock = vi.hoisted(() => vi.fn())
const authStore = vi.hoisted(() => ({
  isAuthed: true,
  user: null,
  csrf: vi.fn(async () => {}),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
  },
}))

vi.mock('@/services/eventFollows', () => ({
  getEventFollowState: (...args) => followStateMock(...args),
  followEvent: (...args) => followEventMock(...args),
  unfollowEvent: (...args) => unfollowEventMock(...args),
  getFollowedEvents: vi.fn(async () => ({ data: { data: [] } })),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authStore,
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    error: vi.fn(),
    success: vi.fn(),
    warn: vi.fn(),
    info: vi.fn(),
  }),
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/events/:id',
        name: 'event-detail',
        component: EventDetailView,
      },
      {
        path: '/events',
        name: 'events',
        component: { template: '<div>events</div>' },
      },
      {
        path: '/login',
        name: 'login',
        component: { template: '<div>login</div>' },
      },
      {
        path: '/profile/edit',
        name: 'profile.edit',
        component: { template: '<div>profile edit</div>' },
      },
    ],
  })
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

async function mountView(path = '/events/12') {
  const pinia = createPinia()
  setActivePinia(pinia)

  const router = makeRouter()
  await router.push(path)
  await router.isReady()

  const wrapper = mount(EventDetailView, {
    global: {
      plugins: [pinia, router],
      stubs: {
        DropdownMenu: {
          props: ['items'],
          template: `
            <div class="dropdown-menu-stub">
              <slot name="trigger" />
              <button
                v-for="item in items"
                :key="item.key"
                type="button"
                class="dropdown-item-stub"
              >
                {{ item.label }}
              </button>
            </div>
          `,
        },
        InviteTicketModal: {
          props: ['open'],
          template: '<div data-testid="invite-modal" :data-open="String(open)"></div>',
        },
        EventViewingWindowForecast: {
          props: ['event', 'userLocation'],
          template: '<div class="forecast-strip-stub"></div>',
        },
      },
    },
  })

  await flush()
  await flush()

  return { wrapper, router }
}

describe('EventDetailView', () => {
  beforeEach(() => {
    apiGetMock.mockReset()
    followStateMock.mockReset()
    followEventMock.mockReset()
    unfollowEventMock.mockReset()
    authStore.isAuthed = true
    authStore.user = null
    authStore.csrf.mockClear()

    apiGetMock.mockResolvedValue({
      data: {
        data: {
          id: 12,
          title: 'Ukazka eventu',
          type: 'other',
          start_at: '2026-03-14T19:30:00Z',
          max_at: '2026-03-14T13:00:00Z',
          description: 'Dlhsi popis pre test detailu udalosti.',
          visibility: 1,
        },
      },
    })

    followStateMock.mockResolvedValue({ data: { followed: false } })
    followEventMock.mockResolvedValue({ data: { followed: true } })
    unfollowEventMock.mockResolvedValue({ data: { followed: false } })
  })

  it('shows login CTA to guests', async () => {
    authStore.isAuthed = false

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Prihlasit sa pre sledovanie')
    expect(wrapper.text()).not.toContain('Diskusia')
  })

  it('shows follow CTA to authenticated users and toggles to followed state', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Sledovat')

    const followButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Sledovat'))

    expect(followButton).toBeTruthy()

    await followButton.trigger('click')
    await flush()

    expect(authStore.csrf).toHaveBeenCalledTimes(1)
    expect(followEventMock).toHaveBeenCalledWith(12)
    expect(wrapper.text()).toContain('Sledujes')
  })

  it('exposes ICS and share actions in the overflow menu', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Pridat do kalendara')
    expect(wrapper.text()).toContain('Zdielat odkaz')
  })
})
