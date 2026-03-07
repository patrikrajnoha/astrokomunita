<template>
  <div class="feed-video-root" @click.stop>
    <video
      ref="videoRef"
      class="feed-video-player post-video"
      controls
      playsinline
      preload="metadata"
    >
      <source v-if="src" :src="src" :type="type || undefined" />
    </video>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import 'plyr/dist/plyr.css'

const props = defineProps({
  src: { type: String, required: true },
  type: { type: String, default: '' },
})

const videoRef = ref(null)
let player = null
let plyrCtorPromise = null

function buildSource() {
  if (!props.src) return null
  return {
    type: 'video',
    sources: [{ src: props.src, type: props.type || undefined }],
  }
}

function destroyPlayer() {
  if (!player) return
  player.destroy()
  player = null
}

function updateSource() {
  if (!player) return
  const source = buildSource()
  if (!source) return
  player.source = source
}

async function getPlyrCtor() {
  if (typeof window === 'undefined') return null
  if (!plyrCtorPromise) {
    plyrCtorPromise = import('plyr').then((mod) => mod.default || mod)
  }
  return plyrCtorPromise
}

async function initPlayer() {
  if (!videoRef.value || typeof window === 'undefined') return
  const PlyrCtor = await getPlyrCtor()
  if (!PlyrCtor || !videoRef.value) return
  destroyPlayer()
  player = new PlyrCtor(videoRef.value, {
    clickToPlay: true,
    controls: [
      'play-large',
      'play',
      'progress',
      'current-time',
      'duration',
      'mute',
      'volume',
      'settings',
      'pip',
      'airplay',
      'fullscreen',
    ],
    fullscreen: { iosNative: true },
    ratio: '16:9',
  })
  updateSource()
}

onMounted(() => {
  void initPlayer()
})

onBeforeUnmount(() => {
  destroyPlayer()
})

watch(
  () => [props.src, props.type],
  () => {
    updateSource()
  },
)
</script>

<style scoped>
.feed-video-root {
  width: 100%;
}

.feed-video-player {
  width: 100%;
  max-height: 340px;
  border-radius: 12px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  background: var(--color-bg-main);
  display: block;
  overflow: hidden;
}

:deep(.plyr) {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  background: var(--color-bg-main);
}

:deep(.plyr video) {
  max-height: 340px;
  object-fit: contain;
}
</style>
