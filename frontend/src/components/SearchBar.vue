<template>
  <form class="relative" role="search" aria-label="Vyhladavanie" @submit.prevent="goToSearch">
    <label for="sidebar-search" class="sr-only">Vyhladat prispevky a pouzivatelov</label>
    <input
      id="sidebar-search"
      v-model="searchQuery"
      type="text"
      placeholder="Hladat..."
      autocomplete="off"
      aria-label="Hladat"
      class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] px-3 py-2.5 pr-10 text-sm text-[var(--color-surface)] placeholder-[color:rgb(var(--color-text-secondary-rgb)/0.7)] transition-colors focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[color:rgb(var(--color-primary-rgb)/0.24)]"
    />
    <button
      type="submit"
      class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-[color:rgb(var(--color-text-secondary-rgb)/0.75)] transition-colors hover:text-[var(--color-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-primary)]"
      aria-label="Spustit vyhladavanie"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
    </button>
  </form>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute()
const searchQuery = ref('')

watch(
  () => route.query.q,
  (q) => {
    searchQuery.value = typeof q === 'string' ? q : ''
  },
  { immediate: true },
)

const goToSearch = () => {
  const query = searchQuery.value.trim()
  const currentQ = typeof route.query.q === 'string' ? route.query.q : ''

  if (route.name === 'search' && currentQ === query) return

  router.push({
    name: 'search',
    query: query ? { q: query } : {},
  }).catch(() => {})
}
</script>