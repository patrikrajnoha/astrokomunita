import { beforeEach, describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'

const popupResponse = vi.hoisted(() => ({ value: { should_show: false, items: [] } }))
const getPopupMock = vi.hoisted(() => vi.fn())
const seenPopupMock = vi.hoisted(() => vi.fn())

const authStore = vi.hoisted(() => ({
  bootstrapDone: true,
  isAuthed: true,
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
}))

const sidebarConfigStore = vi.hoisted(() => ({
  fetchScope: vi.fn(async () => []),
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

vi.mock('@/services/popup', () => ({
  getMarkYourCalendarPopup: (...args) => getPopupMock(...args),
  markYourCalendarPopupSeen: (...args) => seenPopupMock(...args),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    showToast: vi.fn(),
  }),
}))

vi.mock('@/sidebar/engine', () => ({
  getEnabledSidebarSections: () => [],
  normalizeSidebarSections: () => [],
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
      { path: '/login', component: AppLayout },
    ],
  })
}

describe('AppLayout mark-your-calendar popup', () => {
  beforeEach(() => {
    vi.clearAllMocks()
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

    await flush()
    await flush()

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
    expect(shell.classes()).toContain('xl:max-w-[1440px]')
    expect(shell.classes()).toContain('2xl:max-w-[1560px]')
    expect(shell.attributes('style')).toContain('--center-shell-cols: 16rem clamp(680px, 44vw, 920px);')

    const rightRail = wrapper.find('[data-testid="right-rail"]')
    expect(rightRail.exists()).toBe(true)
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
    expect(shell.classes()).toContain('xl:max-w-[1440px]')
    expect(shell.classes()).toContain('2xl:max-w-[1560px]')
    expect(shell.attributes('style')).toContain('--center-shell-cols: 16rem clamp(680px, 56vw, 980px);')
    expect(shell.attributes('style')).not.toContain('22rem')

    const rightRail = wrapper.find('[data-testid="right-rail"]')
    expect(rightRail.exists()).toBe(false)
  })

  it('calls seen endpoint once when modal closes', async () => {
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

    await flush()
    await flush()

    const modal = wrapper.findComponent({ name: 'MarkYourCalendarModal' })
    expect(modal.exists()).toBe(true)
    modal.vm.$emit('close')
    await flush()

    expect(seenPopupMock).toHaveBeenCalledTimes(1)
    expect(seenPopupMock).toHaveBeenCalledWith({
      force_version: 5,
      month_key: '2026-02',
    })
  })
})
