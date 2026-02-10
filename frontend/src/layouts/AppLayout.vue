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
          <div class="grid gap-4">
            <SearchBar />
            <RightObservingSidebar
              :lat="observingLat"
              :lon="observingLon"
              :date="observingDate"
              :tz="observingTz"
            />
            <DynamicSidebar />
          </div>
        </aside>
      </div>
    </div>

    <MobileFab
      v-if="auth.isAuthed && !isDrawerOpen && !isComposerOpen"
      :is-authenticated="auth.isAuthed"
      :bottom-offset="fabBottomOffset"
      @click="openComposerModal"
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

    <AppToast />
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import MainNavbar from '@/components/MainNavbar.vue'
import SearchBar from '@/components/SearchBar.vue'
import DynamicSidebar from '@/components/DynamicSidebar.vue'
import RightObservingSidebar from '@/components/RightObservingSidebar.vue'
import PostComposer from '@/components/PostComposer.vue'
import MobileFab from '@/components/MobileFab.vue'
import AppToast from '@/components/shared/AppToast.vue'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { showToast } = useToast()
const isDrawerOpen = ref(false)
const isComposerOpen = ref(false)
const deferredInstallPrompt = ref(null)
const canInstall = ref(false)
const fabBottomOffset = computed(() => (canInstall.value ? 82 : 16))
const showRightSidebar = computed(() => ['home', 'post-detail'].includes(String(route.name || '')))
const isAdminRoute = computed(() => String(route.path || '').startsWith('/admin'))
const observingLocationMeta = computed(() => {
  const value = auth.user?.location_meta
  if (!value || typeof value !== 'object') return null
  return value
})
const observingLat = computed(() => parseNumericValue(observingLocationMeta.value?.lat))
const observingLon = computed(() => parseNumericValue(observingLocationMeta.value?.lon))
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
  closeDrawer()
  isComposerOpen.value = true
}

const closeComposerModal = () => {
  isComposerOpen.value = false
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

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  window.addEventListener('appinstalled', handleInstalled)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  window.removeEventListener('appinstalled', handleInstalled)
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

@media (min-width: 768px) {
  .composeOverlay,
  .composeDialog {
    display: none;
  }
}
</style>
