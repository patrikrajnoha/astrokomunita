const rawDsn = String(import.meta.env.VITE_SENTRY_DSN || '').trim()
const sentryEnv = String(import.meta.env.VITE_SENTRY_ENV || import.meta.env.MODE || 'production')
const sentryRelease = String(import.meta.env.VITE_SENTRY_RELEASE || '').trim()

const enabled = import.meta.env.PROD && rawDsn !== ''

let sentryAuthHeader = ''
let sentryStoreUrl = ''

if (enabled) {
  const parsed = parseDsn(rawDsn)
  if (parsed) {
    sentryAuthHeader = parsed.authHeader
    sentryStoreUrl = parsed.storeUrl
  }
}

const sentEvents = new Set()

function parseDsn(dsn) {
  try {
    const url = new URL(dsn)
    const key = decodeURIComponent(url.username || '')
    const secret = decodeURIComponent(url.password || '')
    const pathParts = url.pathname.split('/').filter(Boolean)
    const projectId = pathParts[pathParts.length - 1] || ''
    if (!key || !projectId) return null

    const authParts = [
      'Sentry sentry_version=7',
      'sentry_client=astrokomunita-frontend/1.0',
      `sentry_key=${key}`,
    ]
    if (secret) {
      authParts.push(`sentry_secret=${secret}`)
    }

    return {
      storeUrl: `${url.protocol}//${url.host}/api/${projectId}/store/`,
      authHeader: authParts.join(', '),
    }
  } catch {
    return null
  }
}

function randomEventId() {
  const bytes = crypto.getRandomValues(new Uint8Array(16))
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')
}

function buildErrorMessage(errorLike) {
  if (!errorLike) return 'Unknown error'
  if (errorLike instanceof Error) return errorLike.message || 'Unknown error'
  if (typeof errorLike === 'object') return String(errorLike.message || errorLike.reason || 'Unknown error')
  return String(errorLike)
}

function buildStack(errorLike) {
  if (!errorLike) return ''
  if (errorLike instanceof Error) return String(errorLike.stack || '')
  if (typeof errorLike === 'object') return String(errorLike.stack || errorLike.reason?.stack || '')
  return ''
}

export function captureClientError(errorLike, source = 'runtime') {
  if (!enabled || !sentryAuthHeader || !sentryStoreUrl) return

  const message = buildErrorMessage(errorLike)
  const stack = buildStack(errorLike)
  const dedupeKey = `${source}:${message}:${stack.slice(0, 256)}`
  if (sentEvents.has(dedupeKey)) return

  sentEvents.add(dedupeKey)
  if (sentEvents.size > 100) {
    sentEvents.clear()
  }

  const payload = {
    event_id: randomEventId(),
    timestamp: new Date().toISOString(),
    platform: 'javascript',
    level: 'error',
    environment: sentryEnv,
    release: sentryRelease,
    message: `[${source}] ${message}`,
    exception: {
      values: [
        {
          type: errorLike?.name || 'Error',
          value: message,
          stacktrace: {
            frames: stack
              .split('\n')
              .filter(Boolean)
              .map((line) => ({ filename: line.trim(), in_app: true })),
          },
        },
      ],
    },
    request: typeof window !== 'undefined'
      ? {
          url: window.location.href,
          headers: {
            'user-agent': navigator.userAgent,
          },
        }
      : undefined,
    tags: {
      source,
    },
  }

  fetch(sentryStoreUrl, {
    method: 'POST',
    mode: 'cors',
    keepalive: true,
    headers: {
      'Content-Type': 'application/json',
      'X-Sentry-Auth': sentryAuthHeader,
    },
    body: JSON.stringify(payload),
  }).catch(() => {
    // Avoid recursive logging loops.
  })
}
