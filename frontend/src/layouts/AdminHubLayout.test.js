import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminHubLayout from './AdminHubLayout.vue'

describe('AdminHubLayout', () => {
  it('renders shared desktop rails and the centered admin content shell', () => {
    const wrapper = mount(AdminHubLayout, {
      global: {
        stubs: {
          RouterView: { template: '<div class="router-view-stub">admin content</div>' },
          MainNavbar: { template: '<nav class="main-nav-stub">main nav</nav>' },
          AdminSubNav: { template: '<aside class="admin-subnav-stub">admin nav</aside>' },
        },
      },
    })

    expect(wrapper.find('.adminHub__mainNav--desktop').exists()).toBe(true)
    expect(wrapper.find('.adminHub__subNav--desktop').exists()).toBe(true)
    expect(wrapper.find('.adminHub__subNav--mobile').exists()).toBe(true)
    expect(wrapper.find('.adminHub__contentCard .router-view-stub').exists()).toBe(true)
  })
})
