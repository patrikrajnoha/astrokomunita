<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
    <div class="max-w-6xl mx-auto px-4 py-8">
      <!-- Header s gradient pozadím -->
      <header class="mb-10 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl mb-4 shadow-lg">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <h1 class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent mb-3">
          Vyhľadávanie a objavovanie
        </h1>
        <p class="text-slate-600 dark:text-slate-400 text-lg max-w-2xl mx-auto">
          Preskúmajte komunitu, nájdite zaujímavých ľudí a objavujte skvelé príspevky
        </p>
      </header>

      <!-- Moderné tab tlačidlá -->
      <div class="mb-8">
        <div class="inline-flex p-1 bg-white dark:bg-slate-800 rounded-xl shadow-lg ring-1 ring-slate-200 dark:ring-slate-700">
          <button
            @click="activeTab = 'users'"
            :class="[
              'relative px-6 py-3 text-sm font-semibold rounded-lg transition-all duration-200',
              activeTab === 'users'
                ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
            ]"
          >
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
              Používatelia
            </span>
          </button>
          <button
            @click="activeTab = 'posts'"
            :class="[
              'relative px-6 py-3 text-sm font-semibold rounded-lg transition-all duration-200',
              activeTab === 'posts'
                ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
            ]"
          >
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
              </svg>
              Príspevky
            </span>
          </button>
        </div>
      </div>

      <!-- Hlavný obsah so sidebarom -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Hlavný obsah -->
        <div class="lg:col-span-2">
          <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 p-6">
            <SearchUsers v-if="activeTab === 'users'" :initial-query="searchQuery" />
            <SearchPosts v-else-if="activeTab === 'posts'" :initial-query="searchQuery" />
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 p-6">
            <TrendingSidebar />
          </div>
          <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 p-6">
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

// Načítaj query parameter z URL
const loadSearchQuery = () => {
  const query = route.query.q
  console.log('📍 Loading search query from URL:', query)
  if (query && typeof query === 'string') {
    searchQuery.value = query
    console.log('✅ Search query set to:', searchQuery.value)
    // Vždy prepni na posts tab ak máme query
    console.log('🔄 Auto-switching to posts tab from loadSearchQuery')
    activeTab.value = 'posts'
  }
}

// Watch pre zmeny query parameteru
watch(() => route.query.q, (newQuery) => {
  console.log('🔄 Route query changed to:', newQuery, 'active tab:', activeTab.value)
  if (newQuery && typeof newQuery === 'string') {
    searchQuery.value = newQuery
    console.log('✅ Search query updated to:', searchQuery.value)
    
    // Vždy prepni na posts tab ak máme query
    console.log('🔄 Auto-switching to posts tab for search results')
    activeTab.value = 'posts'
  }
})

onMounted(() => {
  loadSearchQuery()
})
</script>
