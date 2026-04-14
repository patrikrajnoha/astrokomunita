import { nextTick } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import api from '@/services/api'
import { prefetchHomeFeed } from '@/services/feedPrefetch'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
import { legacySettingsSectionToRouteName } from '@/views/settings/settingsSections'

const wipEnabled = String(import.meta.env.VITE_FEATURE_WIP || 'false').toLowerCase() === 'true'
const SITE_NAME = 'Astrokomunita'
const DEFAULT_DOCUMENT_TITLE = `${SITE_NAME} – astronomická komunita na Slovensku`

const PAGE_TITLE_BY_ROUTE_NAME = Object.freeze({
  home: 'Domov',
  events: 'Udalosti',
  'events-swipe': 'Udalosti',
  'event-detail': 'Detail udalosti',
  observations: 'Pozorovania',
  'observations.create': 'Nové pozorovanie',
  'observations.detail': 'Detail pozorovania',
  learn: 'Vzdelávanie',
  'learn-detail': 'Článok',
  search: 'Vyhľadávanie',
  privacy: 'Ochrana súkromia',
  terms: 'Podmienky používania',
  cookies: 'Cookies',
  settings: 'Nastavenia',
  notifications: 'Notifikácie',
  bookmarks: 'Záložky',
  profile: 'Môj profil',
  'profile.edit': 'Upraviť profil',
  'post-detail': 'Príspevok',
  'tag-feed': 'Tag',
  'hashtag-feed': 'Hashtag',
  'user-profile': 'Profil používateľa',
  'public-invite': 'Pozvanka',
  onboarding: 'Úvodné nastavenie',
  login: 'Prihlásenie',
  register: 'Registrácia',
  'forgot-password': 'Zabudnuté heslo',
  'reset-password': 'Obnova hesla',
  'verify-email.deprecated': 'Overenie e-mailu',
  'verify-email.link-deprecated': 'Overenie e-mailu',
  'not-found': 'Stránka sa nenašla',
})

function firstParam(value) {
  if (Array.isArray(value)) {
    return String(value[0] || '')
  }
  return String(value || '')
}

function trimRouteLabel(value, maxLength = 70) {
  const normalized = String(value || '').trim()
  if (!normalized) return ''
  return normalized.length > maxLength ? `${normalized.slice(0, maxLength).trim()}…` : normalized
}

function resolveRoutePageTitle(route) {
  const explicitTitle = route?.meta?.title
  if (typeof explicitTitle === 'string' && explicitTitle.trim()) {
    return explicitTitle.trim()
  }

  if (typeof explicitTitle === 'function') {
    const computedTitle = explicitTitle(route)
    if (typeof computedTitle === 'string' && computedTitle.trim()) {
      return computedTitle.trim()
    }
  }

  const routeName = typeof route?.name === 'string' ? route.name : ''
  const pageTitle = PAGE_TITLE_BY_ROUTE_NAME[routeName]
  if (pageTitle) {
    if (routeName === 'search') {
      const q = trimRouteLabel(firstParam(route?.query?.q), 42)
      return q ? `Vyhľadávanie: ${q}` : pageTitle
    }

    if (routeName === 'user-profile') {
      const username = trimRouteLabel(firstParam(route?.params?.username), 42).replace(/^@+/, '')
      return username ? `@${username}` : pageTitle
    }

    if (routeName === 'tag-feed') {
      const tag = trimRouteLabel(firstParam(route?.params?.tag), 42).replace(/^#/, '')
      return tag ? `#${tag}` : pageTitle
    }

    if (routeName === 'hashtag-feed') {
      const hashtag = trimRouteLabel(firstParam(route?.params?.name), 42).replace(/^#/, '')
      return hashtag ? `#${hashtag}` : pageTitle
    }

    return pageTitle
  }

  if (routeName.startsWith('settings.')) {
    return 'Nastavenia'
  }

  if (routeName.startsWith('admin.')) {
    return 'Administrácia'
  }

  return ''
}

function composeDocumentTitle(pageTitle) {
  const normalized = trimRouteLabel(pageTitle, 120)
  if (!normalized) {
    return DEFAULT_DOCUMENT_TITLE
  }
  if (normalized === SITE_NAME || normalized.endsWith(`| ${SITE_NAME}`)) {
    return normalized
  }
  return `${normalized} | ${SITE_NAME}`
}

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
    path: 'articles',
    name: 'learn',
    meta: { requiresAuth: false },
    component: () => import('../views/LearnView.vue'),
  },
  {
    path: 'articles/:slug',
    name: 'learn-detail',
    meta: { requiresAuth: false },
    component: () => import('../views/LearnDetailView.vue'),
  },
  {
    path: 'clanky',
    redirect: { name: 'learn' },
  },
  {
    path: 'clanky/:slug',
    redirect: (to) => ({
      name: 'learn-detail',
      params: { slug: to.params.slug },
      query: to.query,
      hash: to.hash,
    }),
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
        path: 'sidebar-widgets',
        name: 'settings.sidebar-widgets',
        component: () => import('../views/settings/SettingsSidebarWidgetsView.vue'),
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
    path: 'invites/public/:token',
    name: 'public-invite',
    meta: { requiresAuth: false },
    component: () => import('../views/PublicInviteView.vue'),
  },
  {
    path: 'admin',
    name: 'admin.root',
    component: () => import('@/layouts/AdminHubLayout.vue'),
    meta: { auth: true, requiresAuth: true, adminHub: true },
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
        path: 'sidebar-config',
        name: 'admin.sidebar-config',
        meta: { adminSection: 'sidebar-config' },
        component: () => import('@/views/admin/AdminSidebarConfigView.vue'),
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
        path: 'users/:id/detail',
        name: 'admin.users.detail',
        meta: { adminSection: 'community', adminTab: 'users' },
        alias: ['users/:id'],
        component: () => import('@/views/admin/UsersView.vue'),
      },
      {
        path: 'users/:id/full',
        name: 'admin.users.detail.page',
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
        meta: { adminSection: 'automation', adminTab: 'dashboard' },
        component: () => import('@/views/admin/BotAdminUnifiedView.vue'),
      },
      {
        path: 'bots/engine',
        name: 'admin.bots.engine',
        meta: { adminSection: 'automation', adminTab: 'legacy-tools' },
        component: () => import('@/views/admin/BotEngineView.vue'),
      },
      {
        path: 'bots/sources',
        name: 'admin.bots.sources',
        meta: { adminSection: 'automation', adminTab: 'sources' },
        component: () => import('@/views/admin/BotAdminUnifiedView.vue'),
      },
      {
        path: 'bots/schedules',
        name: 'admin.bots.schedules',
        meta: { adminSection: 'automation', adminTab: 'schedules' },
        component: () => import('@/views/admin/BotAdminUnifiedView.vue'),
      },
      {
        path: 'bots/activity',
        name: 'admin.bots.activity',
        meta: { adminSection: 'automation', adminTab: 'logs' },
        component: () => import('@/views/admin/BotAdminUnifiedView.vue'),
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
        path: 'performance-metrics',
        name: 'admin.performance-metrics',
        meta: { adminSection: 'performance' },
        component: () => import('@/views/admin/PerformanceMetricsView.vue'),
      },
    ],
  },
  {
    path: ':pathMatch(.*)*',
    name: 'not-found',
    meta: { requiresAuth: false },
    component: () => import('../views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }

    return { left: 0, top: 0 }
  },
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
      path: '/forgot-password',
      name: 'forgot-password',
      meta: { guest: true, requiresAuth: false },
      component: () => import('../views/ForgotPasswordView.vue'),
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      meta: { guest: true, requiresAuth: false },
      component: () => import('../views/ResetPasswordView.vue'),
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
  ],
})

const LAZY_CHUNK_ERROR_PATTERN =
  /Failed to fetch dynamically imported module|Importing a module script failed|Loading CSS chunk|ChunkLoadError/i
let hardReloadTriggeredForChunkError = false

router.onError((error, to) => {
  if (typeof window === 'undefined') return
  if (hardReloadTriggeredForChunkError) return

  const message = String(error?.message || '')
  if (!LAZY_CHUNK_ERROR_PATTERN.test(message)) return

  hardReloadTriggeredForChunkError = true
  const fallbackTarget = `${window.location.pathname}${window.location.search}${window.location.hash}`
  const target = typeof to?.fullPath === 'string' && to.fullPath.trim() ? to.fullPath : fallbackTarget

  window.location.assign(target)
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

    if (to.name === 'home') {
      void prefetchHomeFeed(api)
    }

    const redirectTarget = to.fullPath
    const requiresAuth = Boolean(to.meta?.requiresAuth ?? to.meta?.auth ?? false)

    if (!auth.bootstrapDone) {
      try {
        await auth.waitForBootstrap()
      } catch {
        // Auth bootstrap failure is handled by store state and downstream guards.
      }

      await nextTick()
    }

    const hasResolvedGuestState =
      (auth.bootstrapDone || auth.initialized) &&
      auth.status !== 'loading' &&
      auth.status !== 'error'

    const shouldRedirectGuest =
      requiresAuth &&
      !auth.isAuthed &&
      (auth.status === 'guest' || hasResolvedGuestState)

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
          query: { redirect: redirectTarget, start_tour: '1' },
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

    const isAdminHubRoute = to.path.startsWith('/admin')
    const isEditorUser = Boolean(auth.isEditor)
    const isAdminOrEditor = Boolean(auth.isAdmin || isEditorUser)

    if (isAdminHubRoute && !isAdminOrEditor) {
      return { name: 'home' }
    }

    if (isEditorUser && isAdminHubRoute && !to.path.startsWith('/admin/content')) {
      return { name: 'admin.blog' }
    }

    if (to.meta?.guest && auth.isAuthed) {
      const redirect = typeof to.query?.redirect === 'string' ? to.query.redirect : null
      return redirect || { name: 'home' }
    }

    return true
  })
}

export function applyDocumentTitleGuard(routerInstance) {
  routerInstance.afterEach((to) => {
    if (typeof document === 'undefined') return
    const pageTitle = resolveRoutePageTitle(to)
    document.title = composeDocumentTitle(pageTitle)
  })
}

applyAuthGuards(router)
applyDocumentTitleGuard(router)

export default router
