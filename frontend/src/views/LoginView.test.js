import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const pushMock = vi.fn()

const mockAuth = vi.hoisted(() => ({
  loading: false,
  user: null,
  error: null,
  login: vi.fn(async () => null),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

import LoginView from './LoginView.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', component: LoginView, name: 'login' },
      { path: '/', component: { template: '<div>home</div>' }, name: 'home' },
      { path: '/register', component: { template: '<div>register</div>' }, name: 'register' },
      { path: '/settings', component: { template: '<div>settings</div>' }, name: 'settings' },
      { path: '/settings/email', component: { template: '<div>settings-email</div>' }, name: 'settings.email' },
    ],
  })
}

describe('LoginView', () => {
  beforeEach(() => {
    mockAuth.loading = false
    mockAuth.user = null
    mockAuth.error = null
    mockAuth.login.mockClear()
    pushMock.mockReset()
  })

  it('renders banned state details when auth error indicates banned user', async () => {
    mockAuth.error = {
      type: 'banned',
      reason: 'Repeated abusive behavior.',
      bannedAt: '2026-02-17T09:30:00Z',
    }

    const router = makeRouter()
    await router.push('/login')
    await router.isReady()
    router.push = pushMock

    const wrapper = mount(LoginView, {
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).toContain('Tento ucet je zablokovany.')
    expect(wrapper.text()).toContain('Repeated abusive behavior.')
    expect(wrapper.text()).toContain('Zablokovane:')
  })
})
