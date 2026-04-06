<template>
  <div class="feed-media-root" @click.stop>
    <div
      ref="frameRef"
      class="feed-media-frame"
      :class="{
        'feed-media-frame--blurred': effectiveBlurred,
        'feed-media-frame--pending': effectiveBlurred && isPendingStatus,
        'feed-media-frame--revealing': isRevealing,
      }"
      :style="frameStyle"
      role="button"
      tabindex="0"
      :aria-label="effectiveBlurred ? `Obrázok: ${resolvedPendingLabel}` : 'Otvoriť celý obrázok'"
      @click.stop="openLightbox"
      @keydown.enter.prevent="openLightbox"
      @keydown.space.prevent="openLightbox"
    >
      <img class="feed-media-bg" :src="src" alt="" aria-hidden="true" />
      <img
        class="feed-media-img"
        :src="src"
        :alt="altText"
        loading="lazy"
        @load="onLoad"
      />
      <div v-if="shouldShowOversizeOverlay" class="media-overlay-wrap">
        <button
          type="button"
          class="media-overlay-btn"
          aria-label="Zobrazit cele"
          @click.stop="openLightbox"
        >
          Zobrazit cele
        </button>
      </div>
      <div v-if="effectiveBlurred" class="media-state-overlay" :class="{ 'media-state-overlay--animated': isPendingStatus }">
        <span>
          {{ resolvedPendingLabel }}
        </span>
      </div>
      <transition name="reveal-badge">
        <div v-if="showBadge" class="media-reveal-badge" aria-hidden="true">
          <span>&#10003; Schválené</span>
        </div>
      </transition>
    </div>

    <ImageLightbox :open="isLightboxOpen" :src="src" :alt="altText" @close="closeLightbox" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import ImageLightbox from '@/components/media/ImageLightbox.vue'

const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: 'Príloha' },
  maxHeightDesktop: { type: Number, default: 380 },
  maxHeightMobile: { type: Number, default: 300 },
  blurred: { type: Boolean, default: false },
  status: { type: String, default: '' },
  pendingLabel: { type: String, default: 'Overuje sa obsah…' },
  fit: {
    type: String,
    default: 'contain',
    validator: (value) => ['contain', 'cover'].includes(String(value || '')),
  },
  frameAspectRatio: { type: String, default: '' },
  showOversizeOverlay: { type: Boolean, default: true },
  postId: { type: Number, default: 0 },
})

const emit = defineEmits(['unblurred'])

const frameRef = ref(null)
const naturalWidth = ref(0)
const naturalHeight = ref(0)
const frameHeight = ref(null)
const isOversized = ref(false)
const isLightboxOpen = ref(false)
const isRevealing = ref(false)
const showBadge = ref(false)
let resizeRaf = null
let pollTimer = null

const altText = computed(() => props.alt || 'Priloha')
const effectiveBlurred = computed(() => props.blurred && !isRevealing.value)
const normalizedStatus = computed(() => String(props.status || '').trim().toLowerCase())
const isPendingStatus = computed(() => {
  const status = normalizedStatus.value
  if (status === 'blocked' || status === 'flagged' || status === 'ok') {
    return false
  }

  return (
    status === ''
    || status === 'pending'
    || status === 'pending_moderation'
    || status === 'processing'
    || status === 'queued'
  )
})
const resolvedPendingLabel = computed(() => {
  return props.pendingLabel || 'Overuje sa obsah…'
})
const hasFixedAspectRatio = computed(() => String(props.frameAspectRatio || '').trim() !== '')
const frameStyle = computed(() => {
  const style = {
    '--feed-media-fit': props.fit,
  }

  const ratio = String(props.frameAspectRatio || '').trim()
  if (ratio) {
    return {
      ...style,
      aspectRatio: ratio,
    }
  }

  if (!frameHeight.value) return style
  return {
    ...style,
    height: `${frameHeight.value}px`,
  }
})
const shouldShowOversizeOverlay = computed(() => {
  if (effectiveBlurred.value) return false
  if (!props.showOversizeOverlay) return false
  if (props.fit !== 'contain') return false
  if (hasFixedAspectRatio.value) return false
  return isOversized.value
})

function onLoad(event) {
  const img = event?.target
  naturalWidth.value = img?.naturalWidth || 0
  naturalHeight.value = img?.naturalHeight || 0
  if (!hasFixedAspectRatio.value) {
    recalcDimensions()
  }
  window.removeEventListener('resize', onResize)
  if (!hasFixedAspectRatio.value) {
    window.addEventListener('resize', onResize)
  }
}

function onResize() {
  if (resizeRaf) {
    window.cancelAnimationFrame(resizeRaf)
  }
  resizeRaf = window.requestAnimationFrame(() => {
    recalcDimensions()
    resizeRaf = null
  })
}

function recalcDimensions() {
  const width = frameRef.value?.clientWidth || 0
  const nw = naturalWidth.value
  const nh = naturalHeight.value
  if (!width || !nw || !nh) return

  const maxHeight = getMaxHeight()
  const scaledHeight = (nh / nw) * width
  isOversized.value = scaledHeight > maxHeight + 1
  frameHeight.value = Math.round(Math.min(scaledHeight, maxHeight))
}

function getMaxHeight() {
  if (typeof window === 'undefined') return props.maxHeightDesktop
  return window.matchMedia('(max-width: 768px)').matches
    ? props.maxHeightMobile
    : props.maxHeightDesktop
}

function startPolling() {
  if (!props.postId || !props.blurred) return
  stopPolling()
  pollTimer = setInterval(async () => {
    if (!props.blurred || isRevealing.value) {
      stopPolling()
      return
    }
    try {
      const res = await fetch(`/api/posts/${props.postId}`, {
        credentials: 'include',
        headers: {
          Accept: 'application/json',
        },
      })
      if (!res.ok) return
      const data = await res.json()
      const postPayload = resolvePostPayload(data, props.postId)
      if (!postPayload) return

      const stillBlurred = postPayload.attachment_is_blurred === true
      const moderationStatus = String(postPayload.attachment_moderation_status || '').trim().toLowerCase()

      if (!stillBlurred || moderationStatus === 'ok') {
        triggerReveal({ isBlurred: false, status: moderationStatus })
        return
      }

      if (moderationStatus === 'blocked' || moderationStatus === 'flagged') {
        stopPolling()
        emit('unblurred', { isBlurred: true, status: moderationStatus })
      }
    } catch {
      // silently ignore network errors, keep polling
    }
  }, 4000)
}

function resolvePostPayload(data, postId) {
  if (!data || typeof data !== 'object') return null

  const targetId = Number(postId)
  const matchesTarget = (item) => Number(item?.id || 0) === targetId
  const hasAttachmentState = (item) =>
    item
    && typeof item === 'object'
    && Object.prototype.hasOwnProperty.call(item, 'attachment_is_blurred')

  if (hasAttachmentState(data) && (!targetId || matchesTarget(data))) {
    return data
  }

  const post = data.post
  if (hasAttachmentState(post) && matchesTarget(post)) {
    return post
  }

  const root = data.root
  if (hasAttachmentState(root) && matchesTarget(root)) {
    return root
  }

  const thread = Array.isArray(data.thread) ? data.thread : []
  const threadMatch = thread.find((item) => hasAttachmentState(item) && matchesTarget(item))
  if (threadMatch) {
    return threadMatch
  }

  const nestedReplies = Array.isArray(data.replies) ? data.replies : []
  return findReplyById(nestedReplies, targetId)
}

function findReplyById(items, targetId) {
  for (const item of items) {
    if (!item || typeof item !== 'object') continue
    if (Number(item.id || 0) === targetId && Object.prototype.hasOwnProperty.call(item, 'attachment_is_blurred')) {
      return item
    }

    const nested = Array.isArray(item.replies) ? item.replies : []
    const nestedMatch = findReplyById(nested, targetId)
    if (nestedMatch) return nestedMatch
  }

  return null
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function triggerReveal(payload) {
  stopPolling()
  isRevealing.value = true
  showBadge.value = true
  setTimeout(() => {
    showBadge.value = false
  }, 2200)
  emit('unblurred', payload)
}

function openLightbox() {
  if (effectiveBlurred.value) return
  isLightboxOpen.value = true
}

function closeLightbox() {
  isLightboxOpen.value = false
}

onMounted(() => {
  if (props.blurred && props.postId) {
    startPolling()
  }
})

watch(
  () => props.blurred,
  (val) => {
    if (val && props.postId) {
      isRevealing.value = false
      startPolling()
    } else {
      stopPolling()
    }
  }
)

onBeforeUnmount(() => {
  stopPolling()
  window.removeEventListener('resize', onResize)
  if (resizeRaf) {
    window.cancelAnimationFrame(resizeRaf)
    resizeRaf = null
  }
})
</script>

<style scoped>
.feed-media-root {
  position: relative;
}

.feed-media-frame {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  max-height: 380px;
  overflow: hidden;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  background: linear-gradient(160deg, rgba(15, 23, 42, 0.82), rgba(30, 41, 59, 0.65));
  cursor: zoom-in;
  transition: height 0.16s ease;
}

.feed-media-frame--blurred {
  cursor: default;
}

.feed-media-frame:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.feed-media-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scale(1.08);
  filter: blur(20px);
  opacity: 0.48;
}

.feed-media-img {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 100%;
  object-fit: var(--feed-media-fit, contain);
  display: block;
  background: transparent;
}

.feed-media-frame--blurred .feed-media-img {
  filter: blur(8px) saturate(0.7);
  transform: scale(1.05);
}

.feed-media-frame--revealing .feed-media-img {
  filter: blur(0) saturate(1);
  transform: scale(1);
  transition: filter 0.55s ease, transform 0.55s ease;
}

.feed-media-frame--revealing .feed-media-bg {
  opacity: 0;
  transition: opacity 0.55s ease;
}

.media-overlay-wrap {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2;
  padding: 12px;
  display: flex;
  justify-content: center;
  background: linear-gradient(to top, rgba(2, 6, 23, 0.7), rgba(2, 6, 23, 0));
}

.media-overlay-btn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  border-radius: 999px;
  padding: 8px 14px;
  background: rgb(var(--color-bg-rgb) / 0.88);
  color: var(--color-surface);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.media-overlay-btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.media-state-overlay {
  position: absolute;
  inset: 0;
  z-index: 3;
  display: grid;
  place-items: center;
  padding: 12px;
  background: rgb(2 6 23 / 0.56);
  color: var(--color-white);
  pointer-events: none;
}

.media-state-overlay span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 164px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid var(--color-border);
  background: rgb(2 6 23 / 0.64);
  color: var(--color-white);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-shadow: 0 1px 2px rgb(0 0 0 / 0.45);
}

@supports ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
  .media-state-overlay--animated {
    background: rgb(2 6 23 / 0.35);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    animation: media-state-overlay-blur-pulse 1.8s ease-in-out infinite;
  }
}

@supports not ((-webkit-backdrop-filter: blur(1px)) or (backdrop-filter: blur(1px))) {
  .feed-media-frame--pending .feed-media-img {
    animation: media-state-image-blur-pulse 1.8s ease-in-out infinite;
    will-change: filter;
  }
}

@keyframes media-state-overlay-blur-pulse {
  0%,
  100% {
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
  }
  50% {
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
  }
}

@keyframes media-state-image-blur-pulse {
  0%,
  100% {
    filter: blur(8px) saturate(0.7);
  }
  50% {
    filter: blur(12px) saturate(0.7);
  }
}

.media-reveal-badge {
  position: absolute;
  inset: 0;
  z-index: 4;
  display: grid;
  place-items: center;
  pointer-events: none;
}

.media-reveal-badge span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  border-radius: 999px;
  background: rgb(16 185 129 / 0.92);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.03em;
  box-shadow: 0 2px 16px rgb(16 185 129 / 0.4);
}

.reveal-badge-enter-active {
  animation: reveal-badge-in 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.reveal-badge-leave-active {
  animation: reveal-badge-out 0.38s ease-in both;
  animation-delay: 1.2s;
}

@keyframes reveal-badge-in {
  from {
    opacity: 0;
    transform: scale(0.7);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes reveal-badge-out {
  from {
    opacity: 1;
    transform: scale(1);
  }
  to {
    opacity: 0;
    transform: scale(0.85) translateY(-6px);
  }
}

@media (max-width: 768px) {
  .feed-media-frame {
    max-height: 300px;
  }
}
</style>
