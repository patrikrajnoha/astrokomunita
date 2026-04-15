<template src="./publicProfile/PublicProfileView.template.html"></template>

<script setup>
import { computed, defineAsyncComponent, reactive, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UserAvatar from '@/components/UserAvatar.vue'
import PollCard from '@/components/PollCard.vue'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import SharedPostPreview from '@/components/SharedPostPreview.vue'
import HashtagText from '@/components/HashtagText.vue'
import PostActionBar from '@/components/PostActionBar.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import http from '@/services/api'
import { listObservations } from '@/services/observations'
import { useAuthStore } from '@/stores/auth'
import { useBookmarksStore } from '@/stores/bookmarks'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { formatDateTimeCompact } from '@/utils/dateUtils'
import { resolveUserProfileMedia } from '@/utils/profileMedia'
import { canDeletePost, canReportPost } from '@/utils/postPermissions'
import {
  buildBasePostMenuItems,
  buildPinPostMenuItem,
  handleCommonPostMenuAction,
} from '@/utils/postMenu'
import { hasOriginalDownload } from '@/components/feedList/feedListMedia.utils'
import {
  absoluteUrl as resolveAbsoluteUrl,
  attachmentSrc as resolveAttachmentSrc,
  isImage as isProfileImage,
  observationForPost,
} from './profileView.utils'

const ShareModal = defineAsyncComponent(() => import('@/components/share/ShareModal.vue'))

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const bookmarks = useBookmarksStore()
const { error: toastError, info: toastInfo, success: toastSuccess } = useToast()
const { confirm } = useConfirm()

const user = ref(null)
const loading = ref(true)
const err = ref('')

const tabs = [
  { key: 'posts', label: 'Príspevky', kind: 'roots' },
  { key: 'replies', label: 'Odpovede', kind: 'replies' },
  { key: 'observations', label: 'Pozorovania', kind: 'observations' },
  { key: 'media', label: 'Médiá', kind: 'media' },
]

const stats = reactive({ posts: '--', replies: '--', media: '--' })
const activeTab = ref('posts')

const tabState = reactive({
  posts: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  replies: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  observations: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
  media: { items: [], next: null, loading: false, err: '', total: null, loaded: false },
})

const copyLabel = ref('Zdieľať')
const shareTarget = ref(null)
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')
const likeLoadingIds = ref(new Set())
const likeBumpId = ref(null)
const pinLoadingId = ref(null)
const deleteLoadingId = ref(null)

const reportOptions = [
  { value: 'spam', label: 'Spam' },
  { value: 'abuse', label: 'Nevhodný obsah' },
  { value: 'misinfo', label: 'Dezinformácie' },
  { value: 'harassment', label: 'Obťažovanie' },
  { value: 'other', label: 'Iné' },
]

const username = computed(() => String(route.params.username || '').trim())
const encodedUsername = computed(() => encodeURIComponent(username.value))
const hasUsername = computed(() => username.value.length > 0)
const isAuthed = computed(() => Boolean(auth.user))
const isOwnProfile = computed(() => {
  const authUser = auth.user
  if (!authUser || typeof authUser !== 'object') return false

  const authUsername = String(authUser.username || '').trim().toLowerCase()
  const routeUsername = username.value.toLowerCase()
  if (authUsername !== '' && routeUsername !== '' && authUsername === routeUsername) {
    return true
  }

  const authId = Number(authUser.id || 0)
  const profileId = Number(user.value?.id || 0)
  return Number.isInteger(authId) && authId > 0 && Number.isInteger(profileId) && profileId > 0 && authId === profileId
})
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
  const observationId = Number(observationForPost(post)?.id || 0)
  if (Number.isInteger(observationId) && observationId > 0) {
    router.push(`/observations/${observationId}`)
    return
  }

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
  return isProfileImage(post)
}

function attachmentSrc(post) {
  return resolveAttachmentSrc(post, http?.defaults?.baseURL || '')
}

function parentHandle(post) {
  const parentUser = post?.parent?.user
  if (parentUser?.username) return parentUser.username
  const name = toNonEmptyText(parentUser?.name)
  if (name && !looksLikeEmail(name)) return safeHandle(name)
  return 'user'
}

function updatePostPoll(post, nextPoll) {
  if (!post || !nextPoll) return
  post.poll = nextPoll
}

function onPollLoginRequired() {
  tabState[activeTab.value].err = 'Prihlás sa pre hlasovanie.'
}

function publicObservationParams(overrides = {}) {
  const userId = Number(user.value?.id || 0)
  if (!Number.isInteger(userId) || userId <= 0) return null

  return {
    user_id: userId,
    public_only: true,
    ...overrides,
  }
}

async function copyProfileLink() {
  if (!hasUsername.value) return
  const url = `${window.location.origin}/u/${encodedUsername.value}`
  try {
    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
      await navigator.share({
        title: displayName.value,
        text: `Profil @${handle.value} na Astrokomunita`,
        url,
      })
      copyLabel.value = 'Zdieľané'
    } else {
      await navigator.clipboard.writeText(url)
      copyLabel.value = 'Skopírované'
    }
  } catch (error) {
    if (error?.name === 'AbortError') return
    copyLabel.value = 'Nepodarilo sa zdieľať'
  }
  setTimeout(() => {
    copyLabel.value = 'Zdieľať'
  }, 1500)
}

function canDelete(post) {
  return canDeletePost(post, auth.user)
}

function canReport(post) {
  return canReportPost(post, auth.user)
}

function setActiveTabError(message) {
  const state = tabState[activeTab.value]
  if (!state) return
  state.err = String(message || '')
}

function clearActiveTabError() {
  setActiveTabError('')
}

function isLikeLoading(post) {
  const postId = Number(post?.id || 0)
  return likeLoadingIds.value.has(postId)
}

function setLikeLoading(postId, enabled) {
  const id = Number(postId || 0)
  if (!Number.isInteger(id) || id <= 0) return

  const next = new Set(likeLoadingIds.value)
  if (enabled) next.add(id)
  else next.delete(id)
  likeLoadingIds.value = next
}

function bumpLike(postId) {
  likeBumpId.value = postId
  window.setTimeout(() => {
    if (likeBumpId.value === postId) likeBumpId.value = null
  }, 220)
}

function isBookmarkLoading(post) {
  return bookmarks.isLoading(post?.id)
}

function openShareModal(post) {
  if (!post?.id) return
  shareTarget.value = post
}

function closeShareModal() {
  shareTarget.value = null
}

function menuItemsForPost(post) {
  const baseItems = buildBasePostMenuItems({
    hasOriginalDownload: hasOriginalDownload(post),
    canReport: canReport(post),
    canDelete: canDelete(post),
  })

  if (auth.user?.is_admin && post?.id) {
    baseItems.push(buildPinPostMenuItem(post))
  }

  return baseItems.filter(Boolean)
}

function downloadOriginalAttachment(post) {
  const url = resolveAbsoluteUrl(post?.attachment_download_url, http?.defaults?.baseURL || '')
  if (!url) return

  toastInfo('Sťahujem...')
  try {
    window.open(url, '_blank', 'noopener')
  } catch {
    toastError('Stiahnutie zlyhalo.')
  }
}

async function toggleLike(post) {
  if (!post?.id || isLikeLoading(post)) return
  if (!isAuthed.value) {
    setActiveTabError('Prihlás sa pre lajkovanie.')
    return
  }

  clearActiveTabError()
  const prevLiked = !!post.liked_by_me
  const prevCount = Number(post.likes_count ?? 0) || 0

  post.liked_by_me = !prevLiked
  post.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
  bumpLike(post.id)
  setLikeLoading(post.id, true)

  try {
    if (typeof auth.csrf === 'function') {
      await auth.csrf()
    }

    const response = prevLiked
      ? await http.delete(`/posts/${post.id}/like`)
      : await http.post(`/posts/${post.id}/like`)

    const payload = response?.data || {}
    if (payload.likes_count !== undefined) post.likes_count = Number(payload.likes_count || 0)
    if (payload.liked_by_me !== undefined) post.liked_by_me = Boolean(payload.liked_by_me)
  } catch (error) {
    post.liked_by_me = prevLiked
    post.likes_count = prevCount
    const status = Number(error?.response?.status || 0)
    if (status === 401) setActiveTabError('Prihlás sa.')
    else setActiveTabError(error?.response?.data?.message || 'Lajk zlyhal.')
  } finally {
    setLikeLoading(post.id, false)
  }
}

async function toggleBookmark(post) {
  if (!post?.id || isBookmarkLoading(post)) return
  if (!isAuthed.value) {
    setActiveTabError('Prihlás sa pre záložky.')
    return
  }

  clearActiveTabError()
  const previousState = Boolean(post.is_bookmarked)
  const previousBookmarkedAt = post.bookmarked_at || null
  const nextState = !previousState

  post.is_bookmarked = nextState
  post.bookmarked_at = nextState ? new Date().toISOString() : null
  bookmarks.setBookmarked(post.id, nextState)

  try {
    if (typeof auth.csrf === 'function') {
      await auth.csrf()
    }

    const state = await bookmarks.toggleBookmark(post.id, previousState)
    post.is_bookmarked = state
    post.bookmarked_at = state ? (post.bookmarked_at || new Date().toISOString()) : null
  } catch (error) {
    post.is_bookmarked = previousState
    post.bookmarked_at = previousBookmarkedAt
    bookmarks.setBookmarked(post.id, previousState)
    setActiveTabError(error?.response?.data?.message || 'Uloženie záložky zlyhalo.')
    toastError('Uloženie záložky zlyhalo.')
  }
}

function openReport(post) {
  if (!post?.id || !canReport(post)) return
  reportTarget.value = post
}

function closeReport() {
  reportTarget.value = null
  reportReason.value = 'spam'
  reportMessage.value = ''
}

async function submitReport() {
  const post = reportTarget.value
  if (!post?.id) return

  try {
    if (typeof auth.csrf === 'function') {
      await auth.csrf()
    }

    await http.post('/reports', {
      target_id: post.id,
      reason: reportReason.value,
      message: reportMessage.value || null,
    })

    clearActiveTabError()
    toastSuccess('Ďakujeme, nahlásenie sme prijali.')
  } catch (error) {
    const status = Number(error?.response?.status || 0)
    if (status === 401) setActiveTabError('Prihlás sa.')
    else if (status === 409) setActiveTabError('Už si reportoval tento príspevok.')
    else setActiveTabError(error?.response?.data?.message || 'Nahlásenie zlyhalo.')
  } finally {
    closeReport()
  }
}

function removePostFromAllTabs(postId) {
  const id = Number(postId || 0)
  if (!Number.isInteger(id) || id <= 0) return

  Object.keys(tabState).forEach((tabKey) => {
    const state = tabState[tabKey]
    state.items = state.items.filter((item) => Number(item?.id || 0) !== id)
  })
}

async function deletePost(post) {
  if (!post?.id || deleteLoadingId.value) return
  if (!canDelete(post)) return

  clearActiveTabError()
  deleteLoadingId.value = post.id

  try {
    if (typeof auth.csrf === 'function') {
      await auth.csrf()
    }
    await http.delete(`/posts/${post.id}`)
    removePostFromAllTabs(post.id)
    toastSuccess('Príspevok bol zmazaný.')
  } catch (error) {
    const status = Number(error?.response?.status || 0)
    if (status === 401) setActiveTabError('Prihlás sa.')
    else if (status === 403) setActiveTabError('Nemáš oprávnenie.')
    else setActiveTabError(error?.response?.data?.message || 'Mazanie zlyhalo.')
  } finally {
    deleteLoadingId.value = null
  }
}

async function confirmDelete(post) {
  if (!post?.id || !canDelete(post) || deleteLoadingId.value) return

  const approved = await confirm({
    title: 'Zmazať príspevok?',
    message: 'Túto akciu už nie je možné vrátiť.',
    confirmText: 'Zmazať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })

  if (!approved) return
  await deletePost(post)
}

async function togglePin(post) {
  if (!post?.id || pinLoadingId.value) return
  if (!auth.user?.is_admin) {
    setActiveTabError('Akcia je dostupná len pre admina.')
    return
  }

  clearActiveTabError()
  pinLoadingId.value = post.id
  const wasPinned = Boolean(post.pinned_at)

  try {
    if (typeof auth.csrf === 'function') {
      await auth.csrf()
    }

    if (wasPinned) {
      await http.patch(`/admin/posts/${post.id}/unpin`)
      post.pinned_at = null
      toastSuccess('Príspevok bol odopnutý.')
    } else {
      await http.patch(`/admin/posts/${post.id}/pin`)
      post.pinned_at = new Date().toISOString()
      toastSuccess('Príspevok bol pripnutý.')
    }
  } catch (error) {
    const status = Number(error?.response?.status || 0)
    if (status === 401) setActiveTabError('Prihlás sa.')
    else if (status === 403) setActiveTabError('Nemáš oprávnenie.')
    else setActiveTabError(error?.response?.data?.message || 'Zmena pripnutia zlyhala.')
  } finally {
    pinLoadingId.value = null
  }
}

function onPostMenuAction(item, post) {
  if (!item?.key || !post?.id) return

  handleCommonPostMenuAction(item, post, {
    downloadOriginalAttachment,
    openReport,
    confirmDelete: (nextPost) => {
      void confirmDelete(nextPost)
    },
    togglePin: (nextPost) => {
      void togglePin(nextPost)
    },
  })
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
    else err.value = e?.response?.data?.message || 'Načítanie profilu zlyhalo.'
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

  const observationParams = publicObservationParams({ page: 1, per_page: 1 })

  const [postsResult, repliesResult, mediaResult, obsResult] = await Promise.allSettled([
    http.get(`/users/${encodedUsername.value}/posts`, { params: { kind: 'roots', per_page: 1 } }),
    http.get(`/users/${encodedUsername.value}/posts`, { params: { kind: 'replies', per_page: 1 } }),
    http.get(`/users/${encodedUsername.value}/posts`, { params: { kind: 'media', per_page: 1 } }),
    observationParams
      ? listObservations(observationParams)
      : Promise.reject(new Error('no user id')),
  ])

  const results = [postsResult, repliesResult, mediaResult]
  for (let i = 0; i < kinds.length; i++) {
    const k = kinds[i]
    const result = results[i]
    if (result.status === 'fulfilled') {
      const data = result.value?.data
      const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
      stats[k.key] = String(total)
      if (tabState[k.key]) tabState[k.key].total = String(total)
    } else {
      stats[k.key] = '--'
      if (tabState[k.key]) tabState[k.key].total = '--'
    }
  }

  if (obsResult.status === 'fulfilled') {
    const data = obsResult.value?.data
    const total = Number.isFinite(data?.total) ? data.total : data?.data?.length || 0
    tabState.observations.total = String(total)
  } else {
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
      const page = reset ? 1 : Number(state.next || 0)
      if (!page) return
      const observationParams = publicObservationParams({
        page,
        per_page: 10,
      })
      if (!observationParams) return

      const { data } = await listObservations(observationParams)

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
    bookmarks.hydrateFromPosts(rows)
    if (reset) state.items = rows
    else state.items = [...state.items, ...rows]

    state.next = data?.next_page_url ?? null
    state.total = Number.isFinite(data?.total) ? String(data.total) : state.total
    state.loaded = true
  } catch (e) {
    state.err = e?.response?.data?.message || 'Načítanie zlyhalo.'
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
