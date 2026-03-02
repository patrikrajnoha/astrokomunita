<template>
  <div
    class="min-h-screen overflow-x-hidden bg-[var(--bg-app)] text-[var(--text-primary)] transition-colors duration-700"
    :style="appShellStyle"
  >
    <header
      class="sticky top-0 z-40 flex items-center justify-between border-b border-[var(--border)] bg-[color:rgb(var(--bg-app-rgb)/0.92)] px-4 py-3 backdrop-blur md:hidden"
    >
      <button
        type="button"
        class="ui-pill ui-pill--secondary ui-pill--icon"
        aria-label="Open navigation"
        @click="openDrawer"
      >
        ☰
      </button>

      <RouterLink
        to="/"
        class="inline-flex items-center gap-2 text-base font-semibold text-[var(--color-surface)]"
        aria-label="Home"
      >
        <span
          class="grid h-9 w-9 place-items-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-gradient-to-br from-[color:rgb(var(--color-primary-rgb)/0.3)] via-[color:rgb(var(--color-bg-rgb)/0.4)] to-[color:rgb(var(--color-primary-rgb)/0.2)] text-base shadow-lg"
          aria-hidden="true"
        >
          🌌
        </span>
        <Transition name="brand-fade" mode="out-in">
          <TypingText
            v-if="showBrandGreeting"
            :text="brandGreetingText"
            :speed-ms="56"
            :start-delay-ms="150"
            class="brandLabel font-bold"
            @done="onBrandGreetingDone"
          />
          <span v-else class="brandLabel">Astrokomunita</span>
        </Transition>
      </RouterLink>

      <span class="h-10 w-10" aria-hidden="true"></span>
    </header>

    <aside
      :class="[
        'fixed inset-y-0 left-0 hidden w-64 flex-col bg-[var(--bg-app)] px-4 py-6 md:left-3 md:inset-y-3 md:rounded-2xl md:flex xl:hidden',
        isHomeFeedRoute ? '' : 'border-r border-[var(--border)]',
      ]"
    >
      <div class="flex h-full flex-col">
        <nav class="flex-1" aria-label="Main navigation">
          <MainNavbar />
        </nav>

        <div class="legalLinks" data-testid="desktop-legal-links">
          <RouterLink
            v-for="item in legalLinks"
            :key="`tablet-${item.to}`"
            :to="item.to"
            class="legalLinks__item"
          >
            {{ item.label }}
          </RouterLink>
        </div>
      </div>
    </aside>

    <div
      :class="[
        showMobileBottomNav
          ? 'pb-[calc(var(--mobile-bottom-nav-offset)+env(safe-area-inset-bottom)+1rem)]'
          : 'pb-0',
        'guest-cta-safe',
        'md:pb-0 md:pl-64 xl:pl-0',
      ]"
    >
      <div
        v-if="showAuthFallbackBanner || showAuthBannedBanner"
        class="authFallbackBanner"
        :class="{ 'is-danger': showAuthBannedBanner }"
        role="status"
        aria-live="polite"
      >
        <span>{{ authBannerMessage }}</span>
        <button
          v-if="showAuthFallbackBanner"
          type="button"
          class="authFallbackRetry ui-pill ui-pill--secondary"
          @click="retryAuthFetch"
        >
          Skusit znova
        </button>
      </div>

      <div :class="desktopFrameClass" data-testid="desktop-frame">
        <div :class="centerShellClass" :style="centerShellStyle" data-testid="center-shell">
          <aside
            v-if="showDesktopMainSidebar"
            :class="[
              'hidden h-screen overflow-y-auto bg-[var(--bg-app)] px-4 py-6 xl:pl-6 2xl:pl-8 xl:sticky xl:top-0 xl:block',
              isHomeFeedRoute ? '' : 'border-r border-[var(--border)]',
            ]"
            data-testid="layout-left"
          >
            <div class="flex h-full flex-col">
              <nav class="flex-1" aria-label="Main navigation">
                <MainNavbar />
              </nav>

              <div class="legalLinks" data-testid="desktop-legal-links">
                <RouterLink
                  v-for="item in legalLinks"
                  :key="`desktop-${item.to}`"
                  :to="item.to"
                  class="legalLinks__item"
                >
                  {{ item.label }}
                </RouterLink>
              </div>
            </div>
          </aside>

          <main
            :class="[
              'min-w-0',
              isProfileRoute ? 'px-0 py-0 md:px-0 md:py-0' : 'px-4 py-6 md:px-8',
              isAdminRoute ? 'xl:px-6' : isProfileRoute ? 'xl:px-4 2xl:px-6' : 'xl:px-2 2xl:px-4',
              isProfileRoute ? 'lg:border-x lg:border-[var(--border)]' : '',
            ]"
            data-testid="layout-center"
          >
            <div :class="mainContentClass">
              <RouterView />
            </div>
          </main>
        </div>

        <aside
          v-if="showRightSidebar"
          class="hidden xl:col-start-2 xl:block xl:justify-self-end xl:self-start xl:pr-3 2xl:pr-4"
          data-testid="layout-right"
          aria-label="Right sidebar"
        >
          <div
            data-testid="right-rail"
            :class="[
              'rightRail h-screen w-[22rem] overflow-y-auto bg-[var(--bg-app)] px-5 py-6 xl:sticky xl:top-0',
              isHomeFeedRoute ? '' : 'border-l border-[var(--border)]',
            ]"
          >
            <DynamicSidebar
              v-if="!showDirectObservingSidebar"
              :observing-lat="observingLat"
              :observing-lon="observingLon"
              :observing-date="observingDate"
              :observing-tz="observingTz"
              :observing-location-name="observingLocationName"
            />
            <RightObservingSidebar
              v-else
              :lat="observingLat"
              :lon="observingLon"
              :date="observingDate"
              :tz="observingTz"
              :location-name="observingLocationName"
            />
          </div>
        </aside>
      </div>
    </div>

    <MobileBottomNav v-if="showMobileBottomNav" />

    <MobileFab
      v-if="
        auth.isAuthed && !isDrawerOpen && !isComposerOpen && !isWidgetMenuOpen && !isWidgetSheetOpen
      "
      :is-authenticated="auth.isAuthed"
      :bottom-offset="fabBottomOffset"
      @widgets="openWidgetsMenu"
    />

    <button
      v-if="canInstall"
      type="button"
      class="installBtn ui-pill ui-pill--secondary"
      @click="installApp"
    >
      Install app
    </button>

    <transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isDrawerOpen"
        class="fixed inset-0 z-40 bg-[color:rgb(var(--bg-app-rgb)/0.72)] md:hidden"
        aria-hidden="true"
        @click="closeDrawer"
      ></div>
    </transition>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="-translate-x-full opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="-translate-x-full opacity-0"
    >
      <aside
        v-if="isDrawerOpen"
        :class="[
          'fixed inset-y-0 left-0 z-50 w-72 overflow-y-auto bg-[var(--bg-app)] px-4 py-6 md:hidden',
          isHomeFeedRoute ? '' : 'border-r border-[var(--border)]',
        ]"
        aria-label="Mobile navigation"
      >
        <div class="flex items-center justify-between">
          <RouterLink
            to="/"
            class="inline-flex items-center gap-2 text-base font-semibold text-[var(--text-primary)]"
            aria-label="Home"
            @click="closeDrawer"
          >
            <span
              class="grid h-9 w-9 place-items-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-gradient-to-br from-[color:rgb(var(--color-primary-rgb)/0.3)] via-[color:rgb(var(--color-bg-rgb)/0.4)] to-[color:rgb(var(--color-primary-rgb)/0.2)] text-base shadow-lg"
              aria-hidden="true"
            >
              🌌
            </span>
            <Transition name="brand-fade" mode="out-in">
              <TypingText
                v-if="showBrandGreeting"
                :text="brandGreetingText"
                :speed-ms="56"
                :start-delay-ms="150"
                class="brandLabel font-bold"
                @done="onBrandGreetingDone"
              />
              <span v-else class="brandLabel">Astrokomunita</span>
            </Transition>
          </RouterLink>

          <button
            type="button"
            class="ui-pill ui-pill--secondary ui-pill--icon"
            aria-label="Close navigation"
            @click="closeDrawer"
          >
            ✖
          </button>
        </div>

        <div class="mt-6">
          <MainNavbar />
        </div>

        <div class="legalLinks mt-8" data-testid="mobile-legal-links">
          <RouterLink
            v-for="item in legalLinks"
            :key="`mobile-${item.to}`"
            :to="item.to"
            class="legalLinks__item"
            @click="closeDrawer"
          >
            {{ item.label }}
          </RouterLink>
        </div>
      </aside>
    </transition>

    <transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isComposerOpen"
        class="composeOverlay"
        aria-hidden="true"
        @click="closeComposerModal"
      ></div>
    </transition>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-6 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-6 opacity-0"
    >
      <section
        v-if="isComposerOpen"
        class="composeDialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mobile-compose-title"
        @click.stop
      >
        <div class="composeHead">
          <h2 id="mobile-compose-title" class="composeTitle">Vytvorit prispevok</h2>
          <button
            type="button"
            class="composeClose ui-pill ui-pill--secondary ui-pill--icon"
            aria-label="Zavriet tvorbu prispevku"
            @click="closeComposerModal"
          >
            ×
          </button>
        </div>
        <PostComposer @created="onPostCreated" />
      </section>
    </transition>

    <transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isWidgetMenuOpen || isWidgetSheetOpen"
        class="sheetOverlay"
        aria-hidden="true"
        @click="closeWidgetLayers"
      ></div>
    </transition>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-8 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-8 opacity-0"
    >
      <section
        v-if="isWidgetMenuOpen"
        class="sheetDialog"
        :style="{ transform: `translateY(${widgetMenuOffsetY}px)` }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mobile-widgets-menu-title"
        @click.stop
      >
        <button
          type="button"
          class="sheetHandle"
          aria-label="Close widgets menu"
          @touchstart="onSheetTouchStart($event, 'menu')"
          @touchmove="onSheetTouchMove($event, 'menu')"
          @touchend="onSheetTouchEnd('menu')"
          @click="closeWidgetMenu"
        ></button>
        <div class="sheetHead">
          <h2 id="mobile-widgets-menu-title" class="sheetTitle">Widgets</h2>
          <button
            type="button"
            class="sheetClose ui-pill ui-pill--secondary ui-pill--icon"
            aria-label="Close widgets menu"
            @click="closeWidgetMenu"
          >
            ×
          </button>
        </div>

        <div class="sheetList">
          <button
            type="button"
            class="sheetAction ui-pill ui-pill--primary createAction"
            @click="openComposerFromWidgets"
          >
            <span class="sheetActionIconWrap">
              <svg class="sheetActionIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 5v14" />
                <path d="M5 12h14" />
              </svg>
            </span>
            <span class="sheetActionText">Vytvorit prispevok</span>
          </button>

          <button
            v-if="enabledMobileSections.length > 1"
            type="button"
            class="sheetAction ui-pill ui-pill--secondary"
            @click="openAllWidgetsSheet"
          >
            <span class="sheetActionIconWrap">
              <svg class="sheetActionIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 7h16" />
                <path d="M4 12h16" />
                <path d="M4 17h16" />
              </svg>
            </span>
            <span class="sheetActionText">Zobrazit vsetky</span>
          </button>

          <button
            v-if="lastOpenedWidget"
            type="button"
            class="sheetAction ui-pill ui-pill--secondary"
            @click="openWidgetSheet(lastOpenedWidget)"
          >
            <span class="sheetActionIconWrap">
              <svg
                class="sheetActionIcon"
                :viewBox="resolveSidebarIcon(lastOpenedWidget.section_key).viewBox"
                fill="none"
                aria-hidden="true"
              >
                <path
                  v-for="(path, index) in resolveSidebarIcon(lastOpenedWidget.section_key).paths"
                  :key="`last-${index}`"
                  :d="path"
                />
              </svg>
            </span>
            <span class="sheetActionText">Naposledy: {{ lastOpenedWidget.title }}</span>
          </button>

          <template v-if="enabledMobileSections.length > 0">
            <button
              v-for="section in enabledMobileSections"
              :key="section.section_key"
              type="button"
              class="sheetAction ui-pill ui-pill--secondary"
              @click="openWidgetSheet(section)"
            >
              <span class="sheetActionIconWrap">
                <svg
                  class="sheetActionIcon"
                  :viewBox="resolveSidebarIcon(section.section_key).viewBox"
                  fill="none"
                  aria-hidden="true"
                >
                  <path
                    v-for="(path, index) in resolveSidebarIcon(section.section_key).paths"
                    :key="`${section.section_key}-${index}`"
                    :d="path"
                  />
                </svg>
              </span>
              <span class="sheetActionText">{{ section.title }}</span>
            </button>
          </template>
          <div v-else class="sheetEmpty">Ziadne widgety</div>
        </div>
      </section>
    </transition>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-8 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-8 opacity-0"
    >
      <section
        v-if="isWidgetSheetOpen"
        class="sheetDialog"
        :style="{ transform: `translateY(${widgetSheetOffsetY}px)` }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mobile-widget-title"
        @click.stop
      >
        <button
          type="button"
          class="sheetHandle"
          aria-label="Close widget"
          @touchstart="onSheetTouchStart($event, 'content')"
          @touchmove="onSheetTouchMove($event, 'content')"
          @touchend="onSheetTouchEnd('content')"
          @click="closeWidgetSheet"
        ></button>
        <div class="sheetHead">
          <h2 id="mobile-widget-title" class="sheetTitle">{{ activeWidgetTitle }}</h2>
          <button
            type="button"
            class="sheetClose ui-pill ui-pill--secondary ui-pill--icon"
            aria-label="Close widget"
            @click="closeWidgetSheet"
          >
            ×
          </button>
        </div>

        <div class="sheetBody">
          <template v-if="showAllWidgets">
            <div class="sheetWidgetList">
              <component
                :is="resolveSidebarComponent(section.section_key)"
                v-for="section in enabledMobileSections"
                :key="`sheet-${section.section_key}`"
                v-bind="propsForWidget(section.section_key, section.title)"
              />
            </div>
          </template>
          <template v-else-if="activeWidgetComponent">
            <component
              :is="activeWidgetComponent"
              v-bind="propsForWidget(activeWidgetKey, activeWidgetTitle)"
            />
          </template>
          <div v-else class="sheetEmpty">Widget nie je dostupny.</div>
        </div>
      </section>
    </transition>

    <MarkYourCalendarModal
      v-if="isCalendarPopupVisible && !isOnboardingFlowActive"
      :items="calendarPopupPayload?.items || []"
      :bundle-ics-url="calendarPopupPayload?.calendar?.bundle_ics_url || ''"
      @close="closeCalendarPopup"
      @go-calendar="goToCalendarFromPopup"
    />

    <OnboardingTour
      v-if="onboardingTour.isOpen && !isCalendarPopupVisible && !isOnboardingFlowActive"
    />
    <GuestBottomCTA />
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
import { useNotificationsStore } from '@/stores/notifications'
import MainNavbar from '@/components/MainNavbar.vue'
import DynamicSidebar from '@/components/DynamicSidebar.vue'
import RightObservingSidebar from '@/components/RightObservingSidebar.vue'
import PostComposer from '@/components/PostComposer.vue'
import MobileFab from '@/components/MobileFab.vue'
import MobileBottomNav from '@/components/nav/MobileBottomNav.vue'
import GuestBottomCTA from '@/components/GuestBottomCTA.vue'
import TypingText from '@/components/TypingText.vue'
import { SIDEBAR_SCOPE } from '@/generated/sidebarScopes'
import MarkYourCalendarModal from '@/components/MarkYourCalendarModal.vue'
import OnboardingTour from '@/components/onboarding/OnboardingTour.vue'
import { useToast } from '@/composables/useToast'
import { resolveSidebarScopeFromPath } from '@/utils/sidebarScope'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import { useOnboardingTourStore } from '@/stores/onboardingTour'
import { getMarkYourCalendarPopup, markYourCalendarPopupSeen } from '@/services/popup'
import {
  getEnabledSidebarSections,
  normalizeSidebarSections,
  resolveSidebarComponent,
  resolveSidebarIcon,
} from '@/sidebar/engine'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const preferences = useEventPreferencesStore()
const notifications = useNotificationsStore()
const sidebarConfigStore = useSidebarConfigStore()
const onboardingTour = useOnboardingTourStore()
const { showToast } = useToast()
const isDrawerOpen = ref(false)
const isComposerOpen = ref(false)
const isWidgetMenuOpen = ref(false)
const isWidgetSheetOpen = ref(false)
const showAllWidgets = ref(false)
const activeWidgetKey = ref('')
const activeWidgetTitle = ref('Widget')
const mobileSidebarSections = ref([])
const deferredInstallPrompt = ref(null)
const canInstall = ref(false)
const isMobileViewport = ref(false)
const widgetSheetOffsetY = ref(0)
const widgetMenuOffsetY = ref(0)
const touchStartY = ref(0)
const touchMode = ref('')
const showBrandGreeting = ref(false)
const brandGreetingText = ref('')
const lastWidgetStorageKey = 'mobile_sidebar_last_widget'
const lastWidgetKey = ref('')
let brandGreetingHideTimer = null
const calendarPopupSessionChecked = ref(false)
const isCalendarPopupVisible = ref(false)
const calendarPopupPayload = ref(null)
const calendarPopupAckInFlight = ref(false)
const fabBottomOffset = computed(() => (canInstall.value ? 82 : 16))
const showMobileBottomNav = computed(() => isMobileViewport.value && !isAdminRoute.value)
const appShellStyle = computed(() => ({
  '--mobile-bottom-nav-offset': showMobileBottomNav.value ? '88px' : '0px',
}))
const legalLinks = [
  { to: '/privacy', label: 'Privacy' },
  { to: '/terms', label: 'Terms' },
  { to: '/cookies', label: 'Cookies' },
]
const currentSidebarScope = computed(() => resolveSidebarScopeFromPath(route.path || ''))
const showRightSidebar = computed(() => Boolean(currentSidebarScope.value))
const showDirectObservingSidebar = computed(() => {
  return (
    currentSidebarScope.value === SIDEBAR_SCOPE.SKY ||
    currentSidebarScope.value === SIDEBAR_SCOPE.OBSERVING
  )
})
const isAdminRoute = computed(() => String(route.path || '').startsWith('/admin'))
const isProfileRoute = computed(() => String(route.path || '').startsWith('/profile'))
const isHomeFeedRoute = computed(() => route.name === 'home')
const showDesktopMainSidebar = computed(() => !isAdminRoute.value)
const isLayoutDebugEnabled = computed(() => {
  return import.meta.env.DEV && String(import.meta.env.VITE_DEBUG_LAYOUT || '') === 'true'
})
const desktopFrameClass = computed(() => {
  if (isAdminRoute.value) {
    return 'adminDesktopFrame mx-auto w-full max-w-[1500px]'
  }

  return 'desktopFrame mx-auto w-full max-w-[1500px] xl:grid'
})
const centerShellClass = computed(() => {
  if (isAdminRoute.value) {
    return 'adminCenterShell mx-auto w-full'
  }

  return 'centerShellGrid w-full xl:col-start-1 xl:grid xl:gap-1 2xl:gap-2'
})
const centerShellColumns = computed(() => {
  if (isAdminRoute.value) return null

  return '16rem minmax(600px, 640px)'
})
const centerShellStyle = computed(() => {
  if (isAdminRoute.value) {
    return null
  }

  return {
    '--center-shell-cols': centerShellColumns.value,
    outline: isLayoutDebugEnabled.value
      ? '1px solid rgb(var(--color-primary-rgb) / 0.4)'
      : undefined,
  }
})
const mainContentClass = computed(() => {
  if (isAdminRoute.value) {
    return 'adminMainContent mx-auto w-full'
  }

  if (isProfileRoute.value) {
    return 'mx-auto w-full max-w-[620px]'
  }

  return 'mx-auto w-full max-w-[640px]'
})
const enabledMobileSections = computed(() => getEnabledSidebarSections(mobileSidebarSections.value))
const activeWidgetComponent = computed(() => resolveSidebarComponent(activeWidgetKey.value))
const lastOpenedWidget = computed(() => {
  if (!lastWidgetKey.value) return null
  return (
    enabledMobileSections.value.find((section) => section.section_key === lastWidgetKey.value) ||
    null
  )
})
const observingLocationMeta = computed(() => {
  const value = auth.user?.location_meta
  if (!value || typeof value !== 'object') return null
  return value
})
const observingLocationData = computed(() => {
  const value = auth.user?.location_data
  if (!value || typeof value !== 'object') return null
  return value
})
const observingLat = computed(() => {
  const fromCanonical = parseNumericValue(observingLocationData.value?.latitude)
  if (fromCanonical !== null) return fromCanonical
  return parseNumericValue(observingLocationMeta.value?.lat)
})
const observingLon = computed(() => {
  const fromCanonical = parseNumericValue(observingLocationData.value?.longitude)
  if (fromCanonical !== null) return fromCanonical
  return parseNumericValue(observingLocationMeta.value?.lon)
})
const observingLocationName = computed(() => {
  const fromCanonical = parseStringValue(observingLocationData.value?.label)
  if (fromCanonical) return fromCanonical
  const fromMeta = parseStringValue(observingLocationMeta.value?.name)
  if (fromMeta) return fromMeta
  return parseStringValue(auth.user?.location)
})
const observingDate = computed(() => parseDateQuery(route.query.date) ?? localIsoDate(new Date()))
const observingTz = computed(() => {
  const canonicalTz = parseStringValue(observingLocationData.value?.timezone)
  if (canonicalTz) return canonicalTz
  const metaTz = parseStringValue(observingLocationMeta.value?.tz)
  if (metaTz) return metaTz
  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Bratislava'
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
const canCheckCalendarPopup = computed(() => {
  return (
    auth.bootstrapDone &&
    auth.isAuthed &&
    !auth.isAdmin &&
    Boolean(auth.user?.email_verified_at) &&
    !isOnboardingFlowActive.value &&
    !onboardingTour.isOpen &&
    preferences.isOnboardingCompleted &&
    !calendarPopupSessionChecked.value
  )
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

const parseStringValue = (value) => {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed !== '' ? trimmed : null
}

const parseNumericValue = (value) => {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value !== 'string') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const parseDateQuery = (value) => {
  const source = parseStringValue(Array.isArray(value) ? value[0] : value)
  if (!source) return null
  return /^\d{4}-\d{2}-\d{2}$/.test(source) ? source : null
}

const localIsoDate = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const openDrawer = () => {
  closeComposerModal()
  isDrawerOpen.value = true
}

const closeDrawer = () => {
  isDrawerOpen.value = false
}

const openComposerModal = () => {
  closeWidgetLayers()
  closeDrawer()
  isComposerOpen.value = true
}

const closeComposerModal = () => {
  isComposerOpen.value = false
}

const closeWidgetMenu = () => {
  isWidgetMenuOpen.value = false
  widgetMenuOffsetY.value = 0
}

const closeWidgetSheet = () => {
  isWidgetSheetOpen.value = false
  showAllWidgets.value = false
  activeWidgetKey.value = ''
  activeWidgetTitle.value = 'Widget'
  widgetSheetOffsetY.value = 0
}

const closeWidgetLayers = () => {
  closeWidgetMenu()
  closeWidgetSheet()
}

const openWidgetsMenu = async () => {
  if (!isMobileViewport.value) return
  closeComposerModal()
  closeDrawer()
  await warmSidebarConfig()
  isWidgetMenuOpen.value = true
}

const openWidgetSheet = (section) => {
  if (!section) return
  activeWidgetKey.value = section.section_key
  activeWidgetTitle.value = section.title || 'Widget'
  showAllWidgets.value = false
  closeWidgetMenu()
  isWidgetSheetOpen.value = true
  lastWidgetKey.value = section.section_key

  if (typeof window !== 'undefined') {
    window.localStorage.setItem(lastWidgetStorageKey, section.section_key)
  }
}

const openAllWidgetsSheet = () => {
  showAllWidgets.value = true
  activeWidgetKey.value = ''
  activeWidgetTitle.value = 'Vsetky widgety'
  closeWidgetMenu()
  isWidgetSheetOpen.value = true
}

const openComposerFromWidgets = () => {
  closeWidgetMenu()
  openComposerModal()
}

const propsForWidget = (sectionKey, title) => {
  if (sectionKey === 'observing_conditions') {
    return {
      lat: observingLat.value,
      lon: observingLon.value,
      date: observingDate.value,
      tz: observingTz.value,
      locationName: observingLocationName.value,
    }
  }

  if (
    sectionKey === 'nasa_apod' ||
    sectionKey === 'next_event' ||
    sectionKey === 'latest_articles'
  ) {
    return title ? { title } : {}
  }

  return {}
}

const emitPostCreated = (createdPost) => {
  if (typeof window === 'undefined' || !createdPost?.id) return
  window.dispatchEvent(new CustomEvent('post:created', { detail: createdPost }))
}

const onPostCreated = async (createdPost) => {
  closeComposerModal()
  showToast('Prispevok bol publikovany.', 'success')

  if (route.name === 'home') {
    emitPostCreated(createdPost)
    return
  }

  if (route.name !== 'home') {
    await router.push({ name: 'home' })
    window.setTimeout(() => emitPostCreated(createdPost), 60)
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
    closeComposerModal()
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

const maybeCheckCalendarPopup = async () => {
  if (!canCheckCalendarPopup.value) return

  calendarPopupSessionChecked.value = true
  try {
    const response = await getMarkYourCalendarPopup()
    const payload = response?.data || null

    if (payload?.should_show) {
      if (onboardingTour.isOpen) {
        onboardingTour.closeTour()
      }
      calendarPopupPayload.value = payload
      isCalendarPopupVisible.value = true
    }
  } catch {
    // Session check is best effort.
  }
}

const closeCalendarPopup = async () => {
  if (!isCalendarPopupVisible.value || calendarPopupAckInFlight.value) {
    return
  }

  calendarPopupAckInFlight.value = true
  try {
    const payload = calendarPopupPayload.value || {}
    await markYourCalendarPopupSeen({
      force_version: Number(payload.force_version || 0),
      month_key: payload.month_key || null,
    })
  } catch {
    // Do not block dismissal when acknowledge fails.
  } finally {
    isCalendarPopupVisible.value = false
    calendarPopupAckInFlight.value = false
  }
}

const goToCalendarFromPopup = async () => {
  await closeCalendarPopup()
  await router.push('/calendar')
}

const warmSidebarConfig = async () => {
  const scope = currentSidebarScope.value
  if (!scope) {
    mobileSidebarSections.value = []
    return
  }

  const items = await sidebarConfigStore.fetchScope(scope)
  mobileSidebarSections.value = normalizeSidebarSections(items)
}

const updateViewportState = () => {
  if (typeof window === 'undefined') {
    isMobileViewport.value = false
    return
  }

  isMobileViewport.value = window.matchMedia('(max-width: 767px)').matches
}

const onSheetTouchStart = (event, mode) => {
  const point = event?.touches?.[0]
  if (!point) return
  touchStartY.value = point.clientY
  touchMode.value = mode
}

const onSheetTouchMove = (event, mode) => {
  if (touchMode.value !== mode) return
  const point = event?.touches?.[0]
  if (!point) return

  const delta = Math.max(0, point.clientY - touchStartY.value)
  if (mode === 'content') {
    widgetSheetOffsetY.value = Math.min(180, delta)
  } else if (mode === 'menu') {
    widgetMenuOffsetY.value = Math.min(180, delta)
  }
}

const onSheetTouchEnd = (mode) => {
  if (touchMode.value !== mode) return

  if (mode === 'content') {
    if (widgetSheetOffsetY.value > 80) {
      closeWidgetSheet()
    }
    widgetSheetOffsetY.value = 0
  } else if (mode === 'menu') {
    if (widgetMenuOffsetY.value > 80) {
      closeWidgetMenu()
    }
    widgetMenuOffsetY.value = 0
  }

  touchMode.value = ''
}

const clearBrandGreetingTimer = () => {
  if (brandGreetingHideTimer !== null) {
    window.clearTimeout(brandGreetingHideTimer)
    brandGreetingHideTimer = null
  }
}

const hideBrandGreetingNow = () => {
  clearBrandGreetingTimer()
  showBrandGreeting.value = false
  brandGreetingText.value = ''
}

const onBrandGreetingDone = () => {
  clearBrandGreetingTimer()
  brandGreetingHideTimer = window.setTimeout(() => {
    showBrandGreeting.value = false
  }, 2500)
}

const userGreetingName = (user) => {
  const fromName = parseStringValue(user?.name)
  if (fromName) return fromName
  return parseStringValue(user?.username) || ''
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
      hideBrandGreetingNow()
      onboardingTour.closeTour()
      calendarPopupSessionChecked.value = false
      isCalendarPopupVisible.value = false
      calendarPopupPayload.value = null
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
  async (nextUserId) => {
    if (nextUserId) {
      await notifications.startRealtime()
      await notifications.fetchUnreadCount()
      return
    }

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
      !auth.isAdmin &&
      Boolean(auth.user?.email_verified_at) &&
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

watch(
  () => auth.loginSequence,
  (next, prev) => {
    if (!Number.isFinite(next) || next <= 0) return
    if (typeof prev === 'number' && next <= prev) return
    if (!auth.user) return

    const name = userGreetingName(auth.user)
    if (!name) {
      hideBrandGreetingNow()
      return
    }

    clearBrandGreetingTimer()
    brandGreetingText.value = `Ahoj ${name}! \u{1F44B}`
    showBrandGreeting.value = true
  },
  { immediate: true },
)

onMounted(() => {
  updateViewportState()
  if (typeof window !== 'undefined') {
    const persisted = window.localStorage.getItem(lastWidgetStorageKey)
    if (persisted) {
      lastWidgetKey.value = persisted
    }
  }

  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  window.addEventListener('appinstalled', handleInstalled)
  window.addEventListener('resize', updateViewportState)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  window.removeEventListener('appinstalled', handleInstalled)
  window.removeEventListener('resize', updateViewportState)
  notifications.stopRealtime({ disconnect: true })
  clearBrandGreetingTimer()
})
</script>

<style scoped>
.installBtn {
  position: fixed;
  right: 1rem;
  bottom: 1rem;
  z-index: 60;
}

.authFallbackBanner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  margin: 0.8rem auto 0;
  padding: 0.65rem 0.9rem;
  max-width: 1160px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--bg-surface);
  color: var(--text-primary);
  font-size: 0.82rem;
}

.authFallbackBanner.is-danger {
  border-color: var(--primary-active);
  background: rgb(var(--primary-active-rgb) / 0.14);
}

.brandLabel {
  display: inline-block;
  min-height: 1.2rem;
  white-space: nowrap;
}

.legalLinks {
  display: grid;
  gap: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid rgb(var(--border-rgb) / 0.8);
}

.legalLinks__item {
  font-size: 0.8rem;
  color: var(--text-secondary);
  text-decoration: none;
}

.legalLinks__item:hover,
.legalLinks__item.router-link-active {
  color: var(--text-primary);
}

.brand-fade-enter-active,
.brand-fade-leave-active {
  transition: opacity 0.18s ease;
}

.brand-fade-enter-from,
.brand-fade-leave-to {
  opacity: 0;
}

.composeOverlay {
  position: fixed;
  inset: 0;
  z-index: 70;
  background: rgb(var(--bg-app-rgb) / 0.66);
}

.composeDialog {
  position: fixed;
  right: 0.65rem;
  left: 0.65rem;
  bottom: max(0.65rem, env(safe-area-inset-bottom));
  z-index: 75;
  max-height: calc(100vh - 1.3rem - env(safe-area-inset-bottom));
  overflow-y: auto;
  border: 1px solid var(--border);
  border-radius: 1.1rem;
  background: var(--bg-surface-2);
  padding: 0.75rem;
  box-shadow: 0 24px 50px rgb(var(--bg-app-rgb) / 0.36);
}

.composeHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.55rem;
}

.composeTitle {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--text-primary);
}

.sheetOverlay {
  position: fixed;
  inset: 0;
  z-index: 78;
  background: rgb(var(--bg-app-rgb) / 0.72);
}

.sheetDialog {
  position: fixed;
  right: 0.65rem;
  left: 0.65rem;
  bottom: max(0.65rem, env(safe-area-inset-bottom));
  z-index: 79;
  max-height: 85vh;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 1.15rem;
  background: var(--bg-surface-2);
  box-shadow: 0 24px 50px rgb(var(--bg-app-rgb) / 0.36);
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr);
}

.sheetHandle {
  width: 3.5rem;
  height: 0.4rem;
  margin: 0.55rem auto 0.25rem;
  border: 0;
  border-radius: 999px;
  background: rgb(var(--text-secondary-rgb) / 0.4);
}

.sheetHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.2rem 0.75rem 0.55rem;
}

.sheetTitle {
  font-size: 0.92rem;
  font-weight: 800;
  color: var(--text-primary);
}

.sheetList,
.sheetBody {
  overflow-y: auto;
  padding: 0 0.75rem 0.75rem;
}

.sheetList {
  display: grid;
  gap: 0.5rem;
}

.sheetAction {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  width: 100%;
  justify-content: flex-start;
  text-align: left;
  white-space: normal;
}

.sheetActionIconWrap {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgb(var(--bg-app-rgb) / 0.22);
}

.sheetActionIcon {
  width: 1.12rem;
  height: 1.12rem;
  stroke: var(--primary);
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sheetActionText {
  font-size: 0.88rem;
  font-weight: 600;
}

.createAction {
  box-shadow: 0 12px 24px rgb(var(--primary-rgb) / 0.18);
}

.sheetWidgetList {
  display: grid;
  gap: 0.75rem;
}

.sheetEmpty {
  padding: 1.2rem 0.3rem;
  color: var(--text-secondary);
  text-align: center;
  font-size: 0.88rem;
}

.rightRail {
  scrollbar-width: none;
  -ms-overflow-style: none;
  overscroll-behavior-x: contain;
}

.rightRail::-webkit-scrollbar {
  width: 0;
  height: 0;
  display: none;
}

.rightRail > * {
  min-width: 0;
  max-width: 100%;
}

.rightRail :is(img, svg, video, canvas, iframe) {
  max-width: 100%;
}

@media (min-width: 768px) {
  .composeOverlay,
  .composeDialog,
  .sheetOverlay,
  .sheetDialog {
    display: none;
  }
}

@media (min-width: 901px) {
  .adminDesktopFrame {
    margin-left: auto !important;
    margin-right: auto !important;
  }

  .adminCenterShell,
  .adminMainContent {
    width: 100%;
    margin-inline: auto;
  }
}

@media (min-width: 1280px) {
  .desktopFrame {
    align-items: start;
    justify-content: center;
    column-gap: 0.75rem;
    grid-template-columns: auto auto;
    margin-left: auto !important;
    margin-right: auto !important;
    transform: translateX(-2rem);
  }

  .centerShellGrid {
    grid-template-columns: var(--center-shell-cols);
  }
}

@media (min-width: 1536px) {
  .desktopFrame {
    transform: translateX(-1rem);
  }
}
</style>
