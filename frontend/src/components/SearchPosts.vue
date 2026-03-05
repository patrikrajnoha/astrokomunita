<template>
  <div class="w-full space-y-4">
    <input
      v-model="searchQuery"
      type="search"
      placeholder="Hladat prispevky"
      class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900"
      @input="onSearchInput"
    >

    <div v-if="searchQuery.length < 2" class="text-sm text-slate-500">
      Zadaj aspon 2 znaky.
    </div>

    <div v-else-if="isLoading" class="text-sm text-slate-500">Nacitavam...</div>

    <div v-else-if="posts.length === 0" class="text-sm text-slate-500">
      Nenasli sa ziadne prispevky.
    </div>

    <ul v-else class="space-y-3">
      <li
        v-for="post in posts"
        :key="post.id"
        class="rounded-xl border border-slate-200 bg-white p-4"
      >
        <div class="text-xs text-slate-500">
          @{{ post?.user?.username || 'user' }} · {{ formatDate(post.created_at) }}
        </div>
        <p class="mt-2 whitespace-pre-wrap text-sm text-slate-900">{{ post.content }}</p>
        <RouterLink
          class="mt-2 inline-block text-sm text-blue-600 hover:underline"
          :to="`/posts/${post.id}`"
        >
          Otvorit
        </RouterLink>
      </li>
    </ul>

    <div v-if="hasMorePages" class="pt-2">
      <button
        type="button"
        class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
        :disabled="isLoadingMore"
        @click="loadMore"
      >
        {{ isLoadingMore ? 'Nacitavam...' : 'Nacitat viac' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/services/api'

const props = defineProps({
  initialQuery: {
    type: String,
    default: '',
  },
})

const searchQuery = ref('')
const posts = ref([])
const isLoading = ref(false)
const isLoadingMore = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

watch(
  () => props.initialQuery,
  (newQuery) => {
    if (newQuery !== searchQuery.value) {
      searchQuery.value = newQuery
    }
  },
  { immediate: true }
)

let timeoutId = null

const searchPosts = (query, page = 1) => {
  if (timeoutId) clearTimeout(timeoutId)

  timeoutId = setTimeout(async () => {
    if ((query || '').length < 2) {
      posts.value = []
      currentPage.value = 1
      lastPage.value = 1
      return
    }

    try {
      if (page === 1) isLoading.value = true
      else isLoadingMore.value = true

      const response = await api.get('/search/posts', {
        params: { q: query, limit: 10, page },
      })

      const pageItems = Array.isArray(response?.data?.data) ? response.data.data : []
      const resolvedPage = Number(response?.data?.current_page || page)
      const resolvedLastPage = Number(response?.data?.last_page || resolvedPage)

      if (page === 1) posts.value = pageItems
      else posts.value = [...posts.value, ...pageItems]

      currentPage.value = resolvedPage
      lastPage.value = resolvedLastPage
    } catch (error) {
      console.error('Search posts failed:', error)
      if (page === 1) posts.value = []
    } finally {
      isLoading.value = false
      isLoadingMore.value = false
    }
  }, 250)
}

watch(
  searchQuery,
  (newQuery) => {
    currentPage.value = 1
    searchPosts(newQuery, 1)
  },
  { immediate: true }
)

onUnmounted(() => {
  if (timeoutId) clearTimeout(timeoutId)
})

const onSearchInput = (event) => {
  searchQuery.value = event.target.value
}

const hasMorePages = computed(() => currentPage.value < lastPage.value)

const loadMore = () => {
  if (hasMorePages.value) {
    searchPosts(searchQuery.value, currentPage.value + 1)
  }
}

const formatDate = (value) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'
  return date.toLocaleString('sk-SK', { dateStyle: 'short', timeStyle: 'short' })
}
</script>
