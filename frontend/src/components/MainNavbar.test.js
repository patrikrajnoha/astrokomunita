import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { nextTick } from 'vue'
import MainNavbar from '@/components/MainNavbar.vue'

const authStore = vi.hoisted(() => ({
  user: null,
  isAuthed: false,
  isAdmin: false,
  isEditor: false,
  logout: vi.fn(async () => {}),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authStore,
}))

const notificationsStore = vi.hoisted(() => ({
  unreadBadge: '',
  unreadCount: 0,
  unreadCountHydrated: true,
  latestItems: [],
  latestLoading: false,
  latestError: '',
  fetchUnreadCount: vi.fn(async () => {}),
  fetchLatest: vi.fn(async () => {}),
  markAllRead: vi.fn(async () => {}),
  markRead: vi.fn(async () => {}),
}))

vi.mock('@/stores/notifications', () => ({
  useNotificationsStore: () => notificationsStore,
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
      { path: '/observations/new', name: 'observations.create', component: { template: '<div>observations create</div>' } },
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

const mountNavbarAtWithRouter = async (path) => {
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
  return { wrapper, router }
}

describe('MainNavbar active route state', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authStore.user = null
    authStore.isAuthed = false
    authStore.isAdmin = false
    authStore.isEditor = false
    notificationsStore.unreadBadge = ''
    notificationsStore.unreadCount = 0
    notificationsStore.unreadCountHydrated = true
    notificationsStore.latestItems = []
    notificationsStore.latestLoading = false
    notificationsStore.latestError = ''
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

  it('hides Nastavenia for unauthenticated users', async () => {
    const wrapper = await mountNavbarAt('/')

    expect(wrapper.find('.navScroll a[aria-label="Nastavenia"]').exists()).toBe(false)
  })

  it('shows Nastavenia for authenticated users', async () => {
    authStore.isAuthed = true
    authStore.user = { id: 1, name: 'Test User' }
    const wrapper = await mountNavbarAt('/')

    expect(wrapper.find('.navScroll a[aria-label="Nastavenia"]').exists()).toBe(true)
  })

  it('shows content picker with Pozorovanie and routes to observation create', async () => {
    authStore.isAuthed = true
    authStore.user = { id: 1, name: 'Test User' }
    const { wrapper, router } = await mountNavbarAtWithRouter('/')
    const pushSpy = vi.spyOn(router, 'push')

    await wrapper.get('button[data-testid="create-content-trigger"]').trigger('click')
    expect(wrapper.get('#create-content-menu').text()).toContain('Pozorovanie')

    await wrapper.get('button[data-create-type="observation"]').trigger('click')
    await nextTick()

    expect(pushSpy).toHaveBeenCalledWith('/observations/new')
  })

  it('navigates to notifications page when notifications trigger is clicked', async () => {
    authStore.isAuthed = true
    authStore.user = { id: 1, name: 'Test User' }
    notificationsStore.unreadBadge = '2'

    const { wrapper, router } = await mountNavbarAtWithRouter('/')
    const pushSpy = vi.spyOn(router, 'push')

    await wrapper.get('[data-testid="notifications-trigger"]').trigger('click')
    await nextTick()

    expect(pushSpy).toHaveBeenCalledWith('/notifications')
    expect(notificationsStore.fetchLatest).not.toHaveBeenCalled()
  })
})
