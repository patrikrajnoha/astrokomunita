<template>
  <div class="min-h-screen bg-[#000000] text-[#e7e9ea]">
    <div class="mx-auto max-w-5xl px-3 py-4 sm:px-4 sm:py-6">
      <header class="mb-5 text-center sm:mb-6">
        <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-r from-[#1d9bf0] to-[#1a8cd8] shadow-md sm:h-14 sm:w-14">
          <svg class="h-6 w-6 text-white sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <h1 class="mb-2 bg-gradient-to-r from-[#e7e9ea] to-[#8b98a5] bg-clip-text text-2xl font-bold text-transparent sm:text-3xl">
          Vyhladavanie a objavovanie
        </h1>

        <p class="mx-auto max-w-xl text-sm leading-relaxed text-[#8b98a5] sm:text-base">
          Preskumaj komunitu, najdi zaujimavych ludi a objavuj skvele prispevky.
        </p>
      </header>

      <div class="mb-4 flex justify-center sm:mb-5">
        <div class="inline-flex max-w-full rounded-xl bg-[#16181c] p-1 shadow-sm ring-1 ring-[#2f3336]">
          <button
            @click="activeTab = 'users'"
            :class="[
              'relative whitespace-nowrap rounded-lg px-4 py-2 text-xs font-semibold transition-all duration-200 sm:px-5 sm:text-sm',
              activeTab === 'users'
                ? 'bg-gradient-to-r from-[#1d9bf0] to-[#1a8cd8] text-white shadow-sm'
                : 'text-[#8b98a5] hover:text-[#e7e9ea]'
            ]"
          >
            <span class="flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
              Pouzivatelia
            </span>
          </button>

          <button
            @click="activeTab = 'posts'"
            :class="[
              'relative whitespace-nowrap rounded-lg px-4 py-2 text-xs font-semibold transition-all duration-200 sm:px-5 sm:text-sm',
              activeTab === 'posts'
                ? 'bg-gradient-to-r from-[#1d9bf0] to-[#1a8cd8] text-white shadow-sm'
                : 'text-[#8b98a5] hover:text-[#e7e9ea]'
            ]"
          >
            <span class="flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
              </svg>
              Prispevky
            </span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:gap-5 lg:grid-cols-12">
        <div class="lg:col-span-8">
          <div class="rounded-xl bg-[#16181c] p-4 shadow-md ring-1 ring-[#2f3336] sm:rounded-2xl sm:p-5">
            <SearchUsers v-if="activeTab === 'users'" :initial-query="searchQuery" />
            <SearchPosts v-else-if="activeTab === 'posts'" :initial-query="searchQuery" />
          </div>
        </div>

        <div class="space-y-4 self-start lg:col-span-4 lg:sticky lg:top-4 sm:space-y-5">
          <div class="rounded-xl bg-[#16181c] p-4 shadow-md ring-1 ring-[#2f3336] sm:rounded-2xl sm:p-5">
            <TrendingSidebar />
          </div>

          <div class="rounded-xl bg-[#16181c] p-4 shadow-md ring-1 ring-[#2f3336] sm:rounded-2xl sm:p-5">
            <RecommendationsWidget />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import SearchUsers from '@/components/SearchUsers.vue'
import SearchPosts from '@/components/SearchPosts.vue'
import TrendingSidebar from '@/components/TrendingSidebar.vue'
import RecommendationsWidget from '@/components/RecommendationsWidget.vue'

const route = useRoute()
const activeTab = ref('users')
const searchQuery = ref('')

const loadSearchQuery = () => {
  const query = route.query.q
  if (query && typeof query === 'string') {
    searchQuery.value = query
    activeTab.value = 'posts'
  }
}

watch(
  () => route.query.q,
  (newQuery) => {
    if (newQuery && typeof newQuery === 'string') {
      searchQuery.value = newQuery
      activeTab.value = 'posts'
    }
  }
)

onMounted(() => {
  loadSearchQuery()
})
</script>
