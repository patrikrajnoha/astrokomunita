import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // VerejnĂ© cesty
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/events',
      name: 'events',
      component: () => import('../views/EventsView.vue'),
    },
    {
      path: '/events/swipe',
      name: 'events-swipe',
      component: () => import('../views/SwipeEventsView.vue'),
    },
    {
      path: '/events/:id',
      name: 'event-detail',
      component: () => import('../views/EventDetailView.vue'),
    },
    {
      path: '/calendar',
      name: 'calendar',
      component: () => import('../views/CalendarView.vue'),
    },
    {
      path: '/observations',
      name: 'observations',
      component: () => import('../views/ObservationsView.vue'),
    },
    {
      path: '/learn',
      name: 'learn',
      component: () => import('../views/LearnView.vue'),
    },
    {
      path: '/learn/:slug',
      name: 'learn-detail',
      component: () => import('../views/LearnDetailView.vue'),
    },
    {
      path: '/settings',
      name: 'settings',
      meta: { auth: true },
      component: () => import('../views/SettingsView.vue'),
    },
    {
      path: '/creator-studio',
      name: 'creator-studio',
      meta: { auth: true },
      component: () => import('../views/CreatorStudioView.vue'),
    },

    // Cesty len pre neprihlĂˇsenĂ˝ch (Guest)
    {
      path: '/login',
      name: 'login',
      meta: { guest: true },
      component: () => import('../views/LoginView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      meta: { guest: true },
      component: () => import('../views/RegisterView.vue'),
    },

    // ChrĂˇnenĂ© cesty (vyĹľadujĂş prihlĂˇsenie)
    {
      path: '/notifications',
      name: 'notifications',
      meta: { auth: true },
      component: () => import('../views/NotificationsView.vue'),
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('../views/ProfileView.vue'),
    },
    {
      path: '/profile/edit',
      name: 'profile.edit',
      meta: { auth: true },
      component: () => import('../views/ProfileEdit.vue'),
    },

    // âś… ADMIN (MVP)
    {
      path: '/admin/candidates',
      name: 'admin.candidates',
      meta: { auth: true, admin: true },
      component: () => import('../views/CandidatesListView.vue'),
    },
    {
      path: '/admin/candidates/:id',
      name: 'admin.candidate.detail',
      meta: { auth: true, admin: true },
      component: () => import('../views/CandidateDetailView.vue'),
    },
    {
      path: '/admin/blog-posts',
      name: 'admin.blog-posts',
      meta: { auth: true, admin: true },
      component: () => import('../views/AdminBlogPostsView.vue'),
    },

    {
      path: '/posts/:id',
      name: 'post-detail',
      component: () => import('@/views/PostDetailView.vue'),
    },
    {
      path: '/u/:username',
      name: 'user-profile',
      component: () => import('../views/PublicProfileView.vue'),
    },

    // 404 - Not Found (musĂ­ byĹĄ na konci)
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
    },
  ],
})

/**
 * Navigation Guard
 */
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // 1) Ak user eĹˇte nie je inicializovanĂ˝ (napr. po refreshi), skĂşsime ho naÄŤĂ­taĹĄ
  if (!auth.initialized) {
    await auth.fetchUser()
  }

  const redirectTarget = to.fullPath

  // 2) Ak cesta vyĹľaduje auth a user nie je prihlĂˇsenĂ˝
  if (to.meta?.auth && !auth.isAuthed) {
    return {
      name: 'login',
      query: { redirect: redirectTarget },
    }
  }

  // 3) Admin-only: ak cesta vyĹľaduje admin a user nie je admin
  if (to.meta?.admin) {
    // đź”§ JEDEN RIADOK NA PRISPĂ”SOBENIE podÄľa toho, ako mĂˇĹˇ usera v store:
    const isAdmin = !!auth.user?.is_admin

    if (!isAdmin) {
      return { name: 'home' }
    }
  }

  // 4) Ak je cesta pre hostĂ­ (login/register) a user je uĹľ prihlĂˇsenĂ˝
  if (to.meta?.guest && auth.isAuthed) {
    const redirect = typeof to.query?.redirect === 'string' ? to.query.redirect : null
    return redirect || { name: 'home' }
  }

  return true
})

export default router
