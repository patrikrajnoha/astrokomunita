<template>
  <div class="min-h-screen bg-[var(--color-bg)] text-[var(--color-surface)]">
    <div class="mx-auto w-full max-w-[980px] px-4 py-5 sm:px-6">
      <section class="rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.68)] p-4 backdrop-blur sm:p-5">
        <label for="global-search-input" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">
          Prehladavat Astrokomunitu
        </label>
        <input
          id="global-search-input"
          v-model="query"
          type="search"
          placeholder="Prehladavat Astrokomunitu"
          class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.35)] bg-[color:rgb(var(--color-bg-rgb)/0.88)] px-4 py-3 text-sm text-[var(--color-surface)] placeholder-[color:rgb(var(--color-text-secondary-rgb)/0.86)] focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[color:rgb(var(--color-primary-rgb)/0.2)]"
        />
      </section>

      <nav class="mt-4 flex gap-2 overflow-x-auto pb-1">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="rounded-lg px-3 py-2 text-sm font-semibold transition"
          :class="activeTab === tab.key
            ? 'bg-[color:rgb(var(--color-primary-rgb)/0.9)] text-white'
            : 'bg-[color:rgb(var(--color-bg-rgb)/0.75)] text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] hover:text-[var(--color-surface)]'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="showSearchResults" class="mt-4 space-y-4">
        <div v-if="isGlobalLoading" class="rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.48)] p-4 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
          Nacitavam vysledky...
        </div>

        <template v-else>
          <section v-if="globalResults.users.length" class="resultSection">
            <h3 class="sectionTitle">Pouzivatelia</h3>
            <RouterLink
              v-for="user in globalResults.users"
              :key="`u-${user.id}`"
              :to="{ name: 'user-profile', params: { username: user.username } }"
              class="listItem"
            >
              <div class="font-semibold">{{ user.name || user.username }}</div>
              <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">@{{ user.username }}</div>
            </RouterLink>
          </section>

          <section v-if="globalResults.posts.length" class="resultSection">
            <h3 class="sectionTitle">Prispevky</h3>
            <RouterLink
              v-for="post in globalResults.posts"
              :key="`p-${post.id}`"
              :to="{ name: 'post-detail', params: { id: post.id } }"
              class="listItem"
            >
              <div class="line-clamp-2">{{ postSnippet(post.content) }}</div>
              <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">
                {{ post.user?.name || 'Neznamy autor' }} | {{ post.likes_count || 0 }} likes | {{ post.replies_count || 0 }} komentarov
              </div>
            </RouterLink>
          </section>

          <section v-if="globalResults.events.length" class="resultSection">
            <h3 class="sectionTitle">Udalosti</h3>
            <RouterLink
              v-for="event in globalResults.events"
              :key="`e-${event.id}`"
              :to="{ name: 'event-detail', params: { id: event.id } }"
              class="listItem"
            >
              <div class="font-semibold">{{ event.title }}</div>
              <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ formatEventDate(event) }}</div>
            </RouterLink>
          </section>

          <section v-if="globalResults.articles.length" class="resultSection">
            <h3 class="sectionTitle">Clanky</h3>
            <RouterLink
              v-for="article in globalResults.articles"
              :key="`a-${article.id}`"
              :to="`/clanky/${article.slug || article.id}`"
              class="listItem"
            >
              <div class="font-semibold">{{ article.title }}</div>
              <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ article.views || 0 }} zobrazeni</div>
            </RouterLink>
          </section>

          <section v-if="globalResults.hashtags.length" class="resultSection">
            <h3 class="sectionTitle">Hashtagy</h3>
            <RouterLink
              v-for="tag in globalResults.hashtags"
              :key="`h-${tag.id}`"
              :to="{ name: 'hashtag-feed', params: { name: tag.name } }"
              class="listItem"
            >
              <div class="font-semibold">{{ tag.value }}</div>
              <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ tag.posts_count || 0 }} prispevkov</div>
            </RouterLink>
          </section>

          <section v-if="globalResults.keywords.length" class="resultSection">
            <h3 class="sectionTitle">Klucove slova</h3>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="item in globalResults.keywords"
                :key="`k-${item.id}`"
                type="button"
                class="rounded-full border border-white/5 px-3 py-1 text-xs transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
                @click="query = item.value"
              >
                {{ item.value }}
              </button>
            </div>
          </section>

          <div
            v-if="!hasAnyGlobalResults"
            class="rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.48)] p-4 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]"
          >
            Nic sme nenasli.
          </div>
        </template>
      </section>

      <section v-else class="mt-4 space-y-4">
        <div v-if="isDiscoveryLoading" class="rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.48)] p-4 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">
          Nacitavam trendy obsah...
        </div>

        <template v-else>
          <template v-if="activeTab === 'trendy'">
            <section class="resultSection">
              <h3 class="sectionTitle">Top 3 udalosti</h3>
              <RouterLink v-for="event in discovery.trending.events" :key="`te-${event.id}`" :to="{ name: 'event-detail', params: { id: event.id } }" class="listItem">
                <div class="font-semibold">{{ event.title }}</div>
                <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ formatEventDate(event) }}</div>
              </RouterLink>
            </section>
            <section class="resultSection">
              <h3 class="sectionTitle">Top 3 prispevky (interakcia)</h3>
              <RouterLink v-for="post in discovery.trending.posts" :key="`tp-${post.id}`" :to="{ name: 'post-detail', params: { id: post.id } }" class="listItem">
                <div class="line-clamp-2">{{ postSnippet(post.content) }}</div>
                <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ post.likes_count || 0 }} likes | {{ post.replies_count || 0 }} komentarov | {{ post.views || 0 }} zobrazeni</div>
              </RouterLink>
            </section>
          </template>

          <template v-if="activeTab === 'spravy'">
            <section class="resultSection">
              <h3 class="sectionTitle">Spravy (NASA RSS / Kozmobot)</h3>
              <RouterLink v-for="post in discovery.news.posts" :key="`np-${post.id}`" :to="{ name: 'post-detail', params: { id: post.id } }" class="listItem">
                <div class="line-clamp-2">{{ postSnippet(post.content) }}</div>
                <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ post.user?.name || 'Bot' }}</div>
              </RouterLink>
            </section>
            <section class="resultSection">
              <h3 class="sectionTitle">Najnovsie clanky</h3>
              <RouterLink v-for="article in discovery.news.articles" :key="`na-${article.id}`" :to="`/clanky/${article.slug || article.id}`" class="listItem">
                <div class="font-semibold">{{ article.title }}</div>
                <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ article.views || 0 }} zobrazeni</div>
              </RouterLink>
            </section>
          </template>

          <template v-if="activeTab === 'udalosti'">
            <section class="resultSection">
              <h3 class="sectionTitle">Udalosti (max 9)</h3>
              <RouterLink v-for="event in discovery.events.events" :key="`ee-${event.id}`" :to="{ name: 'event-detail', params: { id: event.id } }" class="listItem">
                <div class="font-semibold">{{ event.title }}</div>
                <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ formatEventDate(event) }}</div>
              </RouterLink>
            </section>
            <section class="resultSection">
              <h3 class="sectionTitle">Suhvisiace top prispevky</h3>
              <RouterLink v-for="post in discovery.events.posts" :key="`ep-${post.id}`" :to="{ name: 'post-detail', params: { id: post.id } }" class="listItem">
                <div class="line-clamp-2">{{ postSnippet(post.content) }}</div>
                <div class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">{{ post.likes_count || 0 }} likes | {{ post.replies_count || 0 }} komentarov | {{ post.views || 0 }} zobrazeni</div>
              </RouterLink>
            </section>
          </template>

          <section v-if="discovery.keywords.length" class="resultSection">
            <h3 class="sectionTitle">Klucove slova pre profil a hladanie</h3>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="item in discovery.keywords"
                :key="`dk-${item.id}`"
                type="button"
                class="rounded-full border border-white/5 px-3 py-1 text-xs transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
                @click="query = item.value"
              >
                {{ item.value }}
              </button>
            </div>
          </section>
        </template>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
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

const trimmedQuery = computed(() => String(query.value || '').trim())
const showSearchResults = computed(() => trimmedQuery.value.length >= 2)
const hasAnyGlobalResults = computed(() => (
  globalResults.value.users.length > 0 ||
  globalResults.value.posts.length > 0 ||
  globalResults.value.events.length > 0 ||
  globalResults.value.articles.length > 0 ||
  globalResults.value.hashtags.length > 0 ||
  globalResults.value.keywords.length > 0
))

const postSnippet = (content) => {
  const text = String(content || '').replace(/\s+/g, ' ').trim()
  if (!text) return '(Bez textu)'
  return text.length > 150 ? `${text.slice(0, 150)}...` : text
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
    return `${dateLabel} · ${context.message}`
  }

  return `${dateLabel} · ${context.timeString} (${context.timezoneLabelShort})`
}

const syncRouteQuery = () => {
  const next = {}
  if (trimmedQuery.value) next.q = trimmedQuery.value
  const currentQ = typeof route.query.q === 'string' ? route.query.q : ''
  if ((next.q || '') === currentQ) return
  router.replace({ name: 'search', query: next }).catch(() => {})
}

const loadGlobalResults = async () => {
  if (!showSearchResults.value) {
    globalResults.value = { users: [], posts: [], events: [], articles: [], hashtags: [], keywords: [] }
    return
  }

  globalRequestController?.abort()
  globalRequestController = new AbortController()
  isGlobalLoading.value = true

  try {
    const response = await api.get('/search/global', {
      params: {
        q: trimmedQuery.value,
        limit: 6,
      },
      signal: globalRequestController.signal,
      meta: { skipErrorToast: true },
    })

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
    if (error?.code !== 'ERR_CANCELED' && error?.name !== 'CanceledError') {
      globalResults.value = { users: [], posts: [], events: [], articles: [], hashtags: [], keywords: [] }
    }
  } finally {
    isGlobalLoading.value = false
  }
}

const loadDiscovery = async () => {
  discoveryRequestController?.abort()
  discoveryRequestController = new AbortController()
  isDiscoveryLoading.value = true

  try {
    const response = await api.get('/search/discovery', {
      params: {
        limit_events: 9,
        limit_posts: 3,
      },
      signal: discoveryRequestController.signal,
      meta: { skipErrorToast: true },
    })

    const data = response?.data?.data || {}
    discovery.value = {
      trending: data.trending || { events: [], posts: [] },
      news: data.news || { posts: [], articles: [] },
      events: data.events || { events: [], posts: [] },
      keywords: Array.isArray(data.keywords) ? data.keywords : [],
    }
  } catch (error) {
    if (error?.code !== 'ERR_CANCELED' && error?.name !== 'CanceledError') {
      discovery.value = {
        trending: { events: [], posts: [] },
        news: { posts: [], articles: [] },
        events: { events: [], posts: [] },
        keywords: [],
      }
    }
  } finally {
    isDiscoveryLoading.value = false
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
  }, 220)
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
.resultSection {
  padding: 0;
  display: grid;
  gap: 0;
}

.resultSection + .resultSection {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--divider-color);
}

.sectionTitle {
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 700;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
}

.listItem {
  border-bottom: 1px solid var(--divider-color);
  padding: 0.72rem 0;
  display: block;
  transition: color 120ms ease, background-color 120ms ease;
}

.listItem:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.04);
}

.resultSection > .listItem:last-child {
  border-bottom: none;
}
</style>
