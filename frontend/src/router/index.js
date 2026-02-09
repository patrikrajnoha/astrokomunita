import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },

    {
      path: '/events',
      children: [
        {
          path: '',
          name: 'events',
          component: () => import('../views/EventsView.vue'),
        },
        {
          path: 'swipe',
          name: 'events-swipe',
          component: () => import('../views/SwipeEventsView.vue'),
        },
        {
          path: ':id',
          name: 'event-detail',
          component: () => import('../views/EventDetailView.vue'),
        },
      ],
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
      path: '/search',
      name: 'search',
      component: () => import('../views/SearchView.vue'),
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

    {
      path: '/admin',
      component: () => import('../layouts/AdminLayout.vue'),
      meta: { auth: true, admin: true },
      children: [
        {
          path: 'dashboard',
          name: 'admin.dashboard',
          component: () => import('../views/admin/DashboardView.vue'),
        },
        {
          path: 'event-candidates',
          name: 'admin.event-candidates',
          component: () => import('../views/admin/CandidatesListView.vue'),
        },
        {
          path: 'candidates/:id',
          name: 'admin.candidate.detail',
          component: () => import('../views/admin/CandidateDetailView.vue'),
        },
        {
          path: 'blog',
          name: 'admin.blog',
          component: () => import('../views/admin/BlogPostsView.vue'),
        },
        {
          path: 'events',
          name: 'admin.events',
          component: () => import('../views/admin/EventsUnifiedView.vue'),
        },
        {
          path: 'events/create',
          name: 'admin.events.create',
          component: () => import('../views/admin/EventFormView.vue'),
        },
        {
          path: 'events/:id/edit',
          name: 'admin.events.edit',
          component: () => import('../views/admin/EventFormView.vue'),
        },
        {
          path: 'reports',
          name: 'admin.reports',
          component: () => import('../views/admin/ReportsView.vue'),
        },
        {
          path: 'users',
          name: 'admin.users',
          component: () => import('../views/admin/UsersView.vue'),
        },
        {
          path: 'astrobot',
          name: 'admin.astrobot',
          component: () => import('../views/admin/AstroBotView.vue'),
        },
        {
          path: 'sidebar',
          name: 'admin.sidebar',
          component: () => import('../views/admin/SidebarConfigView.vue'),
        },
      ],
    },

    {
      path: '/posts/:id',
      name: 'post-detail',
      component: () => import('@/views/PostDetailView.vue'),
    },
    {
      path: '/tags/:tag',
      name: 'tag-feed',
      component: () => import('../views/TagFeedView.vue'),
    },
    {
      path: '/hashtags/:name',
      name: 'hashtag-feed',
      component: () => import('../views/HashtagFeedView.vue'),
    },
    {
      path: '/u/:username',
      name: 'user-profile',
      component: () => import('../views/PublicProfileView.vue'),
    },

    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  const redirectTarget = to.fullPath

  if (to.meta?.auth && !auth.isAuthed) {
    return {
      name: 'login',
      query: { redirect: redirectTarget },
    }
  }

  if (to.meta?.admin && !auth.isAdmin) {
    return { name: 'home' }
  }

  if (to.meta?.guest && auth.isAuthed) {
    const redirect = typeof to.query?.redirect === 'string' ? to.query.redirect : null
    return redirect || { name: 'home' }
  }

  return true
})

export default router
