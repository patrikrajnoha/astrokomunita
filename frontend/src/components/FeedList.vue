<template src="./feedList/FeedList.template.html"></template>

<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import FeedSwitcher from '@/components/FeedSwitcher.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import HashtagText from './HashtagText.vue'
import PostActionBar from '@/components/PostActionBar.vue'
import PostMediaImage from '@/components/media/PostMediaImage.vue'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useBookmarksStore } from '@/stores/bookmarks'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { canDeletePost, canReportPost } from '@/utils/postPermissions'
import { formatRelativeShort } from '@/utils/dateUtils'
import { avatarDebug } from '@/utils/avatarDebug'
import {
  botSourceLabel,
  isBotPost,
  sourceAttributionLabel,
} from './feedList/feedListBotContent.utils'
import {
  attachmentDownloadSrc as attachmentDownloadSrcUtil,
  attachmentMime,
  attachmentSrc as attachmentSrcUtil,
  formatEventRange,
  isAttachmentBlocked,
  isAttachmentPending,
  isImage,
  isVideo,
  normalizeFeedError,
  postGifTitle,
} from './feedList/feedListMedia.utils'
import { useFeedListTabs } from './feedList/useFeedListTabs'
import { useFeedListInteractions } from './feedList/useFeedListInteractions'
import { useFeedListPostDisplay } from './feedList/useFeedListPostDisplay'

const PollCard = defineAsyncComponent(() => import('@/components/PollCard.vue'))
const PostMediaVideo = defineAsyncComponent(() => import('@/components/media/PostMediaVideo.vue'))
const ShareModal = defineAsyncComponent(() => import('@/components/share/ShareModal.vue'))

const props = defineProps({
  mode: {
    type: String,
    default: 'home',
  },
})

const router = useRouter()
const auth = useAuthStore()
const bookmarks = useBookmarksStore()
const { error: toastError, info: toastInfo, success: toastSuccess } = useToast()
const { confirm } = useConfirm()
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportMessage = ref('')

const reportOptions = [
  { value: 'spam',       icon: '🚫', label: 'Spam' },
  { value: 'abuse',      icon: '⚠️',  label: 'Nevhodný obsah' },
  { value: 'misinfo',    icon: '📢', label: 'Dezinformácie' },
  { value: 'harassment', icon: '😤', label: 'Obťažovanie' },
  { value: 'other',      icon: '💬', label: 'Iné' },
]
const highlightedPostId = ref(null)
const editingPostId = ref(null)
const editContentDraft = ref('')
const editSavingId = ref(null)
let highlightTimer = null

const {
  activeTab,
  currentFeed,
  err,
  feedState,
  isBookmarksMode,
  items,
  load,
  loading,
  nextPageUrl,
  retryCurrentTab,
  switchTab,
  tabs,
} = useFeedListTabs({
  modeRef: computed(() => props.mode),
  api,
  bookmarks,
  avatarDebug,
  normalizeFeedError,
  reportTarget,
  closeReport,
})

const {
  closeShareModal,
  confirmDelete,
  deleteLoadingId,
  downloadOriginalAttachment,
  isBookmarkLoading,
  isLikeLoading,
  likeBumpId,
  openShareModal,
  pinLoadingId,
  shareTarget,
  toggleBookmark,
  toggleLike,
  togglePin,
} = useFeedListInteractions({
  auth,
  api,
  bookmarks,
  currentFeed,
  loadFeed: load,
  canDelete,
  confirm,
  toastError,
  toastInfo,
  toastSuccess,
  attachmentDownloadSrc: (post) => attachmentDownloadSrcUtil(post, api?.defaults?.baseURL || ''),
})

const {
  attachedEventForPost,
  canAdminEditBotPost,
  canEditTranslatedVariant,
  defaultBotVariant,
  displayPostContent,
  isBotContentCollapsible,
  isBotVariantActive,
  isPostContentExpanded,
  isStelaPost,
  menuItemsForPost,
  onMenuAction,
  postGifUrl,
  resolvedBotVariant,
  resolvedDisplayText,
  setBotContentVariant,
  sourceLink,
  stelaPreviewImageSrc,
  togglePostContent,
} = useFeedListPostDisplay({
  auth,
  apiBaseUrl: api?.defaults?.baseURL || '',
  botContentPreviewLimit: 800,
  canDelete,
  canReport,
  isBotPost,
  downloadOriginalAttachment,
  openReport,
  confirmDelete,
  startInlineEdit,
  togglePin,
})

function observationForPost(post) {
  const embedded = post?.attached_observation
  if (embedded && typeof embedded === 'object') {
    return embedded
  }

  const fallbackId = Number(post?.meta?.observation?.observation_id || 0)
  if (!Number.isInteger(fallbackId) || fallbackId <= 0) {
    return null
  }

  return {
    id: fallbackId,
    title: `Pozorovanie #${fallbackId}`,
    media: [],
  }
}

function openPost(post) {
  const observationId = Number(observationForPost(post)?.id || 0)
  if (Number.isInteger(observationId) && observationId > 0) {
    router.push(`/observations/${observationId}`)
    return
  }

  if (!post?.id) return
  router.push({ path: `/posts/${post.id}`, state: { seedPost: JSON.parse(JSON.stringify(post)) } })
}

function openProfile(post) {
  const username = post?.user?.username
  if (!username) return
  router.push(`/u/${username}`)
}

function goExplore() {
  router.push('/search')
}

function handleEmptyAction() {
  if (isBookmarksMode.value) {
    goExplore()
    return
  }

  retryCurrentTab()
}

function canDelete(post) {
  return canDeletePost(post, auth.user)
}

function canReport(post) {
  return canReportPost(post, auth.user)
}

function openAttachedEvent(post) {
  const eventId = Number(attachedEventForPost(post)?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function isEditingPost(post) {
  return Number(editingPostId.value) === Number(post?.id)
}

function startInlineEdit(post) {
  if (!post?.id || !canEditTranslatedVariant(post)) return

  editingPostId.value = Number(post.id)
  editContentDraft.value = String(post?.content || '')
}

function cancelInlineEdit() {
  editingPostId.value = null
  editContentDraft.value = ''
}

async function saveInlineEdit(post) {
  if (!post?.id || !isEditingPost(post) || editSavingId.value) return
  if (!canEditTranslatedVariant(post)) return

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
    toastError(message)
  } finally {
    editSavingId.value = null
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
    await auth.csrf()
    await api.post('/reports', {
      target_id: post.id,
      reason: reportReason.value,
      message: reportMessage.value || null,
    })
    currentFeed.value.err = ''
    toastSuccess('Ďakujeme, nahlásenie sme prijali.')
  } catch (e) {
    const status = e?.response?.status
    if (status === 401) currentFeed.value.err = 'Prihlás sa.'
    else if (status === 409) currentFeed.value.err = 'Už si reportoval tento post.'
    else currentFeed.value.err = e?.response?.data?.message || 'Nahlásenie zlyhalo.'
  } finally {
    closeReport()
  }
}
function updatePostPoll(post, nextPoll) {
  if (!post || !nextPoll) return
  post.poll = nextPoll
}

function onPollLoginRequired() {
  currentFeed.value.err = 'Prihlás sa pre hlasovanie.'
}

function fmt(iso) {
  return formatRelativeShort(iso)
}

function attachmentSrc(p) {
  return attachmentSrcUtil(p, api?.defaults?.baseURL || '')
}

function highlightPost(postId) {
  highlightedPostId.value = postId

  if (highlightTimer) {
    clearTimeout(highlightTimer)
  }
  highlightTimer = setTimeout(() => {
    if (highlightedPostId.value === postId) highlightedPostId.value = null
    highlightTimer = null
  }, 1800)
}

function mergePostIntoState(state, post, { insertWhenMissing = false } = {}) {
  if (!state || !post?.id) return false

  const postId = Number(post.id)
  const existingIndex = state.items.findIndex((item) => Number(item?.id || 0) === postId)
  if (existingIndex >= 0) {
    state.items = [
      ...state.items.slice(0, existingIndex),
      { ...state.items[existingIndex], ...post },
      ...state.items.slice(existingIndex + 1),
    ]
    state.loaded = true
    return true
  }

  if (!insertWhenMissing) {
    return false
  }

  state.items = [post, ...state.items]
  state.loaded = true
  return true
}

function upsert(post, { insertWhenMissing = true, highlight = true } = {}) {
  if (!post?.id) return

  let mergedExisting = false
  Object.values(feedState).forEach((state) => {
    if (mergePostIntoState(state, post, { insertWhenMissing: false })) {
      mergedExisting = true
    }
  })

  if (!mergedExisting && insertWhenMissing) {
    mergePostIntoState(feedState.for_you, post, { insertWhenMissing: true })
  }

  if (highlight) {
    highlightPost(Number(post.id))
  }
}

function prepend(post) {
  upsert(post, { insertWhenMissing: true, highlight: true })
}

function onPostUnblurred(post, { isBlurred, status }) {
  post.attachment_is_blurred = isBlurred
  post.attachment_moderation_status = status
}

onBeforeUnmount(() => {
  if (highlightTimer) {
    clearTimeout(highlightTimer)
    highlightTimer = null
  }
})

defineExpose({ load, prepend, upsert })
</script>

<style scoped src="./feedList/FeedList.css"></style>

