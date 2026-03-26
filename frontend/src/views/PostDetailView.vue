<template src="./postDetail/PostDetailView.template.html"></template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import HashtagText from '@/components/HashtagText.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import PostActionBar from '@/components/PostActionBar.vue'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'
import api from '@/services/api'
import PostMediaImage from '@/components/media/PostMediaImage.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useAuthStore } from '@/stores/auth'
import { useBookmarksStore } from '@/stores/bookmarks'
import { useToast } from '@/composables/useToast'
import { canReportPost } from '@/utils/postPermissions'
import { formatRelativeShort } from '@/utils/dateUtils'
import {
  attachmentDownloadSrc as resolveAttachmentDownloadSrc,
  attachmentSrc as resolveAttachmentSrc,
  attachedEventForPost,
  canAdminEditBotPost as resolveCanAdminEditBotPost,
  formatEventRange,
  isAttachmentBlocked,
  isAttachmentPending,
  isBotPost as resolveIsBotPost,
  isImage,
  postGifTitle,
  postGifUrl as resolvePostGifUrl,
} from './postDetail/postDetailView.utils'

const PollCard = defineAsyncComponent(() => import('@/components/PollCard.vue'))
const ShareModal = defineAsyncComponent(() => import('@/components/share/ShareModal.vue'))
const ReplyComposer = defineAsyncComponent(() => import('@/components/ReplyComposer.vue'))

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const bookmarks = useBookmarksStore()
const { info: toastInfo, error: toastError } = useToast()

const post = ref(null)
const root = ref(null)
const replies = ref([])
const activeReplyId = ref(null)
const rootComposerRef = ref(null)
const highlightReplyId = ref(null)

const loading = ref(true)
const error = ref('')
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')
const reportNotice = ref('')

const reportOptions = [
  { value: 'spam',       icon: '🚫', label: 'Spam' },
  { value: 'abuse',      icon: '⚠️',  label: 'Nevhodný obsah' },
  { value: 'misinfo',    icon: '📢', label: 'Dezinformácie' },
  { value: 'harassment', icon: '😤', label: 'Obťažovanie' },
  { value: 'other',      icon: '💬', label: 'Iné' },
]
const editingPostId = ref(null)
const editContentDraft = ref('')
const editSavingId = ref(null)
const likeLoadingIds = ref(new Set())
const likeBumpId = ref(null)
const shareTarget = ref(null)
let highlightReplyTimer = null

const apiBaseUrl = api?.defaults?.baseURL || ''
const attachmentSrc = (item) => resolveAttachmentSrc(item, apiBaseUrl)
const attachmentDownloadSrc = (item) => resolveAttachmentDownloadSrc(item, apiBaseUrl)
const postGifUrl = (item) => resolvePostGifUrl(item, apiBaseUrl)
const isBotPost = (item) => resolveIsBotPost(item)
const canAdminEditBotPost = (item) => resolveCanAdminEditBotPost(item, auth.user)

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/')
  }
}

function openProfile(user) {
  const username = user?.username
  if (!username) return
  router.push(`/u/${username}`)
}

function fmt(iso) {
  return formatRelativeShort(iso)
}

function openAttachedEvent(post) {
  const eventId = Number(attachedEventForPost(post)?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function clearReplyHighlightTimer() {
  if (highlightReplyTimer !== null) {
    window.clearTimeout(highlightReplyTimer)
    highlightReplyTimer = null
  }
}

function highlightReply(replyId) {
  highlightReplyId.value = replyId
  clearReplyHighlightTimer()
  highlightReplyTimer = window.setTimeout(() => {
    if (Number(highlightReplyId.value) === Number(replyId)) {
      highlightReplyId.value = null
    }
    highlightReplyTimer = null
  }, 2400)
}

function scrollReplyIntoView(replyId) {
  nextTick(() => {
    const node = document.querySelector(`[data-reply-id="${replyId}"]`)
    if (node instanceof HTMLElement) {
      node.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    }
  })
}

function onReplyCreated(newReply) {
  if (!newReply?.id) return

  const rootId = root.value?.id
  if (!rootId) return

  if (Number(newReply.parent_id) === Number(rootId)) {
    const next = [...replies.value, { ...newReply, replies: [] }]
    next.sort((a, b) => new Date(a?.created_at || 0) - new Date(b?.created_at || 0))
    replies.value = next
  } else {
    const parent = replies.value.find((r) => Number(r.id) === Number(newReply.parent_id))
    if (parent) {
      const children = Array.isArray(parent.replies) ? [...parent.replies, newReply] : [newReply]
      children.sort((a, b) => new Date(a?.created_at || 0) - new Date(b?.created_at || 0))
      parent.replies = children
      replies.value = [...replies.value]
    }
  }

  // keep local counter (if exists)
  if (root.value && typeof root.value === 'object') {
    const curr = Number(root.value.replies_count ?? replies.value.length - 1)
    root.value.replies_count = Number.isFinite(curr) ? curr + 1 : replies.value.length
  }

  highlightReply(newReply.id)
  scrollReplyIntoView(newReply.id)
  activeReplyId.value = null
}

function toggleReplyComposer(id) {
  activeReplyId.value = activeReplyId.value === id ? null : id
}

function focusReplyComposer() {
  activeReplyId.value = null
  nextTick(() => {
    rootComposerRef.value?.focusInput?.()
  })
}

function isLikeLoading(item) {
  return likeLoadingIds.value.has(item?.id)
}

function setLikeLoading(id, on) {
  const next = new Set(likeLoadingIds.value)
  if (on) next.add(id)
  else next.delete(id)
  likeLoadingIds.value = next
}

function bumpLike(id) {
  likeBumpId.value = id
  window.setTimeout(() => {
    if (likeBumpId.value === id) likeBumpId.value = null
  }, 220)
}

function isBookmarkLoading(item) {
  return bookmarks.isLoading(item?.id)
}

async function toggleLike(item) {
  if (!item?.id || isLikeLoading(item)) return
  if (!auth.isAuthed) {
    reportNotice.value = 'Prihlás sa pre lajkovanie.'
    return
  }

  reportNotice.value = ''
  const prevLiked = !!item.liked_by_me
  const prevCount = Number(item.likes_count ?? 0) || 0

  item.liked_by_me = !prevLiked
  item.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
  bumpLike(item.id)
  setLikeLoading(item.id, true)

  try {
    await auth.csrf()
    const res = prevLiked
      ? await api.delete(`/posts/${item.id}/like`)
      : await api.post(`/posts/${item.id}/like`)

    const data = res?.data
    if (data?.likes_count !== undefined) item.likes_count = data.likes_count
    if (data?.liked_by_me !== undefined) item.liked_by_me = data.liked_by_me
  } catch (e) {
    item.liked_by_me = prevLiked
    item.likes_count = prevCount
    reportNotice.value = e?.response?.data?.message || 'Lajk zlyhal.'
  } finally {
    setLikeLoading(item.id, false)
  }
}

async function toggleBookmark(item) {
  if (!item?.id || isBookmarkLoading(item)) return
  if (!auth.isAuthed) {
    reportNotice.value = 'Prihlás sa pre záložky.'
    return
  }

  reportNotice.value = ''
  const prevBookmarked = !!item.is_bookmarked
  const prevBookmarkedAt = item.bookmarked_at || null
  const nextBookmarked = !prevBookmarked

  item.is_bookmarked = nextBookmarked
  item.bookmarked_at = nextBookmarked ? new Date().toISOString() : null
  bookmarks.setBookmarked(item.id, nextBookmarked)

  try {
    await auth.csrf()
    const state = await bookmarks.toggleBookmark(item.id, prevBookmarked)
    item.is_bookmarked = state
    item.bookmarked_at = state ? item.bookmarked_at || new Date().toISOString() : null
  } catch (e) {
    item.is_bookmarked = prevBookmarked
    item.bookmarked_at = prevBookmarkedAt
    bookmarks.setBookmarked(item.id, prevBookmarked)
    reportNotice.value = e?.response?.data?.message || 'Uloženie záložky zlyhalo.'
  }
}

function openShareModal(item) {
  if (!item?.id) return
  shareTarget.value = item
}

function closeShareModal() {
  shareTarget.value = null
}

function openReport(post) {
  if (!post?.id) return
  if (!canReportPost(post, auth.user)) return
  reportTarget.value = post
  reportNotice.value = ''
}

function menuItemsForPost(post) {
  const items = []

  if (hasOriginalDownload(post)) {
    items.push({
      key: 'download_original',
      label: 'Stiahnu\u0165 v plnej kvalite',
      danger: false,
      icon: 'download',
    })
  }

  if (canReportPost(post, auth.user)) {
    items.push({ key: 'report', label: 'Nahlásiť', danger: false, icon: 'report' })
  }

  if (canAdminEditBotPost(post)) {
    items.push({ key: 'edit', label: 'Upraviť', danger: false })
  }

  return items
}

function onMenuAction(item, post) {
  if (!item?.key || !post?.id) return
  if (item.key === 'download_original') {
    downloadOriginalAttachment(post)
    return
  }
  if (item.key === 'report') {
    openReport(post)
    return
  }
  if (item.key === 'edit') {
    startInlineEdit(post)
  }
}

function isEditingPost(post) {
  return Number(editingPostId.value) === Number(post?.id)
}

function startInlineEdit(post) {
  if (!post?.id || !canAdminEditBotPost(post)) return

  editingPostId.value = Number(post.id)
  editContentDraft.value = String(post?.content || '')
}

function cancelInlineEdit() {
  editingPostId.value = null
  editContentDraft.value = ''
}

async function saveInlineEdit(post) {
  if (!post?.id || !isEditingPost(post) || editSavingId.value) return
  if (!canAdminEditBotPost(post)) return

  const currentContent = String(post?.content || '')
  const trimmed = editContentDraft.value.trim()
  if (!trimmed || trimmed === currentContent) {
    cancelInlineEdit()
    return
  }

  try {
    editSavingId.value = post.id
    let res = null
    try {
      await auth.csrf()
      res = await api.patch(
        `/posts/${post.id}`,
        { content: trimmed, edit_variant: 'translated' },
        { meta: { skipErrorToast: true } },
      )
    } catch (e) {
      const status = Number(e?.response?.status || 0)
      if (status !== 401 && status !== 419) throw e
      await auth.fetchUser({ source: 'inline-post-edit', retry: false, markBootstrap: true })
      await auth.csrf()
      res = await api.patch(
        `/posts/${post.id}`,
        { content: trimmed, edit_variant: 'translated' },
        { meta: { skipErrorToast: true } },
      )
    }

    const updated = res?.data
    if (updated && typeof updated === 'object') {
      Object.assign(post, updated)
    }

    post.content = trimmed
    if (post?.meta && typeof post.meta === 'object') {
      const nextMeta = { ...post.meta }
      nextMeta.translated_content = trimmed
      nextMeta.used_translation = true
      post.meta = nextMeta
    }
    cancelInlineEdit()
  } catch (e) {
    const status = Number(e?.response?.status || 0)
    const message =
      status === 401 || status === 419
        ? 'Relácia vypršala. Prihlás sa znova.'
        : e?.response?.data?.message || 'Úprava príspevku zlyhala.'
    reportNotice.value = message
    toastError(message)
  } finally {
    editSavingId.value = null
  }
}

function hasOriginalDownload(post) {
  if (!isImage(post)) return false
  if (isAttachmentPending(post) || isAttachmentBlocked(post)) return false
  return Boolean(post?.attachment_download_url)
}

function downloadOriginalAttachment(post) {
  const url = attachmentDownloadSrc(post)
  if (!url) return

  toastInfo('S\u0165ahujem...')
  try {
    window.open(url, '_blank', 'noopener')
  } catch {
    toastError('Stiahnutie zlyhalo.')
  }
}

function closeReport() {
  reportTarget.value = null
  reportReason.value = 'spam'
  reportMessage.value = ''
}

function updateRootPoll(nextPoll) {
  if (!root.value || !nextPoll) return
  root.value.poll = nextPoll
}

function onPollLoginRequired() {
  reportNotice.value = 'Prihlás sa pre hlasovanie.'
}

function onAttachmentUnblurred(item, { isBlurred, status }) {
  if (!item || typeof item !== 'object') return
  item.attachment_is_blurred = isBlurred
  if (typeof status === 'string' && status.trim() !== '') {
    item.attachment_moderation_status = status
  }
}


async function submitReport() {
  const post = reportTarget.value
  if (!post?.id) return
  if (!canReportPost(post, auth.user)) {
    closeReport()
    return
  }

  try {
    await auth.csrf()
    await api.post('/reports', {
      target_id: post.id,
      reason: reportReason.value,
      message: reportMessage.value || null,
    })
    reportNotice.value = 'Ďakujeme, nahlásenie sme prijali.'
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) reportNotice.value = 'Prihlás sa.'
    else if (status === 409) reportNotice.value = 'Už si reportoval tento post.'
    else reportNotice.value = e?.response?.data?.message || 'Nahlásenie zlyhalo.'
  } finally {
    closeReport()
  }
}

async function loadPost() {
  const hasSeed = root.value !== null
  if (!hasSeed) loading.value = true
  error.value = ''
  if (!hasSeed) {
    post.value = null
    root.value = null
    replies.value = []
  }
  activeReplyId.value = null
  highlightReplyId.value = null
  clearReplyHighlightTimer()

  try {
    const res = await api.get(`/posts/${route.params.id}`)
    const payload = res.data || {}

    post.value = payload.post ?? null
    root.value = payload.root ?? payload.post ?? null
    if (root.value?.id) {
      bookmarks.hydrateFromPosts([root.value])
    }

    if (Array.isArray(payload.replies) && payload.replies.length > 0) {
      replies.value = payload.replies
    } else {
      const thread = Array.isArray(payload.thread) ? payload.thread : []
      const rootId = root.value?.id
      const byParent = thread.reduce((acc, p) => {
        const key = p?.parent_id ?? null
        if (!acc[key]) acc[key] = []
        acc[key].push(p)
        return acc
      }, {})

      const rootReplies = (byParent[rootId] || []).map((p) => ({
        ...p,
        replies: (byParent[p.id] || []).slice(),
      }))

      replies.value = rootReplies
    }

  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      'Príspevok sa nepodarilo načítať.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  const seed = window.history.state?.seedPost
  if (seed && String(seed.id) === String(route.params.id)) {
    post.value = seed
    root.value = seed
    loading.value = false
    if (seed.id) bookmarks.hydrateFromPosts([seed])
  }
  loadPost()
})

onBeforeUnmount(() => {
  clearReplyHighlightTimer()
})

watch(
  () => route.params.id,
  () => {
    post.value = null
    root.value = null
    replies.value = []
    loadPost()
  }
)

const repliesCount = computed(() => {
  return replies.value.reduce((acc, r) => {
    const childCount = Array.isArray(r.replies) ? r.replies.length : 0
    return acc + 1 + childCount
  }, 0)
})

const repliesCountLabel = computed(() => {
  const count = Number(repliesCount.value || 0)
  if (count === 1) return '1 odpoveď'
  if (count >= 2 && count <= 4) return `${count} odpovede`
  return `${count} odpovedí`
})
</script>

<style scoped src="./postDetail/PostDetailView.css"></style>
