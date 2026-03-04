import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import baseRouter, { applyAuthGuards } from './index'

const authState = {
  initialized: true,
  bootstrapDone: true,
  status: 'authenticated',
  loading: false,
  isAuthed: true,
  isAdmin: true,
  user: {
    email_verified_at: '2026-03-01T00:00:00Z',
    requires_email_verification: false,
  },
  bootstrapAuth: vi.fn(async () => {}),
}

const preferencesState = {
  loaded: true,
  isOnboardingCompleted: true,
  fetchPreferences: vi.fn(async () => {}),
}

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => preferencesState,
}))

function makeRouter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: baseRouter.options.routes,
  })

  applyAuthGuards(router)
  return router
}

describe('admin nested section routes', () => {
  beforeEach(() => {
    authState.isAuthed = true
    authState.isAdmin = true
    authState.status = 'authenticated'
    authState.bootstrapDone = true
    authState.initialized = true
    authState.loading = false
    authState.user = {
      email_verified_at: '2026-03-01T00:00:00Z',
      requires_email_verification: false,
    }
  })

  it('maps existing admin route names to new nested paths', () => {
    const router = makeRouter()

    expect(router.resolve({ name: 'admin.event-sources' }).path).toBe('/admin/events/crawling')
    expect(router.resolve({ name: 'admin.event-candidates' }).path).toBe('/admin/events/candidates')
    expect(router.resolve({ name: 'admin.events' }).path).toBe('/admin/events/published')
    expect(router.resolve({ name: 'admin.users' }).path).toBe('/admin/community/users')
    expect(router.resolve({ name: 'admin.moderation' }).path).toBe('/admin/community/moderation')
    expect(router.resolve({ name: 'admin.blog' }).path).toBe('/admin/content/articles')
    expect(router.resolve({ name: 'admin.newsletter' }).path).toBe('/admin/content/newsletter')
  })

  it('assigns stable names to admin containers, defaults and legacy redirects', () => {
    const router = makeRouter()

    expect(router.resolve({ name: 'admin.root' }).path).toBe('/admin')
    expect(router.resolve({ name: 'admin.root.default' }).path).toBe('/admin')

    expect(router.resolve({ name: 'admin.events.section' }).path).toBe('/admin/events')
    expect(router.resolve({ name: 'admin.events.default' }).path).toBe('/admin/events')

    expect(router.resolve({ name: 'admin.community.section' }).path).toBe('/admin/community')
    expect(router.resolve({ name: 'admin.community.default' }).path).toBe('/admin/community')

    expect(router.resolve({ name: 'admin.content.section' }).path).toBe('/admin/content')
    expect(router.resolve({ name: 'admin.content.default' }).path).toBe('/admin/content')

    expect(router.resolve({ name: 'admin.legacy.event-sources' }).path).toBe('/admin/event-sources')
    expect(router.resolve({ name: 'admin.legacy.event-candidates' }).path).toBe('/admin/event-candidates')
    expect(router.resolve({ name: 'admin.legacy.candidates' }).path).toBe('/admin/candidates')
    expect(router.resolve({ name: 'admin.legacy.users' }).path).toBe('/admin/users')
    expect(router.resolve({ name: 'admin.legacy.moderation' }).path).toBe('/admin/moderation')
    expect(router.resolve({ name: 'admin.legacy.blog' }).path).toBe('/admin/blog')
    expect(router.resolve({ name: 'admin.legacy.newsletter' }).path).toBe('/admin/newsletter')
  })

  it('provides admin section and tab meta for canonical and detail routes', () => {
    const router = makeRouter()

    expect(router.resolve({ name: 'admin.event-sources' }).meta.adminSection).toBe('events')
    expect(router.resolve({ name: 'admin.event-sources' }).meta.adminTab).toBe('crawling')

    expect(router.resolve({ name: 'admin.event-candidates' }).meta.adminSection).toBe('events')
    expect(router.resolve({ name: 'admin.event-candidates' }).meta.adminTab).toBe('candidates')

    expect(router.resolve({ name: 'admin.events' }).meta.adminSection).toBe('events')
    expect(router.resolve({ name: 'admin.events' }).meta.adminTab).toBe('published')

    expect(router.resolve({ name: 'admin.candidate.detail', params: { id: '15' } }).meta.adminSection).toBe('events')
    expect(router.resolve({ name: 'admin.candidate.detail', params: { id: '15' } }).meta.adminTab).toBe('candidates')

    expect(router.resolve({ name: 'admin.crawl-run.detail', params: { id: '7' } }).meta.adminSection).toBe('events')
    expect(router.resolve({ name: 'admin.crawl-run.detail', params: { id: '7' } }).meta.adminTab).toBe('crawling')

    expect(router.resolve({ name: 'admin.users' }).meta.adminSection).toBe('community')
    expect(router.resolve({ name: 'admin.users' }).meta.adminTab).toBe('users')

    expect(router.resolve({ name: 'admin.users.detail', params: { id: '4' } }).meta.adminSection).toBe('community')
    expect(router.resolve({ name: 'admin.users.detail', params: { id: '4' } }).meta.adminTab).toBe('users')

    expect(router.resolve({ name: 'admin.moderation' }).meta.adminSection).toBe('community')
    expect(router.resolve({ name: 'admin.moderation' }).meta.adminTab).toBe('moderation')

    expect(router.resolve({ name: 'admin.blog' }).meta.adminSection).toBe('content')
    expect(router.resolve({ name: 'admin.blog' }).meta.adminTab).toBe('articles')

    expect(router.resolve({ name: 'admin.newsletter' }).meta.adminSection).toBe('content')
    expect(router.resolve({ name: 'admin.newsletter' }).meta.adminTab).toBe('newsletter')
  })

  it('provides admin section meta for standalone named admin routes', () => {
    const router = makeRouter()

    expect(router.resolve({ name: 'admin.dashboard' }).meta.adminSection).toBe('dashboard')
    expect(router.resolve({ name: 'admin.contests' }).meta.adminSection).toBe('content')
    expect(router.resolve({ name: 'admin.featured-events' }).meta.adminSection).toBe('content')

    expect(router.resolve({ name: 'admin.astrobot' }).meta.adminSection).toBe('automation')
    expect(router.resolve({ name: 'admin.bots' }).meta.adminSection).toBe('automation')
    expect(router.resolve({ name: 'admin.bots.kozmo' }).meta.adminSection).toBe('automation')
    expect(router.resolve({ name: 'admin.bots.stellar' }).meta.adminSection).toBe('automation')

    expect(router.resolve({ name: 'admin.sidebar' }).meta.adminSection).toBe('frontend')
    expect(router.resolve({ name: 'admin.performance-metrics' }).meta.adminSection).toBe('performance')

    if (router.hasRoute('admin.banned-words')) {
      expect(router.resolve({ name: 'admin.banned-words' }).meta.adminSection).toBe('content')
    }
  })

  it('redirects legacy paths and keeps query/hash', async () => {
    const router = makeRouter()

    await router.push('/admin/event-sources?source=imo#recent')
    await router.isReady()
    expect(router.currentRoute.value.path).toBe('/admin/events/crawling')
    expect(router.currentRoute.value.query.source).toBe('imo')
    expect(router.currentRoute.value.hash).toBe('#recent')

    await router.push('/admin/users?page=2')
    expect(router.currentRoute.value.path).toBe('/admin/community/users')
    expect(router.currentRoute.value.query.page).toBe('2')

    await router.push('/admin/blog?status=draft')
    expect(router.currentRoute.value.path).toBe('/admin/content/articles')
    expect(router.currentRoute.value.query.status).toBe('draft')

    await router.push('/admin/reports?scope=open')
    expect(router.currentRoute.value.path).toBe('/admin/community/moderation')
    expect(router.currentRoute.value.query.scope).toBe('open')
    expect(router.currentRoute.value.query.tab).toBe('reports')

    await router.push('/admin/reports?tab=queue&scope=open')
    expect(router.currentRoute.value.path).toBe('/admin/community/moderation')
    expect(router.currentRoute.value.query.scope).toBe('open')
    expect(router.currentRoute.value.query.tab).toBe('queue')
  })
})
