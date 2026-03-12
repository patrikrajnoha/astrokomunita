import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import EventDetailView from '@/views/EventDetailView.vue'

const apiGetMock = vi.hoisted(() => vi.fn())
const followStateMock = vi.hoisted(() => vi.fn())
const followEventMock = vi.hoisted(() => vi.fn())
const unfollowEventMock = vi.hoisted(() => vi.fn())
const updateEventPlanMock = vi.hoisted(() => vi.fn())
const viewingForecastStateMock = vi.hoisted(() => ({ value: null }))
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
  updateEventPlan: (...args) => updateEventPlanMock(...args),
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
          emits: ['state'],
          mounted() {
            if (viewingForecastStateMock.value) {
              this.$emit('state', viewingForecastStateMock.value)
            }
          },
          template: '<div class="forecast-strip-stub"></div>',
        },
        BaseModal: {
          props: ['open'],
          emits: ['update:open'],
          template: '<div v-if="open" class="base-modal-stub"><slot /><button type="button" class="modal-close" @click="$emit(\'update:open\', false)">close</button></div>',
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
    updateEventPlanMock.mockReset()
    viewingForecastStateMock.value = null
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
    updateEventPlanMock.mockResolvedValue({ data: { followed: true, data: { id: 12 } } })
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

  it('clarifies daytime maximum when viewing opens after sunset', async () => {
    viewingForecastStateMock.value = {
      loading: false,
      missingLocation: false,
      viewingWindow: {
        start_at: '2026-03-14T17:16:00Z',
        end_at: '2026-03-14T21:16:00Z',
      },
    }

    const { wrapper } = await mountView()
    const rendered = wrapper.text()

    expect(rendered).toContain('Pozorovanie: 18:16 - 22:16')
    expect(rendered).toContain('Maximum javu (cez den) o 14:00')
    expect(rendered).toContain('Cas "Maximum" znamena astronomicky vrchol javu')
  })

  it('navigates to next event on swipe left', async () => {
    apiGetMock.mockImplementation((url) => {
      if (url === '/events/12') {
        return Promise.resolve({
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
      }

      if (url === '/events/13') {
        return Promise.resolve({
          data: {
            data: {
              id: 13,
              title: 'Dalsi event',
              type: 'other',
              start_at: '2026-03-15T19:30:00Z',
              max_at: '2026-03-15T13:00:00Z',
              description: 'Dalsi popis.',
              visibility: 1,
            },
          },
        })
      }

      if (url === '/events') {
        return Promise.resolve({
          data: {
            data: [
              { id: 11, start_at: '2026-03-13T18:00:00Z' },
              { id: 12, start_at: '2026-03-14T19:30:00Z' },
              { id: 13, start_at: '2026-03-15T19:30:00Z' },
            ],
          },
        })
      }

      return Promise.resolve({ data: { data: [] } })
    })

    const { wrapper, router } = await mountView('/events/12')
    const card = wrapper.find('.eventCard')
    expect(card.exists()).toBe(true)

    await card.trigger('touchstart', {
      touches: [{ clientX: 240, clientY: 200 }],
    })
    await card.trigger('touchend', {
      changedTouches: [{ clientX: 110, clientY: 208 }],
    })
    await new Promise((resolve) => setTimeout(resolve, 140))
    await flush()
    await flush()

    expect(router.currentRoute.value.params.id).toBe('13')
  })

  it('saves event plan data via the existing event flow', async () => {
    const { wrapper } = await mountView()

    const planButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Naplanovat pozorovanie'))

    expect(planButton).toBeTruthy()

    await planButton.trigger('click')
    await flush()

    await wrapper.find('textarea').setValue('Priniest dalekohlad.')
    await wrapper.find('input[type="text"]').setValue('Kopec nad mestom')
    await wrapper.find('form').trigger('submit.prevent')
    await flush()

    expect(updateEventPlanMock).toHaveBeenCalledWith(12, expect.objectContaining({
      personal_note: 'Priniest dalekohlad.',
      planned_location_label: 'Kopec nad mestom',
    }))
  })

  it('shows dedicated missing-event state when API returns 404', async () => {
    apiGetMock.mockRejectedValue({
      response: {
        status: 404,
        data: { message: 'Event not found.' },
      },
    })

    const { wrapper } = await mountView('/events/404')

    expect(wrapper.find('.missingEventCard').exists()).toBe(true)
    expect(wrapper.text()).toContain('Tato udalost uz neexistuje.')
    expect(wrapper.text()).toContain('Vsetky udalosti')
    expect(wrapper.find('[data-testid="inline-status"]').exists()).toBe(false)
  })

  it('keeps generic inline error for non-404 failures', async () => {
    apiGetMock.mockRejectedValue({
      response: {
        status: 500,
        data: { message: 'Server timeout.' },
      },
    })

    const { wrapper } = await mountView('/events/500')

    expect(wrapper.find('[data-testid="inline-status"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Server timeout.')
  })
})
