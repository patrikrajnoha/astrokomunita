<template>
  <section class="searchPage w-full min-w-0 text-[var(--color-surface)]" data-testid="search-page-root">
    <div class="searchPage__shell w-full min-w-0 px-3 py-4 sm:px-4 sm:py-5 md:py-6" data-testid="search-page-shell">
      <header class="searchPage__intro" aria-label="Hľadanie">
        <h1 class="searchPage__title">Hľadanie</h1>
        <p class="searchPage__subtitle">
          Zadaj kľúčové slovo a rýchlo nájdi to, čo hľadáš.
        </p>
      </header>

      <section class="searchPage__toolbar" data-testid="search-page-toolbar">
        <SearchBar
          v-model="query"
          :loading="showSearchResults && isGlobalLoading"
          placeholder="Napr. Perzeidy, Mars, ISS"
          @submit="loadGlobalResults"
        />

        <nav class="searchPage__tabs" aria-label="Karty hľadania">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="searchPage__tab"
            :class="{ 'is-active': activeTab === tab.key }"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </nav>
      </section>

      <section v-if="showSearchResults" class="searchPage__content">
        <SearchSkeleton v-if="isGlobalLoading" :sections="3" :rows="3" />

        <section
          v-else-if="globalError"
          class="searchPage__notice searchPage__notice--error"
          role="status"
          aria-live="polite"
        >
          <p>{{ globalError }}</p>
          <button type="button" class="searchPage__retry" @click="loadGlobalResults">
            Skúsiť znova
          </button>
        </section>

        <template v-else>
          <section
            v-for="section in globalSections"
            :key="section.key"
            class="searchSection"
            :aria-label="section.title"
          >
            <header class="searchSection__header">
              <h2 class="searchSection__title">{{ section.title }}</h2>
              <span class="searchSection__count">{{ section.items.length }}</span>
            </header>

            <div class="searchSection__list">
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

          <section v-if="globalResults.keywords.length" class="searchSection" aria-label="Kľúčové slová">
            <header class="searchSection__header">
              <h2 class="searchSection__title">Kľúčové slová</h2>
              <span class="searchSection__count">{{ globalResults.keywords.length }}</span>
            </header>
            <div class="searchSection__keywords">
              <button
                v-for="item in globalResults.keywords"
                :key="`k-${item.id}`"
                type="button"
                class="searchChip"
                @click="useKeyword(item.value)"
              >
                {{ item.value }}
              </button>
            </div>
          </section>

          <SearchEmptyState
            v-if="!hasAnyGlobalResults"
            title="Nič sme nenašli"
            message="Skús iný výraz alebo jednoduchšie kľúčové slovo."
            hint="Tip: funguje aj názov udalosti, hashtag alebo meno používateľa."
            type="search"
          />
        </template>
      </section>

      <section v-else class="searchPage__content">
        <SearchSkeleton v-if="isDiscoveryLoading" :sections="2" :rows="3" />

        <section
          v-else-if="discoveryError"
          class="searchPage__notice searchPage__notice--error"
          role="status"
          aria-live="polite"
        >
          <p>{{ discoveryError }}</p>
          <button type="button" class="searchPage__retry" @click="loadDiscovery">
            Skúsiť znova
          </button>
        </section>

        <template v-else>
          <section
            v-for="section in activeDiscoverySections"
            :key="section.key"
            class="searchSection"
            :aria-label="section.title"
          >
            <header class="searchSection__header">
              <h2 class="searchSection__title">{{ section.title }}</h2>
              <span class="searchSection__count">{{ section.items.length }}</span>
            </header>

            <div class="searchSection__list">
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

          <section v-if="discovery.keywords.length" class="searchSection" aria-label="Populárne kľúčové slová">
            <header class="searchSection__header">
              <h2 class="searchSection__title">Populárne kľúčové slová</h2>
              <span class="searchSection__count">{{ discovery.keywords.length }}</span>
            </header>
            <div class="searchSection__keywords">
              <button
                v-for="item in discovery.keywords"
                :key="`dk-${item.id}`"
                type="button"
                class="searchChip"
                @click="useKeyword(item.value)"
              >
                {{ item.value }}
              </button>
            </div>
          </section>

          <SearchEmptyState
            v-if="!hasAnyDiscoveryResults"
            title="Zatiaľ tu nič nie je"
            message="Skús inú kartu alebo zadaj vlastné hľadanie."
            hint="Obsah sa priebežne aktualizuje."
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
  { key: 'trendy', label: 'Top' },
  { key: 'spravy', label: 'Správy' },
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
  if (!raw) return 'Bez termínu'

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
  title: user.name || user.username || 'Používateľ',
  excerpt: user.username ? `@${user.username}` : '',
  meta: user.location || '',
})

const toPostItem = (post, prefix = 'Príspevok') => ({
  key: `p-${post.id}`,
  to: { name: 'post-detail', params: { id: post.id } },
  kind: 'post',
  title: post.user?.name || prefix,
  excerpt: postSnippet(post.content),
  meta: [
    formatCount(post.likes_count, 'reakcií'),
    formatCount(post.replies_count, 'komentárov'),
    formatCount(post.views, 'zobrazení'),
  ].join(' | '),
})

const toEventItem = (event) => ({
  key: `e-${event.id}`,
  to: { name: 'event-detail', params: { id: event.id } },
  kind: 'event',
  title: event.title || 'Udalosť',
  excerpt: eventSnippet(event),
  meta: formatEventDate(event),
})

const toArticleItem = (article) => ({
  key: `a-${article.id}`,
  to: `/clanky/${article.slug || article.id}`,
  kind: 'article',
  title: article.title || 'Článok',
  excerpt: postSnippet(article.excerpt || article.summary || '', 140),
  meta: formatCount(article.views, 'zobrazení'),
})

const toHashtagItem = (tag) => ({
  key: `h-${tag.id}`,
  to: { name: 'hashtag-feed', params: { name: tag.name } },
  kind: 'hashtag',
  title: `#${tag.value || tag.name || ''}`,
  excerpt: 'Hashtag',
  meta: formatCount(tag.posts_count, 'príspevkov'),
})

const globalSections = computed(() => {
  const sections = [
    {
      key: 'users',
      title: 'Používatelia',
      items: globalResults.value.users.map(toUserItem),
    },
    {
      key: 'posts',
      title: 'Príspevky',
      items: globalResults.value.posts.map((post) => toPostItem(post, 'Príspevok')),
    },
    {
      key: 'events',
      title: 'Udalosti',
      items: globalResults.value.events.map(toEventItem),
    },
    {
      key: 'articles',
      title: 'Články',
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
        title: 'Správy',
        items: discovery.value.news.posts.map((post) => toPostItem(post, post.user?.name || 'Bot')),
      },
      {
        key: 'news-articles',
        title: 'Nové články',
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
        title: 'Súvisiace príspevky',
        items: discovery.value.events.posts.map((post) => toPostItem(post, 'Príspevok')),
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
      title: 'Top príspevky',
      items: discovery.value.trending.posts.map((post) => toPostItem(post, 'Príspevok')),
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
    globalError.value = 'Nepodarilo sa načítať výsledky. Skús to znova.'
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
    discoveryError.value = 'Nepodarilo sa načítať obsah. Skús to znova.'
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
.searchPage__shell {
  display: grid;
  gap: 0.9rem;
}

.searchPage__intro {
  display: grid;
  gap: 0.22rem;
  padding-inline: 0.1rem;
}

.searchPage__title {
  margin: 0;
  font-size: clamp(1.14rem, 1.8vw, 1.45rem);
  font-weight: 700;
  color: var(--color-text-primary);
  letter-spacing: -0.01em;
}

.searchPage__subtitle {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
  line-height: 1.45;
  max-width: 60ch;
}

.searchPage__toolbar {
  position: sticky;
  top: 0.5rem;
  z-index: 20;
  display: grid;
  gap: 0.72rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: rgb(var(--bg-surface-rgb) / 0.9);
  padding: 0.72rem;
  backdrop-filter: blur(8px);
}

.searchPage__tabs {
  display: flex;
  gap: 0.45rem;
  overflow-x: auto;
  scrollbar-width: none;
}

.searchPage__tabs::-webkit-scrollbar {
  display: none;
}

.searchPage__tab {
  flex: 0 0 auto;
  min-height: 34px;
  padding: 0.4rem 0.85rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-app-rgb) / 0.45);
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
  font-weight: 600;
  line-height: 1;
  transition:
    background-color var(--motion-base),
    border-color var(--motion-base),
    color var(--motion-base);
}

.searchPage__tab:hover {
  border-color: var(--color-border-strong);
  color: var(--color-text-primary);
  background: rgb(var(--bg-app-rgb) / 0.62);
}

.searchPage__tab.is-active {
  border-color: rgb(var(--color-accent-rgb) / 0.52);
  color: var(--color-text-primary);
  background: rgb(var(--color-accent-rgb) / 0.18);
}

.searchPage__tab:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.searchPage__content {
  display: grid;
  gap: 0.75rem;
}

.searchPage__notice {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-surface-rgb) / 0.74);
  padding: 0.9rem;
  display: grid;
  gap: 0.7rem;
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
}

.searchPage__notice--error {
  border-color: rgb(var(--color-danger-rgb) / 0.42);
  background: rgb(var(--color-danger-rgb) / 0.1);
}

.searchPage__retry {
  width: fit-content;
  min-height: 32px;
  padding: 0.35rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-app-rgb) / 0.55);
  color: var(--color-text-primary);
  font-size: var(--font-size-sm);
  font-weight: 600;
  transition:
    background-color var(--motion-base),
    border-color var(--motion-base);
}

.searchPage__retry:hover {
  border-color: var(--color-border-strong);
  background: rgb(var(--bg-app-rgb) / 0.72);
}

.searchPage__retry:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.searchSection {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-surface-rgb) / 0.65);
  padding: 0.78rem;
  display: grid;
  gap: 0.65rem;
  min-width: 0;
}

.searchSection__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  min-width: 0;
}

.searchSection__title {
  margin: 0;
  color: var(--color-text-primary);
  font-size: 0.95rem;
  font-weight: 650;
  letter-spacing: -0.01em;
  min-width: 0;
}

.searchSection__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.8rem;
  height: 1.3rem;
  padding: 0 0.4rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: rgb(var(--bg-app-rgb) / 0.48);
  color: var(--color-text-secondary);
  font-size: 0.7rem;
  font-weight: 600;
  line-height: 1;
  flex-shrink: 0;
}

.searchSection__list {
  display: grid;
  gap: 0.48rem;
}

.searchSection__keywords {
  display: flex;
  flex-wrap: wrap;
  gap: 0.48rem;
}

.searchChip {
  min-height: 30px;
  padding: 0.25rem 0.68rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  background: rgb(var(--bg-app-rgb) / 0.34);
  color: var(--color-text-secondary);
  font-size: 0.76rem;
  font-weight: 600;
  line-height: 1;
  transition:
    border-color var(--motion-base),
    background-color var(--motion-base),
    color var(--motion-base);
}

.searchChip:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.5);
  color: var(--color-text-primary);
  background: rgb(var(--color-accent-rgb) / 0.14);
}

.searchChip:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

@media (min-width: 640px) {
  .searchPage__toolbar {
    top: 0.72rem;
    padding: 0.85rem;
  }
}

@media (max-width: 639px) {
  .searchSection {
    padding: 0.7rem;
  }
}
</style>
