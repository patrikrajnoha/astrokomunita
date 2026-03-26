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
      { path: '/forgot-password', component: { template: '<div>forgot</div>' }, name: 'forgot-password' },
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

  it('shows short success animation before redirect after login', async () => {
    vi.useFakeTimers()

    try {
      mockAuth.login.mockResolvedValueOnce({ id: 1 })

      const router = makeRouter()
      await router.push('/login?redirect=%2Fevents')
      await router.isReady()
      router.push = pushMock

      const wrapper = mount(LoginView, {
        global: {
          plugins: [router],
        },
      })

      await wrapper.find('input[type="email"]').setValue('you@example.com')
      await wrapper.find('input[type="password"]').setValue('my-password')

      const submitPromise = wrapper.find('form').trigger('submit.prevent')
      await Promise.resolve()

      expect(mockAuth.login).toHaveBeenCalledWith({
        email: 'you@example.com',
        password: 'my-password',
        remember: true,
      })
      expect(pushMock).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(2799)
      expect(pushMock).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(1)
      await submitPromise
      expect(pushMock).toHaveBeenCalledWith('/events')
    } finally {
      vi.useRealTimers()
    }
  })

  it('submits login when Enter is pressed in password field', async () => {
    vi.useFakeTimers()

    try {
      mockAuth.login.mockResolvedValueOnce({ id: 1 })

      const router = makeRouter()
      await router.push('/login?redirect=%2Fevents')
      await router.isReady()
      router.push = pushMock

      const wrapper = mount(LoginView, {
        global: {
          plugins: [router],
        },
      })

      await wrapper.find('input[type="email"]').setValue('you@example.com')
      await wrapper.find('input[type="password"]').setValue('my-password')

      await wrapper.find('input[type="password"]').trigger('keydown.enter')
      await Promise.resolve()

      expect(mockAuth.login).toHaveBeenCalledWith({
        email: 'you@example.com',
        password: 'my-password',
        remember: true,
      })

      await vi.advanceTimersByTimeAsync(2800)
      expect(pushMock).toHaveBeenCalledWith('/events')
    } finally {
      vi.useRealTimers()
    }
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

    expect(wrapper.text()).toContain('Účet je blokovaný')
    expect(wrapper.text()).toContain('Repeated abusive behavior.')
    expect(wrapper.text()).toContain('Blokované')
  })
})
