<template src="./searchPage/SearchPage.template.html"></template>

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
  to: `/articles/${article.slug || article.id}`,
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

<style scoped src="./searchPage/SearchPage.css"></style>
