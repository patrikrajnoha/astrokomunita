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

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
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

async function completeRegisterWizard(wrapper, {
  name = 'Tester',
  username = 'valid_user',
  email = 'verify-me@example.com',
  password = 'password123',
} = {}) {
  await wrapper.find('input[autocomplete="name"]').setValue(name)
  await wrapper.find('input[autocomplete="username"]').setValue(username)
  await wait(450)
  await flush()

  const stepOneNext = wrapper.findAll('button').find((button) => button.text() === 'Pokračovať')
  await stepOneNext.trigger('click')
  await flush()

  await wrapper.find('input[autocomplete="email"]').setValue(email)
  const passwordInputs = wrapper.findAll('input[autocomplete="new-password"]')
  await passwordInputs[0].setValue(password)
  await passwordInputs[1].setValue(password)

  const stepTwoNext = wrapper.findAll('button').find((button) => button.text() === 'Pokračovať')
  await stepTwoNext.trigger('click')
  await flush()

  await wrapper.find('form').trigger('submit.prevent')
  await flush()
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

    await completeRegisterWizard(wrapper)

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
    expect(toastSuccessMock).toHaveBeenCalledWith('Poslali sme ti overovací kód.')
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

    await completeRegisterWizard(wrapper, {
      username: 'valid_user_two',
      email: 'verify-me-2@example.com',
    })

    expect(pushMock).toHaveBeenCalledWith({
      name: 'settings.email',
      query: { redirect: '/' },
    })
    expect(toastWarnMock).toHaveBeenCalled()
  })
})
