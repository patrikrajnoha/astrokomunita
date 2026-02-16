import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import AdminSubNav from './AdminSubNav.vue'

function makeRouter(path = '/admin/dashboard') {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/dashboard', component: { template: '<div>dashboard</div>' } },
      { path: '/admin/banned-words', component: { template: '<div>banned</div>' } },
      { path: '/admin/event-sources', component: { template: '<div>sources</div>' } },
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
    expect(wrapper.text()).toContain('Event sources')
  })
})
