import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
import { legacySettingsSectionToRouteName } from '@/views/settings/settingsSections'

const wipEnabled = String(import.meta.env.VITE_FEATURE_WIP || 'false').toLowerCase() === 'true'

function resolveLegacySettingsSectionRedirect(to) {
  const sectionCandidate = Array.isArray(to.query?.section) ? to.query.section[0] : to.query?.section
  const section = String(sectionCandidate || '').trim().toLowerCase()
  const routeName = legacySettingsSectionToRouteName[section]

  if (!routeName) {
    return null
  }

  const query = { ...to.query }
  delete query.section

  return {
    name: routeName,
    query,
    hash: to.hash,
    replace: true,
  }
}

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
  ...(wipEnabled
    ? [{
        path: 'observations',
        name: 'observations',
        meta: { requiresAuth: false },
        component: () => import('../views/ObservationsView.vue'),
      }]
    : []),
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
    path: 'privacy',
    name: 'privacy',
    meta: { requiresAuth: false },
    component: () => import('../views/PrivacyPolicyView.vue'),
  },
  {
    path: 'terms',
    name: 'terms',
    meta: { requiresAuth: false },
    component: () => import('../views/TermsOfServiceView.vue'),
  },
  {
    path: 'cookies',
    name: 'cookies',
    meta: { requiresAuth: false },
    component: () => import('../views/CookiesView.vue'),
  },
  {
    path: 'settings',
    meta: { auth: true, requiresAuth: true },
    component: () => import('../views/SettingsView.vue'),
    children: [
      {
        path: '',
        name: 'settings',
        component: () => import('../views/settings/SettingsNavigationView.vue'),
        beforeEnter: (to) => resolveLegacySettingsSectionRedirect(to) || true,
      },
      {
        path: 'onboarding',
        name: 'settings.onboarding',
        component: () => import('../views/settings/SettingsOnboardingView.vue'),
      },
      {
        path: 'email',
        name: 'settings.email',
        component: () => import('../views/settings/SettingsEmailView.vue'),
      },
      {
        path: 'newsletter',
        name: 'settings.newsletter',
        component: () => import('../views/settings/SettingsNewsletterView.vue'),
      },
      {
        path: 'data-export',
        name: 'settings.data-export',
        component: () => import('../views/settings/SettingsDataExportView.vue'),
      },
      {
        path: 'password',
        name: 'settings.password',
        component: () => import('../views/settings/SettingsPasswordView.vue'),
      },
      {
        path: 'activity',
        name: 'settings.activity',
        component: () => import('../views/settings/SettingsActivityView.vue'),
      },
      {
        path: 'deactivate',
        name: 'settings.deactivate',
        component: () => import('../views/settings/SettingsDeactivateView.vue'),
      },
    ],
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
    name: 'admin.root',
    component: () => import('@/layouts/AdminHubLayout.vue'),
    meta: { auth: true, requiresAuth: true, admin: true },
    children: [
      {
        path: '',
        name: 'admin.root.default',
        redirect: { name: 'admin.dashboard' },
      },
      {
        path: 'dashboard',
        name: 'admin.dashboard',
        meta: { adminSection: 'dashboard' },
        component: () => import('@/views/admin/AdminDashboardView.vue'),
      },
      {
        path: 'events/create',
        name: 'admin.events.create',
        meta: { adminSection: 'events', adminTab: 'published' },
        component: () => import('@/views/admin/EventFormView.vue'),
      },
      {
        path: 'events/:id/edit',
        name: 'admin.events.edit',
        meta: { adminSection: 'events', adminTab: 'published' },
        component: () => import('@/views/admin/EventFormView.vue'),
      },
      {
        path: 'users/:id',
        name: 'admin.users.detail',
        meta: { adminSection: 'community', adminTab: 'users' },
        component: () => import('@/views/admin/AdminUserDetailView.vue'),
      },
      {
        path: 'crawl-runs/:id',
        name: 'admin.crawl-run.detail',
        meta: { adminSection: 'events', adminTab: 'crawling' },
        component: () => import('@/views/admin/CrawlRunDetailView.vue'),
      },
      {
        path: 'candidates/:id',
        name: 'admin.candidate.detail',
        meta: { adminSection: 'events', adminTab: 'candidates' },
        component: () => import('@/views/admin/CandidateDetailView.vue'),
      },
      {
        path: 'events',
        name: 'admin.events.section',
        meta: { adminSection: 'events' },
        component: () => import('@/views/admin/AdminEventsSectionView.vue'),
        children: [
          {
            path: '',
            name: 'admin.events.default',
            redirect: (to) => ({
              name: 'admin.events',
              query: to.query,
              hash: to.hash,
            }),
          },
          {
            path: 'crawling',
            name: 'admin.event-sources',
            meta: { adminSection: 'events', adminTab: 'crawling' },
            component: () => import('@/views/admin/EventSourcesView.vue'),
          },
          {
            path: 'candidates',
            name: 'admin.event-candidates',
            meta: { adminSection: 'events', adminTab: 'candidates' },
            component: () => import('@/views/admin/CandidatesListView.vue'),
          },
          {
            path: 'published',
            name: 'admin.events',
            meta: { adminSection: 'events', adminTab: 'published' },
            component: () => import('@/views/admin/EventsUnifiedView.vue'),
          },
        ],
      },
      {
        path: 'community',
        name: 'admin.community.section',
        meta: { adminSection: 'community' },
        component: () => import('@/views/admin/AdminCommunitySectionView.vue'),
        children: [
          {
            path: '',
            name: 'admin.community.default',
            redirect: (to) => ({
              name: 'admin.users',
              query: to.query,
              hash: to.hash,
            }),
          },
          {
            path: 'users',
            name: 'admin.users',
            meta: { adminSection: 'community', adminTab: 'users' },
            component: () => import('@/views/admin/UsersView.vue'),
          },
          {
            path: 'moderation',
            name: 'admin.moderation',
            meta: { adminSection: 'community', adminTab: 'moderation' },
            component: () => import('@/views/admin/ModerationHubView.vue'),
          },
        ],
      },
      {
        path: 'content',
        name: 'admin.content.section',
        meta: { adminSection: 'content' },
        component: () => import('@/views/admin/AdminContentSectionView.vue'),
        children: [
          {
            path: '',
            name: 'admin.content.default',
            redirect: (to) => ({
              name: 'admin.blog',
              query: to.query,
              hash: to.hash,
            }),
          },
          {
            path: 'articles',
            name: 'admin.blog',
            meta: { adminSection: 'content', adminTab: 'articles' },
            component: () => import('@/views/admin/BlogPostsView.vue'),
          },
          {
            path: 'newsletter',
            name: 'admin.newsletter',
            meta: { adminSection: 'content', adminTab: 'newsletter' },
            component: () => import('@/views/admin/AdminNewsletterView.vue'),
          },
        ],
      },
      {
        path: 'event-sources',
        name: 'admin.legacy.event-sources',
        redirect: (to) => ({
          name: 'admin.event-sources',
          query: to.query,
          hash: to.hash,
        }),
      },
      {
        path: 'event-candidates',
        name: 'admin.legacy.event-candidates',
        redirect: (to) => ({
          name: 'admin.event-candidates',
          query: to.query,
          hash: to.hash,
        }),
      },
      {
        path: 'candidates',
        name: 'admin.legacy.candidates',
        redirect: (to) => ({
          name: 'admin.event-candidates',
          query: to.query,
          hash: to.hash,
        }),
      },
      {
        path: 'users',
        name: 'admin.legacy.users',
        redirect: (to) => ({
          name: 'admin.users',
          query: to.query,
          hash: to.hash,
        }),
      },
      {
        path: 'moderation',
        name: 'admin.legacy.moderation',
        redirect: (to) => ({
          name: 'admin.moderation',
          query: to.query,
          hash: to.hash,
        }),
      },
      {
        path: 'reports',
        name: 'admin.reports',
        meta: { adminSection: 'community', adminTab: 'moderation' },
        redirect: (to) => {
          const tab = Array.isArray(to.query?.tab) ? to.query.tab[0] : to.query?.tab
          const hasTab = tab != null && String(tab).trim() !== ''

          return {
            name: 'admin.moderation',
            query: hasTab
              ? { ...to.query }
              : {
                  ...to.query,
                  tab: 'reports',
                },
            hash: to.hash,
          }
        },
      },
      {
        path: 'blog',
        name: 'admin.legacy.blog',
        redirect: (to) => ({
          name: 'admin.blog',
          query: to.query,
          hash: to.hash,
        }),
      },
      {
        path: 'newsletter',
        name: 'admin.legacy.newsletter',
        redirect: (to) => ({
          name: 'admin.newsletter',
          query: to.query,
          hash: to.hash,
        }),
      },
      {
        path: 'contests',
        name: 'admin.contests',
        meta: { adminSection: 'content' },
        component: () => import('@/views/admin/ContestsView.vue'),
      },
      ...(wipEnabled
        ? [{
            path: 'banned-words',
            name: 'admin.banned-words',
            meta: { adminSection: 'content' },
            component: () => import('@/views/admin/BannedWordsView.vue'),
          }]
        : []),
      {
        path: 'featured-events',
        name: 'admin.featured-events',
        meta: { adminSection: 'content' },
        component: () => import('@/views/admin/AdminFeaturedEventsView.vue'),
      },
      {
        path: 'astrobot',
        name: 'admin.astrobot',
        meta: { adminSection: 'automation' },
        redirect: { name: 'admin.bots' },
      },
      {
        path: 'bots',
        name: 'admin.bots',
        meta: { adminSection: 'automation' },
        component: () => import('@/views/admin/BotEngineView.vue'),
      },
      {
        path: 'kozmobot',
        name: 'admin.bots.kozmo',
        meta: { adminSection: 'automation' },
        redirect: { name: 'admin.bots' },
      },
      {
        path: 'stellarbot',
        name: 'admin.bots.stellar',
        meta: { adminSection: 'automation' },
        redirect: { name: 'admin.bots' },
      },
      {
        path: 'sidebar',
        name: 'admin.sidebar',
        meta: { adminSection: 'frontend' },
        component: () => import('@/views/admin/SidebarConfigView.vue'),
      },
      {
        path: 'performance-metrics',
        name: 'admin.performance-metrics',
        meta: { adminSection: 'performance' },
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
      name: 'verify-email.deprecated',
      meta: { requiresAuth: false },
      component: () => import('../views/VerifyEmailView.vue'),
    },
    {
      path: '/verify-email/:id/:hash',
      name: 'verify-email.link-deprecated',
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

    const isVerifyEmailRoute = to.name === 'verify-email.deprecated' || to.name === 'verify-email.link-deprecated'
    const isOnboardingRoute = to.name === 'onboarding'
    const isVerifiedUser = Boolean(auth.isAuthed && auth.user?.email_verified_at)
    const isAdminUser = Boolean(auth.isAdmin)
    const requiresEmailVerification = Boolean(auth.user?.requires_email_verification)
    const routeName = typeof to.name === 'string' ? to.name : ''
    const isSettingsRoute = routeName === 'settings' || routeName.startsWith('settings.')

    if (
      auth.isAuthed &&
      !isAdminUser &&
      requiresEmailVerification &&
      !auth.user?.email_verified_at &&
      !isVerifyEmailRoute &&
      !isSettingsRoute
    ) {
      return {
        name: 'settings.email',
        query: {
          redirect: redirectTarget,
        },
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
