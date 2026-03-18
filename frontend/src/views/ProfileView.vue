<template src="./profile/ProfileView.template.html"></template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useEventFollowsStore } from '@/stores/eventFollows'
import http from '@/services/api'
import api from '@/services/api'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import ProfileEventCard from '@/components/profile/ProfileEventCard.vue'
import ObservationCard from '@/components/observations/ObservationCard.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import DefaultAvatar from '@/components/DefaultAvatar.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import HashtagText from '@/components/HashtagText.vue'
import DropdownMenu from '@/components/shared/DropdownMenu.vue'
import ProfileEdit from './ProfileEdit.vue'
import { EVENT_TIMEZONE, formatEventDate, formatEventDateKey } from '@/utils/eventTime'
import { formatDateTimeCompact } from '@/utils/dateUtils'
import { useProfileAvatarEditor } from './profile/useProfileAvatarEditor'
import { useProfileContentTabs } from './profile/useProfileContentTabs'
import {
  absoluteUrl,
  attachedEventForPost,
  isImage,
  looksLikeEmail,
  parentHandle,
  postGifTitle,
  postGifUrl as resolvePostGifUrl,
  safeHandle,
  shorten,
  toNonEmptyText,
} from './profileView.utils'

const router = useRouter()
const auth = useAuthStore()
const eventFollows = useEventFollowsStore()
const { confirm } = useConfirm()
const toast = useToast()

const copyLabel = ref('Kopírovať link')
const profileEditModalOpen = ref(false)
const {
  AVATAR_COLORS,
  avatarDraft,
  avatarErr,
  avatarInput,
  avatarModalOpen,
  avatarRemoving,
  avatarResolved,
  avatarSaving,
  avatarSrc,
  avatarUploading,
  coverInput,
  coverLoadFailed,
  coverSrc,
  coverUploading,
  iconOptions,
  logAvatarProfileState,
  mediaErr,
  onCoverImageError,
  onMediaChange,
  openAvatarEditor,
  openPicker,
  randomizeAvatar,
  removeAvatarImage,
  resetGeneratedAvatar,
  saveAvatarPreferences,
  selectAvatarColor,
  selectAvatarIcon,
  setAvatarMode,
  syncAvatarDraftFromUser,
} = useProfileAvatarEditor({
  auth,
  confirm,
  http,
  toast,
})

const {
  actionErr,
  actionMsg,
  activeEventItems,
  activeEventSegment,
  activeTab,
  canPinProfilePost,
  deleteLoadingId,
  deletePost,
  eventSegmentCounts,
  eventSegmentEmptyMessage,
  eventSegmentEmptyTitle,
  eventSegments,
  globalEmptyActionLabel,
  globalEmptyMessage,
  globalEmptyTitle,
  initializeProfileContent,
  isPinnedOnProfile,
  loadTab,
  pinLoadingId,
  pinnedPost,
  setActiveTab,
  shouldShowEmptyState,
  shouldShowLoadingState,
  stats,
  tabState,
  tabs,
  togglePin,
} = useProfileContentTabs({
  auth,
  http,
  eventFollows,
  confirm,
})

const displayName = computed(() => {
  const name = toNonEmptyText(auth.user?.name)
  if (name && !looksLikeEmail(name)) return name

  const username = toNonEmptyText(auth.user?.username)
  return username || 'Profil'
})
const handle = computed(() => {
  const username = toNonEmptyText(auth.user?.username)
  if (username) return safeHandle(username)

  const name = toNonEmptyText(auth.user?.name)
  if (name && !looksLikeEmail(name)) return safeHandle(name)

  return 'user'
})

function goHome() {
  router.push({ name: 'home' })
}

function goLogin() {
  router.push({ name: 'login', query: { redirect: '/profile' } })
}

function goToProfileEdit() {
  profileEditModalOpen.value = true
}

function openPost(post) {
  if (!post?.id) return
  router.push(`/posts/${post.id}`)
}

function profilePostMenuItems(post) {
  const items = []
  if (!post?.id) return items

  if (canPinProfilePost(post)) {
    items.push({
      key: 'pin',
      label:
        pinLoadingId.value === post.id
          ? 'Ukladám...'
          : isPinnedOnProfile(post)
            ? 'Odopnúť'
            : 'Pripnúť',
      danger: false,
    })
  }

  items.push({
    key: 'delete',
    label: deleteLoadingId.value === post.id ? 'Mažem...' : 'Vymazať',
    danger: true,
  })

  return items
}

function onProfilePostMenuSelect(item, post) {
  if (!item?.key || !post?.id) return

  if (item.key === 'pin') {
    if (pinLoadingId.value === post.id) return
    void togglePin(post)
    return
  }

  if (item.key === 'delete') {
    if (deleteLoadingId.value === post.id) return
    void deletePost(post)
  }
}

function onGlobalEmptyAction() {
  if (activeTab.value === 'observations') {
    if (typeof window === 'undefined') return
    window.dispatchEvent(new CustomEvent('post:composer:open', { detail: { action: 'observation' } }))
  }
}

function fmt(iso) {
  return formatDateTimeCompact(iso)
}

function formatPostTimestamp(post) {
  if (activeTab.value === 'bookmarks') {
    return fmt(post?.bookmarked_at || post?.created_at)
  }
  return fmt(post?.created_at)
}

function postGifUrl(post) {
  return resolvePostGifUrl(post, api?.defaults?.baseURL || '')
}

function openAttachedEvent(post) {
  const eventId = Number(attachedEventForPost(post)?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function openFollowedEvent(event) {
  const eventId = Number(event?.id || 0)
  if (!Number.isInteger(eventId) || eventId <= 0) return
  router.push(`/events/${eventId}`)
}

function openObservation(observation) {
  const observationId = Number(observation?.id || 0)
  if (!Number.isInteger(observationId) || observationId <= 0) return
  router.push(`/observations/${observationId}`)
}

function formatEventRange(startAt, endAt) {
  const startLabel = formatShortEventDate(startAt, true)
  const endLabel = formatShortEventDate(endAt, true)

  if (!startLabel && !endLabel) return 'Dátum upresnime'
  if (startLabel && !endLabel) return startLabel
  if (!startLabel && endLabel) return endLabel

  const sameDay = formatEventDateKey(startAt, EVENT_TIMEZONE) === formatEventDateKey(endAt, EVENT_TIMEZONE)
  return sameDay ? startLabel : `${startLabel} - ${endLabel}`
}

function formatShortEventDate(value, includeYear = false) {
  if (!value) return ''

  const label = formatEventDate(value, EVENT_TIMEZONE, {
    day: '2-digit',
    month: 'short',
    ...(includeYear ? { year: 'numeric' } : {}),
  })

  return label === '-' ? '' : label
}

async function copyProfileLink() {
  const url = `${window.location.origin}/profile`
  try {
    await navigator.clipboard.writeText(url)
    copyLabel.value = 'Skopírované'
  } catch {
    copyLabel.value = 'Nepodarilo sa kopírovať'
  }
  setTimeout(() => {
    copyLabel.value = 'Kopírovať link'
  }, 1500)
}

onMounted(async () => {
  await initializeProfileContent(() => {
    syncAvatarDraftFromUser()
    logAvatarProfileState('mounted-with-user')
  })
})
</script>

<style scoped src="./profile/ProfileView.css"></style>


