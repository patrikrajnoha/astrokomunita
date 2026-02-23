import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import AdminSubNav from './AdminSubNav.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/dashboard', component: { template: '<div>dashboard</div>' } },
      { path: '/admin/banned-words', component: { template: '<div>banned</div>' } },
      { path: '/admin/event-sources', component: { template: '<div>sources</div>' } },
      { path: '/admin/event-candidates', component: { template: '<div>candidates</div>' } },
      { path: '/admin/:pathMatch(.*)*', component: { template: '<div>admin-any</div>' } },
    ],
  })
}

describe('AdminSubNav', () => {
  it('hides banned words link when VITE_FEATURE_WIP is not enabled', async () => {
    const router = makeRouter()
    await router.push('/admin/dashboard')
    await router.isReady()

    const wrapper = mount(AdminSubNav, {
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).not.toContain('Banned words')
    expect(wrapper.text()).toContain('Crawling hub')
  })

  it('marks crawling hub active on candidates routes too', async () => {
    const router = makeRouter()
    await router.push('/admin/event-candidates')
    await router.isReady()

    const wrapper = mount(AdminSubNav, {
      global: {
        plugins: [router],
      },
    })

    const activeItems = wrapper.findAll('.adminSubNav__item.active')
    expect(activeItems).toHaveLength(1)
    expect(activeItems[0].text()).toContain('Crawling hub')
  })
})
