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
      <main class="px-4 py-6 md:px-8">
        <RouterView />
      </main>
    </div>

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
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import MainNavbar from '@/components/MainNavbar.vue'

const isDrawerOpen = ref(false)

const openDrawer = () => {
  isDrawerOpen.value = true
}

const closeDrawer = () => {
  isDrawerOpen.value = false
}

const handleKeydown = (event) => {
  if (event.key === 'Escape' && isDrawerOpen.value) {
    closeDrawer()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>
