import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const echoState = vi.hoisted(() => ({
  ctor: vi.fn(function EchoMock(options) {
    this.options = options
    this.disconnect = vi.fn()
  }),
}))
const axiosState = vi.hoisted(() => ({
  post: vi.fn(),
}))
const refreshCsrfCookieMock = vi.hoisted(() => vi.fn(async () => {}))

vi.mock('laravel-echo', () => ({
  default: echoState.ctor,
}))

vi.mock('pusher-js', () => ({
  default: vi.fn(),
}))

vi.mock('axios', () => ({
  default: axiosState,
}))

vi.mock('@/services/api', () => ({
  refreshCsrfCookie: (...args) => refreshCsrfCookieMock(...args),
}))

async function loadEchoModule() {
  vi.resetModules()
  return import('@/realtime/echo')
}

describe('realtime echo config', () => {
  beforeEach(() => {
    echoState.ctor.mockClear()
    axiosState.post.mockReset()
    refreshCsrfCookieMock.mockReset()
    vi.stubEnv('VITE_REVERB_APP_KEY', 'local-app-key')
    vi.stubEnv('VITE_API_BASE_URL', 'http://127.0.0.1:8001')
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
  })

  afterEach(() => {
    vi.unstubAllEnvs()
    vi.unstubAllGlobals()
  })

  it('initializes Echo with explicit env host, port and scheme', async () => {
    vi.stubEnv('VITE_REVERB_HOST', 'reverb.local')
    vi.stubEnv('VITE_REVERB_PORT', '9090')
    vi.stubEnv('VITE_REVERB_SCHEME', 'https')

    const { disconnectEcho, getEcho, initEcho } = await loadEchoModule()
    const instance = await initEcho()

    expect(echoState.ctor).toHaveBeenCalledTimes(1)
    const options = echoState.ctor.mock.calls[0][0]
    expect(options.wsHost).toBe('reverb.local')
    expect(options.wsPort).toBe(9090)
    expect(options.wssPort).toBe(9090)
    expect(options.forceTLS).toBe(true)
    expect(getEcho()).toBe(instance)
    expect(window.Echo).toBe(instance)

    disconnectEcho()
    expect(instance.disconnect).toHaveBeenCalledTimes(1)
    expect(getEcho()).toBe(null)
    expect(window.Echo).toBeUndefined()
  })

  it('falls back to window hostname when VITE_REVERB_HOST is missing', async () => {
    vi.stubEnv('VITE_REVERB_HOST', '')
    vi.stubEnv('VITE_REVERB_PORT', '8080')
    vi.stubEnv('VITE_REVERB_SCHEME', 'http')

    const { disconnectEcho, initEcho } = await loadEchoModule()
    await initEcho()

    const options = echoState.ctor.mock.calls[0][0]
    expect(options.wsHost).toBe(window.location.hostname)

    disconnectEcho()
  })

  it('falls back to 127.0.0.1 when window is unavailable', async () => {
    vi.stubEnv('VITE_REVERB_HOST', '')
    vi.stubEnv('VITE_REVERB_PORT', '8080')
    vi.stubEnv('VITE_REVERB_SCHEME', 'http')
    vi.stubGlobal('window', undefined)

    const { disconnectEcho, initEcho } = await loadEchoModule()
    await initEcho()

    const options = echoState.ctor.mock.calls[0][0]
    expect(options.wsHost).toBe('127.0.0.1')

    disconnectEcho()
  })

  it('bootstraps a csrf cookie before private broadcast auth when missing', async () => {
    refreshCsrfCookieMock.mockImplementationOnce(async () => {
      document.cookie = 'XSRF-TOKEN=csrf-cookie-value; path=/'
    })
    axiosState.post.mockResolvedValueOnce({ data: { auth: 'ok' } })

    const { disconnectEcho, initEcho } = await loadEchoModule()
    await initEcho()

    const options = echoState.ctor.mock.calls[0][0]
    const callback = vi.fn()

    await options.authorizer({ name: 'private-users.7' }).authorize('socket-1', callback)

    expect(refreshCsrfCookieMock).toHaveBeenCalledTimes(1)
    expect(axiosState.post).toHaveBeenCalledWith(
      '/broadcasting/auth',
      {
        socket_id: 'socket-1',
        channel_name: 'private-users.7',
      },
      {
        withCredentials: true,
        headers: expect.objectContaining({
          Accept: 'application/json',
          'X-CSRF-TOKEN': 'csrf-cookie-value',
          'X-XSRF-TOKEN': 'csrf-cookie-value',
        }),
      },
    )
    expect(callback).toHaveBeenCalledWith(null, { auth: 'ok' })

    disconnectEcho()
  })

  it('retries private broadcast auth once after a 403', async () => {
    document.cookie = 'XSRF-TOKEN=initial-token; path=/'
    refreshCsrfCookieMock.mockImplementationOnce(async () => {
      document.cookie = 'XSRF-TOKEN=retry-token; path=/'
    })
    axiosState.post
      .mockRejectedValueOnce({ response: { status: 403 } })
      .mockResolvedValueOnce({ data: { auth: 'ok' } })

    const { disconnectEcho, initEcho } = await loadEchoModule()
    await initEcho()

    const options = echoState.ctor.mock.calls[0][0]
    const callback = vi.fn()

    await options.authorizer({ name: 'private-users.7' }).authorize('socket-2', callback)

    expect(refreshCsrfCookieMock).toHaveBeenCalledTimes(1)
    expect(axiosState.post).toHaveBeenCalledTimes(2)
    expect(axiosState.post.mock.calls[1][2].headers['X-XSRF-TOKEN']).toBe('retry-token')
    expect(callback).toHaveBeenCalledWith(null, { auth: 'ok' })

    disconnectEcho()
  })
})
