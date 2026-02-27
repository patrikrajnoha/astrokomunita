import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { ref } from 'vue'
import EventDetailView from '@/views/EventDetailView.vue'

const apiGetMock = vi.hoisted(() => vi.fn())
const favoritesStore = vi.hoisted(() => ({
  fetch: vi.fn(async () => {}),
  toggle: vi.fn(async () => {}),
}))
const authStore = vi.hoisted(() => ({
  isAuthed: true,
}))

vi.mock('@/services/api', () => ({
  default: {
    get: (...args) => apiGetMock(...args),
  },
}))

vi.mock('@/stores/favorites', () => ({
  useFavoritesStore: () => favoritesStore,
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

vi.mock('@/composables/useSwipeCard', () => ({
  useSwipeCard: () => ({
    badge: ref(null),
    cardStyle: ref({}),
    onPointerDown: vi.fn(),
    onPointerMove: vi.fn(),
    onPointerUp: vi.fn(),
  }),
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/events/:id',
        component: EventDetailView,
      },
      {
        path: '/events',
        component: { template: '<div>events</div>' },
      },
    ],
  })
}

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('EventDetailView invite button', () => {
  beforeEach(() => {
    apiGetMock.mockReset()
    favoritesStore.fetch.mockClear()
    favoritesStore.toggle.mockClear()
    authStore.isAuthed = true
  })

  it('opens invite modal from event detail', async () => {
    apiGetMock.mockResolvedValue({
      data: {
        data: {
          id: 12,
          title: 'Ukazka eventu',
          type: 'other',
          start_at: '2026-03-14T19:30:00Z',
          related_events: [
            {
              id: 14,
              title: 'Druhy event',
              start_at: '2026-03-15T19:30:00Z',
            },
          ],
        },
      },
    })

    const router = makeRouter()
    await router.push('/events/12')
    await router.isReady()

    const wrapper = mount(EventDetailView, {
      global: {
        plugins: [router],
        stubs: {
          EventCard: true,
          EventActions: true,
          EventDetailSheet: true,
          InviteTicketModal: {
            name: 'InviteTicketModal',
            props: ['open'],
            template: '<div data-testid="invite-modal" :data-open="String(open)"></div>',
          },
        },
      },
    })

    await flush()
    await flush()

    const inviteButton = wrapper.find('button.inviteBtn-primary')
    expect(inviteButton.exists()).toBe(true)

    await inviteButton.trigger('click')

    const modal = wrapper.find('[data-testid="invite-modal"]')
    expect(modal.exists()).toBe(true)
    expect(modal.attributes('data-open')).toBe('true')
  })
})
