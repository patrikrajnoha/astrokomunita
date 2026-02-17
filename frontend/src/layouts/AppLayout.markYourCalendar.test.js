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
    routes: [{ path: '/', component: AppLayout }],
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
