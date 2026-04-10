import axios from 'axios'
import { refreshCsrfCookie } from '@/services/api'

let echoInstance = null
let echoCtorPromise = null
let pusherCtorPromise = null

const configuredApiBaseUrl = import.meta.env.DEV
  ? ''
  : (import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_API_URL || window.location.origin)
const apiOrigin = String(configuredApiBaseUrl || '').replace(/\/api\/?$/i, '').replace(/\/+$/, '')

function toNumber(value, fallback) {
  const next = Number(value)
  return Number.isFinite(next) && next > 0 ? next : fallback
}

function getCookie(name) {
  if (typeof document === 'undefined') return ''
  const row = document.cookie.split('; ').find((entry) => entry.startsWith(`${name}=`))
  return row ? decodeURIComponent(row.split('=').slice(1).join('=')) : ''
}

function getCsrfToken() {
  if (typeof document === 'undefined') return ''
  const fromMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
  if (fromMeta) return fromMeta
  return getCookie('XSRF-TOKEN')
}

async function ensureBroadcastCsrfCookie() {
  const existingCsrf = getCsrfToken()
  const existingXsrf = getCookie('XSRF-TOKEN')

  if (existingCsrf || existingXsrf) {
    return {
      csrf: existingCsrf,
      xsrf: existingXsrf,
    }
  }

  try {
    await refreshCsrfCookie()
  } catch (error) {
    if (import.meta.env.DEV) {
      console.warn('[realtime] CSRF bootstrap failed:', error)
    }
  }

  return {
    csrf: getCsrfToken(),
    xsrf: getCookie('XSRF-TOKEN'),
  }
}

function shouldRetryBroadcastAuth(error) {
  const status = Number(error?.response?.status || 0)
  return status === 401 || status === 403 || status === 419
}

function resolveReverbOptions() {
  const scheme = String(import.meta.env.VITE_REVERB_SCHEME || 'http').toLowerCase()
  const hostFromEnv = String(import.meta.env.VITE_REVERB_HOST || '').trim()
  const hostFromWindow =
    typeof window !== 'undefined' ? String(window.location.hostname || '').trim() : ''

  return {
    key: String(import.meta.env.VITE_REVERB_APP_KEY || ''),
    host: hostFromEnv || hostFromWindow || '127.0.0.1',
    port: toNumber(import.meta.env.VITE_REVERB_PORT, scheme === 'https' ? 443 : 8080),
    forceTLS: scheme === 'https',
  }
}

function buildAuthorizer(authEndpoint) {
  return (channel) => ({
    async authorize(socketId, callback) {
      let attempt = 0

      while (attempt < 2) {
        const { csrf, xsrf } = await ensureBroadcastCsrfCookie()
        const headers = {
          Accept: 'application/json',
        }

        if (csrf) headers['X-CSRF-TOKEN'] = csrf
        if (xsrf) headers['X-XSRF-TOKEN'] = xsrf

        try {
          // Realtime handshake intentionally bypasses API interceptors.
          const response = await axios.post(
            authEndpoint,
            {
              socket_id: socketId,
              channel_name: channel.name,
            },
            {
              withCredentials: true,
              headers,
            },
          )

          callback(null, response.data)
          return
        } catch (error) {
          if (attempt === 0 && shouldRetryBroadcastAuth(error)) {
            attempt += 1
            try {
              await refreshCsrfCookie()
              continue
            } catch {
              // Fall through to the original auth error below.
            }
          }

          callback(error)
          return
        }
      }
    },
  })
}

async function loadEchoCtor() {
  if (!echoCtorPromise) {
    echoCtorPromise = import('laravel-echo').then((mod) => mod.default || mod)
  }
  return echoCtorPromise
}

async function loadPusherCtor() {
  if (!pusherCtorPromise) {
    pusherCtorPromise = import('pusher-js').then((mod) => mod.default || mod)
  }
  return pusherCtorPromise
}

async function loadRealtimeConstructors() {
  const [EchoCtor, PusherCtor] = await Promise.all([loadEchoCtor(), loadPusherCtor()])
  return { EchoCtor, PusherCtor }
}

export async function initEcho() {
  if (echoInstance) return echoInstance

  const reverb = resolveReverbOptions()
  if (!reverb.key) {
    if (import.meta.env.DEV) {
      console.info('[realtime] VITE_REVERB_APP_KEY is missing, realtime is disabled.')
    }
    return null
  }

  const authEndpoint = apiOrigin ? `${apiOrigin}/broadcasting/auth` : '/broadcasting/auth'

  try {
    const { EchoCtor, PusherCtor } = await loadRealtimeConstructors()
    if (!EchoCtor || !PusherCtor) {
      return null
    }

    echoInstance = new EchoCtor({
      broadcaster: 'reverb',
      key: reverb.key,
      wsHost: reverb.host,
      wsPort: reverb.port,
      wssPort: reverb.port,
      forceTLS: reverb.forceTLS,
      enabledTransports: ['ws', 'wss'],
      authEndpoint,
      authorizer: buildAuthorizer(authEndpoint),
      Pusher: PusherCtor,
    })

    if (typeof window !== 'undefined') {
      window.Echo = echoInstance
    }
  } catch (error) {
    echoInstance = null
    if (import.meta.env.DEV) {
      console.warn('[realtime] Echo init failed:', error)
    }
  }

  return echoInstance
}

export function getEcho() {
  return echoInstance
}

export function disconnectEcho() {
  if (!echoInstance) return
  echoInstance.disconnect()
  if (typeof window !== 'undefined' && window.Echo === echoInstance) {
    delete window.Echo
  }
  echoInstance = null
}
