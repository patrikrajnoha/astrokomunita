import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RegisterView from './RegisterView.vue'

const pushMock = vi.hoisted(() => vi.fn())
const authMock = vi.hoisted(() => ({
  loading: false,
  isAdmin: false,
  user: null,
  csrf: vi.fn(async () => {}),
  register: vi.fn(async () => {
    authMock.user = {
      id: 42,
      email: 'verify-me@example.com',
      requires_email_verification: true,
      email_verified_at: null,
    }
  }),
}))
const httpMock = vi.hoisted(() => ({
  get: vi.fn(async () => ({ data: { reason: 'ok' } })),
  post: vi.fn(async () => ({ data: { message: 'Verification code sent.' } })),
}))
const toastSuccessMock = vi.hoisted(() => vi.fn())
const toastWarnMock = vi.hoisted(() => vi.fn())

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

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: toastSuccessMock,
    warn: toastWarnMock,
  }),
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function mountRegisterView() {
  return mount(RegisterView, {
    global: {
      stubs: {
        RouterLink: true,
      },
    },
  })
}

describe('RegisterView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubEnv('VITE_TURNSTILE_SITE_KEY', 'test-site-key')

    authMock.loading = false
    authMock.isAdmin = false
    authMock.user = null

    window.turnstile = {
      render: vi.fn((_, options) => {
        if (options?.callback) {
          options.callback('turnstile-token')
        }
        return 1
      }),
      remove: vi.fn(),
      reset: vi.fn(),
    }
  })

  it('redirects unverified new user to Settings email section and auto-sends verification code', async () => {
    const wrapper = mountRegisterView()
    await flush()

    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('Tester')
    await inputs[1].setValue('valid_user')
    await inputs[2].setValue('verify-me@example.com')
    await inputs[3].setValue('password123')
    await inputs[4].setValue('password123')

    await wrapper.find('form').trigger('submit.prevent')
    await flush()

    expect(authMock.register).toHaveBeenCalled()
    expect(httpMock.post).toHaveBeenCalledWith(
      '/account/email/verification/send',
      {},
      { meta: { skipErrorToast: true } },
    )
    expect(pushMock).toHaveBeenCalledWith({
      name: 'settings.email',
      query: { redirect: '/' },
    })
    expect(toastSuccessMock).toHaveBeenCalledWith('Poslali sme ti overovaci kod.')
  })

  it('still redirects to Settings when auto-send is rate-limited', async () => {
    httpMock.post.mockRejectedValueOnce({
      response: {
        status: 429,
        data: { message: 'Please wait before requesting another code.' },
      },
    })

    const wrapper = mountRegisterView()
    await flush()

    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('Tester')
    await inputs[1].setValue('valid_user_two')
    await inputs[2].setValue('verify-me-2@example.com')
    await inputs[3].setValue('password123')
    await inputs[4].setValue('password123')

    await wrapper.find('form').trigger('submit.prevent')
    await flush()

    expect(pushMock).toHaveBeenCalledWith({
      name: 'settings.email',
      query: { redirect: '/' },
    })
    expect(toastWarnMock).toHaveBeenCalled()
  })
})
