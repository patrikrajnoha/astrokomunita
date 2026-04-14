import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RegisterView from './RegisterView.vue'

const pushMock = vi.hoisted(() => vi.fn())
const authMock = vi.hoisted(() => ({
  loading: false,
  isAdmin: false,
  user: null,
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

function findStepButton(wrapper, label) {
  return wrapper.findAll('button[type="button"]').find((node) => node.text() === label)
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

  await findStepButton(wrapper, 'Pokračovať').trigger('click')
  await flush()

  await wrapper.find('input[autocomplete="email"]').setValue(email)
  const passwordInputs = wrapper.findAll('input[autocomplete="new-password"]')
  await passwordInputs[0].setValue(password)
  await passwordInputs[1].setValue(password)

  await findStepButton(wrapper, 'Pokračovať').trigger('click')
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

  it('redirects unverified new user to requested route', async () => {
    const wrapper = mountRegisterView()
    await flush()

    await completeRegisterWizard(wrapper)

    expect(authMock.register).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith('/')
  })

  it('redirects verified new user to requested route', async () => {
    authMock.register.mockImplementationOnce(async () => {
      authMock.user = {
        id: 50,
        email: 'verified@example.com',
        requires_email_verification: true,
        email_verified_at: '2026-03-24T22:00:00Z',
      }
    })

    const wrapper = mountRegisterView()
    await flush()

    await completeRegisterWizard(wrapper, {
      username: 'valid_user_two',
      email: 'verified@example.com',
    })

    expect(pushMock).toHaveBeenCalledWith('/')
  })

  it('shows duplicate email error on account step', async () => {
    authMock.register.mockRejectedValueOnce({
      response: {
        data: {
          errors: {
            email: ['Používateľ s týmto e-mailom už existuje.'],
          },
        },
      },
    })

    const wrapper = mountRegisterView()
    await flush()

    await completeRegisterWizard(wrapper, {
      username: 'valid_user_three',
      email: 'existing@example.com',
    })

    expect(pushMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Používateľ s týmto e-mailom už existuje.')
    expect(wrapper.find('input[autocomplete="email"]').exists()).toBe(true)
  })
})
