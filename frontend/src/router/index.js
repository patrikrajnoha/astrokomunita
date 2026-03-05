import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'

const wipEnabled = String(import.meta.env.VITE_FEATURE_WIP || 'false').toLowerCase() === 'true'

const appShellChildren = [
  {
    path: '',
    name: 'home',
    meta: { requiresAuth: false },
    component: HomeView,
  },
  {
    path: 'events',
    children: [
      {
        path: '',
        name: 'events',
        meta: { requiresAuth: false },
        component: () => import('../views/EventsView.vue'),
      },
      {
        path: 'swipe',
        name: 'events-swipe',
        meta: { requiresAuth: false },
        component: () => import('../views/SwipeEventsView.vue'),
      },
      {
        path: ':id',
        name: 'event-detail',
        meta: { requiresAuth: false },
        component: () => import('../views/EventDetailView.vue'),
      },
    ],
  },
  {
    path: 'contests',
    name: 'contests',
    redirect: { name: 'admin.contests' },
  },
  {
    path: 'calendar',
    name: 'calendar',
    redirect: (to) => ({
      name: 'events',
      query: {
        ...to.query,
        view: 'calendar',
      },
    }),
  },
  {
    path: 'observations',
    children: [
      {
        path: '',
        name: 'observations',
        meta: { auth: true, requiresAuth: true },
        component: () => import('../views/ObservationsView.vue'),
      },
      {
        path: 'new',
        name: 'observations.create',
        meta: { auth: true, requiresAuth: true },
        component: () => import('../views/ObservationCreateView.vue'),
      },
      {
        path: ':id',
        name: 'observations.detail',
        meta: { requiresAuth: false },
        component: () => import('../views/ObservationDetailView.vue'),
      },
    ],
  },
  {
    path: 'clanky',
    name: 'learn',
    meta: { requiresAuth: false },
    component: () => import('../views/LearnView.vue'),
  },
  {
    path: 'clanky/:slug',
    name: 'learn-detail',
    meta: { requiresAuth: false },
    component: () => import('../views/LearnDetailView.vue'),
  },
  {
    path: 'learn',
    redirect: { name: 'learn' },
  },
  {
    path: 'learn/:slug',
    redirect: (to) => ({
      name: 'learn-detail',
      params: { slug: to.params.slug },
      query: to.query,
      hash: to.hash,
    }),
  },
  {
    path: 'search',
    name: 'search',
    meta: { requiresAuth: false },
    component: () => import('../views/SearchView.vue'),
  },
  {
    path: 'settings',
    name: 'settings',
    meta: { auth: true, requiresAuth: true },
    component: () => import('../views/SettingsView.vue'),
  },
  ...(wipEnabled
    ? [{
        path: 'creator-studio',
        name: 'creator-studio',
        meta: { auth: true, requiresAuth: true },
        component: () => import('../views/CreatorStudioView.vue'),
      }]
    : []),
  {
    path: 'notifications',
    name: 'notifications',
    meta: { auth: true, requiresAuth: true },
    component: () => import('../views/NotificationsView.vue'),
  },
  {
    path: 'bookmarks',
    name: 'bookmarks',
    meta: { auth: true, requiresAuth: true },
    component: () => import('../views/BookmarksView.vue'),
  },
  {
    path: 'profile',
    name: 'profile',
    meta: { auth: true, requiresAuth: true },
    component: () => import('../views/ProfileView.vue'),
  },
  {
    path: 'profile/edit',
    name: 'profile.edit',
    meta: { auth: true, requiresAuth: true },
    component: () => import('../views/ProfileEdit.vue'),
  },
  {
    path: 'posts/:id',
    name: 'post-detail',
    meta: { requiresAuth: false },
    component: () => import('@/views/PostDetailView.vue'),
  },
  {
    path: 'tags/:tag',
    name: 'tag-feed',
    meta: { requiresAuth: false },
    component: () => import('../views/TagFeedView.vue'),
  },
  {
    path: 'hashtags/:name',
    name: 'hashtag-feed',
    meta: { requiresAuth: false },
    component: () => import('../views/HashtagFeedView.vue'),
  },
  {
    path: 'u/:username',
    name: 'user-profile',
    meta: { requiresAuth: false },
    component: () => import('../views/PublicProfileView.vue'),
  },
  {
    path: 'admin',
    component: () => import('@/layouts/AdminHubLayout.vue'),
    meta: { auth: true, requiresAuth: true, admin: true },
    children: [
      {
        path: '',
        redirect: { name: 'admin.dashboard' },
      },
      {
        path: 'dashboard',
        name: 'admin.dashboard',
        component: () => import('@/views/admin/AdminDashboardView.vue'),
      },
      {
        path: 'event-candidates',
        name: 'admin.event-candidates',
        component: () => import('@/views/admin/CandidatesListView.vue'),
      },
      {
        path: 'candidates',
        redirect: { name: 'admin.event-candidates' },
      },
      {
        path: 'candidates/:id',
        name: 'admin.candidate.detail',
        component: () => import('@/views/admin/CandidateDetailView.vue'),
      },
      {
        path: 'blog',
        name: 'admin.blog',
        component: () => import('@/views/admin/BlogPostsView.vue'),
      },
      {
        path: 'events',
        name: 'admin.events',
        component: () => import('@/views/admin/EventsUnifiedView.vue'),
      },
      {
        path: 'contests',
        name: 'admin.contests',
        component: () => import('@/views/admin/ContestsView.vue'),
      },
      {
        path: 'events/create',
        name: 'admin.events.create',
        component: () => import('@/views/admin/EventFormView.vue'),
      },
      {
        path: 'events/:id/edit',
        name: 'admin.events.edit',
        component: () => import('@/views/admin/EventFormView.vue'),
      },
      {
        path: 'reports',
        name: 'admin.reports',
        component: () => import('@/views/admin/ReportsView.vue'),
      },
      {
        path: 'moderation',
        name: 'admin.moderation',
        component: () => import('@/views/admin/ModerationView.vue'),
      },
      {
        path: 'users',
        name: 'admin.users',
        component: () => import('@/views/admin/UsersView.vue'),
      },
      {
        path: 'users/:id',
        name: 'admin.users.detail',
        component: () => import('@/views/admin/AdminUserDetailView.vue'),
      },
      ...(wipEnabled
        ? [{
            path: 'banned-words',
            name: 'admin.banned-words',
            component: () => import('@/views/admin/BannedWordsView.vue'),
          }]
        : []),
      {
        path: 'event-sources',
        name: 'admin.event-sources',
        component: () => import('@/views/admin/EventSourcesView.vue'),
      },
      {
        path: 'crawl-runs/:id',
        name: 'admin.crawl-run.detail',
        component: () => import('@/views/admin/CrawlRunDetailView.vue'),
      },
      {
        path: 'featured-events',
        name: 'admin.featured-events',
        component: () => import('@/views/admin/AdminFeaturedEventsView.vue'),
      },
      {
        path: 'newsletter',
        name: 'admin.newsletter',
        component: () => import('@/views/admin/AdminNewsletterView.vue'),
      },
      {
        path: 'astrobot',
        name: 'admin.astrobot',
        redirect: { name: 'admin.bots' },
      },
      {
        path: 'bots',
        name: 'admin.bots',
        component: () => import('@/views/admin/BotEngineView.vue'),
      },
      {
        path: 'kozmobot',
        name: 'admin.bots.kozmo',
        redirect: { name: 'admin.bots' },
      },
      {
        path: 'stellarbot',
        name: 'admin.bots.stellar',
        redirect: { name: 'admin.bots' },
      },
      {
        path: 'sidebar',
        name: 'admin.sidebar',
        component: () => import('@/views/admin/SidebarConfigView.vue'),
      },
      {
        path: 'performance-metrics',
        name: 'admin.performance-metrics',
        component: () => import('@/views/admin/PerformanceMetricsView.vue'),
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: AppLayout,
      children: appShellChildren,
    },
    {
      path: '/login',
      name: 'login',
      meta: { guest: true, requiresAuth: false },
      component: () => import('../views/LoginView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      meta: { guest: true, requiresAuth: false },
      component: () => import('../views/RegisterView.vue'),
    },
    {
      path: '/verify-email',
      name: 'verify-email.required',
      meta: { requiresAuth: true },
      component: () => import('../views/VerifyEmailView.vue'),
    },
    {
      path: '/verify-email/:id/:hash',
      name: 'verify-email.link',
      meta: { requiresAuth: false },
      component: () => import('../views/VerifyEmailView.vue'),
    },
    {
      path: '/onboarding',
      name: 'onboarding',
      meta: { requiresAuth: true },
      component: () => import('../views/OnboardingView.vue'),
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
    },
  ],
})

export function applyAuthGuards(routerInstance) {
  routerInstance.beforeEach(async (to) => {
    if (to.path.includes('//')) {
      return {
        path: to.path.replace(/\/{2,}/g, '/'),
        query: to.query,
        hash: to.hash,
        replace: true,
      }
    }

    const auth = useAuthStore()
    const preferences = useEventPreferencesStore()

    if (!auth.bootstrapDone && auth.status === 'idle' && !auth.loading) {
      auth.bootstrapAuth()
    }

    const redirectTarget = to.fullPath
    const requiresAuth = Boolean(to.meta?.requiresAuth ?? to.meta?.auth ?? false)

    const shouldRedirectGuest =
      requiresAuth &&
      !auth.isAuthed &&
      (auth.bootstrapDone || auth.initialized || auth.status === 'guest' || auth.status === 'error')

    if (shouldRedirectGuest) {
      return {
        name: 'login',
        query: { redirect: redirectTarget },
      }
    }

    const isVerifyEmailRoute = to.name === 'verify-email.required' || to.name === 'verify-email.link'
    const isOnboardingRoute = to.name === 'onboarding'
    const isVerifiedUser = Boolean(auth.isAuthed && auth.user?.email_verified_at)
    const isAdminUser = Boolean(auth.isAdmin)

    if (auth.isAuthed && !isAdminUser && !auth.user?.email_verified_at && !isVerifyEmailRoute) {
      return {
        name: 'verify-email.required',
        query: { redirect: redirectTarget },
      }
    }

    if (isVerifiedUser && !isAdminUser) {
      if (!preferences.loaded) {
        try {
          await preferences.fetchPreferences()
        } catch {
          // Skip onboarding redirect when preferences are temporarily unavailable.
        }
      }

      if (!preferences.isOnboardingCompleted && !isOnboardingRoute && !isVerifyEmailRoute) {
        return {
          name: 'onboarding',
          query: { redirect: redirectTarget },
        }
      }

      if (preferences.isOnboardingCompleted && isOnboardingRoute) {
        const redirect = typeof to.query?.redirect === 'string' && to.query.redirect.startsWith('/')
          ? to.query.redirect
          : '/'
        return redirect
      }
    }

    if (isAdminUser && isOnboardingRoute) {
      return { name: 'home' }
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
}

applyAuthGuards(router)

export default router
