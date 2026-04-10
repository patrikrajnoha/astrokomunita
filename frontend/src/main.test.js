import { beforeEach, describe, expect, it, vi } from 'vitest'

const createPiniaMock = vi.hoisted(() => vi.fn(() => ({ __pinia: true })))
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
  bootstrapAuth: vi.fn(async () => null),
}))
const useAuthStoreMock = vi.hoisted(() => vi.fn(() => authStoreMock))
const captureClientErrorMock = vi.hoisted(() => vi.fn())
const initEchoMock = vi.hoisted(() => vi.fn(async () => {}))
const getEchoMock = vi.hoisted(() => vi.fn(() => null))

vi.mock('vue', () => ({
  createApp: (...args) => createAppMock(...args),
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
    globalThis.__astrokomunitaBootstrapPromise__ = null

    createPiniaMock.mockClear()
    createAppMock.mockClear()
    appUseMock.mockClear()
    appMountMock.mockClear()
    setInitErrorMock.mockClear()
    setInitializingMock.mockClear()
    setMountedMock.mockClear()
    installPreloadRecoveryMock.mockClear()
    clearPreloadRecoveryStateMock.mockClear()
    useAuthStoreMock.mockClear()
    authStoreMock.bootstrapAuth.mockReset()
    authStoreMock.bootstrapAuth.mockResolvedValue(null)
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

    authStoreMock.bootstrapAuth.mockReturnValueOnce(bootstrapPromise)

    await import('./main.js')
    await flushPromises()

    expect(appMountMock).toHaveBeenCalledWith('#app')
    expect(authStoreMock.bootstrapAuth).toHaveBeenCalledTimes(1)
    expect(globalThis.__astrokomunitaBootstrapPromise__).toBe(bootstrapPromise)
    expect(setInitializingMock).not.toHaveBeenCalledWith(false)

    resolveBootstrap()
    await flushPromises()

    expect(globalThis.__astrokomunitaBootstrapPromise__).toBeNull()
    expect(setInitializingMock).toHaveBeenCalledWith(false)
    expect(events).toEqual(['bootstrapResolved', 'setInitializingFalse'])
  })

  it('finishes initialization and records init error when auth bootstrap fails', async () => {
    const bootstrapError = new Error('bootstrap failed')
    authStoreMock.bootstrapAuth.mockRejectedValueOnce(bootstrapError)

    await import('./main.js')
    await flushPromises()

    expect(globalThis.__astrokomunitaBootstrapPromise__).toBeNull()
    expect(setInitErrorMock).toHaveBeenCalledWith(expect.objectContaining({
      message: 'bootstrap failed',
    }))
    expect(setInitializingMock).toHaveBeenCalledWith(false)
    expect(captureClientErrorMock).toHaveBeenCalledWith(bootstrapError, 'auth.bootstrapAuth')
  })
})
