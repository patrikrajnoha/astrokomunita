<template>
  <div
    v-if="isVisible"
    ref="bannerRef"
    class="fixed bottom-0 inset-x-0 z-50 bg-gradient-to-r from-sky-600 to-sky-500 text-white shadow-[0_-6px_18px_rgba(0,0,0,0.18)]"
  >
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-3 sm:px-6 md:flex-row md:items-center md:justify-between md:gap-4">
      <div class="min-w-0 flex-1 text-center">
        <p class="text-[1.15rem] font-bold leading-tight sm:text-[2rem] md:text-[2.05rem]">Nenechajte si ujsť aktuálne vesmírne udalosti</p>
        <p class="mt-1 text-sm text-white/95 sm:text-base">Ľudia na Astrokomunite sa ich dozvedia ako prví.</p>
      </div>

      <div class="flex w-full flex-col gap-2 sm:flex-row sm:justify-center md:w-auto md:justify-end md:gap-3">
        <RouterLink
          to="/login"
          class="w-full rounded-full border border-white/90 px-6 py-2.5 text-center text-base font-semibold !text-white no-underline opacity-100 transition hover:bg-white/10 hover:!text-white focus:!text-white visited:!text-white sm:w-auto"
        >
          Prihlásiť sa
        </RouterLink>
        <RouterLink
          to="/register"
          class="w-full rounded-full bg-white px-6 py-2.5 text-center text-base font-semibold !text-slate-800 no-underline transition hover:bg-white/90 hover:!text-slate-800 focus:!text-slate-800 visited:!text-slate-800 sm:w-auto"
        >
          Zaregistrovať sa
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const bannerRef = ref(null)
let resizeObserver = null

const currentUser = computed(() => {
  if (authStore?.user) return authStore.user
  if (typeof window !== 'undefined' && window.AUTH?.user) return window.AUTH.user
  return null
})

const isGuest = computed(() => !currentUser.value)
const isVisible = computed(() => isGuest.value)

const setHeightVar = (height) => {
  if (typeof document === 'undefined') return
  document.documentElement.style.setProperty('--guest-cta-h', `${Math.max(0, Math.round(height || 0))}px`)
}

const measureHeight = () => {
  if (!isVisible.value || !bannerRef.value) {
    setHeightVar(0)
    return
  }
  setHeightVar(bannerRef.value.offsetHeight || 0)
}

onMounted(async () => {
  await nextTick()
  measureHeight()
  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => {
      measureHeight()
    })
    if (bannerRef.value) {
      resizeObserver.observe(bannerRef.value)
    }
  }
})

watch(isVisible, async (visible) => {
  if (!visible) {
    setHeightVar(0)
    if (resizeObserver && bannerRef.value) {
      resizeObserver.unobserve(bannerRef.value)
    }
    return
  }
  await nextTick()
  measureHeight()
  if (resizeObserver && bannerRef.value) {
    resizeObserver.observe(bannerRef.value)
  }
})

onBeforeUnmount(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }
  setHeightVar(0)
})
</script>
