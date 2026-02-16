import { describe, expect, it, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const mockAuth = vi.hoisted(() => ({
  isAuthed: false,
  loading: false,
  user: null,
  fetchUser: vi.fn(async () => null),
}))

const mockApi = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => mockAuth,
}))

vi.mock('@/services/api', () => ({
  default: mockApi,
}))

import VerifyEmailView from './VerifyEmailView.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/verify-email', component: VerifyEmailView },
      { path: '/verify-email/:id/:hash', component: VerifyEmailView },
      { path: '/', component: { template: '<div>home</div>' } },
    ],
  })
}

describe('VerifyEmailView', () => {
  beforeEach(() => {
    mockAuth.isAuthed = false
    mockAuth.loading = false
    mockAuth.user = null
    mockAuth.fetchUser.mockClear()
    mockApi.get.mockReset()
    mockApi.post.mockReset()
  })

  it('disables resend button when user is not authenticated', async () => {
    const router = makeRouter()
    await router.push('/verify-email')
    await router.isReady()

    const wrapper = mount(VerifyEmailView, {
      global: {
        plugins: [router],
      },
    })

    const resendButton = wrapper.findAll('button')[0]
    expect(resendButton.attributes('disabled')).toBeDefined()
  })

  it('resends verification email for authenticated user', async () => {
    mockAuth.isAuthed = true
    mockAuth.user = { email_verified_at: null }
    mockApi.post.mockResolvedValue({ data: { message: 'Verification link sent.' } })

    const router = makeRouter()
    await router.push('/verify-email')
    await router.isReady()

    const wrapper = mount(VerifyEmailView, {
      global: {
        plugins: [router],
      },
    })

    const resendButton = wrapper.findAll('button')[0]
    await resendButton.trigger('click')

    expect(mockApi.post).toHaveBeenCalledWith('/auth/email/verification-notification', {}, { meta: { skipErrorToast: true } })
    expect(wrapper.text()).toContain('Verification link sent.')
  })
})
