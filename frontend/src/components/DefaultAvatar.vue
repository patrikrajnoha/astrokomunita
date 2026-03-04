<template>
  <div class="default-avatar" :style="avatarStyle" :role="role" :aria-label="ariaLabel">
    <svg
      class="default-avatar-icon"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <template v-if="resolvedIconIndex === 0">
        <circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.8" />
        <ellipse cx="12" cy="12" rx="9" ry="3.6" stroke="currentColor" stroke-width="1.6" />
      </template>

      <template v-else-if="resolvedIconIndex === 1">
        <path
          d="M12 3.5L13.95 8.05L18.9 8.45L15.1 11.7L16.25 16.5L12 13.85L7.75 16.5L8.9 11.7L5.1 8.45L10.05 8.05L12 3.5Z"
          stroke="currentColor"
          stroke-width="1.6"
          stroke-linejoin="round"
        />
      </template>

      <template v-else-if="resolvedIconIndex === 2">
        <path
          d="M4.2 12.2C5.8 9.2 9.5 7.8 12.8 8.8C15.6 9.65 17.6 12.05 17.8 14.75C18 17.3 16.6 19.15 14.5 19.35C12.95 19.5 11.55 18.65 11 17.35C10.45 16.05 10.95 14.55 12.15 13.9C13.2 13.35 14.55 13.65 15.2 14.55"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <circle cx="7.2" cy="8.6" r="1.1" fill="currentColor" />
        <circle cx="18.3" cy="10.6" r="1" fill="currentColor" />
      </template>

      <template v-else-if="resolvedIconIndex === 3">
        <circle cx="6.2" cy="8" r="1.2" fill="currentColor" />
        <circle cx="16.8" cy="6.5" r="1.2" fill="currentColor" />
        <circle cx="19" cy="15.5" r="1.2" fill="currentColor" />
        <circle cx="9.5" cy="18" r="1.2" fill="currentColor" />
        <path
          d="M6.9 8.2L15.9 6.7L18.3 14.8L10.2 17.3L6.9 8.2Z"
          stroke="currentColor"
          stroke-width="1.4"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </template>

      <template v-else>
        <path
          d="M15.6 4.5C12 4.5 9 7.45 9 11.1C9 13.95 10.85 16.35 13.4 17.25C9.75 18.05 6.5 15.25 6.5 11.4C6.5 7.25 9.9 3.9 14.05 3.9C14.6 3.9 15.15 3.95 15.6 4.5Z"
          fill="currentColor"
        />
      </template>
    </svg>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  coerceAvatarIndex,
  pickDeterministicAvatarIndex,
} from '@/constants/avatar'

const props = defineProps({
  size: {
    type: Number,
    default: 40,
  },
  userId: {
    type: [String, Number],
    default: null,
  },
  seed: {
    type: [String, Number],
    default: '',
  },
  ariaLabel: {
    type: String,
    default: 'Default avatar',
  },
  role: {
    type: String,
    default: 'img',
  },
  colorIndex: {
    type: [String, Number],
    default: null,
  },
  colorHex: {
    type: String,
    default: '',
  },
  iconIndex: {
    type: [String, Number],
    default: null,
  },
})

const resolvedSeed = computed(() => {
  const userId = String(props.userId ?? '').trim()
  if (userId !== '') return userId

  const seed = String(props.seed ?? '').trim()
  if (seed !== '') return seed

  return 'guest'
})

const resolvedColorIndex = computed(() => {
  const explicitColorIndex = coerceAvatarIndex(props.colorIndex, AVATAR_COLORS.length - 1)
  if (explicitColorIndex !== null) {
    return explicitColorIndex
  }

  const explicitColorHex = String(props.colorHex || '').trim().toLowerCase()
  if (explicitColorHex) {
    const foundIndex = AVATAR_COLORS.findIndex((color) => color.toLowerCase() === explicitColorHex)
    if (foundIndex >= 0) return foundIndex
  }

  return pickDeterministicAvatarIndex(resolvedSeed.value, 'color', AVATAR_COLORS.length)
})

const resolvedIconIndex = computed(() => {
  const explicitIconIndex = coerceAvatarIndex(props.iconIndex, AVATAR_ICONS.length - 1)
  if (explicitIconIndex !== null) {
    return explicitIconIndex
  }

  return pickDeterministicAvatarIndex(resolvedSeed.value, 'icon', AVATAR_ICONS.length)
})

const avatarStyle = computed(() => ({
  '--default-avatar-prop-size': `${Math.max(1, Number(props.size) || 40)}px`,
  backgroundColor: AVATAR_COLORS[resolvedColorIndex.value],
}))
</script>

<style scoped>
.default-avatar {
  width: var(--default-avatar-size, var(--default-avatar-prop-size));
  height: var(--default-avatar-size, var(--default-avatar-prop-size));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  overflow: hidden;
  flex-shrink: 0;
}

.default-avatar-icon {
  width: 56%;
  height: 56%;
  display: block;
}
</style>
