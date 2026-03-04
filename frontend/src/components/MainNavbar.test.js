import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { nextTick } from 'vue'
import MainNavbar from '@/components/MainNavbar.vue'

const authStore = vi.hoisted(() => ({
  user: null,
  isAuthed: false,
  isAdmin: false,
  logout: vi.fn(async () => {}),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authStore,
}))

vi.mock('@/stores/notifications', () => ({
  useNotificationsStore: () => ({
    unreadBadge: '',
    unreadCount: 0,
    unreadCountHydrated: true,
    fetchUnreadCount: vi.fn(async () => {}),
  }),
}))

const makeRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>home</div>' } },
      { path: '/search', name: 'search', component: { template: '<div>search</div>' } },
      { path: '/events', name: 'events', component: { template: '<div>events</div>' } },
      { path: '/events/:id', name: 'event-detail', component: { template: '<div>event</div>' } },
      { path: '/clanky', name: 'learn', component: { template: '<div>learn</div>' } },
      { path: '/notifications', name: 'notifications', component: { template: '<div>notifications</div>' } },
      { path: '/profile', name: 'profile', component: { template: '<div>profile</div>' } },
      { path: '/settings', name: 'settings', component: { template: '<div>settings</div>' } },
      { path: '/learn', name: 'learn-legacy', component: { template: '<div>learn legacy</div>' } },
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
    authStore.user = null
    authStore.isAuthed = false
    authStore.isAdmin = false
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

  it('hides Settings for unauthenticated users', async () => {
    const wrapper = await mountNavbarAt('/')

    expect(wrapper.find('.navScroll a[aria-label="Settings"]').exists()).toBe(false)
  })

  it('shows Settings for authenticated users', async () => {
    authStore.isAuthed = true
    authStore.user = { id: 1, name: 'Test User' }
    const wrapper = await mountNavbarAt('/')

    expect(wrapper.find('.navScroll a[aria-label="Settings"]').exists()).toBe(true)
  })
})
