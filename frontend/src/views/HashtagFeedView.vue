<template>
  <div class="max-w-2xl mx-auto">
    <header class="mb-6">
      <h1 class="text-2xl font-bold text-[var(--color-surface)] mb-2">
        #{{ hashtagName }}
      </h1>
      <p class="text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">
        Príspevky s hashtagom #{{ hashtagName }}
      </p>
    </header>

    <!-- Loading state -->
    <div v-if="isLoading" class="text-center py-8">
      <div class="inline-flex items-center gap-2 text-[color:rgb(var(--color-text-secondary-rgb)/0.7)]">
        <div class="h-5 w-5 animate-spin rounded-full border-2 border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] border-t-[var(--color-primary)]" />
        Načítavanie príspevkov...
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="text-center py-8">
      <div class="text-red-500 mb-4">{{ error }}</div>
      <button 
        @click="loadPosts(1, { force: true })"
        class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] px-4 py-2 text-[var(--color-surface)] transition-colors hover:bg-[color:rgb(var(--color-bg-rgb)/0.8)]"
      >
        Skúsiť znova
      </button>
    </div>

    <!-- Posts -->
    <div v-else-if="posts.length > 0" class="space-y-4">
      <div class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.7)] mb-2">
        {{ totalPosts }} príspevkov
      </div>

      <article
        v-for="post in posts"
        :key="post.id"
        class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] p-4 transition-all hover:bg-[color:rgb(var(--color-bg-rgb)/0.8)]"
      >
        <!-- Header s užívateľom -->
        <div class="flex items-start gap-3 mb-3">
          <UserAvatar class="h-10 w-10 rounded-full object-cover" :user="post.user" :size="40" :alt="post.user.name || 'avatar'" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="font-medium text-[var(--color-surface)]">
                {{ post.user.name }}
              </span>
              <span class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">
                @{{ post.user.username }}
              </span>
            </div>
            <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.6)]">
              {{ formatDate(post.created_at) }}
            </div>
          </div>
        </div>

        <!-- Obsah príspevku s klikateľnými hashtagmi -->
        <div class="text-[var(--color-surface)] whitespace-pre-wrap mb-3">
          <HashtagText :content="post.content" />
        </div>

        <!-- Tagy -->
        <div v-if="post.hashtags && post.hashtags.length > 0" class="flex flex-wrap gap-2 mb-3">
          <RouterLink
            v-for="hashtag in post.hashtags"
            :key="hashtag.id"
            :to="`/hashtags/${hashtag.name}`"
            class="inline-flex items-center rounded-full bg-[color:rgb(var(--color-primary-rgb)/0.1)] px-2.5 py-1 text-xs font-medium text-[var(--color-primary)] transition-colors hover:bg-[color:rgb(var(--color-primary-rgb)/0.2)]"
          >
            #{{ hashtag.name }}
          </RouterLink>
        </div>

        <!-- Footer s interakciami -->
        <div class="flex items-center gap-4 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.8)]">
          <span class="flex items-center gap-1">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
            {{ post.likes_count }}
          </span>
          <span class="flex items-center gap-1">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            {{ post.replies_count }}
          </span>
        </div>
      </article>

      <!-- Pagination -->
      <div v-if="hasMorePages" class="flex justify-center mt-6">
        <button
          @click="loadMore"
          :disabled="isLoadingMore"
          class="rounded-lg border border-[color:rgb(var(--color-text-secondary-rgb)/0.3)] bg-[color:rgb(var(--color-bg-rgb)/0.6)] px-4 py-2 text-[var(--color-surface)] transition-colors hover:bg-[color:rgb(var(--color-bg-rgb)/0.8)] disabled:opacity-50"
        >
          <span v-if="isLoadingMore">Načítavanie...</span>
          <span v-else>Načítať viac</span>
        </button>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-8 text-[color:rgb(var(--color-text-secondary-rgb)/0.7)]">
      <div class="mb-4">
        <svg class="h-12 w-12 mx-auto text-[color:rgb(var(--color-text-secondary-rgb)/0.5)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        </svg>
      </div>
      <p>Ešte neboli vytvorené žiadne príspevky s hashtagom #{{ hashtagName }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { RouterLink } from 'vue-router'
import api from '@/services/api'
import UserAvatar from '@/components/UserAvatar.vue'
import HashtagText from '@/components/HashtagText.vue'

const route = useRoute()
const hashtagName = computed(() => {
  const raw = route.params.name
  const value = Array.isArray(raw) ? raw[0] : raw
  return String(value || '').trim()
})

const posts = ref([])
const isLoading = ref(false)
const isLoadingMore = ref(false)
const error = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalPosts = ref(0)
const requestSequence = ref(0)

const resetFeedState = () => {
  posts.value = []
  currentPage.value = 1
  lastPage.value = 1
  totalPosts.value = 0
  error.value = ''
  isLoading.value = false
  isLoadingMore.value = false
}

const loadPosts = async (page = 1, { force = false } = {}) => {
  if ((isLoading.value || isLoadingMore.value) && !force) {
    return
  }

  const hashtag = hashtagName.value
  if (!hashtag) {
    resetFeedState()
    error.value = 'Hashtag nie je zadany'
    return
  }

  const requestId = ++requestSequence.value

  try {
    if (page === 1) {
      isLoading.value = true
      error.value = ''
    } else {
      isLoadingMore.value = true
    }

    const response = await api.get(`/hashtags/${encodeURIComponent(hashtag)}/posts`, {
      params: {
        limit: 10,
        page,
      },
    })

    if (requestId !== requestSequence.value) {
      return
    }

    const payload = response?.data || {}
    const rows = Array.isArray(payload.data) ? payload.data : []

    if (page === 1) {
      posts.value = rows
    } else {
      posts.value = [...posts.value, ...rows]
    }

    totalPosts.value = Number(payload.total || 0)
    lastPage.value = Number(payload.last_page || 1)
    currentPage.value = Number(payload.current_page || page)
  } catch {
    if (requestId !== requestSequence.value) {
      return
    }

    error.value = 'Nepodarilo sa nacitat prispevky'
    if (page === 1) {
      posts.value = []
    }
  } finally {
    if (requestId === requestSequence.value) {
      isLoading.value = false
      isLoadingMore.value = false
    }
  }
}

const loadMore = () => {
  if (currentPage.value < lastPage.value) {
    void loadPosts(currentPage.value + 1)
  }
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffHours < 1) {
    return 'Prave teraz'
  } else if (diffHours < 24) {
    return `Pred ${diffHours} hod`
  } else if (diffDays < 7) {
    return `Pred ${diffDays} dnami`
  } else {
    return date.toLocaleDateString('sk-SK')
  }
}

const hasMorePages = computed(() => currentPage.value < lastPage.value)

watch(hashtagName, () => {
  requestSequence.value += 1
  resetFeedState()
  void loadPosts(1, { force: true })
}, { immediate: true })
</script>
