<template src="./publicProfile/PublicProfileView.template.html"></template>

<script setup>
import { computed, reactive, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserAvatar from '@/components/UserAvatar.vue'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import HashtagText from '@/components/HashtagText.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import http from '@/services/api'
import { listObservations } from '@/services/observations'
import { formatDateTimeCompact } from '@/utils/dateUtils'
import { resolveUserProfileMedia } from '@/utils/profileMedia'

const router = useRouter()
const route = useRoute()

const user = ref(null)
const loading = ref(true)
const err = ref('')

const tabs = [
  { key: 'posts', label: 'Prispevky', kind: 'roots' },
  { key: 'replies', label: 'Odpovede', kind: 'replies' },
  { key: 'observations', label: 'Pozorovania', kind: 'observations' },
  { key: 'media', label: 'Media', kind: 'media' },
]

const stats = reactive({ posts: '--', replies: '--', media: '--' })
const activeTab = ref('posts')

const tabState = reactive({
  posts: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  replies: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  observations: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  media: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
})

const copyLabel = ref('Kopirovat link')

const username = computed(() => String(route.params.username || '').trim())
const encodedUsername = computed(() => encodeURIComponent(username.value))
const hasUsername = computed(() => username.value.length > 0)
const displayName = computed(() => {
  const name = toNonEmptyText(user.value?.name)
  if (name && !looksLikeEmail(name)) return name

  const profileUsername = toNonEmptyText(user.value?.username)
  return profileUsername || 'Profil'
})
const handle = computed(() => {
  const profileUsername = toNonEmptyText(user.value?.username)
  if (profileUsername) return safeHandle(profileUsername)
  return safeHandle(displayName.value || 'user')
})
const resolvedMedia = computed(() => resolveUserProfileMedia(user.value))
const avatarUser = computed(() => resolvedMedia.value.avatarUser)
const coverMedia = computed(() => resolvedMedia.value.cover)

function safeHandle(input) {
  return String(input).toLowerCase().replace(/[^a-z0-9_]+/g, '').slice(0, 20) || 'user'
}

function toNonEmptyText(value) {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

function looksLikeEmail(value) {
  if (typeof value !== 'string') return false
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

function resetTabState(tabKey) {
  if (!tabState[tabKey]) return
  tabState[tabKey].items = []
  tabState[tabKey].next = null
  tabState[tabKey].loading = false
  tabState[tabKey].err = ''
  tabState[tabKey].total = null
  tabState[tabKey].loaded = false
}

function goHome() {
  router.push({ name: 'home' })
}

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function openObservation(observation) {
  const observationId = Number(observation?.id || 0)
  if (!Number.isInteger(observationId) || observationId <= 0) return
  router.push(`/observations/${observationId}`)
}

function setActiveTab(key) {
  activeTab.value = key
}

function fmt(iso) {
  return formatDateTimeCompact(iso)
}

function shorten(text) {
  if (!text) return ''
  const clean = String(text).trim()
  return clean.length > 80 ? clean.slice(0, 77) + '...' : clean
}

function isImage(post) {
  const mime = post?.attachment_mime || ''
  return mime.startsWith('image/')
}

function parentHandle(post) {
  const parentUser = post?.parent?.user
  if (parentUser?.username) return parentUser.username
  const name = toNonEmptyText(parentUser?.name)
  if (name && !looksLikeEmail(name)) return safeHandle(name)
  return 'user'
}

async function copyProfileLink() {
  if (!hasUsername.value) return
  const url = `${window.location.origin}/u/${encodedUsername.value}`
  try {
    await navigator.clipboard.writeText(url)
    copyLabel.value = 'Skopirovane'
  } catch {
    copyLabel.value = 'Nepodarilo sa kopirovat'
  }
  setTimeout(() => {
    copyLabel.value = 'Kopirovat link'
  }, 1500)
}

async function loadUser() {
  loading.value = true
  err.value = ''
  user.value = null

  if (!hasUsername.value) {
    err.value = 'Profil neexistuje.'
    loading.value = false
    return
  }

  try {
    const { data } = await http.get(`/users/${encodedUsername.value}`)
    user.value = data
  } catch (e) {
    const status = e?.response?.status
    if (status === 404) err.value = 'Profil neexistuje.'
    else err.value = e?.response?.data?.message || 'Nacitanie profilu zlyhalo.'
  } finally {
    loading.value = false
  }
}

async function loadCounts() {
  if (!hasUsername.value) {
    stats.posts = '--'
    stats.replies = '--'
    stats.media = '--'
    tabState.posts.total = '--'
    tabState.replies.total = '--'
    tabState.media.total = '--'
    tabState.observations.total = '--'
    return
  }

  const kinds = [
    { key: 'posts', kind: 'roots' },
    { key: 'replies', kind: 'replies' },
    { key: 'media', kind: 'media' },
  ]

  for (const k of kinds) {
    try {
      const { data } = await http.get(`/users/${encodedUsername.value}/posts`, {
        params: { kind: k.kind, per_page: 1 },
      })

      const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
      stats[k.key] = String(total)
      if (tabState[k.key]) {
        tabState[k.key].total = String(total)
      }
    } catch {
      stats[k.key] = '--'
      if (tabState[k.key]) {
        tabState[k.key].total = '--'
      }
    }
  }

  try {
    const userId = Number(user.value?.id || 0)
    if (Number.isInteger(userId) && userId > 0) {
      const { data } = await listObservations({ user_id: userId, page: 1, per_page: 1 })
      const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
      tabState.observations.total = String(total)
    } else {
      tabState.observations.total = '--'
    }
  } catch {
    tabState.observations.total = '--'
  }
}

async function loadTab(key, reset = true) {
  const tab = tabs.find((t) => t.key === key)
  const state = tabState[key]
  if (!tab || !state) return

  if (state.loading) return
  state.loading = true
  state.err = ''

  try {
    if (tab.kind === 'observations') {
      const userId = Number(user.value?.id || 0)
      if (!Number.isInteger(userId) || userId <= 0) return

      const page = reset ? 1 : Number(state.next || 0)
      if (!page) return

      const { data } = await listObservations({
        user_id: userId,
        page,
        per_page: 10,
      })

      const rows = Array.isArray(data?.data) ? data.data : []
      state.items = reset ? rows : [...state.items, ...rows]

      const currentPage = Number(data?.current_page || page)
      const lastPage = Number(data?.last_page || currentPage)
      state.next = currentPage < lastPage ? currentPage + 1 : null
      state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
      state.loaded = true
      return
    }

    const url = reset ? `/users/${encodedUsername.value}/posts` : state.next
    if (!url) return

    const { data } = await http.get(url, {
      params: reset ? { kind: tab.kind, per_page: 10 } : undefined,
    })

    const rows = data?.data ?? []
    if (reset) state.items = rows
    else state.items = [...state.items, ...rows]

    state.next = data?.next_page_url ?? null
    state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
    state.loaded = true
  } catch (e) {
    state.err = e?.response?.data?.message || 'Nacitanie zlyhalo.'
  } finally {
    state.loading = false
  }
}

async function refreshProfile() {
  await loadUser()
  if (!err.value) {
    await loadCounts()
    await loadTab(activeTab.value, true)
  }
}

watch(
  () => activeTab.value,
  (key) => {
    if (!tabState[key].loaded) loadTab(key, true)
  }
)

watch(
  () => username.value,
  async () => {
    activeTab.value = 'posts'
    resetTabState('posts')
    resetTabState('replies')
    resetTabState('observations')
    resetTabState('media')
    await refreshProfile()
  }
)

onMounted(async () => {
  await refreshProfile()
})
</script>

<style scoped src="./publicProfile/PublicProfileView.css"></style>

