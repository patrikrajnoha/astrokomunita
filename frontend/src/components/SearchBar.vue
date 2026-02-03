<template>
  <div class="relative">
    <input
      v-model="searchQuery"
      type="text"
      placeholder="Hľadať..."
      class="w-full rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] px-3 py-2 pr-8 text-sm text-[var(--color-surface)] placeholder-[color:rgb(var(--color-text-secondary-rgb)/0.7)] focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[color:rgb(var(--color-primary-rgb)/0.2)]"
      @input="onSearchInput"
      @keydown.enter.prevent="goToSearch"
    />
    <button
      @click="goToSearch"
      class="absolute right-2 top-1/2 -translate-y-1/2 text-[color:rgb(var(--color-text-secondary-rgb)/0.7)] hover:text-[var(--color-primary)] transition-colors"
      aria-label="Hľadať"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const searchQuery = ref('')

const onSearchInput = (event) => {
  searchQuery.value = event.target.value
}

const goToSearch = () => {
  const query = searchQuery.value.trim()
  if (query) {
    console.log('Navigating to search with query:', query)
    router.push({
      name: 'search',
      query: { q: query }
    }).catch(err => {
      console.error('Navigation error:', err)
    })
  }
}
</script>
