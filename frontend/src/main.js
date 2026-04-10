import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { appInitState, setInitError, setInitializing, setMounted } from '@/bootstrap/appInitState'
import { clearPreloadRecoveryState, installPreloadRecovery } from '@/bootstrap/preloadRecovery'
import { useAuthStore } from '@/stores/auth'
import { captureClientError } from '@/services/errorTracker'

installPreloadRecovery()

function formatError(errorLike) {
  if (!errorLike) return { message: 'Neznáma chyba', stack: '' }

  if (errorLike instanceof Error) {
    return {
      message: errorLike.message || 'Neznáma chyba',
      stack: errorLike.stack || '',
    }
  }

  if (typeof errorLike === 'object') {
    const anyError = errorLike
    return {
      message: String(anyError.message || anyError.reason || 'Neznáma chyba'),
      stack: String(anyError.stack || anyError.reason?.stack || ''),
    }
  }

  return {
    message: String(errorLike),
    stack: '',
  }
}

function ensureFatalOverlay(errorLike, source = 'runtime') {
  const { message, stack } = formatError(errorLike)

  console.error(`[APP FATAL][${source}]`, message, errorLike)
  captureClientError(errorLike, source)

  if (!import.meta.env.DEV || typeof document === 'undefined') return

  const existing = document.getElementById('app-init-fatal-overlay')
  if (existing) {
    const pre = existing.querySelector('pre')
    if (pre) {
      pre.textContent = `${message}\n\n${stack}`.trim()
    }
    return
  }

  const overlay = document.createElement('div')
  overlay.id = 'app-init-fatal-overlay'
  overlay.style.position = 'fixed'
  overlay.style.inset = '0'
  overlay.style.zIndex = '2147483647'
  overlay.style.padding = '20px'
  overlay.style.background = 'var(--bg-app)'
  overlay.style.color = 'var(--text-primary)'
  overlay.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace'
  overlay.style.overflow = 'auto'

  const title = document.createElement('h1')
  title.textContent = 'Aplikácia sa nepodarila spustiť'
  title.style.margin = '0 0 12px'
  title.style.fontSize = '18px'

  const subtitle = document.createElement('div')
  subtitle.textContent = `Zdroj: ${source}`
  subtitle.style.marginBottom = '12px'
  subtitle.style.opacity = '0.85'

  const pre = document.createElement('pre')
  pre.style.whiteSpace = 'pre-wrap'
  pre.style.margin = '0'
  pre.style.lineHeight = '1.45'
  pre.textContent = `${message}\n\n${stack}`.trim()

  overlay.appendChild(title)
  overlay.appendChild(subtitle)
  overlay.appendChild(pre)
  document.body.appendChild(overlay)
}

function attachGlobalDiagnostics() {
  window.onerror = function onWindowError(message, source, lineno, colno, error) {
    const lineInfo = source ? `${source}:${lineno || 0}:${colno || 0}` : 'unknown-source'
    console.error('[APP ERROR]', message, lineInfo, error)
    captureClientError(error || String(message), 'window.onerror')
    ensureFatalOverlay(error || String(message), 'window.onerror')
    return false
  }

  window.onunhandledrejection = function onUnhandledRejection(event) {
    const reason = event?.reason || new Error('Nespracované odmietnutie sľubu')
    console.error('[APP ERROR] unhandledrejection', reason)
    captureClientError(reason, 'unhandledrejection')
    ensureFatalOverlay(reason, 'unhandledrejection')
  }
}

function enforceDevCanonicalHost() {
  if (!import.meta.env.DEV || import.meta.env.VITEST || typeof window === 'undefined') {
    return false
  }

  const currentUrl = new URL(window.location.href)
  if (currentUrl.hostname !== 'localhost') {
    return false
  }

  currentUrl.hostname = '127.0.0.1'
  window.location.replace(currentUrl.toString())
  return true
}

async function bootstrap() {
  if (enforceDevCanonicalHost()) {
    return
  }

  console.info('[APP INIT] start')
  console.info('[APP INIT] mode=', import.meta.env.MODE, 'base=', import.meta.env.BASE_URL)

  attachGlobalDiagnostics()

  const app = createApp(App)
  const pinia = createPinia()

  app.config.errorHandler = (error, instance, info) => {
    console.error('[APP ERROR] vue errorHandler', info, instance, error)
    captureClientError(error, `vue.errorHandler:${info}`)
    // Only show the fatal overlay during bootstrap (before the app is mounted).
    // After mount, errors in watchers / navigation hooks are non-fatal and
    // should not block the entire UI.
    if (!appInitState.mounted) {
      ensureFatalOverlay(error, 'vue.errorHandler')
    }
  }

  app.use(pinia)
  app.use(router)

  try {
    app.mount('#app')
    setMounted(true)
    console.info('[APP INIT] mounted');

  // 1. Dynamic import and initialization
  const { initEcho, getEcho } = await import('@/realtime/echo');
  await initEcho();

  // 2. Setup Echo listener
  // Using a small delay if the library needs internal "handshake" time, 
  // though usually initEcho should resolve when ready.
  setTimeout(() => {
    const echo = getEcho();
    if (!echo) return;

    echo.channel('posts')
      .listen('.PostUpdated', (event) => {
        window.dispatchEvent(new CustomEvent('post:updated', { detail: event?.post ?? event }))
      })
  }, 1000);

  clearPreloadRecoveryState()

} catch (error) {
  // Handle initialization or import failures
  setInitError(formatError(error));
  setInitializing(false);
  ensureFatalOverlay(error, 'mount');
  throw error;
}

  const auth = useAuthStore(pinia)

  try {
    const bsPromise = auth.bootstrapAuth()
    globalThis['__astrokomunitaBootstrapPromise__'] = bsPromise
    await bsPromise
  } catch (error) {
    setInitError(formatError(error))
    ensureFatalOverlay(error, 'auth.bootstrapAuth')
  } finally {
    globalThis['__astrokomunitaBootstrapPromise__'] = null
    setInitializing(false)
  }

  if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
      try {
        const swUrl = `${import.meta.env.BASE_URL}sw.js`
        await navigator.serviceWorker.register(swUrl)
      } catch (error) {
        console.warn('[APP INIT] service worker registration failed', error)
      }
    })
  }

  if (import.meta.env.DEV && 'serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations()
      .then((registrations) => Promise.all(registrations.map((registration) => registration.unregister())))
      .catch((error) => {
        console.warn('[APP INIT] service worker cleanup failed', error)
      })
  }
}

bootstrap().catch((error) => {
  const formatted = formatError(error)
  setInitError(formatted)
  setInitializing(false)
  ensureFatalOverlay(error, 'bootstrap.catch')
  appInitState.initializing = false
})
