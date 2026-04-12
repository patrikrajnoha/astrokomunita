import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import App from './App.vue'

const authState = vi.hoisted(() => ({
  bootstrapDone: true,
  isAuthed: false,
  isAdmin: false,
  user: null,
  fetchUser: vi.fn(async () => null),
}))

const preferencesState = vi.hoisted(() => ({
  isOnboardingCompleted: true,
  loaded: true,
  loading: false,
  fetchPreferences: vi.fn(async () => null),
}))

const routeState = vi.hoisted(() => ({
  fullPath: '/admin/dashboard',
  name: 'admin.dashboard',
}))

const routerPushMock = vi.hoisted(() => vi.fn())
const appInitStateMock = vi.hoisted(() => ({
  initializing: false,
  initError: null,
}))

vi.mock('vue-router', () => ({
  RouterView: {
    template: '<div class="router-view-stub">route content</div>',
  },
  useRoute: () => routeState,
  useRouter: () => ({
    push: routerPushMock,
  }),
}))

vi.mock('@/bootstrap/appInitState', () => ({
  appInitState: appInitStateMock,
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => preferencesState,
}))

vi.mock('@/components/auth/EmailVerificationGateModal.vue', () => ({
  default: {
    props: ['open'],
    template: '<div v-if="open" class="email-modal-stub">email modal</div>',
  },
}))

vi.mock('@/components/ui/Toaster.vue', () => ({
  default: {
    template: '<div class="toaster-stub">toaster</div>',
  },
}))

vi.mock('@/components/ui/ConfirmModal.vue', () => ({
  default: {
    template: '<div class="confirm-modal-stub">confirm</div>',
  },
}))

describe('App bootstrap gate', () => {
  beforeEach(() => {
    authState.bootstrapDone = true
    authState.isAuthed = false
    authState.isAdmin = false
    authState.user = null
    authState.fetchUser.mockClear()
    preferencesState.isOnboardingCompleted = true
    preferencesState.loaded = true
    preferencesState.loading = false
    preferencesState.fetchPreferences.mockClear()
    routeState.fullPath = '/admin/dashboard'
    routeState.name = 'admin.dashboard'
    routerPushMock.mockClear()
    appInitStateMock.initializing = false
    appInitStateMock.initError = null
  })

  it('does not mount route content until auth bootstrap completes', () => {
    authState.bootstrapDone = false

    const wrapper = mount(App)

    expect(wrapper.find('.router-view-stub').exists()).toBe(false)
    expect(wrapper.find('.email-modal-stub').exists()).toBe(false)
    expect(wrapper.text()).toContain('Astrokomunita')
    expect(wrapper.text()).toContain('Čakám na dokončenie prihlásenia')
  })

  it('renders route content after auth bootstrap is done', () => {
    const wrapper = mount(App)

    expect(wrapper.find('.router-view-stub').exists()).toBe(true)
  })
})
