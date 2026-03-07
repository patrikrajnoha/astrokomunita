import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ResetPasswordView from './ResetPasswordView.vue'

const pushMock = vi.hoisted(() => vi.fn())
const authMock = vi.hoisted(() => ({
  csrf: vi.fn(async () => {}),
}))
const httpMock = vi.hoisted(() => ({
  post: vi.fn(async () => ({ data: { message: 'ok' } })),
}))
const routeState = vi.hoisted(() => ({
  query: {
    email: 'reset@example.com',
  },
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => routeState,
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

describe('ResetPasswordView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    routeState.query = { email: 'reset@example.com' }
  })

  it('submits code and new password then redirects to login', async () => {
    const wrapper = mount(ResetPasswordView, {
      global: {
        stubs: {
          RouterLink: true,
        },
      },
    })

    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('12345-67890')
    await inputs[1].setValue('new-password-123')

    await wrapper.find('form').trigger('submit.prevent')
    await flush()

    expect(authMock.csrf).toHaveBeenCalledTimes(1)
    expect(httpMock.post).toHaveBeenCalledWith(
      '/auth/password/reset',
      {
        email: 'reset@example.com',
        code: '12345-67890',
        password: 'new-password-123',
        password_confirmation: 'new-password-123',
      },
      { meta: { skipErrorToast: true } },
    )
    expect(pushMock).toHaveBeenCalledWith({
      name: 'login',
      query: {
        email: 'reset@example.com',
        reset: '1',
      },
    })
  })

  it('shows invalid code error without API call when code format is wrong', async () => {
    const wrapper = mount(ResetPasswordView, {
      global: {
        stubs: {
          RouterLink: true,
        },
      },
    })

    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('BADCODE')
    await inputs[1].setValue('new-password-123')

    await wrapper.find('form').trigger('submit.prevent')
    await flush()

    expect(httpMock.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('You have entered an invalid code. It should look like XXXXX-XXXXX.')
  })
})
