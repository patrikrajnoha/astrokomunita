<template>
  <section class="searchPage w-full min-w-0 text-[var(--color-surface)]" data-testid="search-page-root">
    <div class="searchPage__shell w-full min-w-0 px-3 py-4 sm:px-4 sm:py-5 md:py-6" data-testid="search-page-shell">
      <section
        class="sticky top-2 z-20 rounded-2xl border border-[var(--color-border)] bg-[color:rgb(var(--color-bg-rgb)/0.78)] p-3 shadow-sm backdrop-blur sm:top-3 sm:p-4"
        data-testid="search-page-toolbar"
      >
        <SearchBar
          v-model="query"
          :loading="showSearchResults && isGlobalLoading"
          placeholder="Prehladavat Astrokomunitu"
          @submit="loadGlobalResults"
        />

        <nav class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Karty hladania">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="rounded-lg px-3 py-2 text-sm font-semibold transition"
            :class="activeTab === tab.key
              ? 'bg-[color:rgb(var(--color-primary-rgb)/0.92)] text-white shadow-sm'
              : 'bg-[color:rgb(var(--color-bg-rgb)/0.84)] text-[color:rgb(var(--color-text-secondary-rgb)/0.96)] hover:bg-[color:rgb(var(--color-text-secondary-rgb)/0.08)] hover:text-[var(--color-surface)]'"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </nav>
      </section>

      <section v-if="showSearchResults" class="mt-4 space-y-4">
        <SearchSkeleton v-if="isGlobalLoading" :sections="3" :rows="3" />

        <section
          v-else-if="globalError"
          class="rounded-2xl border border-[color:rgb(var(--color-primary-rgb)/0.32)] bg-[color:rgb(var(--color-primary-rgb)/0.08)] p-4"
          role="status"
          aria-live="polite"
        >
          <p class="text-sm text-[var(--color-surface)]">{{ globalError }}</p>
          <button
            type="button"
            class="mt-3 rounded-lg border border-[color:rgb(var(--color-primary-rgb)/0.5)] bg-[color:rgb(var(--color-primary-rgb)/0.16)] px-3 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:bg-[color:rgb(var(--color-primary-rgb)/0.24)]"
            @click="loadGlobalResults"
          >
            Skusit znova
          </button>
        </section>

        <template v-else>
          <section
            v-for="section in globalSections"
            :key="section.key"
            class="searchSection"
            :aria-label="section.title"
          >
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.94)]">
              {{ section.title }}
            </h2>
            <div class="space-y-2">
              <SearchResultCard
                v-for="item in section.items"
                :key="item.key"
                :to="item.to"
                :kind="item.kind"
                :title="item.title"
                :excerpt="item.excerpt"
                :meta="item.meta"
                :query="trimmedQuery"
              />
            </div>
          </section>

          <section v-if="globalResults.keywords.length" class="searchSection" aria-label="Klucove slova">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.94)]">
              Klucove slova
            </h2>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="item in globalResults.keywords"
                :key="`k-${item.id}`"
                type="button"
                class="rounded-full border border-[var(--color-border)] bg-[color:rgb(var(--color-bg-rgb)/0.72)] px-3 py-1.5 text-xs font-medium text-[color:rgb(var(--color-text-secondary-rgb)/0.92)] transition hover:border-[color:rgb(var(--color-primary-rgb)/0.58)] hover:text-[var(--color-primary)]"
                @click="useKeyword(item.value)"
              >
                {{ item.value }}
              </button>
            </div>
          </section>

          <SearchEmptyState
            v-if="!hasAnyGlobalResults"
            title="Nic sme nenasli"
            message="Skus iny vyraz alebo pridaj dalsie klucove slovo."
            hint="Tip: pouzi aspon 2 znaky a skus konkretnejsi dotaz."
            type="search"
          />
        </template>
      </section>

      <section v-else class="mt-4 space-y-4">
        <SearchSkeleton v-if="isDiscoveryLoading" :sections="2" :rows="3" />

        <section
          v-else-if="discoveryError"
          class="rounded-2xl border border-[color:rgb(var(--color-primary-rgb)/0.32)] bg-[color:rgb(var(--color-primary-rgb)/0.08)] p-4"
          role="status"
          aria-live="polite"
        >
          <p class="text-sm text-[var(--color-surface)]">{{ discoveryError }}</p>
          <button
            type="button"
            class="mt-3 rounded-lg border border-[color:rgb(var(--color-primary-rgb)/0.5)] bg-[color:rgb(var(--color-primary-rgb)/0.16)] px-3 py-2 text-sm font-semibold text-[var(--color-surface)] transition hover:bg-[color:rgb(var(--color-primary-rgb)/0.24)]"
            @click="loadDiscovery"
          >
            Skusit znova
          </button>
        </section>

        <template v-else>
          <section
            v-for="section in activeDiscoverySections"
            :key="section.key"
            class="searchSection"
            :aria-label="section.title"
          >
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.94)]">
              {{ section.title }}
            </h2>
            <div class="space-y-2">
              <SearchResultCard
                v-for="item in section.items"
                :key="item.key"
                :to="item.to"
                :kind="item.kind"
                :title="item.title"
                :excerpt="item.excerpt"
                :meta="item.meta"
                :query="trimmedQuery"
              />
            </div>
          </section>

          <section v-if="discovery.keywords.length" class="searchSection" aria-label="Popularne klucove slova">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.94)]">
              Popularne klucove slova
            </h2>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="item in discovery.keywords"
                :key="`dk-${item.id}`"
                type="button"
                class="rounded-full border border-[var(--color-border)] bg-[color:rgb(var(--color-bg-rgb)/0.72)] px-3 py-1.5 text-xs font-medium text-[color:rgb(var(--color-text-secondary-rgb)/0.92)] transition hover:border-[color:rgb(var(--color-primary-rgb)/0.58)] hover:text-[var(--color-primary)]"
                @click="useKeyword(item.value)"
              >
                {{ item.value }}
              </button>
            </div>
          </section>

          <SearchEmptyState
            v-if="!hasAnyDiscoveryResults"
            title="Discovery je zatial prazdne"
            message="Skus prepnut tab alebo spusti hladanie konkretnym vyrazom."
            hint="Obsah sa priebezne aktualizuje."
            type="discovery"
          />
        </template>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SearchBar from '@/components/search/SearchBar.vue'
import SearchEmptyState from '@/components/search/SearchEmptyState.vue'
import SearchResultCard from '@/components/search/SearchResultCard.vue'
import SearchSkeleton from '@/components/search/SearchSkeleton.vue'
import api from '@/services/api'
import { EVENT_TIMEZONE, formatEventDate as formatEventDay, resolveEventTimeContext } from '@/utils/eventTime'

const route = useRoute()
const router = useRouter()

const tabs = [
  { key: 'trendy', label: 'Trendy' },
  { key: 'spravy', label: 'Spravy' },
  { key: 'udalosti', label: 'Udalosti' },
]

const activeTab = ref('trendy')
const query = ref(typeof route.query.q === 'string' ? route.query.q : '')
const isGlobalLoading = ref(false)
const isDiscoveryLoading = ref(false)
const globalError = ref('')
const discoveryError = ref('')
const globalResults = ref({
  users: [],
  posts: [],
  events: [],
  articles: [],
  hashtags: [],
  keywords: [],
})
const discovery = ref({
  trending: { events: [], posts: [] },
  news: { posts: [], articles: [] },
  events: { events: [], posts: [] },
  keywords: [],
})

let searchTimer = null
let globalRequestController = null
let discoveryRequestController = null
let globalRequestToken = 0
let discoveryRequestToken = 0

const trimmedQuery = computed(() => String(query.value || '').trim())
const showSearchResults = computed(() => trimmedQuery.value.length >= 2)

const hasAnyGlobalResults = computed(() => (
  globalSections.value.length > 0 || globalResults.value.keywords.length > 0
))

const postSnippet = (content, limit = 150) => {
  const text = String(content || '').replace(/\s+/g, ' ').trim()
  if (!text) return '(Bez textu)'
  return text.length > limit ? `${text.slice(0, limit)}...` : text
}

const eventSnippet = (event) => {
  const raw = event?.description || event?.summary || event?.excerpt || ''
  return postSnippet(raw, 120)
}

const formatEventDate = (event) => {
  const raw = event?.start_at || event?.max_at
  if (!raw) return 'Bez terminu'

  const dateLabel = formatEventDay(raw, EVENT_TIMEZONE, {
    day: '2-digit',
    month: '2-digit',
  })
  const context = resolveEventTimeContext(event, EVENT_TIMEZONE)

  if (!context.showTimezoneLabel) {
    return `${dateLabel} | ${context.message}`
  }

  return `${dateLabel} | ${context.timeString} (${context.timezoneLabelShort})`
}

const formatCount = (value, unit) => `${Number(value || 0)} ${unit}`

const toUserItem = (user) => ({
  key: `u-${user.id}`,
  to: { name: 'user-profile', params: { username: user.username } },
  kind: 'user',
  title: user.name || user.username || 'Pouzivatel',
  excerpt: user.username ? `@${user.username}` : '',
  meta: user.location || '',
})

const toPostItem = (post, prefix = 'Prispevok') => ({
  key: `p-${post.id}`,
  to: { name: 'post-detail', params: { id: post.id } },
  kind: 'post',
  title: post.user?.name || prefix,
  excerpt: postSnippet(post.content),
  meta: [
    formatCount(post.likes_count, 'likes'),
    formatCount(post.replies_count, 'komentarov'),
    formatCount(post.views, 'zobrazeni'),
  ].join(' | '),
})

const toEventItem = (event) => ({
  key: `e-${event.id}`,
  to: { name: 'event-detail', params: { id: event.id } },
  kind: 'event',
  title: event.title || 'Udalost',
  excerpt: eventSnippet(event),
  meta: formatEventDate(event),
})

const toArticleItem = (article) => ({
  key: `a-${article.id}`,
  to: `/clanky/${article.slug || article.id}`,
  kind: 'article',
  title: article.title || 'Clanok',
  excerpt: postSnippet(article.excerpt || article.summary || '', 140),
  meta: formatCount(article.views, 'zobrazeni'),
})

const toHashtagItem = (tag) => ({
  key: `h-${tag.id}`,
  to: { name: 'hashtag-feed', params: { name: tag.name } },
  kind: 'hashtag',
  title: `#${tag.value || tag.name || ''}`,
  excerpt: 'Hashtag',
  meta: formatCount(tag.posts_count, 'prispevkov'),
})

const globalSections = computed(() => {
  const sections = [
    {
      key: 'users',
      title: 'Pouzivatelia',
      items: globalResults.value.users.map(toUserItem),
    },
    {
      key: 'posts',
      title: 'Prispevky',
      items: globalResults.value.posts.map((post) => toPostItem(post, 'Prispevok')),
    },
    {
      key: 'events',
      title: 'Udalosti',
      items: globalResults.value.events.map(toEventItem),
    },
    {
      key: 'articles',
      title: 'Clanky',
      items: globalResults.value.articles.map(toArticleItem),
    },
    {
      key: 'hashtags',
      title: 'Hashtagy',
      items: globalResults.value.hashtags.map(toHashtagItem),
    },
  ]

  return sections.filter((section) => section.items.length > 0)
})

const activeDiscoverySections = computed(() => {
  if (activeTab.value === 'spravy') {
    return [
      {
        key: 'news-posts',
        title: 'Spravy (NASA RSS / Kozmobot)',
        items: discovery.value.news.posts.map((post) => toPostItem(post, post.user?.name || 'Bot')),
      },
      {
        key: 'news-articles',
        title: 'Najnovsie clanky',
        items: discovery.value.news.articles.map(toArticleItem),
      },
    ].filter((section) => section.items.length > 0)
  }

  if (activeTab.value === 'udalosti') {
    return [
      {
        key: 'events-main',
        title: 'Udalosti',
        items: discovery.value.events.events.map(toEventItem),
      },
      {
        key: 'events-posts',
        title: 'Suhvisiace top prispevky',
        items: discovery.value.events.posts.map((post) => toPostItem(post, 'Prispevok')),
      },
    ].filter((section) => section.items.length > 0)
  }

  return [
    {
      key: 'trendy-events',
      title: 'Top udalosti',
      items: discovery.value.trending.events.map(toEventItem),
    },
    {
      key: 'trendy-posts',
      title: 'Top prispevky (interakcia)',
      items: discovery.value.trending.posts.map((post) => toPostItem(post, 'Prispevok')),
    },
  ].filter((section) => section.items.length > 0)
})

const hasAnyDiscoveryResults = computed(() => (
  activeDiscoverySections.value.length > 0 || discovery.value.keywords.length > 0
))

const syncRouteQuery = () => {
  const next = {}
  if (trimmedQuery.value) next.q = trimmedQuery.value

  const currentQ = typeof route.query.q === 'string' ? route.query.q : ''
  if ((next.q || '') === currentQ) return

  router.replace({ name: 'search', query: next }).catch(() => {})
}

const useKeyword = (value) => {
  query.value = String(value || '').trim()
}

const loadGlobalResults = async () => {
  if (!showSearchResults.value) {
    globalRequestController?.abort()
    globalRequestController = null
    isGlobalLoading.value = false
    globalError.value = ''
    globalResults.value = { users: [], posts: [], events: [], articles: [], hashtags: [], keywords: [] }
    return
  }

  globalRequestController?.abort()
  globalRequestController = new AbortController()
  const token = ++globalRequestToken
  isGlobalLoading.value = true
  globalError.value = ''

  try {
    const response = await api.get('/search/global', {
      params: {
        q: trimmedQuery.value,
        limit: 6,
      },
      signal: globalRequestController.signal,
      meta: { skipErrorToast: true },
    })

    if (token !== globalRequestToken) return

    const data = response?.data?.data || {}
    globalResults.value = {
      users: Array.isArray(data.users) ? data.users : [],
      posts: Array.isArray(data.posts) ? data.posts : [],
      events: Array.isArray(data.events) ? data.events : [],
      articles: Array.isArray(data.articles) ? data.articles : [],
      hashtags: Array.isArray(data.hashtags) ? data.hashtags : [],
      keywords: Array.isArray(data.keywords) ? data.keywords : [],
    }
  } catch (error) {
    if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return
    if (token !== globalRequestToken) return

    globalResults.value = { users: [], posts: [], events: [], articles: [], hashtags: [], keywords: [] }
    globalError.value = 'Nepodarilo sa nacitat vysledky. Skus to prosim znova.'
  } finally {
    if (token === globalRequestToken) {
      isGlobalLoading.value = false
    }
  }
}

const loadDiscovery = async () => {
  discoveryRequestController?.abort()
  discoveryRequestController = new AbortController()
  const token = ++discoveryRequestToken
  isDiscoveryLoading.value = true
  discoveryError.value = ''

  try {
    const response = await api.get('/search/discovery', {
      params: {
        limit_events: 9,
        limit_posts: 3,
      },
      signal: discoveryRequestController.signal,
      meta: { skipErrorToast: true },
    })

    if (token !== discoveryRequestToken) return

    const data = response?.data?.data || {}
    discovery.value = {
      trending: data.trending || { events: [], posts: [] },
      news: data.news || { posts: [], articles: [] },
      events: data.events || { events: [], posts: [] },
      keywords: Array.isArray(data.keywords) ? data.keywords : [],
    }
  } catch (error) {
    if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return
    if (token !== discoveryRequestToken) return

    discovery.value = {
      trending: { events: [], posts: [] },
      news: { posts: [], articles: [] },
      events: { events: [], posts: [] },
      keywords: [],
    }
    discoveryError.value = 'Nepodarilo sa nacitat discovery obsah. Skus to prosim znova.'
  } finally {
    if (token === discoveryRequestToken) {
      isDiscoveryLoading.value = false
    }
  }
}

watch(
  () => route.query.q,
  (nextQ) => {
    const normalized = typeof nextQ === 'string' ? nextQ : ''
    if (normalized !== query.value) query.value = normalized
  },
)

watch(query, () => {
  syncRouteQuery()
  if (searchTimer) clearTimeout(searchTimer)

  searchTimer = setTimeout(() => {
    loadGlobalResults()
  }, 300)
})

onMounted(() => {
  loadDiscovery()
  if (showSearchResults.value) {
    loadGlobalResults()
  }
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
  globalRequestController?.abort()
  discoveryRequestController?.abort()
})
</script>

<style scoped>
.searchSection + .searchSection {
  border-top: 1px solid var(--divider-color);
  margin-top: 1rem;
  padding-top: 1rem;
}
</style>
