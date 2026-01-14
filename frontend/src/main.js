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
