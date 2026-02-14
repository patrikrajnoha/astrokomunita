import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { applyAuthGuards } from './index'

const authState = {
  initialized: true,
  isAuthed: false,
  isAdmin: false,
  fetchUser: vi.fn(async () => {}),
}

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

function makeRouter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>home</div>' }, meta: { requiresAuth: false } },
      { path: '/events', name: 'events', component: { template: '<div>events</div>' }, meta: { requiresAuth: false } },
      { path: '/settings', name: 'settings', component: { template: '<div>settings</div>' }, meta: { requiresAuth: true } },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' }, meta: { guest: true } },
      { path: '/admin', name: 'admin', component: { template: '<div>admin</div>' }, meta: { requiresAuth: true, admin: true } },
    ],
  })

  applyAuthGuards(router)
  return router
}

describe('router auth guard', () => {
  beforeEach(() => {
    authState.initialized = true
    authState.isAuthed = false
    authState.isAdmin = false
    authState.fetchUser.mockClear()
  })

  it('allows guest access to public pages without redirection', async () => {
    const router = makeRouter()
    await router.push('/events')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('events')
  })

  it('redirects guest from protected routes to login', async () => {
    const router = makeRouter()
    await router.push('/settings')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/settings')
  })

  it('keeps authenticated non-admin users out of admin routes', async () => {
    authState.isAuthed = true
    const router = makeRouter()
    await router.push('/admin')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('home')
  })
})
