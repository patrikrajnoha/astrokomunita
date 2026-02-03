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
          placeholder="Hľadať používateľov..."
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
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ users.length }} nájdených</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Výsledky vyhľadávania -->
    <div
      v-if="searchQuery.length >= 2 && (isLoading || users.length > 0)"
      class="relative"
    >
      <div class="absolute z-50 w-full mt-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-2xl backdrop-blur-sm max-h-96 overflow-hidden">
        <!-- Loading state -->
        <div v-if="isLoading" class="p-6 text-center">
          <div class="inline-flex items-center gap-3 text-slate-600 dark:text-slate-400">
            <div class="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 dark:border-slate-600 border-t-blue-500" />
            <span class="text-sm font-medium">Vyhľadávam používateľov...</span>
          </div>
        </div>

        <!-- Zoznam používateľov -->
        <div v-else-if="users.length > 0" class="overflow-y-auto max-h-80">
          <div class="sticky top-0 bg-slate-50 dark:bg-slate-700 px-4 py-3 border-b border-slate-200 dark:border-slate-600">
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                Nájdených {{ users.length }} používateľov
              </span>
              <button
                @click="clearSearch"
                class="text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
              >
                Zrušiť
              </button>
            </div>
          </div>
          <div class="divide-y divide-slate-100 dark:divide-slate-700">
            <RouterLink
              v-for="user in users"
              :key="user.id"
              :to="`/users/${user.username}`"
              class="flex items-center gap-4 px-4 py-4 transition-all duration-200 hover:bg-slate-50 dark:hover:bg-slate-700 focus:bg-slate-50 dark:focus:bg-slate-700 focus:outline-none group"
              @click="clearSearch"
            >
              <div class="relative">
                <img
                  :src="user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random&size=40`"
                  :alt="user.name"
                  class="h-12 w-12 rounded-full object-cover ring-2 ring-slate-200 dark:ring-slate-600 group-hover:ring-blue-500 transition-all duration-200"
                />
                <div class="absolute -bottom-1 -right-1 h-4 w-4 bg-green-500 rounded-full border-2 border-white dark:border-slate-800"></div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-semibold text-slate-900 dark:text-slate-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                    {{ user.name }}
                  </span>
                  <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                  <span class="font-medium">@{{ user.username }}</span>
                  <span class="text-slate-400">•</span>
                  <span>Člen komunity</span>
                </div>
              </div>
              <div class="flex items-center">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </div>
            </RouterLink>
          </div>
        </div>
      </div>
    </div>

    <!-- Žiadne výsledky -->
    <div v-else-if="searchQuery.length >= 2 && !isLoading && users.length === 0" class="text-center py-12">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 dark:bg-slate-700 rounded-2xl mb-4">
        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      </div>
      <div class="text-slate-600 dark:text-slate-400 mb-2">
        <div class="text-lg font-medium mb-1">
          Neboli nájdení žiadni používatelia
        </div>
        <div class="text-sm">
          pre výraz <strong class="text-slate-900 dark:text-slate-100">"{{ searchQuery }}"</strong>
        </div>
      </div>
      <div class="text-xs text-slate-500 dark:text-slate-500">
        Skúste iný vyhľadávací výraz alebo skontrolujte preklepy
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'

const props = defineProps({
  initialQuery: {
    type: String,
    default: ''
  }
})

const searchQuery = ref('')
const users = ref([])
const isLoading = ref(false)

// Watch pre zmeny v initialQuery
watch(() => props.initialQuery, (newQuery) => {
  if (newQuery !== searchQuery.value) {
    console.log('📥 SearchUsers received initialQuery:', newQuery)
    searchQuery.value = newQuery
  }
}, { immediate: true })

// Vlastná debounce implementácia
let timeoutId = null
const searchUsers = (query) => {
  console.log('🔍 SearchUsers called with query:', query)
  clearTimeout(timeoutId)
  timeoutId = setTimeout(async () => {
    if (query.length < 2) {
      console.log('📝 Query too short, clearing results')
      users.value = []
      return
    }

    try {
      isLoading.value = true
      console.log('🌐 Making request to:', `http://127.0.0.1:8000/api/search/users?q=${encodeURIComponent(query)}&limit=10`)
      const response = await axios.get(`http://127.0.0.1:8000/api/search/users?q=${encodeURIComponent(query)}&limit=10`)
      console.log('📥 Response received:', response.data)
      users.value = response.data || []
    } catch (error) {
      console.error('❌ Chyba pri vyhľadávaní používateľov:', error)
      users.value = []
    } finally {
      isLoading.value = false
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
  console.log('🔄 SearchUsers searchQuery changed to:', newQuery)
  searchUsers(newQuery)
}, { immediate: true })

const onSearchInput = (event) => {
  searchQuery.value = event.target.value
}

const clearSearch = () => {
  searchQuery.value = ''
  users.value = []
}
</script>
