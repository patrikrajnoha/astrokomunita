import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import AdminSubNav from './AdminSubNav.vue'

const authState = {
  isAdmin: true,
  isEditor: false,
}

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/dashboard', name: 'admin.dashboard', component: { template: '<div>dashboard</div>' } },
      { path: '/admin/events/crawling', name: 'admin.event-sources', meta: { adminSection: 'events', adminTab: 'crawling' }, component: { template: '<div>crawling</div>' } },
      { path: '/admin/events/candidates', name: 'admin.event-candidates', meta: { adminSection: 'events', adminTab: 'candidates' }, component: { template: '<div>candidates</div>' } },
      { path: '/admin/events/published', name: 'admin.events', meta: { adminSection: 'events', adminTab: 'published' }, component: { template: '<div>published</div>' } },
      { path: '/admin/candidates/:id', name: 'admin.candidate.detail', meta: { adminSection: 'events', adminTab: 'candidates' }, component: { template: '<div>candidate detail</div>' } },
      { path: '/admin/crawl-runs/:id', name: 'admin.crawl-run.detail', meta: { adminSection: 'events', adminTab: 'crawling' }, component: { template: '<div>run detail</div>' } },
      { path: '/admin/community/users', name: 'admin.users', meta: { adminSection: 'community', adminTab: 'users' }, component: { template: '<div>users</div>' } },
      { path: '/admin/users/:id', name: 'admin.users.detail', meta: { adminSection: 'community', adminTab: 'users' }, component: { template: '<div>user detail</div>' } },
      { path: '/admin/community/moderation', name: 'admin.moderation', meta: { adminSection: 'community', adminTab: 'moderation' }, component: { template: '<div>moderation</div>' } },
      { path: '/admin/content/articles', name: 'admin.blog', meta: { adminSection: 'content', adminTab: 'articles' }, component: { template: '<div>articles</div>' } },
      { path: '/admin/content/newsletter', name: 'admin.newsletter', meta: { adminSection: 'content', adminTab: 'newsletter' }, component: { template: '<div>newsletter</div>' } },
      { path: '/admin/featured-events', name: 'admin.featured-events', component: { template: '<div>featured</div>' } },
      { path: '/admin/contests', name: 'admin.contests', component: { template: '<div>contests</div>' } },
      { path: '/admin/sidebar', name: 'admin.sidebar', component: { template: '<div>sidebar</div>' } },
      { path: '/admin/bots', name: 'admin.bots', component: { template: '<div>bots</div>' } },
      { path: '/admin/bots/engine', name: 'admin.bots.engine', component: { template: '<div>bots-engine</div>' } },
      { path: '/admin/bots/sources', name: 'admin.bots.sources', component: { template: '<div>bot-sources</div>' } },
      { path: '/admin/bots/schedules', name: 'admin.bots.schedules', component: { template: '<div>bot-schedules</div>' } },
      { path: '/admin/bots/activity', name: 'admin.bots.activity', component: { template: '<div>bot-activity</div>' } },
      { path: '/admin/performance-metrics', name: 'admin.performance-metrics', component: { template: '<div>performance</div>' } },
      { path: '/admin/:pathMatch(.*)*', component: { template: '<div>admin-any</div>' } },
    ],
  })
}

async function mountAt(path) {
  const router = makeRouter()
  await router.push(path)
  await router.isReady()

  const wrapper = mount(AdminSubNav, {
    global: {
      plugins: [router],
    },
  })

  return { wrapper, router }
}

function activeItems(wrapper) {
  return wrapper.findAll('.adminSubNav__item.active')
}

describe('AdminSubNav', () => {
  it('shows editor-only navigation when actor is editor', async () => {
    authState.isAdmin = false
    authState.isEditor = true
    const { wrapper } = await mountAt('/admin/content/articles')

    expect(wrapper.text()).toContain('Editor Hub')
    expect(wrapper.text()).not.toContain('SprĂˇva komunity')

    authState.isAdmin = true
    authState.isEditor = false
  })

  it('hides banned words link when VITE_FEATURE_WIP is not enabled', async () => {
    const { wrapper } = await mountAt('/admin/dashboard')

    expect(wrapper.text()).not.toContain('Reporty')
    expect(wrapper.text()).not.toContain('Zakázané slová')
    expect(wrapper.text()).toContain('Event Pipeline')
    expect(wrapper.text()).toContain('Správa komunity')
  })

  it.each([
    ['/admin/candidates/12', 'Event Pipeline'],
    ['/admin/crawl-runs/22', 'Event Pipeline'],
    ['/admin/users/15', 'Správa komunity'],
    ['/admin/events/candidates', 'Event Pipeline'],
    ['/admin/community/moderation', 'Správa komunity'],
  ])('marks correct section active for %s', async (path, expectedLabel) => {
    const { wrapper } = await mountAt(path)

    const active = activeItems(wrapper)
    expect(active).toHaveLength(1)
    expect(active[0].text()).toContain(expectedLabel)
  })
})
