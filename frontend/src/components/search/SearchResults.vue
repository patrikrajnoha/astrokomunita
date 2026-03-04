<template>
  <section class="rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.66)] p-4 backdrop-blur sm:p-5">
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
            Vymazať históriu
          </button>
        </div>

        <div v-if="recentSearches.length" class="flex flex-wrap gap-2">
          <button
            v-for="item in recentSearches"
            :key="item"
            type="button"
            class="rounded-full border border-white/5 bg-[color:rgb(var(--color-bg-rgb)/0.86)] px-3 py-1.5 text-xs text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
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

        <div v-else-if="mode === 'users' && recommendedUsers.length" class="ui-list-divider">
          <RouterLink
            v-for="user in recommendedUsers.slice(0, 5)"
            :key="`recommended-user-${user.id}`"
            :to="{ name: 'user-profile', params: { username: user.username } }"
            class="flex items-center gap-3 px-0 py-3 transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.28)]"
          >
            <UserAvatar class="h-10 w-10 rounded-full object-cover" :user="user" :size="40" :alt="user.name || user.username" />
            <div class="min-w-0">
              <div class="truncate text-sm font-semibold text-[var(--color-surface)]">{{ user.name || user.username }}</div>
              <div class="truncate text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">@{{ user.username }}</div>
            </div>
          </RouterLink>
        </div>

        <div v-else-if="mode === 'posts' && recommendedPosts.length" class="ui-list-divider">
          <RouterLink
            v-for="post in recommendedPosts.slice(0, 5)"
            :key="`recommended-post-${post.id}`"
            :to="{ name: 'post-detail', params: { id: post.id } }"
            class="block px-0 py-3 transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.28)]"
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

        <div v-if="mode === 'users'" class="ui-list-divider">
          <RouterLink
            v-for="user in results"
            :key="`result-user-${user.id}`"
            :to="{ name: 'user-profile', params: { username: user.username } }"
            class="flex items-center gap-3 px-0 py-3 transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.28)]"
          >
            <UserAvatar class="h-10 w-10 rounded-full object-cover" :user="user" :size="40" :alt="user.name || user.username" />
            <div class="min-w-0">
              <div class="truncate text-sm font-semibold text-[var(--color-surface)]">{{ user.name || user.username }}</div>
              <div class="truncate text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">@{{ user.username }}</div>
            </div>
          </RouterLink>
        </div>

        <div v-else class="ui-list-divider">
          <RouterLink
            v-for="post in results"
            :key="`result-post-${post.id}`"
            :to="{ name: 'post-detail', params: { id: post.id } }"
            class="block px-0 py-3 transition hover:bg-[color:rgb(var(--color-bg-rgb)/0.28)]"
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
            class="w-full rounded-xl border border-white/5 bg-[color:rgb(var(--color-bg-rgb)/0.84)] px-3 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
            @click="loadMore"
          >
            Nacitat viac
          </button>
        </div>
      </div>

      <div v-else class="rounded-xl bg-[color:rgb(var(--color-bg-rgb)/0.44)] p-5 text-center">
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
import UserAvatar from '@/components/UserAvatar.vue'
import api from '@/services/api'

const RECENT_STORAGE_KEY = 'search_recent_queries'
const RECENT_STORAGE_TTL_DAYS = 30
const RECENT_STORAGE_LIMIT = 15

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
    if (!raw) return []

    const parsed = JSON.parse(raw)
    const normalize = (entries) => entries
      .map((entry) => String(entry || '').trim())
      .filter(Boolean)
      .slice(0, RECENT_STORAGE_LIMIT)

    if (Array.isArray(parsed)) {
      return normalize(parsed)
    }

    if (!parsed || typeof parsed !== 'object' || !Array.isArray(parsed.items)) {
      window.localStorage.removeItem(RECENT_STORAGE_KEY)
      return []
    }

    const savedAt = Date.parse(String(parsed.savedAt || ''))
    if (Number.isNaN(savedAt)) {
      window.localStorage.removeItem(RECENT_STORAGE_KEY)
      return []
    }

    const ttlMs = RECENT_STORAGE_TTL_DAYS * 24 * 60 * 60 * 1000
    if (Date.now() - savedAt > ttlMs) {
      window.localStorage.removeItem(RECENT_STORAGE_KEY)
      return []
    }

    return normalize(parsed.items)
  } catch {
    return []
  }
}

const writeRecent = (entries) => {
  if (typeof window === 'undefined') return
  const items = entries
    .map((entry) => String(entry || '').trim())
    .filter(Boolean)
    .slice(0, RECENT_STORAGE_LIMIT)

  window.localStorage.setItem(RECENT_STORAGE_KEY, JSON.stringify({
    items,
    savedAt: new Date().toISOString(),
  }))
}

const addRecent = (entry) => {
  const normalized = String(entry || '').trim()
  if (normalized.length < 2) return

  const next = [normalized, ...recentSearches.value.filter((item) => item !== normalized)]
    .slice(0, RECENT_STORAGE_LIMIT)
  recentSearches.value = next
  writeRecent(next)
}

const clearRecent = () => {
  recentSearches.value = []
  if (typeof window !== 'undefined') {
    window.localStorage.removeItem(RECENT_STORAGE_KEY)
  }
}

const applyRecent = (entry) => {
  emit('update:query', entry)
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
