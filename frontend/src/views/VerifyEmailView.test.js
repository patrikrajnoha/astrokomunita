import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import VerifyEmailView from './VerifyEmailView.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/verify-email', component: VerifyEmailView },
      { path: '/verify-email/:id/:hash', component: VerifyEmailView },
      { path: '/settings', name: 'settings', component: { template: '<div>settings</div>' } },
      { path: '/settings/email', name: 'settings.email', component: { template: '<div>settings-email</div>' } },
    ],
  })
}

describe('VerifyEmailView', () => {
  it('renders deprecated message and CTA', async () => {
    const router = makeRouter()
    await router.push('/verify-email')
    await router.isReady()

    const wrapper = mount(VerifyEmailView, {
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).toContain('Overenie cez odkaz uz nie je podporovane')
    expect(wrapper.text()).toContain('Prejst na overenie e-mailu')
  })

  it('navigates to settings email detail from CTA button', async () => {
    const router = makeRouter()
    await router.push('/verify-email/1/hash')
    await router.isReady()

    const wrapper = mount(VerifyEmailView, {
      global: {
        plugins: [router],
      },
    })

    await wrapper.find('button').trigger('click')
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(router.currentRoute.value.name).toBe('settings.email')
  })
})
