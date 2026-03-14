import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminHubLayout from './AdminHubLayout.vue'

describe('AdminHubLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders mobile admin subnav and admin content shell', () => {
    const wrapper = mount(AdminHubLayout, {
      global: {
        stubs: {
          RouterView: { template: '<div class="router-view-stub">admin content</div>' },
          AdminSubNav: { template: '<aside class="admin-subnav-stub">admin nav</aside>' },
        },
      },
    })

    expect(wrapper.find('.adminHub__subNav--mobile').exists()).toBe(true)
    expect(wrapper.find('.router-view-stub').exists()).toBe(true)
  })
})
