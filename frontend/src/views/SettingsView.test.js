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
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
  }),
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
    httpMock.get.mockResolvedValue({
      data: new Blob(['{"export_version":"1.0"}'], { type: 'application/json' }),
      headers: {
        'content-disposition': 'attachment; filename="nebesky-sprievodca-export-tester-20260221_173000.json"',
      },
    })
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
    const anchorClickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

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
})
