<template>
  <div class="w-full">
    <!-- Moderné vyhľadávacie pole -->
    <div class="relative mb-6">
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
          <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Hľadať príspevky..."
          class="w-full pl-12 pr-12 py-4 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-900 dark:text-slate-100 placeholder-slate-500 dark:placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-lg"
          @input="onSearchInput"
        />
        <div class="absolute inset-y-0 right-0 pr-4 flex items-center">
          <div
            v-if="isLoading"
            class="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 dark:border-slate-600 border-t-blue-500"
          />
          <div v-else-if="searchQuery" class="flex items-center gap-1">
            <div class="h-2 w-2 bg-green-500 rounded-full animate-pulse"></div>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ totalPosts }} nájdených</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Výsledky vyhľadávania -->
    <div v-if="searchQuery.length >= 2" class="space-y-4">
      <!-- Loading state -->
      <div v-if="isLoading" class="text-center py-12">
        <div class="inline-flex items-center gap-3 text-slate-600 dark:text-slate-400">
          <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 dark:border-slate-600 border-t-blue-500" />
          <span class="text-base font-medium">Vyhľadávam príspevky...</span>
        </div>
      </div>

      <!-- Zoznam príspevkov -->
      <div v-else-if="posts.length > 0" class="space-y-4">
        <div class="flex items-center justify-between mb-4">
          <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">
            Nájdených {{ totalPosts }} príspevkov
          </div>
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Výsledky pre "{{ searchQuery }}"</span>
          </div>
        </div>
        
        <div class="space-y-4">
          <article
            v-for="post in posts"
            :key="post.id"
            class="group bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-lg transition-all duration-200 overflow-hidden"
          >
            <!-- Header s užívateľom -->
            <div class="p-6">
              <div class="flex items-start gap-4 mb-4">
                <div class="relative">
                  <img
                    :src="post.user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(post.user.name)}&background=random&size=48`"
                    :alt="post.user.name"
                    class="h-12 w-12 rounded-full object-cover ring-2 ring-slate-200 dark:ring-slate-600 group-hover:ring-blue-500 transition-all duration-200"
                  />
                  <div class="absolute -bottom-1 -right-1 h-4 w-4 bg-green-500 rounded-full border-2 border-white dark:border-slate-800"></div>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-semibold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                      {{ post.user.name }}
                    </span>
                    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <span class="font-medium">@{{ post.user.username }}</span>
                    <span class="text-slate-400">•</span>
                    <span>{{ formatDate(post.created_at) }}</span>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <button class="p-2 text-slate-400 hover:text-blue-500 transition-colors rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Obsah príspevku -->
              <div class="text-slate-900 dark:text-slate-100 whitespace-pre-wrap mb-4 leading-relaxed">
                {{ post.content }}
              </div>

              <!-- Tagy -->
              <div v-if="post.tags && post.tags.length > 0" class="flex flex-wrap gap-2 mb-4">
                <RouterLink
                  v-for="tag in post.tags"
                  :key="tag.id"
                  :to="`/tags/${tag.name}`"
                  class="inline-flex items-center rounded-full bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 px-3 py-1.5 text-xs font-semibold text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 hover:from-blue-100 hover:to-indigo-100 dark:hover:from-blue-900/30 dark:hover:to-indigo-900/30 transition-all duration-200"
                >
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                  </svg>
                  #{{ tag.name }}
                </RouterLink>
              </div>

              <!-- Footer s interakciami -->
              <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-6">
                  <button class="flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-red-500 transition-colors group">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    <span class="text-sm font-medium">{{ post.likes_count }}</span>
                  </button>
                  <button class="flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-blue-500 transition-colors group">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span class="text-sm font-medium">{{ post.replies_count }}</span>
                  </button>
                  <button class="flex items-center gap-2 text-slate-600 dark:text-slate-400 hover:text-green-500 transition-colors group">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z" />
                    </svg>
                    <span class="text-sm font-medium">Zdieľať</span>
                  </button>
                </div>
                <button class="p-2 text-slate-400 hover:text-blue-500 transition-colors rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                  </svg>
                </button>
              </div>
            </div>
          </article>
        </div>

        <!-- Pagination -->
        <div v-if="hasMorePages" class="flex justify-center mt-8">
          <button
            @click="loadMore"
            :disabled="isLoadingMore"
            class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
          >
            <div v-if="isLoadingMore" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
            <span v-else>Načítať viac príspevkov</span>
          </button>
        </div>
      </div>

      <!-- Žiadne výsledky -->
      <div v-else-if="!isLoading && searchQuery.length >= 2" class="text-center py-16">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-slate-100 dark:bg-slate-700 rounded-2xl mb-6">
          <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
          </svg>
        </div>
        <div class="text-slate-600 dark:text-slate-400 mb-3">
          <div class="text-xl font-semibold mb-2">
            Neboli nájdené žiadne príspevky
          </div>
          <div class="text-base">
            pre výraz <strong class="text-slate-900 dark:text-slate-100">"{{ searchQuery }}"</strong>
          </div>
        </div>
        <div class="text-sm text-slate-500 dark:text-slate-500 mb-6">
          Skúste iný vyhľadávací výraz alebo skontrolujte preklepy
        </div>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <button
            @click="searchQuery = ''"
            class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors"
          >
            Vymazať vyhľadávanie
          </button>
          <button
            @click="$router.push('/')"
            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
          >
            Späť na domovskú stránku
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'

const props = defineProps({
  initialQuery: {
    type: String,
    default: ''
  }
})

const searchQuery = ref('')
const posts = ref([])
const isLoading = ref(false)
const isLoadingMore = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const totalPosts = ref(0)

// Watch pre zmeny v initialQuery
watch(() => props.initialQuery, (newQuery) => {
  if (newQuery !== searchQuery.value) {
    console.log('📥 SearchPosts received initialQuery:', newQuery)
    searchQuery.value = newQuery
  }
}, { immediate: true })

// Vlastná debounce implementácia
let timeoutId = null
const searchPosts = (query, page = 1) => {
  console.log('🔍 SearchPosts called with query:', query, 'page:', page)
  clearTimeout(timeoutId)
  timeoutId = setTimeout(async () => {
    if (query.length < 2) {
      console.log('📝 Query too short, clearing results')
      posts.value = []
      totalPosts.value = 0
      return
    }

    try {
      if (page === 1) {
        isLoading.value = true
      } else {
        isLoadingMore.value = true
      }

      const url = `http://127.0.0.1:8000/api/search/posts?q=${encodeURIComponent(query)}&limit=10&page=${page}`
      console.log('🌐 Making request to:', url)
      const response = await axios.get(url)
      console.log('📥 Response received:', response.data)
      console.log('📊 Posts before update:', posts.value)
      
      // Rozdelíme response.data na posts a total
      const postsData = response.data.data || []
      const total = response.data.total || postsData.length || 0
      
      console.log('📊 Extracted postsData:', postsData)
      console.log('📊 Extracted total:', total)

      if (page === 1) {
        posts.value = postsData
        console.log('📊 Posts after update (page 1):', posts.value, 'length:', posts.value.length)
      } else {
        posts.value = [...posts.value, ...postsData]
        console.log('📊 Posts after update (page', page, '):', posts.value, 'length:', posts.value.length)
      }
      
      totalPosts.value = total
      lastPage.value = response.data?.last_page || 1
      currentPage.value = response.data?.current_page || 1
      console.log('📊 Final posts count:', posts.value.length, 'totalPosts:', totalPosts.value)
    } catch (error) {
      console.error('❌ Chyba pri vyhľadávaní príspevkov:', error)
      if (page === 1) {
        posts.value = []
      }
    } finally {
      isLoading.value = false
      isLoadingMore.value = false
    }
  }, 300)
}

// Cleanup
onUnmounted(() => {
  if (timeoutId) {
    clearTimeout(timeoutId)
  }
})

// Watch pre zmeny v searchQuery
watch(searchQuery, (newQuery) => {
  console.log('🔄 SearchPosts searchQuery changed to:', newQuery)
  currentPage.value = 1
  searchPosts(newQuery)
}, { immediate: true })

const onSearchInput = (event) => {
  searchQuery.value = event.target.value
}

const loadMore = () => {
  if (currentPage.value < lastPage.value) {
    searchPosts(searchQuery.value, currentPage.value + 1)
  }
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffHours < 1) {
    return 'Práve teraz'
  } else if (diffHours < 24) {
    return `Pred ${diffHours} hod`
  } else if (diffDays < 7) {
    return `Pred ${diffDays} dňami`
  } else {
    return date.toLocaleDateString('sk-SK')
  }
}

const hasMorePages = computed(() => currentPage.value < lastPage.value)
</script>
