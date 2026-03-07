import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ForgotPasswordView from './ForgotPasswordView.vue'

const pushMock = vi.hoisted(() => vi.fn())
const authMock = vi.hoisted(() => ({
  csrf: vi.fn(async () => {}),
}))
const httpMock = vi.hoisted(() => ({
  post: vi.fn(async () => ({ data: { message: 'ok' } })),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ query: {} }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/services/api', () => ({
  default: httpMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('ForgotPasswordView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('sends reset code request and continues to reset step', async () => {
    const wrapper = mount(ForgotPasswordView, {
      global: {
        stubs: {
          RouterLink: true,
        },
      },
    })

    const emailInput = wrapper.find('input[type="email"]')
    await emailInput.setValue('reset@example.com')
    await wrapper.find('form').trigger('submit.prevent')
    await flush()

    expect(authMock.csrf).toHaveBeenCalledTimes(1)
    expect(httpMock.post).toHaveBeenCalledWith(
      '/auth/password/forgot',
      { email: 'reset@example.com' },
      { meta: { skipErrorToast: true } },
    )
    expect(pushMock).toHaveBeenCalledWith({
      name: 'reset-password',
      query: {
        email: 'reset@example.com',
        sent: '1',
      },
    })
  })
})
