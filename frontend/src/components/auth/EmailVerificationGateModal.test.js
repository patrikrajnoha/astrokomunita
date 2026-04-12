import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EmailVerificationGateModal from './EmailVerificationGateModal.vue'

const authMock = vi.hoisted(() => ({
  user: {
    id: 42,
    email: 'verify-me@example.com',
    email_verified_at: null,
  },
  csrf: vi.fn(async () => {}),
}))

const httpMock = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
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

function normalizeText(value = '') {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

describe('EmailVerificationGateModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    authMock.user = {
      id: 42,
      email: 'verify-me@example.com',
      email_verified_at: null,
    }

    httpMock.get.mockResolvedValue({
      data: {
        data: {
          email: 'verify-me@example.com',
          verified: false,
          email_verified_at: null,
          seconds_to_resend: 0,
        },
      },
    })

    httpMock.post.mockImplementation((url) => {
      if (url === '/account/email/verification/send') {
        return Promise.resolve({
          data: {
            message: 'Overovaci kod bol odoslany.',
            data: {
              email: 'verify-me@example.com',
              verified: false,
              email_verified_at: null,
              seconds_to_resend: 60,
            },
          },
        })
      }

      if (url === '/account/email/verification/confirm') {
        return Promise.resolve({
          data: {
            message: 'E-mail bol uspesne overeny.',
            data: {
              email: 'verify-me@example.com',
              verified: true,
              email_verified_at: '2026-03-24T21:00:00Z',
              seconds_to_resend: 0,
            },
          },
        })
      }

      return Promise.reject(new Error(`Unexpected URL: ${url}`))
    })
  })

  it('auto-sends verification code when opened for unverified account', async () => {
    mount(EmailVerificationGateModal, {
      props: { open: true },
      global: {
        stubs: {
          teleport: true,
        },
      },
    })

    await flush()
    await flush()

    expect(httpMock.get).toHaveBeenCalledWith('/account/email', {
      meta: { skipErrorToast: true },
    })
    expect(httpMock.post).toHaveBeenCalledWith('/account/email/verification/send', {})
  })

  it('confirms code and emits verified event', async () => {
    const wrapper = mount(EmailVerificationGateModal, {
      props: { open: true },
      global: {
        stubs: {
          teleport: true,
        },
      },
    })

    await flush()
    await flush()

    httpMock.post.mockClear()

    await wrapper.get('#email-gate-code').setValue('12345-67890')
    const confirmButton = wrapper.findAll('button').find((node) => normalizeText(node.text()).includes('pokracovat'))
    expect(confirmButton).toBeTruthy()
    if (!confirmButton) {
      throw new Error('Confirm button not found')
    }

    await confirmButton.trigger('click')
    await flush()

    expect(httpMock.post).toHaveBeenCalledWith('/account/email/verification/confirm', {
      code: '12345-67890',
    })
    expect(wrapper.emitted('verified')).toBeTruthy()
  })
})
