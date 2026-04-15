import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, shallowMount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import AdminHubLayout from '@/layouts/AdminHubLayout.vue'

const popupResponse = vi.hoisted(() => ({ value: { should_show: false, items: [] } }))
const getPopupMock = vi.hoisted(() => vi.fn())
const seenPopupMock = vi.hoisted(() => vi.fn())
const getEnabledSidebarSectionsMock = vi.hoisted(() => vi.fn(() => []))
const getSidebarWidgetBundleMock = vi.hoisted(() => vi.fn(async () => ({ requested_sections: [], data: {} })))

const authStore = vi.hoisted(() => ({
  bootstrapDone: true,
  isAuthed: true,
  isAdmin: false,
  user: { email_verified_at: '2026-02-17T10:00:00Z', location_meta: null, location: null },
  error: null,
  loginSequence: 0,
  retryFetchUser: vi.fn(),
}))

const preferencesStore = vi.hoisted(() => ({
  loaded: true,
  loading: false,
  isOnboardingCompleted: true,
  fetchPreferences: vi.fn(),
  sidebarWidgetKeysForScope: vi.fn(() => []),
  hasSidebarWidgetOverrideForScope: vi.fn(() => false),
}))

const sidebarConfigStore = vi.hoisted(() => ({
  fetchScope: vi.fn(async () => []),
}))

const notificationsStore = vi.hoisted(() => ({
  startRealtime: vi.fn(async () => {}),
  stopRealtime: vi.fn(() => {}),
  fetchUnreadCount: vi.fn(async () => {}),
}))

const onboardingTourStore = vi.hoisted(() => ({
  isOpen: false,
  shouldAutoOpen: false,
  hydrate: vi.fn(),
  openTour: vi.fn(),
  closeTour: vi.fn(),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authStore,
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => preferencesStore,
}))

vi.mock('@/stores/sidebarConfig', () => ({
  useSidebarConfigStore: () => sidebarConfigStore,
}))

vi.mock('@/stores/notifications', () => ({
  useNotificationsStore: () => notificationsStore,
}))

vi.mock('@/stores/onboardingTour', () => ({
  useOnboardingTourStore: () => onboardingTourStore,
}))

vi.mock('@/services/popup', () => ({
  getMarkYourCalendarPopup: (...args) => getPopupMock(...args),
  markYourCalendarPopupSeen: (...args) => seenPopupMock(...args),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    showToast: vi.fn(),
  }),
}))

vi.mock('@/services/widgets', () => ({
  getSidebarWidgetBundle: (...args) => getSidebarWidgetBundleMock(...args),
}))

vi.mock('@/sidebar/engine', () => ({
  getEnabledSidebarSections: getEnabledSidebarSectionsMock,
  normalizeSidebarSections: (items) => (Array.isArray(items) ? items : []),
  resolveSidebarComponent: () => null,
  resolveSidebarIcon: () => ({ viewBox: '0 0 24 24', paths: [] }),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: AppLayout },
      { path: '/events', component: AppLayout },
      { path: '/login', component: AppLayout },
      { path: '/profile', component: AppLayout },
      { path: '/profile/edit', component: AppLayout },
      { path: '/settings', component: AppLayout },
      { path: '/u/:username', component: AppLayout },
      { path: '/privacy', component: AppLayout },
      { path: '/:pathMatch(.*)*', name: 'not-found', component: AppLayout },
    ],
  })
}

function makeAdminRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/',
        component: AppLayout,
        children: [
          {
            path: 'privacy',
            component: { template: '<div>privacy</div>' },
          },
          {
            path: 'terms',
            component: { template: '<div>terms</div>' },
          },
          {
            path: 'cookies',
            component: { template: '<div>cookies</div>' },
          },
          {
            path: 'admin',
            component: AdminHubLayout,
            children: [
              {
                path: 'dashboard',
                component: { template: '<div class="admin-dashboard-stub">admin dashboard</div>' },
              },
            ],
          },
        ],
      },
    ],
  })
}

function makeSearchRouter(component) {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/',
        component: AppLayout,
        children: [
          {
            path: 'privacy',
            component: { template: '<div>privacy</div>' },
          },
          {
            path: 'terms',
            component: { template: '<div>terms</div>' },
          },
          {
            path: 'cookies',
            component: { template: '<div>cookies</div>' },
          },
          {
            path: 'search',
            name: 'search',
            component,
          },
        ],
      },
    ],
  })
}

describe('AppLayout mark-your-calendar popup', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  beforeEach(() => {
    vi.clearAllMocks()
    authStore.isAdmin = false
    authStore.user = { email_verified_at: '2026-02-17T10:00:00Z', location_meta: null, location: null }
    preferencesStore.loaded = true
    preferencesStore.loading = false
    preferencesStore.isOnboardingCompleted = false
    getEnabledSidebarSectionsMock.mockImplementation(() => [])
    getSidebarWidgetBundleMock.mockResolvedValue({ requested_sections: [], data: {} })
    sidebarConfigStore.fetchScope.mockResolvedValue([])
    preferencesStore.sidebarWidgetKeysForScope.mockReturnValue([])
    preferencesStore.hasSidebarWidgetOverrideForScope.mockReturnValue(false)
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: vi.fn().mockImplementation(() => ({
        matches: false,
        media: '(max-width: 767px)',
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
      })),
    })
    getPopupMock.mockImplementation(async () => ({ data: popupResponse.value }))
    seenPopupMock.mockResolvedValue({ data: { ok: true } })
    popupResponse.value = { should_show: false, items: [] }
  })

  it('opens modal when popup endpoint returns should_show=true', async () => {
    vi.useFakeTimers()
    preferencesStore.isOnboardingCompleted = true
    popupResponse.value = {
      should_show: true,
      force_version: 3,
      month_key: '2026-02',
      items: [{ id: 1, title: 'Alpha', start_at: null, end_at: null }],
    }

    const router = makeRouter()
    await router.push('/')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await Promise.resolve()
    await Promise.resolve()
    expect(getPopupMock).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1400)
    await Promise.resolve()

    expect(getPopupMock).toHaveBeenCalledTimes(1)
    expect(wrapper.find('mark-your-calendar-modal-stub').exists()).toBe(true)
  })

  it('renders three-zone desktop layout and right rail for routes with right sidebar', async () => {
    const router = makeRouter()
    await router.push('/')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()

    const desktopFrame = wrapper.find('[data-testid="desktop-frame"]')
    expect(desktopFrame.exists()).toBe(true)
    expect(desktopFrame.classes()).toContain('desktopFrame')
    expect(desktopFrame.classes()).toContain('xl:grid')

    const shell = wrapper.find('[data-testid="center-shell"]')
    expect(shell.exists()).toBe(true)
    expect(shell.classes()).toContain('centerShellGrid')
    expect(shell.classes()).toContain('xl:col-start-1')
    expect(shell.attributes('style')).toContain('--center-shell-cols: 16rem minmax(600px, 640px);')

    const mainContent = wrapper.find('main > div')
    expect(mainContent.exists()).toBe(true)
    expect(mainContent.classes()).toContain('mx-auto')
    expect(mainContent.classes()).toContain('max-w-[640px]')

    const rightRail = wrapper.find('[data-testid="right-rail"]')
    expect(rightRail.exists()).toBe(true)
  })

  it('renders explicit profile layout landmarks', async () => {
    const router = makeRouter()
    await router.push('/profile')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()

    expect(wrapper.find('[data-testid="layout-left"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="layout-center"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="layout-right"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="right-rail"]').exists()).toBe(true)
  })

  it('renders the right rail for settings routes', async () => {
    const router = makeRouter()
    await router.push('/settings')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()

    expect(wrapper.find('[data-testid="layout-right"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="right-rail"]').exists()).toBe(true)
  })

  it('fetches unread count only after onboarding is complete and defers realtime bootstrap', async () => {
    vi.useFakeTimers()
    preferencesStore.isOnboardingCompleted = true
    authStore.user = {
      id: 7,
      email_verified_at: '2026-02-17T10:00:00Z',
      location_meta: null,
      location: null,
    }

    const router = makeRouter()
    await router.push('/')
    await router.isReady()

    shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await Promise.resolve()
    await Promise.resolve()

    expect(notificationsStore.fetchUnreadCount).toHaveBeenCalledTimes(1)
    expect(notificationsStore.startRealtime).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1200)

    expect(notificationsStore.startRealtime).toHaveBeenCalledTimes(1)
  })

  it('does not bootstrap notifications while onboarding is still active', async () => {
    vi.useFakeTimers()
    preferencesStore.loaded = false
    preferencesStore.loading = true
    preferencesStore.isOnboardingCompleted = false
    authStore.user = {
      id: 7,
      email_verified_at: '2026-02-17T10:00:00Z',
      location_meta: null,
      location: null,
    }

    const router = makeRouter()
    await router.push('/')
    await router.isReady()

    shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await Promise.resolve()
    await Promise.resolve()
    await vi.advanceTimersByTimeAsync(1200)

    expect(notificationsStore.fetchUnreadCount).not.toHaveBeenCalled()
    expect(notificationsStore.startRealtime).not.toHaveBeenCalled()
  })

  it('keeps profile layout landmarks for profile subroutes', async () => {
    const router = makeRouter()
    await router.push('/profile/edit')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()

    expect(wrapper.find('[data-testid="layout-left"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="layout-center"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="layout-right"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="right-rail"]').exists()).toBe(true)
  })

  it('renders the right rail for public profile routes (/u/:username)', async () => {
    const router = makeRouter()
    await router.push('/u/stellarbot')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()

    expect(wrapper.find('[data-testid="layout-right"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="right-rail"]').exists()).toBe(true)
  })

  it('renders the right rail for not-found routes', async () => {
    const router = makeRouter()
    await router.push('/missing-page')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()

    expect(router.currentRoute.value.name).toBe('not-found')
    expect(wrapper.find('[data-testid="layout-left"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="layout-right"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="right-rail"]').exists()).toBe(true)
  })

  it('collapses desktop grid without phantom right column when sidebar is disabled', async () => {
    const router = makeRouter()
    await router.push('/login')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()

    const desktopFrame = wrapper.find('[data-testid="desktop-frame"]')
    expect(desktopFrame.exists()).toBe(true)
    expect(desktopFrame.classes()).toContain('desktopFrame')
    expect(desktopFrame.classes()).toContain('xl:grid')

    const shell = wrapper.find('[data-testid="center-shell"]')
    expect(shell.exists()).toBe(true)
    expect(shell.classes()).toContain('xl:col-start-1')
    expect(shell.attributes('style')).toContain('--center-shell-cols: 16rem minmax(600px, 640px);')
    expect(shell.attributes('style')).not.toContain('22rem')

    const rightRail = wrapper.find('[data-testid="right-rail"]')
    expect(rightRail.exists()).toBe(false)
  })

  it('routes /admin/dashboard through AppLayout and AdminHubLayout wrappers', async () => {
    authStore.isAdmin = true

    const router = makeAdminRouter()
    await router.push('/admin/dashboard')
    await router.isReady()

    const wrapper = mount(AppLayout, {
      global: {
        plugins: [router],
        stubs: {
          MainNavbar: { template: '<nav class="main-nav-stub">main nav</nav>' },
          DynamicSidebar: { template: '<aside class="dynamic-sidebar-stub">sidebar</aside>' },
          RightObservingSidebar: {
            template: '<aside class="observing-sidebar-stub">observing</aside>',
          },
          PostComposer: { template: '<div class="post-composer-stub">composer</div>' },
          MobileFab: { template: '<button class="mobile-fab-stub">fab</button>' },
          TypingText: { template: '<span class="typing-text-stub">brand</span>' },
          MarkYourCalendarModal: { template: '<div class="calendar-modal-stub">calendar</div>' },
          OnboardingTour: { template: '<div class="onboarding-tour-stub">tour</div>' },
          AdminSubNav: { template: '<aside class="admin-subnav-stub">admin nav</aside>' },
        },
      },
    })

    await flush()
    await flush()

    expect(wrapper.find('[data-testid="desktop-frame"]').classes()).toContain('desktopFrame')
    expect(wrapper.find('[data-testid="center-shell"]').classes()).toContain('centerShellGrid')
    expect(wrapper.find('[data-testid="layout-left"]').exists()).toBe(true)
    expect(wrapper.find('main > div').classes()).toContain('max-w-[640px]')
    expect(wrapper.find('.adminHub').exists()).toBe(true)
    expect(wrapper.find('.adminHub .admin-dashboard-stub').exists()).toBe(true)
    expect(wrapper.find('[data-testid="layout-right"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="right-rail"]').exists()).toBe(true)
  })

  it('does not remount the active route component when only the query string changes', async () => {
    const lifecycle = {
      mounted: 0,
      unmounted: 0,
    }

    const SearchProbe = defineComponent({
      name: 'SearchProbe',
      setup() {
        onMounted(() => {
          lifecycle.mounted += 1
        })

        onUnmounted(() => {
          lifecycle.unmounted += 1
        })

        return () => 'search probe'
      },
    })

    const router = makeSearchRouter(SearchProbe)
    await router.push('/search?q=astro')
    await router.isReady()

    mount(AppLayout, {
      global: {
        plugins: [router],
        stubs: {
          MainNavbar: { template: '<nav class="main-nav-stub">main nav</nav>' },
          DynamicSidebar: { template: '<aside class="dynamic-sidebar-stub">sidebar</aside>' },
          MobileFab: { template: '<button class="mobile-fab-stub">fab</button>' },
          MobileBottomNav: { template: '<nav class="mobile-bottom-nav-stub">bottom nav</nav>' },
          AdminSubNav: { template: '<aside class="admin-subnav-stub">admin nav</aside>' },
          MarkYourCalendarModal: { template: '<div class="calendar-modal-stub">calendar</div>' },
          OnboardingTour: { template: '<div class="onboarding-tour-stub">tour</div>' },
        },
      },
    })

    await flush()
    expect(lifecycle.mounted).toBe(1)
    expect(lifecycle.unmounted).toBe(0)

    await router.replace('/search?q=astrolab')
    await flush()

    expect(lifecycle.mounted).toBe(1)
    expect(lifecycle.unmounted).toBe(0)
  })

  it('calls seen endpoint once when modal closes', async () => {
    vi.useFakeTimers()
    preferencesStore.isOnboardingCompleted = true
    popupResponse.value = {
      should_show: true,
      force_version: 5,
      month_key: '2026-02',
      items: [{ id: 1, title: 'Alpha', start_at: null, end_at: null }],
    }

    const router = makeRouter()
    await router.push('/')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await Promise.resolve()
    await Promise.resolve()
    await vi.advanceTimersByTimeAsync(1400)
    await Promise.resolve()

    const modal = wrapper.findComponent({ name: 'MarkYourCalendarModal' })
    expect(modal.exists()).toBe(true)
    modal.vm.$emit('close')
    await Promise.resolve()
    await Promise.resolve()

    expect(seenPopupMock).toHaveBeenCalledTimes(1)
    expect(seenPopupMock).toHaveBeenCalledWith({
      force_version: 5,
      month_key: '2026-02',
    })
  })

  it('does not call popup endpoint for admin users', async () => {
    authStore.isAdmin = true
    preferencesStore.isOnboardingCompleted = true
    popupResponse.value = { should_show: true, items: [] }

    const router = makeRouter()
    await router.push('/')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(getPopupMock).not.toHaveBeenCalled()
    expect(wrapper.find('mark-your-calendar-modal-stub').exists()).toBe(false)
  })

  it('renders bottom nav on mobile non-admin routes', async () => {
    window.matchMedia = vi.fn().mockImplementation((query) => ({
      matches: query === '(max-width: 767px)',
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }))

    const router = makeRouter()
    await router.push('/')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()

    expect(wrapper.find('mobile-bottom-nav-stub').exists()).toBe(true)
  })

  it('does not render bottom nav on admin routes even on mobile', async () => {
    authStore.isAdmin = true
    window.matchMedia = vi.fn().mockImplementation((query) => ({
      matches: query === '(max-width: 767px)',
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }))

    const router = makeAdminRouter()
    await router.push('/admin/dashboard')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(wrapper.find('mobile-bottom-nav-stub').exists()).toBe(false)
  })

  it('warms mobile sidebar config for the active route scope', async () => {
    window.matchMedia = vi.fn().mockImplementation((query) => ({
      matches: query === '(max-width: 767px)',
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }))

    const router = makeRouter()
    await router.push('/events')
    await router.isReady()

    shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(sidebarConfigStore.fetchScope).toHaveBeenCalledWith('home')
  })

  it('renders mobile widget access on settings routes and warms settings scope', async () => {
    window.matchMedia = vi.fn().mockImplementation((query) => ({
      matches: query === '(max-width: 767px)',
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }))

    const router = makeRouter()
    await router.push('/settings')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(sidebarConfigStore.fetchScope).toHaveBeenCalledWith('home')
    expect(wrapper.find('mobile-fab-stub').exists()).toBe(true)
  })

  it('preloads mobile widget bundle when opening the widget menu', async () => {
    window.matchMedia = vi.fn().mockImplementation((query) => ({
      matches: query === '(max-width: 767px)',
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }))
    authStore.user = {
      email_verified_at: '2026-02-17T10:00:00Z',
      location_meta: {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
        name: 'Bratislava',
      },
      location: 'Bratislava',
    }
    sidebarConfigStore.fetchScope.mockResolvedValue([
      { kind: 'builtin', section_key: 'observing_conditions', title: 'Pozorovanie dnes', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'space_weather', title: 'Slnečná aktivita', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'moon_phases', title: 'Fázy Mesiaca', order: 2, is_enabled: true },
      { kind: 'builtin', section_key: 'neo_watchlist', title: 'Asteroidy nablízku', order: 3, is_enabled: true },
    ])
    getEnabledSidebarSectionsMock.mockImplementation((items) => items)

    const router = makeRouter()
    await router.push('/events')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
        stubs: {
          MobileFab: {
            template: '<button class="mobile-fab-trigger" @click="$emit(\'widgets\')">fab</button>',
          },
        },
      },
    })

    await flush()
    await flush()
    await wrapper.get('.mobile-fab-trigger').trigger('click')
    await flush()
    await flush()

    expect(getSidebarWidgetBundleMock).toHaveBeenCalledWith(
      ['observing_conditions', 'space_weather', 'neo_watchlist'],
      {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
      },
    )
  })

  it('passes an explicit empty sidebar override through on settings routes', async () => {
    window.matchMedia = vi.fn().mockImplementation((query) => ({
      matches: query === '(max-width: 767px)',
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }))
    preferencesStore.sidebarWidgetKeysForScope.mockReturnValue([])
    preferencesStore.hasSidebarWidgetOverrideForScope.mockImplementation((scope) => scope === 'home')

    const router = makeRouter()
    await router.push('/settings')
    await router.isReady()

    const wrapper = shallowMount(AppLayout, {
      global: {
        plugins: [router],
        stubs: {
          MobileFab: {
            template: '<button class="mobile-fab-trigger" @click="$emit(\'widgets\')">fab</button>',
          },
        },
      },
    })

    await flush()
    await flush()
    await wrapper.get('.mobile-fab-trigger').trigger('click')
    await flush()
    await flush()

    expect(getEnabledSidebarSectionsMock).toHaveBeenCalledWith(
      expect.any(Array),
      expect.objectContaining({
        preferredSectionKeys: [],
        allowUserPreferenceOverride: true,
      }),
    )
  })
})
