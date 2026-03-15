<template src="./appLayout/AppLayout.template.html"></template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch, defineAsyncComponent } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
import { useNotificationsStore } from '@/stores/notifications'
import MainNavbar from '@/components/MainNavbar.vue'
import MobileFab from '@/components/MobileFab.vue'
import MobileBottomNav from '@/components/nav/MobileBottomNav.vue'
import AdminSubNav from '@/components/admin/AdminSubNav.vue'
import { useToast } from '@/composables/useToast'
import { DEFAULT_SIDEBAR_SCOPE, resolveSidebarScopeFromPath } from '@/utils/sidebarScope'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import { useOnboardingTourStore } from '@/stores/onboardingTour'
import {
  APP_LAYOUT_COMPOSER_OPEN_EVENT,
  useAppLayoutWidgets,
} from './appLayout/useAppLayoutWidgets'
import { useMarkYourCalendarPopup } from './appLayout/useMarkYourCalendarPopup'
import {
  SIDEBAR_WIDGET_BUNDLE_SECTION_KEYS,
  useSidebarWidgetBundle,
} from '@/composables/useSidebarWidgetBundle'
import {
  dispatchPostCreated,
  parseStringValue,
} from './appLayout/appLayout.utils'
import { resolveObservingContext } from '@/utils/observingContext'
import {
  getEnabledSidebarSections,
  normalizeSidebarSections,
  resolveSidebarComponent,
  resolveSidebarIcon,
} from '@/sidebar/engine'

const DynamicSidebar = defineAsyncComponent(() => import('@/components/DynamicSidebar.vue'))
const CreatePostModal = defineAsyncComponent(() => import('@/components/CreatePostModal.vue'))
const MarkYourCalendarModal = defineAsyncComponent(() => import('@/components/MarkYourCalendarModal.vue'))
const OnboardingTour = defineAsyncComponent(() => import('@/components/onboarding/OnboardingTour.vue'))

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const preferences = useEventPreferencesStore()
const notifications = useNotificationsStore()
const sidebarConfigStore = useSidebarConfigStore()
const onboardingTour = useOnboardingTourStore()
const { showToast } = useToast()
const mobileSidebarSections = ref([])
const mobileWidgetMenuOpenState = ref(false)
const mobileWidgetSheetOpenState = ref(false)
const deferredInstallPrompt = ref(null)
const canInstall = ref(false)
const isMobileViewport = ref(false)
const fabBottomOffset = computed(() => (canInstall.value ? 82 : 16))
const showMobileBottomNav = computed(() => isMobileViewport.value && !isAdminRoute.value)
const appShellStyle = computed(() => ({
  '--app-header-h': 'var(--navbar-height)',
  '--mobile-bottom-nav-offset': showMobileBottomNav.value ? '96px' : '0px',
}))
const legalLinks = [
  { to: '/privacy', label: 'Ochrana súkromia' },
  { to: '/terms', label: 'Podmienky' },
  { to: '/cookies', label: 'Cookies' },
]
const currentSidebarScope = computed(() => resolveSidebarScopeFromPath(route.path || ''))
const isAdminRoute = computed(() => String(route.path || '').startsWith('/admin'))
const isSettingsRoute = computed(() => String(route.path || '').startsWith('/settings'))
const showRightSidebar = computed(() => isAdminRoute.value || Boolean(currentSidebarScope.value))
const mobileFabLabel = computed(() => (isAdminRoute.value ? 'Admin sekcie' : 'Widgety'))
const mobileFabMenuTitle = computed(() => (isAdminRoute.value ? 'Admin sekcie' : 'Widgety'))
const mobileFabMenuCloseLabel = computed(() => (
  isAdminRoute.value ? 'Zavriet menu admin sekcii' : 'Zavriet menu widgetov'
))
const isProfileRoute = computed(() => String(route.path || '').startsWith('/profile'))
const isHomeFeedRoute = computed(() => route.name === 'home')
const showDesktopMainSidebar = computed(() => true)
const isLayoutDebugEnabled = computed(() => {
  return import.meta.env.DEV && String(import.meta.env.VITE_DEBUG_LAYOUT || '') === 'true'
})
const REALTIME_BOOTSTRAP_DELAY_MS = 1200
const desktopFrameClass = computed(() => 'desktopFrame mx-auto w-full max-w-[1500px] xl:grid')
const centerShellClass = computed(
  () => 'centerShellGrid min-w-0 xl:col-start-1 xl:grid xl:gap-1 2xl:gap-2',
)
const centerShellColumns = computed(() => '16rem minmax(600px, 640px)')
const centerShellStyle = computed(() => {
  return {
    '--center-shell-cols': centerShellColumns.value,
    outline: isLayoutDebugEnabled.value
      ? '1px solid rgb(var(--color-primary-rgb) / 0.4)'
      : undefined,
  }
})
const mainContentClass = computed(() => 'mx-auto w-full max-w-[640px]')
const preferredSidebarWidgetKeys = computed(() => {
  if (!auth.isAuthed || !preferences.loaded) return null
  const scope = String(currentSidebarScope.value || DEFAULT_SIDEBAR_SCOPE)
  if (typeof preferences.sidebarWidgetKeysForScope !== 'function') return null
  const selected = preferences.sidebarWidgetKeysForScope(scope)
  if (!Array.isArray(selected)) return null
  if (selected.length > 0) return selected

  const hasExplicitScopeOverride = typeof preferences.hasSidebarWidgetOverrideForScope === 'function'
    ? preferences.hasSidebarWidgetOverrideForScope(scope)
    : false
  const hasExplicitGlobalOverride = typeof preferences.hasSidebarWidgetOverrideForScope === 'function'
    ? preferences.hasSidebarWidgetOverrideForScope(DEFAULT_SIDEBAR_SCOPE)
    : false

  return hasExplicitScopeOverride || hasExplicitGlobalOverride ? [] : null
})
const enabledMobileSections = computed(() => (
  getEnabledSidebarSections(mobileSidebarSections.value, {
    isGuest: !auth.isAuthed,
    collapseObservingForMissingLocation: auth.isAuthed && !hasObservingLocation.value,
    preferredSectionKeys: preferredSidebarWidgetKeys.value,
  })
))
const mobilePreloadableSectionKeys = computed(() => (
  enabledMobileSections.value
    .filter((section) => section?.kind !== 'custom_component')
    .map((section) => String(section?.section_key || ''))
    .filter((sectionKey) => SIDEBAR_WIDGET_BUNDLE_SECTION_KEYS.has(sectionKey))
))
const observingContext = computed(() => resolveObservingContext({
  user: auth.user,
  preferences,
  dateQuery: route.query.date,
}))
const observingLat = computed(() => observingContext.value.lat)
const observingLon = computed(() => observingContext.value.lon)
const hasObservingLocation = computed(() => {
  return observingLat.value !== null && observingLon.value !== null
})
const observingLocationName = computed(() => observingContext.value.locationName)
const observingDate = computed(() => observingContext.value.date)
const observingTz = computed(() => observingContext.value.tz)
const mobileSidebarBundleQuery = computed(() => {
  const query = {}

  if (observingLat.value !== null && observingLon.value !== null) {
    query.lat = observingLat.value
    query.lon = observingLon.value
    if (observingTz.value) {
      query.tz = observingTz.value
    }
  }

  return query
})
const shouldLoadMobileSidebarBundle = computed(() => (
  isMobileViewport.value
  && !isAdminRoute.value
  && (mobileWidgetMenuOpenState.value || mobileWidgetSheetOpenState.value)
))
const {
  bundledSectionPayloads: mobileBundledSectionPayloads,
  bundlePending: mobileSidebarBundlePending,
} = useSidebarWidgetBundle({
  enabled: shouldLoadMobileSidebarBundle,
  query: mobileSidebarBundleQuery,
  sectionKeys: mobilePreloadableSectionKeys,
})
const showAuthFallbackBanner = computed(() => {
  return (
    auth.bootstrapDone &&
    !auth.isAuthed &&
    (auth.error?.type === 'timeout' || auth.error?.type === 'network')
  )
})
const showAuthBannedBanner = computed(() => {
  return auth.bootstrapDone && !auth.isAuthed && auth.error?.type === 'banned'
})
const authFallbackMessage = computed(() => {
  if (auth.error?.type === 'timeout') {
    return 'Nepodarilo sa nacitat profil (timeout). Pokracujes ako host.'
  }

  return 'Backend je nedostupny. Pokracujes ako host.'
})
const authBannedMessage = computed(() => {
  const reason = parseStringValue(auth.error?.reason)
  if (reason) {
    return `Tento ucet je zablokovany. Dovod: ${reason}`
  }

  return 'Tento ucet je zablokovany.'
})
const authBannerMessage = computed(() => {
  if (showAuthBannedBanner.value) {
    return authBannedMessage.value
  }

  return authFallbackMessage.value
})
const isOnboardingRoute = computed(() => route.name === 'onboarding')
const isOnboardingFlowActive = computed(() => {
  if (!auth.isAuthed || auth.isAdmin) return false
  return (
    isOnboardingRoute.value ||
    !preferences.loaded ||
    preferences.loading ||
    !preferences.isOnboardingCompleted
  )
})
const {
  calendarPopupPayload,
  closeCalendarPopup,
  goToCalendarFromPopup,
  isCalendarPopupVisible,
  maybeCheckCalendarPopup,
  resetCalendarPopupState,
} = useMarkYourCalendarPopup({
  auth,
  isOnboardingFlowActive,
  onboardingTour,
  preferences,
  router,
})

const maybeAutoOpenOnboardingTour = () => {
  if (typeof window === 'undefined') return
  if (!auth.isAuthed || auth.isAdmin) return
  if (isOnboardingFlowActive.value) return
  if (isCalendarPopupVisible.value) return

  onboardingTour.hydrate()
  if (onboardingTour.shouldAutoOpen) {
    onboardingTour.openTour()
  }
}

async function warmSidebarConfig() {
  if (!isMobileViewport.value) {
    mobileSidebarSections.value = []
    return
  }

  const scope = currentSidebarScope.value
  if (!scope) {
    mobileSidebarSections.value = []
    return
  }

  const items = await sidebarConfigStore.fetchScope(scope)
  mobileSidebarSections.value = normalizeSidebarSections(items)
}

const {
  activeWidgetKey,
  activeWidgetTitle,
  closeComposerModal,
  closeDrawer,
  closeWidgetLayers,
  closeWidgetMenu,
  closeWidgetSheet,
  composerInitialAction,
  composerInitialAttachmentFile,
  handleComposerOpenEvent,
  hydrateLastWidgetFromStorage,
  isComposerOpen,
  isDrawerOpen,
  isWidgetMenuOpen,
  isWidgetSheetOpen,
  lastOpenedWidget,
  onSheetTouchEnd,
  onSheetTouchMove,
  onSheetTouchStart,
  openAllWidgetsSheet,
  openComposerFromWidgets,
  openComposerModal,
  openDrawer,
  openWidgetSheet,
  openWidgetsMenu,
  propsForWidget,
  showAllWidgets,
  widgetMenuOffsetY,
  widgetSheetOffsetY,
} = useAppLayoutWidgets({
  auth,
  enabledMobileSections,
  isMobileViewport,
  mobileWidgetInitialPayloads: mobileBundledSectionPayloads,
  mobileWidgetBundlePending: mobileSidebarBundlePending,
  observingContext,
  warmSidebarConfig,
  widgetMenuOpenRef: mobileWidgetMenuOpenState,
  widgetSheetOpenRef: mobileWidgetSheetOpenState,
})
const activeWidgetComponent = computed(() => resolveSidebarComponent(activeWidgetKey.value))
let notificationsRealtimeBootstrapTimer = null

const cancelNotificationsRealtimeBootstrap = () => {
  if (notificationsRealtimeBootstrapTimer === null || typeof window === 'undefined') {
    notificationsRealtimeBootstrapTimer = null
    return
  }

  window.clearTimeout(notificationsRealtimeBootstrapTimer)
  notificationsRealtimeBootstrapTimer = null
}

const scheduleNotificationsRealtimeBootstrap = () => {
  cancelNotificationsRealtimeBootstrap()

  if (typeof window === 'undefined') {
    void notifications.startRealtime()
    return
  }

  notificationsRealtimeBootstrapTimer = window.setTimeout(() => {
    notificationsRealtimeBootstrapTimer = null
    void notifications.startRealtime()
  }, REALTIME_BOOTSTRAP_DELAY_MS)
}

const onPostCreated = async (createdPost) => {
  closeComposerModal()
  showToast('Prispevok bol publikovany.', 'success')

  if (route.name === 'home') {
    dispatchPostCreated(createdPost)
    return
  }

  if (route.name !== 'home') {
    await router.push({ name: 'home' })
    window.setTimeout(() => dispatchPostCreated(createdPost), 60)
  }
}

const handleKeydown = (event) => {
  if (event.key !== 'Escape') return

  if (isWidgetSheetOpen.value) {
    closeWidgetSheet()
    return
  }

  if (isWidgetMenuOpen.value) {
    closeWidgetMenu()
    return
  }

  if (isComposerOpen.value) {
    return
  }

  if (isDrawerOpen.value) {
    closeDrawer()
  }
}

const handleBeforeInstallPrompt = (event) => {
  event.preventDefault()
  deferredInstallPrompt.value = event
  canInstall.value = true
}

const handleInstalled = () => {
  deferredInstallPrompt.value = null
  canInstall.value = false
}

const installApp = async () => {
  const promptEvent = deferredInstallPrompt.value
  if (!promptEvent) return

  try {
    await promptEvent.prompt()
    await promptEvent.userChoice
  } catch (error) {
    console.warn('Install prompt failed:', error)
  } finally {
    deferredInstallPrompt.value = null
    canInstall.value = false
  }
}

const retryAuthFetch = async () => {
  await auth.retryFetchUser()
}

const updateViewportState = () => {
  if (typeof window === 'undefined') {
    isMobileViewport.value = false
    return
  }

  isMobileViewport.value = window.matchMedia('(max-width: 767px)').matches
}

watch(
  () => currentSidebarScope.value,
  async () => {
    await warmSidebarConfig()
  },
  { immediate: true },
)

watch(
  () => route.fullPath,
  async () => {
    if (!isMobileViewport.value) return
    closeWidgetLayers()
    await warmSidebarConfig()
  },
)

watch(
  () => isMobileViewport.value,
  async (isMobile) => {
    if (!isMobile) {
      closeWidgetLayers()
      return
    }

    await warmSidebarConfig()
  },
)

watch(
  () => auth.user,
  (nextUser) => {
    if (!nextUser) {
      onboardingTour.closeTour()
      resetCalendarPopupState()
    }
  },
)

watch(
  () => [auth.isAuthed, auth.isAdmin],
  ([isAuthed, isAdmin]) => {
    if (!isAuthed || isAdmin) {
      onboardingTour.closeTour()
      return
    }

    maybeAutoOpenOnboardingTour()
  },
  { immediate: true },
)

watch(
  () => [isOnboardingFlowActive.value, isCalendarPopupVisible.value],
  ([onboardingFlowActive, calendarPopupVisible]) => {
    if (onboardingFlowActive || calendarPopupVisible) {
      onboardingTour.closeTour()
      return
    }

    maybeAutoOpenOnboardingTour()
  },
)

watch(
  () => auth.user?.id,
  (nextUserId) => {
    if (nextUserId) {
      void notifications.fetchUnreadCount()
      scheduleNotificationsRealtimeBootstrap()
      return
    }

    cancelNotificationsRealtimeBootstrap()
    notifications.stopRealtime({
      disconnect: true,
      clearState: true,
    })
  },
  { immediate: true },
)

watch(
  () => [
    auth.bootstrapDone,
    auth.isAuthed,
    auth.user?.email_verified_at,
    preferences.loaded,
    preferences.isOnboardingCompleted,
  ],
  async () => {
    if (
      auth.isAuthed &&
      (auth.isAdmin || Boolean(auth.user?.email_verified_at)) &&
      !preferences.loaded &&
      !preferences.loading
    ) {
      try {
        await preferences.fetchPreferences()
      } catch {
        return
      }
    }

    await maybeCheckCalendarPopup()
  },
  { immediate: true },
)

onMounted(() => {
  updateViewportState()
  hydrateLastWidgetFromStorage()

  window.addEventListener('keydown', handleKeydown)
  window.addEventListener(APP_LAYOUT_COMPOSER_OPEN_EVENT, handleComposerOpenEvent)
  window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  window.addEventListener('appinstalled', handleInstalled)
  window.addEventListener('resize', updateViewportState)
})

onBeforeUnmount(() => {
  cancelNotificationsRealtimeBootstrap()
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener(APP_LAYOUT_COMPOSER_OPEN_EVENT, handleComposerOpenEvent)
  window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  window.removeEventListener('appinstalled', handleInstalled)
  window.removeEventListener('resize', updateViewportState)
  notifications.stopRealtime({ disconnect: true })
})
</script>

<style scoped src="./appLayout/AppLayout.css"></style>
