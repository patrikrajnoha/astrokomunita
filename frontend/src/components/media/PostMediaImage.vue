<template>
  <div class="feed-media-root" @click.stop>
    <div
      ref="frameRef"
      class="feed-media-frame"
      :class="{ 'feed-media-frame--blurred': blurred }"
      :style="frameStyle"
      role="button"
      tabindex="0"
      :aria-label="blurred ? 'Obrazok sa kontroluje' : 'Otvorit cely obrazok'"
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
      <div v-if="isOversized && !blurred" class="media-overlay-wrap">
        <button
          type="button"
          class="media-overlay-btn"
          aria-label="Zobrazit cele"
          @click.stop="openLightbox"
        >
          Zobrazit cele
        </button>
      </div>
      <div v-if="blurred" class="media-state-overlay">
        <span>{{ pendingLabel }}</span>
      </div>
    </div>

    <ImageLightbox :open="isLightboxOpen" :src="src" :alt="altText" @close="closeLightbox" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import ImageLightbox from '@/components/media/ImageLightbox.vue'

const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: 'Priloha' },
  maxHeightDesktop: { type: Number, default: 520 },
  maxHeightMobile: { type: Number, default: 420 },
  blurred: { type: Boolean, default: false },
  pendingLabel: { type: String, default: 'Checking...' },
})

const frameRef = ref(null)
const naturalWidth = ref(0)
const naturalHeight = ref(0)
const frameHeight = ref(null)
const isOversized = ref(false)
const isLightboxOpen = ref(false)
let resizeRaf = null

const altText = computed(() => props.alt || 'Priloha')
const frameStyle = computed(() => {
  if (!frameHeight.value) return {}
  return { height: `${frameHeight.value}px` }
})

function onLoad(event) {
  const img = event?.target
  naturalWidth.value = img?.naturalWidth || 0
  naturalHeight.value = img?.naturalHeight || 0
  recalcDimensions()
  window.removeEventListener('resize', onResize)
  window.addEventListener('resize', onResize)
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

function openLightbox() {
  if (props.blurred) return
  isLightboxOpen.value = true
}

function closeLightbox() {
  isLightboxOpen.value = false
}

onBeforeUnmount(() => {
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
  max-height: 520px;
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

.feed-media-frame:focus {
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
  object-fit: contain;
  display: block;
  background: transparent;
}

.feed-media-frame--blurred .feed-media-img {
  filter: blur(28px) saturate(0.7);
  transform: scale(1.05);
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

.media-overlay-btn:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.media-state-overlay {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 3;
  display: flex;
  justify-content: center;
  padding: 12px;
  background: linear-gradient(to top, rgba(2, 6, 23, 0.82), rgba(2, 6, 23, 0));
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

@media (max-width: 768px) {
  .feed-media-frame {
    max-height: 420px;
  }
}
</style>
