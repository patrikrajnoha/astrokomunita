import { beforeEach, describe, expect, it, vi } from 'vitest'

const createPiniaMock = vi.hoisted(() => vi.fn(() => ({ __pinia: true })))
const watchMock = vi.hoisted(() => vi.fn(() => vi.fn()))
const appUseMock = vi.hoisted(() => vi.fn())
const appMountMock = vi.hoisted(() => vi.fn())
const createAppMock = vi.hoisted(() => vi.fn(() => {
  const app = {
    config: {},
    use(plugin) {
      appUseMock(plugin)
      return app
    },
    mount(target) {
      appMountMock(target)
      return {}
    },
  }

  return app
}))
const routerMock = vi.hoisted(() => ({ __router: true }))
const appInitStateMock = vi.hoisted(() => ({
  initializing: true,
  initError: null,
  mounted: false,
}))
const setInitErrorMock = vi.hoisted(() => vi.fn((error) => {
  appInitStateMock.initError = error
}))
const setInitializingMock = vi.hoisted(() => vi.fn((value) => {
  appInitStateMock.initializing = Boolean(value)
}))
const setMountedMock = vi.hoisted(() => vi.fn((value) => {
  appInitStateMock.mounted = Boolean(value)
}))
const installPreloadRecoveryMock = vi.hoisted(() => vi.fn())
const clearPreloadRecoveryStateMock = vi.hoisted(() => vi.fn())
const authStoreMock = vi.hoisted(() => ({
  bootstrapDone: true,
  bootstrapAuth: vi.fn(async () => null),
  isAuthed: false,
  isAdmin: false,
  user: null,
}))
const preferencesStoreMock = vi.hoisted(() => ({
  loaded: true,
  loading: false,
  isOnboardingCompleted: true,
  fetchPreferences: vi.fn(async () => null),
}))
const useAuthStoreMock = vi.hoisted(() => vi.fn(() => authStoreMock))
const captureClientErrorMock = vi.hoisted(() => vi.fn())
const initEchoMock = vi.hoisted(() => vi.fn(async () => {}))
const getEchoMock = vi.hoisted(() => vi.fn(() => null))

vi.mock('vue', () => ({
  createApp: (...args) => createAppMock(...args),
  watch: (...args) => watchMock(...args),
}))

vi.mock('pinia', () => ({
  createPinia: (...args) => createPiniaMock(...args),
}))

vi.mock('./App.vue', () => ({
  default: { name: 'TestApp' },
}))

vi.mock('./router', () => ({
  default: routerMock,
}))

vi.mock('@/bootstrap/appInitState', () => ({
  appInitState: appInitStateMock,
  setInitError: (...args) => setInitErrorMock(...args),
  setInitializing: (...args) => setInitializingMock(...args),
  setMounted: (...args) => setMountedMock(...args),
}))

vi.mock('@/bootstrap/preloadRecovery', () => ({
  installPreloadRecovery: (...args) => installPreloadRecoveryMock(...args),
  clearPreloadRecoveryState: (...args) => clearPreloadRecoveryStateMock(...args),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: (...args) => useAuthStoreMock(...args),
}))

vi.mock('@/stores/eventPreferences', () => ({
  useEventPreferencesStore: () => preferencesStoreMock,
}))

vi.mock('@/services/errorTracker', () => ({
  captureClientError: (...args) => captureClientErrorMock(...args),
}))

vi.mock('@/realtime/echo', () => ({
  initEcho: (...args) => initEchoMock(...args),
  getEcho: (...args) => getEchoMock(...args),
}))

async function flushPromises() {
  await Promise.resolve()
  await Promise.resolve()
  await Promise.resolve()
}

describe('main bootstrap', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.restoreAllMocks()
    vi.spyOn(console, 'info').mockImplementation(() => {})
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.spyOn(globalThis, 'setTimeout').mockImplementation((handler) => {
      if (typeof handler === 'function') {
        handler()
      }
      return 0
    })

    document.body.innerHTML = '<div id="app"></div>'

    appInitStateMock.initializing = true
    appInitStateMock.initError = null
    appInitStateMock.mounted = false

    createPiniaMock.mockClear()
    watchMock.mockClear()
    createAppMock.mockClear()
    appUseMock.mockClear()
    appMountMock.mockReset()
    setInitErrorMock.mockClear()
    setInitializingMock.mockClear()
    setMountedMock.mockClear()
    installPreloadRecoveryMock.mockClear()
    clearPreloadRecoveryStateMock.mockClear()
    useAuthStoreMock.mockClear()
    authStoreMock.bootstrapAuth.mockReset()
    authStoreMock.bootstrapAuth.mockResolvedValue(null)
    authStoreMock.bootstrapDone = true
    authStoreMock.isAuthed = false
    authStoreMock.isAdmin = false
    authStoreMock.user = null
    preferencesStoreMock.loaded = true
    preferencesStoreMock.loading = false
    preferencesStoreMock.isOnboardingCompleted = true
    preferencesStoreMock.fetchPreferences.mockClear()
    captureClientErrorMock.mockClear()
    initEchoMock.mockReset()
    initEchoMock.mockResolvedValue(undefined)
    getEchoMock.mockReset()
    getEchoMock.mockReturnValue(null)
  })

  it('keeps app initializing until auth bootstrap resolves', async () => {
    const events = []
    let resolveBootstrap
    const bootstrapPromise = new Promise((resolve) => {
      resolveBootstrap = () => {
        events.push('bootstrapResolved')
        resolve(null)
      }
    })

    setInitializingMock.mockImplementation((value) => {
      appInitStateMock.initializing = Boolean(value)
      if (value === false) {
        events.push('setInitializingFalse')
      }
    })

    appMountMock.mockImplementation((target) => {
      events.push('mounted')
      return target
    })
    authStoreMock.bootstrapAuth.mockReturnValueOnce(bootstrapPromise)

    await import('./main.js')
    await flushPromises()

    expect(appMountMock).toHaveBeenCalledWith('#app')
    expect(authStoreMock.bootstrapAuth).toHaveBeenCalledTimes(1)
    expect(events[0]).toBe('mounted')
    expect(setInitializingMock).not.toHaveBeenCalledWith(false)

    resolveBootstrap()
    await flushPromises()

    expect(setInitializingMock).toHaveBeenCalledWith(false)
    expect(events).toEqual(['mounted', 'bootstrapResolved', 'setInitializingFalse'])
  })

  it('finishes initialization and records init error when auth bootstrap fails', async () => {
    const bootstrapError = new Error('bootstrap failed')
    authStoreMock.bootstrapAuth.mockRejectedValueOnce(bootstrapError)

    await import('./main.js')
    await flushPromises()

    expect(setInitErrorMock).toHaveBeenCalledWith(expect.objectContaining({
      message: 'bootstrap failed',
    }))
    expect(setInitializingMock).toHaveBeenCalledWith(false)
    expect(captureClientErrorMock).toHaveBeenCalledWith(bootstrapError, 'auth.bootstrapAuth')
  })

  it('skips websocket bootstrap for authenticated users who have not finished onboarding', async () => {
    authStoreMock.isAuthed = true
    authStoreMock.user = {
      id: 7,
      email_verified_at: '2026-03-10T10:00:00Z',
    }
    preferencesStoreMock.loaded = false
    preferencesStoreMock.loading = false
    preferencesStoreMock.isOnboardingCompleted = false
    preferencesStoreMock.fetchPreferences.mockImplementation(async () => {
      preferencesStoreMock.loaded = true
    })

    await import('./main.js')
    await flushPromises()

    expect(preferencesStoreMock.fetchPreferences).toHaveBeenCalledTimes(1)
    expect(initEchoMock).not.toHaveBeenCalled()
  })
})
