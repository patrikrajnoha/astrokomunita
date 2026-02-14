<template>
  <div class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-4">
    <header class="mb-4">
      <h3 class="font-semibold text-[var(--color-surface)] flex items-center gap-2">
        <svg class="h-5 w-5 text-[var(--color-primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        Trending
      </h3>
      <p class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.7)] mt-1">
        Najpopulárnejšie za 24h
      </p>
    </header>

    <!-- Loading state -->
    <div v-if="isLoading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="animate-pulse">
        <div class="h-4 bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)] rounded w-3/4 mb-1"></div>
        <div class="h-3 bg-[color:rgb(var(--color-text-secondary-rgb)/0.1)] rounded w-1/2"></div>
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="text-center py-4">
      <div class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.7)] mb-2">
        Nepodarilo sa načítať trending
      </div>
      <button 
        @click="loadTrending"
        class="text-xs text-[var(--color-primary)] hover:underline"
      >
        Skúsiť znova
      </button>
    </div>

    <!-- Trending hashtags -->
    <div v-else-if="trending.length > 0" class="space-y-3">
      <RouterLink
        v-for="(item, index) in trending"
        :key="item.name"
        :to="`/hashtags/${item.name}`"
        class="block group transition-colors hover:bg-[color:rgb(var(--color-bg-rgb)/0.8)] -mx-2 px-2 py-2 rounded"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-sm font-medium text-[color:rgb(var(--color-text-secondary-rgb)/0.6)]">
                {{ index + 1 }}
              </span>
              <span class="font-medium text-[var(--color-surface)] group-hover:text-[var(--color-primary)] transition-colors">
                #{{ item.name }}
              </span>
            </div>
            <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.7)]">
              {{ item.posts_count }} {{ item.posts_count === 1 ? 'príspevok' : item.posts_count >= 2 && item.posts_count <= 4 ? 'príspevky' : 'príspevkov' }}
            </div>
          </div>
          <div class="flex-shrink-0">
            <svg class="h-4 w-4 text-[color:rgb(var(--color-text-secondary-rgb)/0.4)] group-hover:text-[var(--color-primary)] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </div>
        </div>
      </RouterLink>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-4">
      <svg class="h-8 w-8 mx-auto text-[color:rgb(var(--color-text-secondary-rgb)/0.4)] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
      </svg>
      <p class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.7)]">
        Žiadne trending hashtagy
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import http from '@/services/api'

const trending = ref([])
const isLoading = ref(false)
const error = ref('')

const loadTrending = async () => {
  try {
    isLoading.value = true
    error.value = ''
    
    const response = await http.get('/trending?limit=10')
    trending.value = response.data || []
  } catch (err) {
    console.error('Chyba pri načítaní trending hashtagov:', err)
    error.value = 'Nepodarilo sa načítať trending'
    trending.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadTrending()
})
</script>
