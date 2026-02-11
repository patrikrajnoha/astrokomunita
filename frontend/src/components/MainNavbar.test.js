import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { nextTick } from 'vue'
import MainNavbar from '@/components/MainNavbar.vue'

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: null,
    isAuthed: false,
    isAdmin: false,
    logout: vi.fn(async () => {}),
  }),
}))

vi.mock('@/stores/notifications', () => ({
  useNotificationsStore: () => ({
    unreadBadge: '',
    fetchUnreadCount: vi.fn(async () => {}),
  }),
}))

const makeRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>home</div>' } },
      { path: '/events', name: 'events', component: { template: '<div>events</div>' } },
      { path: '/events/:id', name: 'event-detail', component: { template: '<div>event</div>' } },
      { path: '/learn', name: 'learn', component: { template: '<div>learn</div>' } },
      { path: '/more', name: 'more', component: { template: '<div>more</div>' } },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
      { path: '/register', name: 'register', component: { template: '<div>register</div>' } },
    ],
  })

const isHighlighted = (element) => element.className.includes("before:content-['']")

const mountNavbarAt = async (path) => {
  const router = makeRouter()
  router.push(path)
  await router.isReady()

  const wrapper = mount(MainNavbar, {
    global: {
      plugins: [router],
    },
    attachTo: document.body,
  })

  await nextTick()
  return wrapper
}

describe('MainNavbar active route state', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('shows active highlight on Events when route is /events?view=calendar', async () => {
    const wrapper = await mountNavbarAt('/events?view=calendar')

    const homeLink = wrapper.get('.navScroll a[aria-label="Domov"]')
    const eventsLink = wrapper.get('.navScroll a[aria-label="Udalosti"]')

    expect(isHighlighted(homeLink.element)).toBe(false)
    expect(isHighlighted(eventsLink.element)).toBe(true)
  })

  it('shows active highlight only on Events when route is /events', async () => {
    const wrapper = await mountNavbarAt('/events')

    const homeLink = wrapper.get('.navScroll a[aria-label="Domov"]')
    const eventsLink = wrapper.get('.navScroll a[aria-label="Udalosti"]')

    expect(isHighlighted(homeLink.element)).toBe(false)
    expect(isHighlighted(eventsLink.element)).toBe(true)
  })

  it('shows active highlight only on Home when route is /', async () => {
    const wrapper = await mountNavbarAt('/')

    const homeLink = wrapper.get('.navScroll a[aria-label="Domov"]')
    const eventsLink = wrapper.get('.navScroll a[aria-label="Udalosti"]')

    expect(isHighlighted(homeLink.element)).toBe(true)
    expect(isHighlighted(eventsLink.element)).toBe(false)
  })
})
