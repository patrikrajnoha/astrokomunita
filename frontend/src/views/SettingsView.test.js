import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SettingsView from './SettingsView.vue'

const pushMock = vi.hoisted(() => vi.fn())

const authMock = vi.hoisted(() => ({
  user: {
    id: 1,
    name: 'Tester',
    email: 'tester@example.com',
    newsletter_subscribed: false,
  },
  initialized: true,
  csrf: vi.fn(async () => {}),
  fetchUser: vi.fn(async () => {}),
  logout: vi.fn(async () => {}),
}))

const httpMock = vi.hoisted(() => ({
  patch: vi.fn(),
  delete: vi.fn(),
  get: vi.fn(),
  post: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
  }),
  useRoute: () => ({
    query: {},
  }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authMock,
}))

vi.mock('@/stores/onboardingTour', () => ({
  useOnboardingTourStore: () => ({
    restartTour: vi.fn(),
    isOpen: false,
    shouldAutoOpen: false,
    hydrate: vi.fn(),
    openTour: vi.fn(),
    closeTour: vi.fn(),
  }),
}))

vi.mock('@/services/api', () => ({
  default: httpMock,
}))

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('SettingsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authMock.user = {
      id: 1,
      name: 'Tester',
      email: 'tester@example.com',
      newsletter_subscribed: false,
    }
    authMock.initialized = true
    httpMock.patch.mockResolvedValue({
      data: {
        data: {
          newsletter_subscribed: true,
        },
      },
    })
    httpMock.get.mockImplementation((url) => {
      if (url === '/account/email') {
        return Promise.resolve({
          data: {
            data: {
              email: 'tester@example.com',
              verified: true,
              email_verified_at: '2026-03-01T12:00:00Z',
              requires_email_verification: true,
              seconds_to_resend: 0,
              pending_email_change: null,
            },
          },
        })
      }

      return Promise.resolve({
        data: new Blob(['{"export_version":"1.0"}'], { type: 'application/json' }),
        headers: {
          'content-disposition':
            'attachment; filename="nebesky-sprievodca-export-tester-20260221_173000.json"',
        },
      })
    })
    httpMock.post.mockResolvedValue({
      data: {
        message: 'Verification code sent.',
        data: {
          email: 'tester@example.com',
          verified: false,
          email_verified_at: null,
          requires_email_verification: true,
          seconds_to_resend: 60,
          pending_email_change: null,
        },
      },
    })
  })

  it('renders email verification state from account email API', async () => {
    const wrapper = mount(SettingsView)
    await flush()
    await flush()

    expect(httpMock.get).toHaveBeenCalledWith('/account/email', {
      meta: { skipErrorToast: true },
    })
    expect(wrapper.find('[data-testid="settings-email-status"]').text()).toContain('Verified')
    expect(wrapper.find('[data-testid="settings-email-status"]').text()).toContain('tester@example.com')
  })

  it('sends verification code from settings email section', async () => {
    httpMock.get.mockImplementation((url) => {
      if (url === '/account/email') {
        return Promise.resolve({
          data: {
            data: {
              email: 'tester@example.com',
              verified: false,
              email_verified_at: null,
              requires_email_verification: true,
              seconds_to_resend: 0,
              pending_email_change: null,
            },
          },
        })
      }

      return Promise.resolve({
        data: new Blob(['{}'], { type: 'application/json' }),
        headers: {},
      })
    })

    const wrapper = mount(SettingsView)
    await flush()
    await flush()

    await wrapper.get('#settings-email-send').trigger('click')
    await flush()

    expect(authMock.csrf).toHaveBeenCalled()
    expect(httpMock.post).toHaveBeenCalledWith('/account/email/verification/send', {})
    expect(wrapper.text()).toContain('Verification code sent.')
  })

  it('updates newsletter toggle via API', async () => {
    const wrapper = mount(SettingsView)
    await flush()

    const checkbox = wrapper.get('#settings-newsletter')
    await checkbox.setValue(true)
    await flush()

    expect(authMock.csrf).toHaveBeenCalledTimes(1)
    expect(httpMock.patch).toHaveBeenCalledWith('/me/newsletter', {
      newsletter_subscribed: true,
    })
    expect(authMock.user.newsletter_subscribed).toBe(true)
  })

  it('downloads profile export via API', async () => {
    if (!URL.createObjectURL) {
      URL.createObjectURL = () => 'blob:export'
    }
    if (!URL.revokeObjectURL) {
      URL.revokeObjectURL = () => {}
    }

    const createObjectUrlSpy = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:export')
    const revokeObjectUrlSpy = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
    const anchorClickSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => {})

    const wrapper = mount(SettingsView, { attachTo: document.body })
    await flush()

    await wrapper.get('#settings-export-button').trigger('click')
    await flush()

    expect(httpMock.get).toHaveBeenCalledWith('/me/export', {
      responseType: 'blob',
      meta: { skipErrorToast: true },
    })
    expect(createObjectUrlSpy).toHaveBeenCalledTimes(1)
    expect(anchorClickSpy).toHaveBeenCalledTimes(1)

    createObjectUrlSpy.mockRestore()
    revokeObjectUrlSpy.mockRestore()
    anchorClickSpy.mockRestore()
    wrapper.unmount()
  })

  it('keeps user activity hidden by default and loads it on demand', async () => {
    httpMock.get.mockImplementation((url) => {
      if (url === '/account/email') {
        return Promise.resolve({
          data: {
            data: {
              email: 'tester@example.com',
              verified: true,
              email_verified_at: '2026-03-01T12:00:00Z',
              requires_email_verification: true,
              seconds_to_resend: 0,
              pending_email_change: null,
            },
          },
        })
      }

      if (url === '/me/activity') {
        return Promise.resolve({
          data: {
            last_login_at: '2026-02-23T10:00:00Z',
            posts_count: 7,
            event_participations_count: 3,
          },
        })
      }

      return Promise.resolve({
        data: new Blob(['{}'], { type: 'application/json' }),
        headers: {},
      })
    })

    const wrapper = mount(SettingsView)
    await flush()

    expect(wrapper.find('[data-testid="activity-values"]').exists()).toBe(false)

    await wrapper.get('#settings-activity-toggle').trigger('click')
    await flush()

    expect(httpMock.get).toHaveBeenCalledWith('/me/activity', {
      meta: { skipErrorToast: true },
    })
    expect(wrapper.find('[data-testid="activity-values"]').exists()).toBe(true)
  })
})
