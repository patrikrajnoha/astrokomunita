<template>
  <div
    v-if="isVisible"
    ref="bannerRef"
    class="guestCta fixed bottom-0 inset-x-0 z-50"
  >
    <div class="guestCta__shell mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-3 sm:px-6 md:flex-row md:items-center md:justify-between md:gap-4">
      <div class="min-w-0 flex-1 text-center">
        <p class="guestCta__title text-[1.15rem] font-bold leading-tight sm:text-[2rem] md:text-[2.05rem]">
          Nenechajte si ujst aktualne vesmirne udalosti
        </p>
        <p class="guestCta__text mt-1 text-sm sm:text-base">
          Ludia na Astrokomunite sa ich dozvedia ako prvi.
        </p>
      </div>

      <div class="flex w-full flex-col gap-2 sm:flex-row sm:justify-center md:w-auto md:justify-end md:gap-3">
        <RouterLink to="/login" class="ui-pill ui-pill--secondary w-full sm:w-auto">
          Prihlasit sa
        </RouterLink>
        <RouterLink to="/register" class="ui-pill ui-pill--primary w-full sm:w-auto">
          Zaregistrovat sa
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

<style scoped>
.guestCta {
  background: transparent;
  padding: 0 0 0.65rem;
}

.guestCta__shell {
  border: 1px solid var(--border);
  border-radius: 1.4rem;
  background: var(--bg-surface);
  box-shadow: 0 -6px 18px rgb(var(--bg-app-rgb) / 0.18);
}

.guestCta__title {
  color: var(--text-primary);
}

.guestCta__text {
  color: var(--text-secondary);
}
</style>
