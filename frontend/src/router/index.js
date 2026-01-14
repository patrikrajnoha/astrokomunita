import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // Verejné cesty
    { 
      path: '/', 
      name: 'home', 
      component: HomeView 
    },
    { 
      path: '/events', 
      name: 'events', 
      component: () => import('../views/EventsView.vue') 
    },
    { 
      path: '/events/swipe', 
      name: 'events-swipe', 
      component: () => import('../views/SwipeEventsView.vue') 
    },
    { 
      path: '/events/:id', 
      name: 'event-detail', 
      component: () => import('../views/EventDetailView.vue') 
    },
    { 
      path: '/calendar', 
      name: 'calendar', 
      component: () => import('../views/CalendarView.vue') 
    },
    { 
      path: '/observations', 
      name: 'observations', 
      component: () => import('../views/ObservationsView.vue') 
    },
    { 
      path: '/learn', 
      name: 'learn', 
      component: () => import('../views/LearnView.vue') 
    },

    // Cesty len pre neprihlásených (Guest)
    { 
      path: '/login', 
      name: 'login', 
      meta: { guest: true }, 
      component: () => import('../views/LoginView.vue') 
    },
    { 
      path: '/register', 
      name: 'register', 
      meta: { guest: true }, 
      component: () => import('../views/RegisterView.vue') 
    },

    // Chránené cesty (vyžadujú prihlásenie)
    { 
      path: '/favorites', 
      name: 'favorites', 
      meta: { auth: true }, 
      component: () => import('../views/FavoritesView.vue') 
    },
    { 
      path: '/notifications', 
      name: 'notifications', 
      meta: { auth: true }, 
      component: () => import('../views/NotificationsView.vue') 
    },
    { 
      path: '/profile', 
      name: 'profile', 
      meta: { auth: true }, 
      component: () => import('../views/ProfileView.vue') 
    },
    { 
      path: '/profile/edit', 
      name: 'profile.edit', 
      meta: { auth: true }, 
      component: () => import('../views/ProfileEdit.vue') 
    },

    // 404 - Not Found (musí byť na konci)
    { 
      path: '/:pathMatch(.*)*', 
      name: 'not-found', 
      component: () => import('../views/NotFoundView.vue') 
    },
  ],
})

/**
 * Navigation Guard
 */
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // 1. Ak user ešte nie je inicializovaný (napr. po refreshi), skúsime ho načítať
  if (!auth.initialized) {
    await auth.fetchUser()
  }

  const redirectTarget = to.fullPath

  // 2. Ak cesta vyžaduje auth a user nie je prihlásený
  if (to.meta?.auth && !auth.isAuthed) {
    return { 
      name: 'login', 
      query: { redirect: redirectTarget } 
    }
  }

  // 3. Ak je cesta pre hostí (login/register) a user je už prihlásený
  if (to.meta?.guest && auth.isAuthed) {
    // Ak existuje redirect v URL, pošli ho tam, inak na domov
    const redirect = typeof to.query?.redirect === 'string' ? to.query.redirect : null
    return redirect || { name: 'home' }
  }

  return true
})

export default router