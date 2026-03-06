import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import AdminSectionHeader from './AdminSectionHeader.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/candidates/:id',
        name: 'admin.candidate.detail',
        meta: { adminSection: 'events', adminTab: 'candidates' },
        component: { template: '<div>candidate detail</div>' },
      },
      {
        path: '/admin/events/candidates',
        name: 'admin.event-candidates',
        meta: { adminSection: 'events', adminTab: 'candidates' },
        component: { template: '<div>candidates</div>' },
      },
      {
        path: '/admin/events/crawling',
        name: 'admin.event-sources',
        meta: { adminSection: 'events', adminTab: 'crawling' },
        component: { template: '<div>sources</div>' },
      },
      {
        path: '/admin/events/published',
        name: 'admin.events',
        meta: { adminSection: 'events', adminTab: 'published' },
        component: { template: '<div>events</div>' },
      },
    ],
  })
}

describe('AdminSectionHeader', () => {
  it('renders section context, tabs and back link with query preserved', async () => {
    const router = makeRouter()
    await router.push('/admin/candidates/44?page=3&search=lyrids')
    await router.isReady()

    const wrapper = mount(AdminSectionHeader, {
      props: {
        section: 'events',
        title: 'Detail kandidata',
        backTo: { name: 'admin.event-candidates' },
      },
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).toContain('Udalosti')
    expect(wrapper.text()).toContain('Detail kandidata')
    expect(wrapper.find('.adminSectionTabs__tab.active').text()).toContain('Kandidáti')

    const back = wrapper.get('[data-testid="admin-section-back-link"]')
    expect(back.attributes('href')).toContain('/admin/events/candidates?page=3&search=lyrids')
  })

  it('filters back-link query params by whitelist', async () => {
    const router = makeRouter()
    await router.push('/admin/candidates/44?page=3&search=lyrids&token=secret&debug=1')
    await router.isReady()

    const wrapper = mount(AdminSectionHeader, {
      props: {
        section: 'events',
        title: 'Detail kandidata',
        backTo: { name: 'admin.event-candidates' },
      },
      global: {
        plugins: [router],
      },
    })

    const back = wrapper.get('[data-testid="admin-section-back-link"]')
    expect(back.attributes('href')).toContain('/admin/events/candidates?page=3&search=lyrids')
    expect(back.attributes('href')).not.toContain('token=')
    expect(back.attributes('href')).not.toContain('debug=')
  })

  it('omits query on back-link when no whitelisted query keys remain', async () => {
    const router = makeRouter()
    await router.push('/admin/candidates/44?token=secret&debug=1')
    await router.isReady()

    const wrapper = mount(AdminSectionHeader, {
      props: {
        section: 'events',
        title: 'Detail kandidata',
        backTo: { name: 'admin.event-candidates' },
      },
      global: {
        plugins: [router],
      },
    })

    const back = wrapper.get('[data-testid="admin-section-back-link"]')
    expect(back.attributes('href')).toBe('/admin/events/candidates')
  })
})
