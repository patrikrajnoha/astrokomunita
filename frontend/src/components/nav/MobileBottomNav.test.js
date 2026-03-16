import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { nextTick } from 'vue'
import MobileBottomNav from '@/components/nav/MobileBottomNav.vue'

const makeRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>home</div>' } },
      { path: '/search', name: 'search', component: { template: '<div>search</div>' } },
      { path: '/events', name: 'events', component: { template: '<div>events</div>' } },
      {
        path: '/events/:id',
        name: 'event-detail',
        component: { template: '<div>event detail</div>' },
      },
      {
        path: '/notifications',
        name: 'notifications',
        component: { template: '<div>notifications</div>' },
      },
      { path: '/articles', name: 'learn', component: { template: '<div>articles</div>' } },
      {
        path: '/articles/:slug',
        name: 'learn-detail',
        component: { template: '<div>article detail</div>' },
      },
    ],
  })

const mountNavAt = async (path) => {
  const router = makeRouter()
  await router.push(path)
  await router.isReady()

  const wrapper = mount(MobileBottomNav, {
    global: {
      plugins: [router],
    },
    attachTo: document.body,
  })

  await nextTick()
  return wrapper
}

describe('MobileBottomNav', () => {
  it('renders the requested five items in the expected order', async () => {
    const wrapper = await mountNavAt('/')
    const labels = wrapper
      .findAll('[data-testid="mobile-bottom-nav-item"]')
      .map((item) => item.text())

    expect(labels).toEqual(['Domov', 'Preskúmať', 'Udalosti', 'Notifikácie', 'Články'])
  })

  it('keeps Events active for nested event routes', async () => {
    const wrapper = await mountNavAt('/events/123')
    const items = wrapper.findAll('[data-testid="mobile-bottom-nav-item"]')
    const eventsLink = items.find((item) => item.attributes('aria-label') === 'Udalosti')
    const homeLink = items.find((item) => item.attributes('aria-label') === 'Domov')

    expect(eventsLink?.classes()).toContain('is-active')
    expect(homeLink?.classes()).not.toContain('is-active')
  })

  it('keeps Articles active for nested article routes', async () => {
    const wrapper = await mountNavAt('/articles/meteoricky-dazd')
    const items = wrapper.findAll('[data-testid="mobile-bottom-nav-item"]')
    const articlesLink = items.find((item) => item.attributes('aria-label') === 'Články')

    expect(articlesLink?.classes()).toContain('is-active')
  })
})
