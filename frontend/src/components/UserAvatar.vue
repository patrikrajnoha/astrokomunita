<template>
  <span class="user-avatar" :style="wrapperStyle">
    <img
      v-if="usesImage"
      class="user-avatar-media"
      :src="imageUrl"
      :alt="resolvedAlt"
      @error="onImageError"
    />
    <DefaultAvatar
      v-else
      class="user-avatar-media"
      style="--default-avatar-size: 100%;"
      :size="size"
      :user-id="resolvedUserId"
      :seed="avatarState.seed"
      :color-index="avatarState.colorIndex"
      :icon-index="avatarState.iconIndex"
      :aria-label="resolvedAriaLabel"
      role="img"
    />
  </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import DefaultAvatar from '@/components/DefaultAvatar.vue'
import { avatarSeed, resolveAvatarState } from '@/utils/avatar'
import { avatarDebug } from '@/utils/avatarDebug'
import { getBotAvatar, isBotUser } from '@/utils/botAvatar'

const props = defineProps({
  user: {
    type: Object,
    default: null,
  },
  size: {
    type: Number,
    default: 40,
  },
  alt: {
    type: String,
    default: 'Avatar',
  },
  ariaLabel: {
    type: String,
    default: '',
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
    default: true,
  },
  colorIndex: {
    type: [String, Number],
    default: null,
  },
  iconIndex: {
    type: [String, Number],
    default: null,
  },
  seed: {
    type: [String, Number],
    default: '',
  },
  userId: {
    type: [String, Number],
    default: null,
  },
})

const imageFailed = ref(false)
const imageUrl = ref('')
const imageRetryDone = ref(false)
const botFallbackRetryDone = ref(false)

const avatarState = computed(() =>
  resolveAvatarState(props.user, {
    avatarUrl: props.avatarUrl,
    mode: props.mode,
    preferImage: props.preferImage,
    colorIndex: props.colorIndex,
    iconIndex: props.iconIndex,
    seed: props.seed,
  }),
)

watch(
  () => avatarState.value.imageUrl,
  (nextUrl) => {
    imageFailed.value = false
    imageRetryDone.value = false
    botFallbackRetryDone.value = false
    imageUrl.value = String(nextUrl || '')
    avatarDebug('UserAvatar:imageUrl-changed', {
      userId: props.user?.id ?? null,
      username: props.user?.username ?? null,
      nextUrl: imageUrl.value,
      mode: avatarState.value.mode,
      usesImage: avatarState.value.usesImage,
    })
  },
  { immediate: true },
)

const resolvedUserId = computed(() => props.userId ?? avatarSeed(props.user))
const resolvedAlt = computed(() => String(props.alt || 'Avatar').trim() || 'Avatar')
const resolvedAriaLabel = computed(() => String(props.ariaLabel || resolvedAlt.value).trim() || 'Avatar')
const usesImage = computed(
  () => avatarState.value.usesImage && !imageFailed.value && String(imageUrl.value).trim() !== '',
)
const wrapperStyle = computed(() => ({
  '--user-avatar-size': `${Math.max(1, Number(props.size) || 40)}px`,
}))

function onImageError() {
  avatarDebug('UserAvatar:image-error', {
    userId: props.user?.id ?? null,
    username: props.user?.username ?? null,
    currentImageUrl: imageUrl.value,
    retryDone: imageRetryDone.value,
  })

  if (!imageRetryDone.value) {
    const fallbackUrl = toCurrentOriginMediaUrl(imageUrl.value)
    if (fallbackUrl && fallbackUrl !== imageUrl.value) {
      imageRetryDone.value = true
      imageUrl.value = fallbackUrl
      avatarDebug('UserAvatar:image-retry', {
        userId: props.user?.id ?? null,
        username: props.user?.username ?? null,
        fallbackUrl,
      })
      return
    }
  }

  if (!botFallbackRetryDone.value && isBotUser(props.user)) {
    botFallbackRetryDone.value = true
    const botFallbackUrl = getBotAvatar(props.user, {
      avatarPath: '',
      avatarUrl: '',
    })?.url || ''

    if (botFallbackUrl && botFallbackUrl !== imageUrl.value) {
      imageUrl.value = botFallbackUrl
      avatarDebug('UserAvatar:image-retry-bot-default', {
        userId: props.user?.id ?? null,
        username: props.user?.username ?? null,
        fallbackUrl: botFallbackUrl,
      })
      return
    }
  }

  imageFailed.value = true
  avatarDebug('UserAvatar:fallback-to-generated', {
    userId: props.user?.id ?? null,
    username: props.user?.username ?? null,
    avatarState: avatarState.value,
  })
}

function toCurrentOriginMediaUrl(rawUrl) {
  const value = String(rawUrl || '').trim()
  if (!value || typeof window === 'undefined') return ''

  const appOrigin = String(window.location.origin || '').trim()
  if (!appOrigin) return ''

  const encodeMediaPath = (inputPath) =>
    String(inputPath || '')
      .split('/')
      .filter(Boolean)
      .map((segment) => encodeURIComponent(segment))
      .join('/')

  const absoluteStorageMatch = value.match(/^https?:\/\/[^/]+\/storage\/(.+)$/i)
  if (absoluteStorageMatch) {
    return `${appOrigin}/api/media/file/${encodeMediaPath(absoluteStorageMatch[1])}`
  }

  const absoluteMediaMatch = value.match(/^https?:\/\/[^/]+\/api\/media\/file\/(.+)$/i)
  if (absoluteMediaMatch) {
    return `${appOrigin}/api/media/file/${encodeMediaPath(absoluteMediaMatch[1])}`
  }

  if (value.startsWith('/storage/')) {
    return `${appOrigin}/api/media/file/${encodeMediaPath(value.slice('/storage/'.length))}`
  }

  if (value.startsWith('/api/media/file/')) {
    return `${appOrigin}/api/media/file/${encodeMediaPath(value.slice('/api/media/file/'.length))}`
  }

  return ''
}

watch(
  () => [avatarState.value.mode, avatarState.value.imageUrl, usesImage.value, imageFailed.value, imageUrl.value],
  () => {
    avatarDebug('UserAvatar:render-state', {
      userId: props.user?.id ?? null,
      username: props.user?.username ?? null,
      mode: avatarState.value.mode,
      normalizedImageUrl: avatarState.value.imageUrl,
      displayImageUrl: imageUrl.value,
      usesImage: usesImage.value,
      imageFailed: imageFailed.value,
      avatar_mode: props.user?.avatar_mode ?? props.user?.avatarMode ?? null,
      avatar_url: props.user?.avatar_url ?? props.user?.avatarUrl ?? null,
      avatar_path: props.user?.avatar_path ?? null,
    })
  },
  { immediate: true },
)
</script>

<style scoped>
.user-avatar {
  width: var(--user-avatar-size);
  height: var(--user-avatar-size);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: inherit;
  overflow: hidden;
  flex-shrink: 0;
}

.user-avatar-media {
  width: 100%;
  height: 100%;
  display: block;
  border-radius: inherit;
}

img.user-avatar-media {
  object-fit: cover;
}
</style>
