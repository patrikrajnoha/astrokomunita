<template>
  <div class="min-h-screen bg-[#000000] text-[var(--color-surface)]">
    <div class="mx-auto w-full max-w-[1200px] px-4 py-4 sm:px-6 lg:px-8">
      <header class="mb-4 rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[linear-gradient(120deg,rgba(29,155,240,0.14),rgba(8,10,12,0.88))] px-4 py-4 shadow-[0_12px_32px_rgb(0_0_0/0.24)] backdrop-blur sm:px-6">
        <div class="flex items-center gap-3">
          <div class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-[color:rgb(var(--color-primary-rgb)/0.2)] text-[var(--color-primary)] ring-1 ring-[color:rgb(var(--color-primary-rgb)/0.35)]">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m1-4a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <div>
            <h1 class="text-lg font-semibold tracking-tight sm:text-xl">Vyhladavanie a objavovanie</h1>
            <p class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)] sm:text-sm">
              Najdi ludi, prispevky a trendy temy bez prazdneho miesta.
            </p>
          </div>
        </div>
      </header>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <section class="min-w-0 space-y-4">
          <SearchPanel
            v-model="query"
            :mode="mode"
            @update:mode="setMode"
            @submit="runSearch"
          />

          <SearchResults
            :mode="mode"
            :query="query"
            :recommended-users="recommendedUsers"
            :recommended-posts="recommendedPosts"
            :recommended-loading="isRecommendationsLoading"
            @update:query="runSearch"
          />
        </section>

        <aside class="self-start lg:sticky lg:top-20">
          <DiscoverySidebar
            :trending="trending"
            :recommended-users="recommendedUsers"
            :popular-posts="recommendedPosts"
            :loading-trending="isTrendingLoading"
            :loading-users="isRecommendationsLoading"
            :loading-posts="isPopularPostsLoading"
            @refresh="loadDiscovery"
          />
        </aside>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import DiscoverySidebar from '@/components/search/DiscoverySidebar.vue'
import SearchPanel from '@/components/search/SearchPanel.vue'
import SearchResults from '@/components/search/SearchResults.vue'

const route = useRoute()
const router = useRouter()

const mode = ref('users')
const query = ref('')

const trending = ref([])
const recommendedUsers = ref([])
const recommendedPosts = ref([])
const isTrendingLoading = ref(false)
const isRecommendationsLoading = ref(false)
const isPopularPostsLoading = ref(false)

let syncFromRoute = false
let discoveryAbortController = null

const parseRouteState = () => {
  const routeQuery = typeof route.query.q === 'string' ? route.query.q : ''
  const routeType = route.query.type === 'posts' ? 'posts' : route.query.type === 'users' ? 'users' : null

  syncFromRoute = true
  query.value = routeQuery
  mode.value = routeType || (routeQuery ? 'posts' : 'users')
  syncFromRoute = false
}

const syncRouteState = () => {
  if (syncFromRoute) return

  const trimmedQuery = query.value.trim()
  const nextQuery = {}

  if (trimmedQuery) {
    nextQuery.q = trimmedQuery
    nextQuery.type = mode.value
  } else if (mode.value !== 'users') {
    nextQuery.type = mode.value
  }

  const currentQ = typeof route.query.q === 'string' ? route.query.q : ''
  const currentType = typeof route.query.type === 'string' ? route.query.type : ''
  const nextQ = nextQuery.q || ''
  const nextType = nextQuery.type || ''

  if (currentQ === nextQ && currentType === nextType) return

  router.replace({
    name: 'search',
    query: nextQuery,
  }).catch(() => {})
}

const setMode = (nextMode) => {
  mode.value = nextMode === 'posts' ? 'posts' : 'users'
}

const runSearch = (nextQuery) => {
  query.value = String(nextQuery || '').trimStart()
}

const loadDiscovery = async () => {
  discoveryAbortController?.abort()
  discoveryAbortController = new AbortController()

  isTrendingLoading.value = true
  isRecommendationsLoading.value = true
  isPopularPostsLoading.value = true

  const signal = discoveryAbortController.signal

  const trendingPromise = api
    .get('/trending', {
      params: { limit: 8 },
      signal,
      meta: { skipErrorToast: true },
    })
    .then((response) => {
      trending.value = Array.isArray(response.data) ? response.data : []
    })
    .catch((error) => {
      if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return
      trending.value = []
    })
    .finally(() => {
      isTrendingLoading.value = false
    })

  const usersPromise = api
    .get('/recommendations/users', {
      params: { limit: 5 },
      signal,
      meta: { skipErrorToast: true },
    })
    .then((response) => {
      recommendedUsers.value = Array.isArray(response.data) ? response.data : []
    })
    .catch((error) => {
      if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return
      recommendedUsers.value = []
    })
    .finally(() => {
      isRecommendationsLoading.value = false
    })

  const postsPromise = api
    .get('/recommendations/posts', {
      params: { limit: 5 },
      signal,
      meta: { skipErrorToast: true },
    })
    .then((response) => {
      recommendedPosts.value = Array.isArray(response.data) ? response.data : []
    })
    .catch((error) => {
      if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return
      recommendedPosts.value = []
    })
    .finally(() => {
      isPopularPostsLoading.value = false
    })

  await Promise.allSettled([trendingPromise, usersPromise, postsPromise])
}

watch(
  () => [route.query.q, route.query.type],
  () => {
    parseRouteState()
  },
  { immediate: true },
)

watch([mode, query], () => {
  syncRouteState()
})

onMounted(() => {
  loadDiscovery()
})

onBeforeUnmount(() => {
  discoveryAbortController?.abort()
})
</script>
