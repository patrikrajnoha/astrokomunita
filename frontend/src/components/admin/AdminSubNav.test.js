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
      { path: '/admin/bots', name: 'admin.bots', component: { template: '<div>bots</div>' } },
      { path: '/admin/bots/engine', name: 'admin.bots.engine', component: { template: '<div>bots-engine</div>' } },
      { path: '/admin/bots/sources', name: 'admin.bots.sources', component: { template: '<div>bot-sources</div>' } },
      { path: '/admin/bots/schedules', name: 'admin.bots.schedules', component: { template: '<div>bot-schedules</div>' } },
      { path: '/admin/bots/activity', name: 'admin.bots.activity', component: { template: '<div>bot-activity</div>' } },
      { path: '/admin/performance-metrics', name: 'admin.performance-metrics', component: { template: '<div>performance</div>' } },
      { path: '/admin/sidebar-config', name: 'admin.sidebar-config', component: { template: '<div>sidebar-config</div>' } },
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

    expect(wrapper.text()).toContain('Editor')
    expect(wrapper.text()).not.toContain('Správa komunity')

    authState.isAdmin = true
    authState.isEditor = false
  })

  it('renders grouped IA and supports collapsible content sub-navigation', async () => {
    const { wrapper } = await mountAt('/admin/dashboard')

    expect(wrapper.text()).toContain('Prehľad')
    expect(wrapper.text()).toContain('HLAVNÉ')
    expect(wrapper.text()).toContain('KOMUNITA')
    expect(wrapper.text()).toContain('SYSTÉM')
    expect(wrapper.text()).toContain('Udalosti')
    expect(wrapper.text()).toContain('Obsah')
    expect(wrapper.text()).not.toContain('Vybrané udalosti')
    expect(wrapper.text()).not.toContain('Súťaže')

    await wrapper.get('button[aria-label="Zobraziť podsekcie Obsah"]').trigger('click')

    expect(wrapper.text()).toContain('Vybrané udalosti')
    expect(wrapper.text()).toContain('Súťaže')
  })

  it('hides banned words link when VITE_FEATURE_WIP is not enabled', async () => {
    const { wrapper } = await mountAt('/admin/dashboard')

    expect(wrapper.text()).not.toContain('Reporty')
    expect(wrapper.text()).not.toContain('Zakázané slová')
    expect(wrapper.text()).toContain('Udalosti')
    expect(wrapper.text()).toContain('Správa komunity')
    expect(wrapper.text()).toContain('Boti')
    expect(wrapper.text()).toContain('Výkonnosť')
  })

  it('marks featured events sub-item active inside Obsah group', async () => {
    const { wrapper } = await mountAt('/admin/featured-events')

    const active = activeItems(wrapper)
    expect(active).toHaveLength(1)
    expect(active[0].text()).toContain('Vybrané udalosti')
  })

  it.each([
    ['/admin/candidates/12', 'Udalosti'],
    ['/admin/crawl-runs/22', 'Udalosti'],
    ['/admin/users/15', 'Správa komunity'],
    ['/admin/events/candidates', 'Udalosti'],
    ['/admin/community/moderation', 'Správa komunity'],
  ])('marks correct section active for %s', async (path, expectedLabel) => {
    const { wrapper } = await mountAt(path)

    const active = activeItems(wrapper)
    expect(active).toHaveLength(1)
    expect(active[0].text()).toContain(expectedLabel)
  })

  it('emits navigate when a navigation item is clicked', async () => {
    const { wrapper } = await mountAt('/admin/dashboard')

    await wrapper.get('.adminSubNav__item--overview').trigger('click')

    expect(wrapper.emitted('navigate')).toBeTruthy()
  })
})
