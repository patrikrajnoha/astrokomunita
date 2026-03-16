import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import DynamicSidebar from './DynamicSidebar.vue'

const fetchScopeMock = vi.hoisted(() => vi.fn(async () => []))
const getSidebarWidgetBundleMock = vi.hoisted(() => vi.fn(async () => ({ requested_sections: [], data: {} })))
const getEnabledSidebarSectionsMock = vi.hoisted(() => vi.fn((items) => items))
const resolveSidebarComponentMock = vi.hoisted(() => vi.fn(() => ({ template: '<div class="widget-stub" />' })))
const authStore = vi.hoisted(() => ({
  isAuthed: false,
}))
const preferencesStore = vi.hoisted(() => ({
  loaded: false,
  sidebarWidgetKeysForScope: vi.fn(() => []),
  hasSidebarWidgetOverrideForScope: vi.fn(() => false),
}))

vi.mock('@/stores/sidebarConfig', () => ({
  useSidebarConfigStore: () => ({
    fetchScope: fetchScopeMock,
  }),
}))

vi.mock('@/services/widgets', () => ({
  getSidebarWidgetBundle: getSidebarWidgetBundleMock,
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authStore,
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => preferencesStore,
}))

vi.mock('@/sidebar/engine', () => ({
  getEnabledSidebarSections: getEnabledSidebarSectionsMock,
  resolveSidebarComponent: resolveSidebarComponentMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('DynamicSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authStore.isAuthed = false
    preferencesStore.loaded = false
    preferencesStore.sidebarWidgetKeysForScope.mockReturnValue([])
    preferencesStore.hasSidebarWidgetOverrideForScope.mockReturnValue(false)
    fetchScopeMock.mockResolvedValue([])
    getSidebarWidgetBundleMock.mockResolvedValue({ requested_sections: [], data: {} })
    window.matchMedia = vi.fn().mockImplementation(() => ({
      matches: true,
      media: '(min-width: 1280px)',
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }))
  })

  it('loads the real route scope instead of forcing home scope', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/events', component: DynamicSidebar },
      ],
    })

    await router.push('/events')
    await router.isReady()

    mount(DynamicSidebar, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(fetchScopeMock).toHaveBeenCalledWith('events')
  })

  it('uses the homepage widget fallback on home scope before preferences are loaded', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: DynamicSidebar },
      ],
    })

    await router.push('/')
    await router.isReady()

    mount(DynamicSidebar, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(getEnabledSidebarSectionsMock).toHaveBeenCalledWith(
      expect.any(Array),
      expect.objectContaining({
        preferredSectionKeys: ['next_event', 'nasa_apod', 'search'],
      }),
    )
  })

  it('requests bundled sidebar data for preloadable builtin widgets', async () => {
    fetchScopeMock.mockResolvedValue([
      { kind: 'builtin', section_key: 'nasa_apod', title: 'NASA', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'neo_watchlist', title: 'NEO watchlist', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'search', title: 'Search', order: 2, is_enabled: true },
    ])

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/events', component: DynamicSidebar },
      ],
    })

    await router.push('/events')
    await router.isReady()

    mount(DynamicSidebar, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(getSidebarWidgetBundleMock).toHaveBeenCalledWith(['nasa_apod', 'neo_watchlist'], {})
  })

  it('passes observing context when bundling space weather widgets', async () => {
    fetchScopeMock.mockResolvedValue([
      { kind: 'builtin', section_key: 'space_weather', title: 'Vesmirne pocasie', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'aurora_watch', title: 'Aurora watch', order: 1, is_enabled: true },
    ])

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/events', component: DynamicSidebar },
      ],
    })

    await router.push('/events')
    await router.isReady()

    mount(DynamicSidebar, {
      props: {
        observingLat: 48.1486,
        observingLon: 17.1077,
        observingTz: 'Europe/Bratislava',
      },
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(getSidebarWidgetBundleMock).toHaveBeenCalledWith(
      ['space_weather', 'aurora_watch'],
      {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
      },
    )
  })

  it('bundles observing sidebar widgets through the shared sidebar-data endpoint', async () => {
    fetchScopeMock.mockResolvedValue([
      { kind: 'builtin', section_key: 'observing_conditions', title: 'Astronomicke podmienky', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'observing_weather', title: 'Pocasie pre pozorovanie', order: 1, is_enabled: true },
      { kind: 'builtin', section_key: 'night_sky', title: 'Nocna obloha', order: 2, is_enabled: true },
      { kind: 'builtin', section_key: 'iss_pass', title: 'ISS prelet', order: 3, is_enabled: true },
    ])

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/settings', component: DynamicSidebar },
      ],
    })

    await router.push('/settings')
    await router.isReady()

    mount(DynamicSidebar, {
      props: {
        observingLat: 48.1486,
        observingLon: 17.1077,
        observingTz: 'Europe/Bratislava',
      },
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(getSidebarWidgetBundleMock).toHaveBeenCalledWith(
      ['observing_conditions', 'observing_weather', 'night_sky', 'iss_pass'],
      {
        lat: 48.1486,
        lon: 17.1077,
        tz: 'Europe/Bratislava',
      },
    )
  })

  it('preserves an explicit empty widget override instead of falling back to defaults', async () => {
    authStore.isAuthed = true
    preferencesStore.loaded = true
    preferencesStore.sidebarWidgetKeysForScope.mockReturnValue([])
    preferencesStore.hasSidebarWidgetOverrideForScope.mockImplementation((scope) => scope === 'home')
    getEnabledSidebarSectionsMock.mockImplementation((items, options = {}) => {
      if (Array.isArray(options?.preferredSectionKeys) && options.preferredSectionKeys.length === 0) {
        return []
      }

      return items
    })
    fetchScopeMock.mockResolvedValue([
      { kind: 'builtin', section_key: 'observing_conditions', title: 'Astronomicke podmienky', order: 0, is_enabled: true },
      { kind: 'builtin', section_key: 'observing_weather', title: 'Pocasie pre pozorovanie', order: 1, is_enabled: true },
    ])

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/settings', component: DynamicSidebar },
      ],
    })

    await router.push('/settings')
    await router.isReady()

    mount(DynamicSidebar, {
      global: {
        plugins: [router],
      },
    })

    await flush()
    await flush()

    expect(getEnabledSidebarSectionsMock).toHaveBeenCalledWith(
      expect.any(Array),
      expect.objectContaining({
        preferredSectionKeys: [],
      }),
    )
    expect(getSidebarWidgetBundleMock).not.toHaveBeenCalled()
  })
})
