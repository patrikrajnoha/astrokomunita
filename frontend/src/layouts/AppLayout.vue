<template>
  <div class="min-h-screen bg-[var(--color-bg)] text-[var(--color-surface)]">
    <header
      class="sticky top-0 z-40 flex items-center justify-between border-b border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.85)] px-4 py-3 backdrop-blur md:hidden"
    >
      <button
        type="button"
        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[var(--color-primary)] transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.7)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
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
        <span>Astrokomunita</span>
      </RouterLink>

      <span class="h-10 w-10" aria-hidden="true"></span>
    </header>

    <aside
      class="fixed inset-y-0 left-0 hidden w-64 flex-col border-r border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.95)] px-4 py-6 md:flex"
    >
      <MainNavbar />
    </aside>

    <div class="md:pl-64">
      <div
        :class="[
          'mx-auto w-full',
          isAdminRoute
            ? 'max-w-[1560px]'
            : 'xl:grid xl:max-w-[1160px] xl:grid-cols-[minmax(0,760px)_22rem] xl:gap-6',
        ]"
      >
        <main :class="['px-4 py-6 md:px-8', isAdminRoute ? 'xl:px-6' : 'xl:px-0']">
          <div :class="['mx-auto w-full', isAdminRoute ? 'max-w-none' : 'max-w-[760px]']">
            <RouterView />
          </div>
        </main>

        <aside
          v-if="showRightSidebar"
          class="hidden border-l border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.95)] px-5 py-6 xl:block"
          aria-label="Right sidebar"
        >
          <DynamicSidebar
            :observing-lat="observingLat"
            :observing-lon="observingLon"
            :observing-date="observingDate"
            :observing-tz="observingTz"
            :observing-location-name="observingLocationName"
          />
        </aside>
      </div>
    </div>

    <MobileFab
      v-if="auth.isAuthed && !isDrawerOpen && !isComposerOpen && !isWidgetMenuOpen && !isWidgetSheetOpen"
      :is-authenticated="auth.isAuthed"
      :bottom-offset="fabBottomOffset"
      @widgets="openWidgetsMenu"
    />

    <button
      v-if="canInstall"
      type="button"
      class="installBtn"
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
        class="fixed inset-0 z-40 bg-black/60 md:hidden"
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
        class="fixed inset-y-0 left-0 z-50 w-72 overflow-y-auto border-r border-[color:rgb(var(--color-text-secondary-rgb)/0.5)] bg-[color:rgb(var(--color-bg-rgb)/0.95)] px-4 py-6 md:hidden"
        aria-label="Mobile navigation"
      >
        <div class="flex items-center justify-between">
          <RouterLink
            to="/"
            class="inline-flex items-center gap-2 text-base font-semibold text-[var(--color-surface)]"
            aria-label="Home"
            @click="closeDrawer"
          >
            <span
              class="grid h-9 w-9 place-items-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-gradient-to-br from-[color:rgb(var(--color-primary-rgb)/0.3)] via-[color:rgb(var(--color-bg-rgb)/0.4)] to-[color:rgb(var(--color-primary-rgb)/0.2)] text-base shadow-lg"
              aria-hidden="true"
            >
              🌌
            </span>
            <span>Astrokomunita</span>
          </RouterLink>

          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[var(--color-primary)] transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.7)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
            aria-label="Close navigation"
            @click="closeDrawer"
          >
            ✖
          </button>
        </div>

        <div class="mt-6">
          <MainNavbar />
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
            class="composeClose"
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
          <button type="button" class="sheetClose" aria-label="Close widgets menu" @click="closeWidgetMenu">
            ×
          </button>
        </div>

        <div class="sheetList">
          <button type="button" class="sheetAction createAction" @click="openComposerFromWidgets">
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
            class="sheetAction"
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
            class="sheetAction"
            @click="openWidgetSheet(lastOpenedWidget)"
          >
            <span class="sheetActionIconWrap">
              <svg class="sheetActionIcon" :viewBox="resolveSidebarIcon(lastOpenedWidget.section_key).viewBox" fill="none" aria-hidden="true">
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
              class="sheetAction"
              @click="openWidgetSheet(section)"
            >
              <span class="sheetActionIconWrap">
                <svg class="sheetActionIcon" :viewBox="resolveSidebarIcon(section.section_key).viewBox" fill="none" aria-hidden="true">
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
          <button type="button" class="sheetClose" aria-label="Close widget" @click="closeWidgetSheet">×</button>
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
            <component :is="activeWidgetComponent" v-bind="propsForWidget(activeWidgetKey, activeWidgetTitle)" />
          </template>
          <div v-else class="sheetEmpty">Widget nie je dostupny.</div>
        </div>
      </section>
    </transition>

  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import MainNavbar from '@/components/MainNavbar.vue'
import DynamicSidebar from '@/components/DynamicSidebar.vue'
import PostComposer from '@/components/PostComposer.vue'
import MobileFab from '@/components/MobileFab.vue'
import { useToast } from '@/composables/useToast'
import { resolveSidebarScopeFromPath } from '@/utils/sidebarScope'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import {
  getEnabledSidebarSections,
  normalizeSidebarSections,
  resolveSidebarComponent,
  resolveSidebarIcon,
} from '@/sidebar/engine'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const sidebarConfigStore = useSidebarConfigStore()
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
const lastWidgetStorageKey = 'mobile_sidebar_last_widget'
const lastWidgetKey = ref('')
const fabBottomOffset = computed(() => (canInstall.value ? 82 : 16))
const currentSidebarScope = computed(() => resolveSidebarScopeFromPath(route.path || ''))
const showRightSidebar = computed(() => Boolean(currentSidebarScope.value))
const isAdminRoute = computed(() => String(route.path || '').startsWith('/admin'))
const enabledMobileSections = computed(() => getEnabledSidebarSections(mobileSidebarSections.value))
const activeWidgetComponent = computed(() => resolveSidebarComponent(activeWidgetKey.value))
const lastOpenedWidget = computed(() => {
  if (!lastWidgetKey.value) return null
  return enabledMobileSections.value.find((section) => section.section_key === lastWidgetKey.value) || null
})
const observingLocationMeta = computed(() => {
  const value = auth.user?.location_meta
  if (!value || typeof value !== 'object') return null
  return value
})
const observingLat = computed(() => parseNumericValue(observingLocationMeta.value?.lat))
const observingLon = computed(() => parseNumericValue(observingLocationMeta.value?.lon))
const observingLocationName = computed(() => {
  const fromMeta = parseStringValue(observingLocationMeta.value?.name)
  if (fromMeta) return fromMeta
  return parseStringValue(auth.user?.location)
})
const observingDate = computed(() => parseDateQuery(route.query.date) ?? localIsoDate(new Date()))
const observingTz = computed(() => {
  const metaTz = parseStringValue(observingLocationMeta.value?.tz)
  if (metaTz) return metaTz
  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Bratislava'
})

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

  if (sectionKey === 'nasa_apod' || sectionKey === 'next_event' || sectionKey === 'latest_articles') {
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
})
</script>

<style scoped>
.installBtn {
  position: fixed;
  right: 1rem;
  bottom: 1rem;
  z-index: 60;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.6);
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.9);
  color: var(--color-surface);
  padding: 0.55rem 0.9rem;
  font-size: 0.8rem;
  font-weight: 600;
}

.installBtn:hover {
  border-color: var(--color-primary);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.composeOverlay {
  position: fixed;
  inset: 0;
  z-index: 70;
  background: rgb(0 0 0 / 0.56);
}

.composeDialog {
  position: fixed;
  right: 0.65rem;
  left: 0.65rem;
  bottom: max(0.65rem, env(safe-area-inset-bottom));
  z-index: 75;
  max-height: calc(100vh - 1.3rem - env(safe-area-inset-bottom));
  overflow-y: auto;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  border-radius: 1.1rem;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 0.75rem;
  box-shadow: 0 24px 50px rgb(0 0 0 / 0.42);
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
  color: var(--color-surface);
}

.composeClose {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
  background: rgb(var(--color-bg-rgb) / 0.72);
  color: var(--color-surface);
  font-size: 1.25rem;
  line-height: 1;
}

.composeClose:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.sheetOverlay {
  position: fixed;
  inset: 0;
  z-index: 78;
  background: rgb(0 0 0 / 0.62);
}

.sheetDialog {
  position: fixed;
  right: 0.65rem;
  left: 0.65rem;
  bottom: max(0.65rem, env(safe-area-inset-bottom));
  z-index: 79;
  max-height: 85vh;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.55);
  border-radius: 1.15rem;
  background: rgb(var(--color-bg-rgb) / 0.96);
  box-shadow: 0 24px 50px rgb(0 0 0 / 0.42);
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr);
}

.sheetHandle {
  width: 3.5rem;
  height: 0.4rem;
  margin: 0.55rem auto 0.25rem;
  border: 0;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.4);
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
  color: var(--color-surface);
}

.sheetClose {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.6);
  background: rgb(var(--color-bg-rgb) / 0.72);
  color: var(--color-surface);
  font-size: 1.25rem;
  line-height: 1;
}

.sheetClose:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
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
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
  border-radius: 0.85rem;
  background: rgb(var(--color-bg-rgb) / 0.5);
  color: var(--color-surface);
  padding: 0.72rem 0.75rem;
  text-align: left;
}

.sheetActionIconWrap {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 0.5rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.36);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgb(var(--color-bg-rgb) / 0.34);
}

.sheetActionIcon {
  width: 1.12rem;
  height: 1.12rem;
  stroke: rgb(var(--color-primary-rgb) / 0.92);
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sheetActionText {
  font-size: 0.88rem;
  font-weight: 600;
}

.createAction {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.15);
}

.sheetWidgetList {
  display: grid;
  gap: 0.75rem;
}

.sheetEmpty {
  padding: 1.2rem 0.3rem;
  color: var(--color-text-secondary);
  text-align: center;
  font-size: 0.88rem;
}

@media (min-width: 768px) {
  .composeOverlay,
  .composeDialog,
  .sheetOverlay,
  .sheetDialog {
    display: none;
  }
}
</style>
