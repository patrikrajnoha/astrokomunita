import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { appInitState, setInitError, setInitializing, setMounted } from '@/bootstrap/appInitState'
import { useAuthStore } from '@/stores/auth'

function formatError(errorLike) {
  if (!errorLike) return { message: 'Unknown error', stack: '' }

  if (errorLike instanceof Error) {
    return {
      message: errorLike.message || 'Unknown error',
      stack: errorLike.stack || '',
    }
  }

  if (typeof errorLike === 'object') {
    const anyError = errorLike
    return {
      message: String(anyError.message || anyError.reason || 'Unknown error'),
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
  overlay.style.background = '#120f14'
  overlay.style.color = '#f8f7fb'
  overlay.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace'
  overlay.style.overflow = 'auto'

  const title = document.createElement('h1')
  title.textContent = 'App failed to start'
  title.style.margin = '0 0 12px'
  title.style.fontSize = '18px'

  const subtitle = document.createElement('div')
  subtitle.textContent = `Source: ${source}`
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
    ensureFatalOverlay(error || String(message), 'window.onerror')
    return false
  }

  window.onunhandledrejection = function onUnhandledRejection(event) {
    const reason = event?.reason || new Error('Unhandled promise rejection')
    console.error('[APP ERROR] unhandledrejection', reason)
    ensureFatalOverlay(reason, 'unhandledrejection')
  }
}

async function bootstrap() {
  console.info('[APP INIT] start')
  console.info('[APP INIT] mode=', import.meta.env.MODE, 'base=', import.meta.env.BASE_URL)

  attachGlobalDiagnostics()

  const app = createApp(App)
  const pinia = createPinia()

  app.config.errorHandler = (error, instance, info) => {
    console.error('[APP ERROR] vue errorHandler', info, instance, error)
    ensureFatalOverlay(error, 'vue.errorHandler')
  }

  app.use(pinia)
  app.use(router)

  try {
    app.mount('#app')
    setMounted(true)
    console.info('[APP INIT] mounted')
  } catch (error) {
    setInitError(formatError(error))
    setInitializing(false)
    ensureFatalOverlay(error, 'mount')
    throw error
  }

  const auth = useAuthStore(pinia)

  try {
    await auth.fetchUser()
  } catch (error) {
    setInitError(formatError(error))
    ensureFatalOverlay(error, 'auth.fetchUser')
  } finally {
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
