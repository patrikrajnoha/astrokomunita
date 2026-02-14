<template>
  <section class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.2)] bg-[color:rgb(var(--color-bg-rgb)/0.66)] p-4 shadow-[0_14px_34px_rgb(0_0_0/0.22)] backdrop-blur sm:p-5">
    <div v-if="!trimmedQuery" class="space-y-6">
      <section>
        <div class="mb-2 flex items-center justify-between">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">Nedavne hladania</h2>
          <button
            v-if="recentSearches.length"
            type="button"
            class="text-xs text-[var(--color-primary)] transition hover:opacity-80"
            @click="clearRecent"
          >
            Vymazat
          </button>
        </div>

        <div v-if="recentSearches.length" class="flex flex-wrap gap-2">
          <button
            v-for="item in recentSearches"
            :key="item"
            type="button"
            class="rounded-full border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.86)] px-3 py-1.5 text-xs text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
            @click="applyRecent(item)"
          >
            {{ item }}
          </button>
        </div>

        <p v-else class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.86)]">Zatial nemas ziadne nedavne hladania.</p>
      </section>

      <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">
          {{ mode === 'users' ? 'Odporucane ucty' : 'Odporucane prispevky' }}
        </h2>

        <div v-if="recommendedLoading" class="space-y-2">
          <div
            v-for="index in 5"
            :key="`rec-skeleton-${index}`"
            class="h-14 animate-pulse rounded-xl bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)]"
          ></div>
        </div>

        <div v-else-if="mode === 'users' && recommendedUsers.length" class="space-y-2">
          <RouterLink
            v-for="user in recommendedUsers.slice(0, 5)"
            :key="`recommended-user-${user.id}`"
            :to="{ name: 'user-profile', params: { username: user.username } }"
            class="flex items-center gap-3 rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] bg-[color:rgb(var(--color-bg-rgb)/0.84)] px-3 py-2.5 transition hover:border-[color:rgb(var(--color-primary-rgb)/0.7)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.92)]"
          >
            <img
              :src="avatarUrl(user)"
              :alt="user.name || user.username"
              class="h-10 w-10 rounded-full object-cover"
            />
            <div class="min-w-0">
              <div class="truncate text-sm font-semibold text-[var(--color-surface)]">{{ user.name || user.username }}</div>
              <div class="truncate text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">@{{ user.username }}</div>
            </div>
          </RouterLink>
        </div>

        <div v-else-if="mode === 'posts' && recommendedPosts.length" class="space-y-2">
          <RouterLink
            v-for="post in recommendedPosts.slice(0, 5)"
            :key="`recommended-post-${post.id}`"
            :to="{ name: 'post-detail', params: { id: post.id } }"
            class="block rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] bg-[color:rgb(var(--color-bg-rgb)/0.84)] px-3 py-3 transition hover:border-[color:rgb(var(--color-primary-rgb)/0.7)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.92)]"
          >
            <p class="line-clamp-2 text-sm text-[var(--color-surface)]">{{ postSnippet(post.content) }}</p>
            <p class="mt-1 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
              {{ post.user?.name || 'Neznamy autor' }} • {{ post.likes_count || 0 }} likes
            </p>
          </RouterLink>
        </div>

        <p v-else class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.86)]">Odporucania sa zatial nepodarilo nacitat.</p>
      </section>
    </div>

    <div v-else class="space-y-4">
      <div v-if="isLoading" class="space-y-2">
        <div
          v-for="index in 8"
          :key="`skeleton-${index}`"
          class="h-16 animate-pulse rounded-xl bg-[color:rgb(var(--color-text-secondary-rgb)/0.2)]"
        ></div>
      </div>

      <div v-else-if="results.length" class="space-y-2">
        <header class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-[var(--color-surface)]">Vysledky pre "{{ trimmedQuery }}"</h2>
          <span class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ results.length }} poloziek</span>
        </header>

        <div v-if="mode === 'users'" class="space-y-2">
          <RouterLink
            v-for="user in results"
            :key="`result-user-${user.id}`"
            :to="{ name: 'user-profile', params: { username: user.username } }"
            class="flex items-center gap-3 rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.24)] bg-[color:rgb(var(--color-bg-rgb)/0.84)] px-3 py-3 transition hover:border-[color:rgb(var(--color-primary-rgb)/0.72)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.92)]"
          >
            <img :src="avatarUrl(user)" :alt="user.name || user.username" class="h-10 w-10 rounded-full object-cover" />
            <div class="min-w-0">
              <div class="truncate text-sm font-semibold text-[var(--color-surface)]">{{ user.name || user.username }}</div>
              <div class="truncate text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">@{{ user.username }}</div>
            </div>
          </RouterLink>
        </div>

        <div v-else class="space-y-2">
          <RouterLink
            v-for="post in results"
            :key="`result-post-${post.id}`"
            :to="{ name: 'post-detail', params: { id: post.id } }"
            class="block rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.24)] bg-[color:rgb(var(--color-bg-rgb)/0.84)] px-3 py-3 transition hover:border-[color:rgb(var(--color-primary-rgb)/0.72)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.92)]"
          >
            <div class="mb-1 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
              {{ post.user?.name || 'Neznamy autor' }} • {{ formatDate(post.created_at) }}
            </div>
            <p class="line-clamp-3 text-sm text-[var(--color-surface)]">{{ postSnippet(post.content) }}</p>
          </RouterLink>
        </div>

        <div v-if="canLoadMore" class="pt-2">
          <button
            type="button"
            class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.84)] px-3 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
            @click="loadMore"
          >
            Nacitat viac
          </button>
        </div>
      </div>

      <div v-else class="rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.24)] bg-[color:rgb(var(--color-bg-rgb)/0.84)] p-5 text-center">
        <h3 class="mb-1 text-base font-semibold text-[var(--color-surface)]">Nic sme nenasli</h3>
        <p class="text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.88)]">
          Skus iny vyraz, pridaj viac slov alebo prehod tab medzi Pouzivatelia a Prispevky.
        </p>
      </div>

      <p v-if="errorMessage" class="text-xs text-red-300">{{ errorMessage }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/services/api'

const RECENT_STORAGE_KEY = 'search_recent_queries'

const props = defineProps({
  mode: {
    type: String,
    default: 'users',
  },
  query: {
    type: String,
    default: '',
  },
  recommendedUsers: {
    type: Array,
    default: () => [],
  },
  recommendedPosts: {
    type: Array,
    default: () => [],
  },
  recommendedLoading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:query'])

const results = ref([])
const isLoading = ref(false)
const errorMessage = ref('')
const recentSearches = ref([])
const displayLimit = ref(10)
const lastBatchLength = ref(0)

const trimmedQuery = computed(() => String(props.query || '').trim())
const canLoadMore = computed(() => (
  Boolean(trimmedQuery.value) &&
  !isLoading.value &&
  displayLimit.value < 50 &&
  lastBatchLength.value >= displayLimit.value
))

let debounceTimer = null
let requestController = null

const readRecent = () => {
  if (typeof window === 'undefined') return []

  try {
    const raw = window.localStorage.getItem(RECENT_STORAGE_KEY)
    const parsed = JSON.parse(raw || '[]')
    if (!Array.isArray(parsed)) return []
    return parsed
      .map((entry) => String(entry || '').trim())
      .filter(Boolean)
      .slice(0, 8)
  } catch {
    return []
  }
}

const writeRecent = (entries) => {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(RECENT_STORAGE_KEY, JSON.stringify(entries.slice(0, 8)))
}

const addRecent = (entry) => {
  const normalized = String(entry || '').trim()
  if (normalized.length < 2) return

  const next = [normalized, ...recentSearches.value.filter((item) => item !== normalized)].slice(0, 8)
  recentSearches.value = next
  writeRecent(next)
}

const clearRecent = () => {
  recentSearches.value = []
  writeRecent([])
}

const applyRecent = (entry) => {
  emit('update:query', entry)
}

const avatarUrl = (user) => {
  if (user?.avatar_url) return user.avatar_url
  if (user?.name) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=0f172a&color=fff&size=80`
  }
  return ''
}

const postSnippet = (content) => {
  const text = String(content || '').replace(/\s+/g, ' ').trim()
  if (!text) return '(Bez textu)'
  return text.length > 160 ? `${text.slice(0, 160)}...` : text
}

const formatDate = (value) => {
  if (!value) return 'Neznamy cas'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Neznamy cas'
  return date.toLocaleString('sk-SK', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const cancelInFlight = () => {
  requestController?.abort()
  requestController = null
}

const fetchResults = async () => {
  if (!trimmedQuery.value) {
    cancelInFlight()
    isLoading.value = false
    results.value = []
    errorMessage.value = ''
    lastBatchLength.value = 0
    return
  }

  cancelInFlight()
  requestController = new AbortController()
  isLoading.value = true
  errorMessage.value = ''

  try {
    const endpoint = props.mode === 'posts' ? '/search/posts' : '/search/users'
    const response = await api.get(endpoint, {
      params: {
        q: trimmedQuery.value,
        limit: displayLimit.value,
      },
      signal: requestController.signal,
      meta: { skipErrorToast: true },
    })

    const items = Array.isArray(response.data?.data) ? response.data.data : []
    results.value = items
    lastBatchLength.value = items.length
    addRecent(trimmedQuery.value)
  } catch (error) {
    if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return
    results.value = []
    lastBatchLength.value = 0
    errorMessage.value = 'Nepodarilo sa nacitat vysledky. Skus to prosim znova.'
  } finally {
    isLoading.value = false
  }
}

const scheduleFetch = () => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchResults, 300)
}

const loadMore = () => {
  displayLimit.value = Math.min(displayLimit.value + 10, 50)
  fetchResults()
}

watch(
  () => [props.mode, trimmedQuery.value],
  () => {
    displayLimit.value = 10
    scheduleFetch()
  },
  { immediate: true },
)

onMounted(() => {
  recentSearches.value = readRecent()
})

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
  cancelInFlight()
})
</script>
