import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const echoState = vi.hoisted(() => ({
  ctor: vi.fn(function EchoMock(options) {
    this.options = options
    this.disconnect = vi.fn()
  }),
}))

vi.mock('laravel-echo', () => ({
  default: echoState.ctor,
}))

vi.mock('pusher-js', () => ({
  default: vi.fn(),
}))

vi.mock('axios', () => ({
  default: {
    post: vi.fn(),
  },
}))

async function loadEchoModule() {
  vi.resetModules()
  return import('@/realtime/echo')
}

describe('realtime echo config', () => {
  beforeEach(() => {
    echoState.ctor.mockClear()
    vi.stubEnv('VITE_REVERB_APP_KEY', 'local-app-key')
    vi.stubEnv('VITE_API_BASE_URL', 'http://127.0.0.1:8001')
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
})
