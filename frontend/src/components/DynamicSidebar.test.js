import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import DynamicSidebar from './DynamicSidebar.vue'

const fetchScopeMock = vi.hoisted(() => vi.fn(async () => []))

vi.mock('@/stores/sidebarConfig', () => ({
  useSidebarConfigStore: () => ({
    fetchScope: fetchScopeMock,
  }),
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
  getEnabledSidebarSections: () => [],
  resolveSidebarComponent: () => null,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('DynamicSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks()
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
})
