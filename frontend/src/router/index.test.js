import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { applyAuthGuards } from './index'

const authState = {
  initialized: true,
  bootstrapDone: true,
  status: 'authenticated',
  loading: false,
  isAuthed: false,
  isAdmin: false,
  isEditor: false,
  user: null,
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
    routes: [
      { path: '/', name: 'home', component: { template: '<div>home</div>' }, meta: { requiresAuth: false } },
      { path: '/events', name: 'events', component: { template: '<div>events</div>' }, meta: { requiresAuth: false } },
      { path: '/privacy', name: 'privacy', component: { template: '<div>privacy</div>' }, meta: { requiresAuth: false } },
      { path: '/terms', name: 'terms', component: { template: '<div>terms</div>' }, meta: { requiresAuth: false } },
      { path: '/cookies', name: 'cookies', component: { template: '<div>cookies</div>' }, meta: { requiresAuth: false } },
      { path: '/settings', name: 'settings', component: { template: '<div>settings</div>' }, meta: { requiresAuth: true } },
      { path: '/settings/email', name: 'settings.email', component: { template: '<div>settings-email</div>' }, meta: { requiresAuth: true } },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' }, meta: { guest: true } },
      { path: '/verify-email', name: 'verify-email.deprecated', component: { template: '<div>verify</div>' }, meta: { requiresAuth: false } },
      { path: '/onboarding', name: 'onboarding', component: { template: '<div>onboarding</div>' }, meta: { requiresAuth: true } },
      { path: '/admin', name: 'admin', component: { template: '<div>admin</div>' }, meta: { requiresAuth: true, admin: true } },
      { path: '/admin/content/articles', name: 'admin.blog', component: { template: '<div>admin blog</div>' }, meta: { requiresAuth: true } },
      { path: '/admin/community/users', name: 'admin.users', component: { template: '<div>admin users</div>' }, meta: { requiresAuth: true } },
      { path: '/:pathMatch(.*)*', name: 'not-found', component: { template: '<div>notfound</div>' } },
    ],
  })

  applyAuthGuards(router)
  return router
}

describe('router auth guard', () => {
  beforeEach(() => {
    authState.initialized = true
    authState.bootstrapDone = true
    authState.status = 'authenticated'
    authState.loading = false
    authState.isAuthed = false
    authState.isAdmin = false
    authState.isEditor = false
    authState.user = null
    authState.bootstrapAuth.mockClear()

    preferencesState.loaded = true
    preferencesState.isOnboardingCompleted = true
    preferencesState.fetchPreferences.mockClear()
  })

  it('allows guest access to public pages without redirection', async () => {
    const router = makeRouter()
    await router.push('/events')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('events')
  })

  it('keeps legal pages public', async () => {
    const router = makeRouter()

    await router.push('/privacy')
    await router.isReady()
    expect(router.currentRoute.value.name).toBe('privacy')

    await router.push('/terms')
    expect(router.currentRoute.value.name).toBe('terms')

    await router.push('/cookies')
    expect(router.currentRoute.value.name).toBe('cookies')
  })

  it('redirects guest from protected routes to login', async () => {
    const router = makeRouter()
    await router.push('/settings')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/settings')
  })

  it('redirects authenticated unverified users to settings email route', async () => {
    authState.isAuthed = true
    authState.user = {
      email_verified_at: null,
      requires_email_verification: true,
    }

    const router = makeRouter()
    await router.push('/events')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('settings.email')
    expect(router.currentRoute.value.query.redirect).toBe('/events')
  })

  it('keeps authenticated non-admin users out of admin routes', async () => {
    authState.isAuthed = true
    authState.user = { email_verified_at: '2026-02-17T00:00:00Z' }

    const router = makeRouter()
    await router.push('/admin')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('home')
  })

  it('allows editor users into content admin routes and blocks community routes', async () => {
    authState.isAuthed = true
    authState.isEditor = true
    authState.user = { email_verified_at: '2026-02-17T00:00:00Z', role: 'editor' }

    const router = makeRouter()
    await router.push('/admin/content/articles')
    await router.isReady()
    expect(router.currentRoute.value.name).toBe('admin.blog')

    await router.push('/admin/community/users')
    expect(router.currentRoute.value.name).toBe('admin.blog')
  })

  it('redirects verified users with incomplete onboarding to onboarding route', async () => {
    authState.isAuthed = true
    authState.user = { email_verified_at: '2026-02-17T00:00:00Z' }
    preferencesState.loaded = true
    preferencesState.isOnboardingCompleted = false

    const router = makeRouter()
    await router.push('/settings')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('onboarding')
    expect(router.currentRoute.value.query.redirect).toBe('/settings')
  })

  it('does not reopen onboarding when completed on later navigation', async () => {
    authState.isAuthed = true
    authState.user = { email_verified_at: '2026-02-17T00:00:00Z' }
    preferencesState.loaded = true
    preferencesState.isOnboardingCompleted = true

    const router = makeRouter()
    await router.push('/settings')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('settings')
  })

  it('skips onboarding and preferences fetch for admin users', async () => {
    authState.isAuthed = true
    authState.isAdmin = true
    authState.user = { email_verified_at: '2026-02-17T00:00:00Z' }
    preferencesState.loaded = false
    preferencesState.isOnboardingCompleted = false

    const router = makeRouter()
    await router.push('/settings')
    await router.isReady()

    expect(preferencesState.fetchPreferences).not.toHaveBeenCalled()
    expect(router.currentRoute.value.name).toBe('settings')
  })

  it('redirects admin users away from onboarding route', async () => {
    authState.isAuthed = true
    authState.isAdmin = true
    authState.user = { email_verified_at: '2026-02-17T00:00:00Z' }

    const router = makeRouter()
    await router.push('/onboarding')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('home')
  })

  it('normalizes duplicated slashes in path', async () => {
    const router = makeRouter()
    await router.push('//events')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('events')
    expect(router.currentRoute.value.path).toBe('/events')
  })
})
