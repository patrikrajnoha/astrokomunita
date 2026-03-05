<template>
  <span class="userAvatar" :style="wrapperStyle">
    <img
      v-if="showImage"
      class="userAvatarImg"
      :src="resolvedState.imageUrl"
      :alt="altText"
      loading="lazy"
      decoding="async"
      @error="onImageError"
    >
    <DefaultAvatar
      v-else
      class="userAvatarFallback"
      :size="resolvedSize"
      :color-index="resolvedState.colorIndex"
      :icon-index="resolvedState.iconIndex"
      :seed="resolvedState.seed"
      :label="altText"
    />
  </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import DefaultAvatar from '@/components/DefaultAvatar.vue'
import { resolveAvatarState } from '@/utils/avatar'

const props = defineProps({
  user: {
    type: Object,
    default: null,
  },
  alt: {
    type: String,
    default: 'User avatar',
  },
  size: {
    type: [Number, String, null],
    default: null,
  },
  avatarUrl: {
    type: String,
    default: '',
  },
  mode: {
    type: String,
    default: '',
  },
  preferImage: {
    type: Boolean,
    default: false,
  },
  colorIndex: {
    type: [Number, String, null],
    default: null,
  },
  iconIndex: {
    type: [Number, String, null],
    default: null,
  },
  seed: {
    type: String,
    default: '',
  },
})

const imageFailed = ref(false)

const resolvedState = computed(() => resolveAvatarState(props.user, {
  avatarUrl: props.avatarUrl,
  mode: props.mode,
  colorIndex: props.colorIndex,
  iconIndex: props.iconIndex,
  seed: props.seed,
}))

const resolvedSize = computed(() => {
  const parsed = Number(props.size)
  if (!Number.isFinite(parsed) || parsed <= 0) return 40
  return parsed
})

const altText = computed(() => String(props.alt || 'User avatar'))

const showImage = computed(() => {
  if (imageFailed.value) return false
  const imageUrl = String(resolvedState.value.imageUrl || '').trim()
  if (!imageUrl) return false
  return props.preferImage || resolvedState.value.mode === 'image'
})

const wrapperStyle = computed(() => ({
  '--user-avatar-size': `${resolvedSize.value}px`,
}))

watch(
  () => resolvedState.value.imageUrl,
  () => {
    imageFailed.value = false
  },
)

function onImageError() {
  imageFailed.value = true
}
</script>

<style scoped>
.userAvatar {
  width: var(--user-avatar-size);
  height: var(--user-avatar-size);
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: rgb(var(--color-text-secondary-rgb, 148 163 184) / 0.2);
}

.userAvatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.userAvatarFallback {
  width: 100%;
  height: 100%;
}
</style>
