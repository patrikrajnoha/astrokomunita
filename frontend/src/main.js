import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

// 🔐 Auth store (Sanctum SPA)
import { useAuthStore } from '@/stores/auth'

const app = createApp(App)

// Pinia
const pinia = createPinia()
app.use(pinia)

// Router
app.use(router)

// 🔄 Init auth (po refreshi zostane user prihlásený)
const auth = useAuthStore(pinia)
auth.fetchUser().finally(() => {
  app.mount('#app')
})

// PWA: safe SW registration (only in production, does not break app if registration fails)
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const swUrl = `${import.meta.env.BASE_URL}sw.js`
      await navigator.serviceWorker.register(swUrl)
    } catch (error) {
      console.warn('Service worker registration failed:', error)
    }
  })
}
