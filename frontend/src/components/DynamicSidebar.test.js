import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import DynamicSidebar from './DynamicSidebar.vue'

const fetchScopeMock = vi.hoisted(() => vi.fn(async () => []))
const getSidebarWidgetBundleMock = vi.hoisted(() => vi.fn(async () => ({ requested_sections: [], data: {} })))
const getEnabledSidebarSectionsMock = vi.hoisted(() => vi.fn((items) => items))
const resolveSidebarComponentMock = vi.hoisted(() => vi.fn(() => ({ template: '<div class="widget-stub" />' })))

vi.mock('@/stores/sidebarConfig', () => ({
  useSidebarConfigStore: () => ({
    fetchScope: fetchScopeMock,
  }),
}))

vi.mock('@/services/widgets', () => ({
  getSidebarWidgetBundle: getSidebarWidgetBundleMock,
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    isAuthed: false,
  }),
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => ({
    loaded: false,
  }),
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

    expect(getSidebarWidgetBundleMock).toHaveBeenCalledWith(['nasa_apod', 'neo_watchlist'])
  })
})
