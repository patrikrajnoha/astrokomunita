import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminHubLayout from './AdminHubLayout.vue'

const getAdminAiConfigMock = vi.hoisted(() => vi.fn())

vi.mock('@/services/api/admin/ai', () => ({
  getAdminAiConfig: (...args) => getAdminAiConfigMock(...args),
}))

describe('AdminHubLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getAdminAiConfigMock.mockResolvedValue({
      data: {
        data: {
          features: {
            event_description_generate: {
              last_run: {
                status: 'success',
                updated_at: '2026-02-24T10:00:00Z',
              },
            },
            newsletter_prime_insights: {
              last_run: null,
            },
          },
        },
      },
    })
  })

  it('renders admin subnav rails and the centered admin content shell', () => {
    const wrapper = mount(AdminHubLayout, {
      global: {
        stubs: {
          RouterView: { template: '<div class="router-view-stub">admin content</div>' },
          RouterLink: { template: '<a class="router-link-stub"><slot /></a>' },
          AdminSubNav: { template: '<aside class="admin-subnav-stub">admin nav</aside>' },
        },
      },
    })

    expect(wrapper.find('.adminHub__mainNav--desktop').exists()).toBe(false)
    expect(wrapper.find('.adminHub__subNav--desktop').exists()).toBe(true)
    expect(wrapper.find('.adminHub__subNav--mobile').exists()).toBe(true)
    expect(wrapper.find('.adminHub__contentCard .router-view-stub').exists()).toBe(true)
    expect(wrapper.text()).toContain('AI:')
  })
})
