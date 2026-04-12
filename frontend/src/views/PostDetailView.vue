<template src="./postDetail/PostDetailView.template.html"></template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageMeta } from '@/composables/usePageMeta'
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
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { canDeletePost, canReportPost } from '@/utils/postPermissions'
import { buildBasePostMenuItems, buildPinPostMenuItem, handleCommonPostMenuAction } from '@/utils/postMenu'
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
const { setMeta, setJsonLd, resetMeta } = usePageMeta()
const bookmarks = useBookmarksStore()
const { confirm } = useConfirm()
const { info: toastInfo, error: toastError, success: toastSuccess } = useToast()

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
  { value: 'spam', icon: '🚫', label: 'Spam' },
  { value: 'abuse', icon: '⚠️', label: 'Nevhodný obsah' },
  { value: 'misinfo', icon: '📢', label: 'Dezinformácie' },
  { value: 'harassment', icon: '😤', label: 'Obťažovanie' },
  { value: 'other', icon: '💬', label: 'Iné' },
]

const editingPostId = ref(null)
const editContentDraft = ref('')
const editSavingId = ref(null)
const deleteLoadingId = ref(null)
const likeLoadingIds = ref(new Set())
const likeBumpId = ref(null)
const pinLoadingId = ref(null)
const shareTarget = ref(null)
let highlightReplyTimer = null

const apiBaseUrl = api?.defaults?.baseURL || ''
const attachmentSrc = (item) => resolveAttachmentSrc(item, apiBaseUrl)
const attachmentDownloadSrc = (item) => resolveAttachmentDownloadSrc(item, apiBaseUrl)
const postGifUrl = (item) => resolvePostGifUrl(item, apiBaseUrl)
const isBotPost = (item) => resolveIsBotPost(item)
const canAdminEditBotPost = (item) => resolveCanAdminEditBotPost(item, auth.user)

function extractPostTitle(p) {
  const explicit = p?.translated_title || p?.original_title
  if (explicit) return String(explicit).trim().slice(0, 80)
  const text = String(p?.content || '').replace(/<[^>]+>/g, '').replace(/#\w+/g, '').trim()
  const first = text.split('\n')[0].trim()
  return first.length > 10 ? first.slice(0, 80) : null
}

function extractPostExcerpt(p) {
  return String(p?.content || '')
    .replace(/<[^>]+>/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 160) || null
}

function applyPostMeta(p) {
  if (!p) return

  const postTitle = extractPostTitle(p)
  const postDesc = extractPostExcerpt(p)
  const authorName = p.user?.name || p.user?.username || 'Používateľ'
  const pageUrl = `https://astrokomunita.sk/posts/${p.id}`
  const image = p.attachment_web_path
    ? `${api?.defaults?.baseURL || ''}${p.attachment_web_path}`
    : null

  setMeta({
    title: postTitle || `Príspevok od ${authorName}`,
    description: postDesc,
    image,
    url: pageUrl,
    type: 'article',
  })

  // schema.org/SocialMediaPosting for community posts
  setJsonLd({
    '@context': 'https://schema.org',
    '@type': 'SocialMediaPosting',
    headline: postTitle || `Príspevok od ${authorName}`,
    text: postDesc || undefined,
    datePublished: p.created_at ?? undefined,
    url: pageUrl,
    author: {
      '@type': 'Person',
      name: authorName,
    },
    publisher: {
      '@type': 'Organization',
      name: 'Astrokomunita',
      url: 'https://astrokomunita.sk',
    },
  })
}

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

function fmtFull(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString('sk-SK', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return fmt(iso)
  }
}

function openAttachedEvent(item) {
  const eventId = Number(attachedEventForPost(item)?.id || 0)
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
    const parent = replies.value.find((row) => Number(row.id) === Number(newReply.parent_id))
    if (parent) {
      const children = Array.isArray(parent.replies) ? [...parent.replies, newReply] : [newReply]
      children.sort((a, b) => new Date(a?.created_at || 0) - new Date(b?.created_at || 0))
      parent.replies = children
      replies.value = [...replies.value]
    }
  }

  if (root.value && typeof root.value === 'object') {
    const current = Number(root.value.replies_count ?? replies.value.length - 1)
    root.value.replies_count = Number.isFinite(current) ? current + 1 : replies.value.length
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

function setLikeLoading(id, enabled) {
  const next = new Set(likeLoadingIds.value)
  if (enabled) next.add(id)
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
  const prevLiked = Boolean(item.liked_by_me)
  const prevCount = Number(item.likes_count ?? 0) || 0

  item.liked_by_me = !prevLiked
  item.likes_count = Math.max(0, prevCount + (prevLiked ? -1 : 1))
  bumpLike(item.id)
  setLikeLoading(item.id, true)

  try {
    await auth.csrf()
    const response = prevLiked
      ? await api.delete(`/posts/${item.id}/like`)
      : await api.post(`/posts/${item.id}/like`)

    const data = response?.data
    if (data?.likes_count !== undefined) item.likes_count = data.likes_count
    if (data?.liked_by_me !== undefined) item.liked_by_me = data.liked_by_me
  } catch (fetchError) {
    item.liked_by_me = prevLiked
    item.likes_count = prevCount
    reportNotice.value = fetchError?.response?.data?.message || 'Lajk zlyhal.'
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
  const prevBookmarked = Boolean(item.is_bookmarked)
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
  } catch (fetchError) {
    item.is_bookmarked = prevBookmarked
    item.bookmarked_at = prevBookmarkedAt
    bookmarks.setBookmarked(item.id, prevBookmarked)
    reportNotice.value = fetchError?.response?.data?.message || 'Uloženie záložky zlyhalo.'
  }
}

function openShareModal(item) {
  if (!item?.id) return
  shareTarget.value = item
}

function closeShareModal() {
  shareTarget.value = null
}

function canDelete(item) {
  return canDeletePost(item, auth.user)
}

function canPin(item) {
  return Boolean(
    auth.user?.is_admin &&
    item?.id &&
    !isBotPost(item) &&
    Number(item?.parent_id || 0) === 0,
  )
}

function openReport(item) {
  if (!item?.id) return
  if (!canReportPost(item, auth.user)) return
  reportTarget.value = item
  reportNotice.value = ''
}

async function deletePost(item) {
  if (!item?.id || deleteLoadingId.value) return
  if (!canDelete(item)) return

  reportNotice.value = ''
  deleteLoadingId.value = item.id

  try {
    await auth.csrf()
    await api.delete(`/posts/${item.id}`)

    if (Number(root.value?.id) === Number(item.id)) {
      toastSuccess('Príspevok bol zmazaný.')
      goBack()
      return
    }

    await loadPost()
    toastSuccess('Príspevok bol zmazaný.')
  } catch (fetchError) {
    const status = Number(fetchError?.response?.status || 0)
    reportNotice.value =
      status === 401
        ? 'Prihlás sa.'
        : status === 403
          ? 'Nemáš oprávnenie.'
          : fetchError?.response?.data?.message || 'Mazanie zlyhalo.'
  } finally {
    deleteLoadingId.value = null
  }
}

async function confirmDelete(item) {
  if (!item?.id || !canDelete(item) || deleteLoadingId.value === item.id) return

  const approved = await confirm({
    title: 'Zmazať príspevok?',
    message: 'Túto akciu už nie je možné vrátiť.',
    confirmText: 'Zmazať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })

  if (!approved) return
  await deletePost(item)
}

async function togglePin(item) {
  if (!item?.id || pinLoadingId.value) return
  if (!canPin(item)) {
    reportNotice.value = 'Akcia je dostupná len pre admina.'
    return
  }

  reportNotice.value = ''
  pinLoadingId.value = item.id
  const wasPinned = Boolean(item?.pinned_at)

  try {
    await auth.csrf()
    if (wasPinned) {
      await api.patch(`/admin/posts/${item.id}/unpin`)
    } else {
      await api.patch(`/admin/posts/${item.id}/pin`)
    }

    await loadPost()
    toastSuccess(wasPinned ? 'Príspevok bol odopnutý.' : 'Príspevok bol pripnutý.')
  } catch (fetchError) {
    const status = Number(fetchError?.response?.status || 0)
    reportNotice.value =
      status === 401
        ? 'Prihlás sa.'
        : status === 403
          ? 'Nemáš oprávnenie.'
          : fetchError?.response?.data?.message || 'Zmena pripnutia zlyhala.'
  } finally {
    pinLoadingId.value = null
  }
}

function menuItemsForPost(item) {
  const items = buildBasePostMenuItems({
    hasOriginalDownload: hasOriginalDownload(item),
    canReport: canReportPost(item, auth.user),
    canDelete: canDelete(item),
  })

  if (canAdminEditBotPost(item)) {
    items.push({ key: 'edit', label: 'Upraviť', danger: false })
  }

  if (canPin(item)) {
    items.push(buildPinPostMenuItem(item))
  }

  return items
}

function onMenuAction(menuItem, item) {
  if (!menuItem?.key || !item?.id) return

  if (handleCommonPostMenuAction(menuItem, item, {
    downloadOriginalAttachment,
    openReport,
    confirmDelete: (nextItem) => void confirmDelete(nextItem),
    togglePin: (nextItem) => void togglePin(nextItem),
  })) {
    return
  }

  if (menuItem.key === 'edit') {
    startInlineEdit(item)
  }
}

function isEditingPost(item) {
  return Number(editingPostId.value) === Number(item?.id)
}

function startInlineEdit(item) {
  if (!item?.id || !canAdminEditBotPost(item)) return

  editingPostId.value = Number(item.id)
  editContentDraft.value = String(item?.content || '')
}

function cancelInlineEdit() {
  editingPostId.value = null
  editContentDraft.value = ''
}

async function saveInlineEdit(item) {
  if (!item?.id || !isEditingPost(item) || editSavingId.value) return
  if (!canAdminEditBotPost(item)) return

  const currentContent = String(item?.content || '')
  const trimmed = editContentDraft.value.trim()
  if (!trimmed || trimmed === currentContent) {
    cancelInlineEdit()
    return
  }

  try {
    editSavingId.value = item.id
    let response = null

    try {
      await auth.csrf()
      response = await api.patch(
        `/posts/${item.id}`,
        { content: trimmed, edit_variant: 'translated' },
        { meta: { skipErrorToast: true } },
      )
    } catch (fetchError) {
      const status = Number(fetchError?.response?.status || 0)
      if (status !== 401 && status !== 419) throw fetchError
      await auth.fetchUser({ source: 'inline-post-edit', retry: false, markBootstrap: true })
      await auth.csrf()
      response = await api.patch(
        `/posts/${item.id}`,
        { content: trimmed, edit_variant: 'translated' },
        { meta: { skipErrorToast: true } },
      )
    }

    const updated = response?.data
    if (updated && typeof updated === 'object') {
      Object.assign(item, updated)
    }

    item.content = trimmed
    if (item?.meta && typeof item.meta === 'object') {
      const nextMeta = { ...item.meta }
      nextMeta.translated_content = trimmed
      nextMeta.used_translation = true
      item.meta = nextMeta
    }

    cancelInlineEdit()
  } catch (fetchError) {
    const status = Number(fetchError?.response?.status || 0)
    const message =
      status === 401 || status === 419
        ? 'Relácia vypršala. Prihlás sa znova.'
        : fetchError?.response?.data?.message || 'Úprava príspevku zlyhala.'
    reportNotice.value = message
    toastError(message)
  } finally {
    editSavingId.value = null
  }
}

function hasOriginalDownload(item) {
  if (!isImage(item)) return false
  if (isAttachmentPending(item) || isAttachmentBlocked(item)) return false
  return Boolean(item?.attachment_download_url)
}

function downloadOriginalAttachment(item) {
  const url = attachmentDownloadSrc(item)
  if (!url) return

  toastInfo('Sťahujem...')
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
  const item = reportTarget.value
  if (!item?.id) return
  if (!canReportPost(item, auth.user)) {
    closeReport()
    return
  }

  try {
    await auth.csrf()
    await api.post('/reports', {
      target_id: item.id,
      reason: reportReason.value,
      message: reportMessage.value || null,
    })
    reportNotice.value = 'Ďakujeme, nahlásenie sme prijali.'
  } catch (fetchError) {
    const status = fetchError?.response?.status
    if (status === 401) reportNotice.value = 'Prihlás sa.'
    else if (status === 409) reportNotice.value = 'Už si reportoval tento post.'
    else reportNotice.value = fetchError?.response?.data?.message || 'Nahlásenie zlyhalo.'
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
    const response = await api.get(`/posts/${route.params.id}`)
    const payload = response.data || {}

    post.value = payload.post ?? null
    root.value = payload.root ?? payload.post ?? null
    if (root.value?.id) {
      bookmarks.hydrateFromPosts([root.value])
    }
    applyPostMeta(root.value)

    if (Array.isArray(payload.replies) && payload.replies.length > 0) {
      replies.value = payload.replies
    } else {
      const thread = Array.isArray(payload.thread) ? payload.thread : []
      const rootId = root.value?.id
      const byParent = thread.reduce((acc, row) => {
        const key = row?.parent_id ?? null
        if (!acc[key]) acc[key] = []
        acc[key].push(row)
        return acc
      }, {})

      replies.value = (byParent[rootId] || []).map((row) => ({
        ...row,
        replies: (byParent[row.id] || []).slice(),
      }))
    }
  } catch (fetchError) {
    error.value =
      fetchError?.response?.data?.message ||
      fetchError?.message ||
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
  resetMeta()
})

watch(
  () => route.params.id,
  () => {
    post.value = null
    root.value = null
    replies.value = []
    loadPost()
  },
)

const repliesCount = computed(() => (
  replies.value.reduce((acc, row) => {
    const childCount = Array.isArray(row.replies) ? row.replies.length : 0
    return acc + 1 + childCount
  }, 0)
))

const repliesCountLabel = computed(() => {
  const count = Number(repliesCount.value || 0)
  if (count === 1) return '1 odpoveď'
  if (count >= 2 && count <= 4) return `${count} odpovede`
  return `${count} odpovedí`
})
</script>

<style scoped src="./postDetail/PostDetailView.css"></style>
