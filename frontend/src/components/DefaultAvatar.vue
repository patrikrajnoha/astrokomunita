<template>
  <div class="default-avatar" :style="avatarStyle" :role="role" :aria-label="ariaLabel">
    <svg
      class="default-avatar-icon"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <template v-if="resolvedIconKey === 'planet'">
        <circle cx="12" cy="12" r="4.25" stroke="currentColor" stroke-width="1.8" />
        <ellipse
          cx="12"
          cy="12"
          rx="8.7"
          ry="3.1"
          transform="rotate(-13 12 12)"
          stroke="currentColor"
          stroke-width="1.55"
        />
        <circle cx="17.3" cy="8.15" r="0.95" fill="currentColor" />
      </template>

      <template v-else-if="resolvedIconKey === 'star'">
        <path
          d="M12 4.1L13.55 8.45L17.9 10L13.55 11.55L12 15.9L10.45 11.55L6.1 10L10.45 8.45L12 4.1Z"
          stroke="currentColor"
          stroke-width="1.7"
          stroke-linejoin="round"
        />
        <path d="M17.7 4.8L18.25 6.35L19.8 6.9L18.25 7.45L17.7 9L17.15 7.45L15.6 6.9L17.15 6.35Z" fill="currentColor" />
      </template>

      <template v-else-if="resolvedIconKey === 'comet'">
        <circle cx="16.35" cy="7.55" r="2.25" stroke="currentColor" stroke-width="1.7" />
        <path
          d="M14.7 9.15C12.9 10.4 11.35 11.95 10.05 13.85C8.8 15.65 7.7 17.75 6.8 20"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <path
          d="M12.8 8.7C10.5 9.35 8.3 10.45 6.4 12.05"
          stroke="currentColor"
          stroke-width="1.55"
          stroke-linecap="round"
        />
        <path
          d="M13.6 11.3C11.95 12.2 10.35 13.55 9.05 15.3"
          stroke="currentColor"
          stroke-width="1.55"
          stroke-linecap="round"
        />
      </template>

      <template v-else-if="resolvedIconKey === 'constellation'">
        <circle cx="5.8" cy="8.2" r="1.15" fill="currentColor" />
        <circle cx="10" cy="6.1" r="1.1" fill="currentColor" />
        <circle cx="16.3" cy="7.4" r="1.15" fill="currentColor" />
        <circle cx="18.2" cy="15.4" r="1.15" fill="currentColor" />
        <circle cx="9.2" cy="18" r="1.15" fill="currentColor" />
        <path
          d="M6.5 8.1L10 6.4L16 7.4L17.7 15.2L9.8 17.6L6.5 8.1Z"
          stroke="currentColor"
          stroke-width="1.4"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </template>

      <template v-else-if="resolvedIconKey === 'moon'">
        <path
          d="M15.7 5.2C13.15 5.2 11.05 7.35 11.05 9.95C11.05 12.35 12.85 14.35 15.15 14.7C14.1 15.42 12.8 15.85 11.4 15.85C7.95 15.85 5.15 13.05 5.15 9.6C5.15 6.15 7.95 3.35 11.4 3.35C13 3.35 14.45 3.95 15.7 5.2Z"
          fill="currentColor"
        />
        <circle cx="18.15" cy="6.8" r="0.9" fill="currentColor" />
      </template>

      <template v-else-if="resolvedIconKey === 'sun'">
        <circle cx="12" cy="12" r="3.85" stroke="currentColor" stroke-width="1.7" />
        <path
          d="M12 3.6V6.05M12 17.95V20.4M3.6 12H6.05M17.95 12H20.4M6.25 6.25L7.95 7.95M16.05 16.05L17.75 17.75M17.75 6.25L16.05 7.95M7.95 16.05L6.25 17.75"
          stroke="currentColor"
          stroke-width="1.65"
          stroke-linecap="round"
        />
      </template>

      <template v-else-if="resolvedIconKey === 'galaxy'">
        <path
          d="M18.45 10.95C18.45 7.9 16.05 5.35 12.95 5.35C9.25 5.35 6.2 8.15 6.2 11.55C6.2 14.2 8.25 16.15 10.7 16.15C12.8 16.15 14.45 14.72 14.45 12.9C14.45 11.4 13.3 10.3 11.85 10.3C10.75 10.3 9.78 10.95 9.45 11.95"
          stroke="currentColor"
          stroke-width="1.7"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <circle cx="18.1" cy="6.8" r="0.9" fill="currentColor" />
        <circle cx="6.2" cy="17.35" r="0.85" fill="currentColor" />
      </template>

      <template v-else-if="resolvedIconKey === 'rocket'">
        <path
          d="M14.1 4.15C16.8 5.35 18.65 8.1 18.7 11.15L15.65 14.2C12.6 14.15 9.85 12.3 8.65 9.6L14.1 4.15Z"
          stroke="currentColor"
          stroke-width="1.7"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <circle cx="14.15" cy="8.7" r="1.15" stroke="currentColor" stroke-width="1.55" />
        <path d="M8.7 15.3L6.2 17.8M10 16.6L7.5 19.1" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" />
        <path d="M9.55 10.5L6.3 13.75" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" />
      </template>

      <template v-else-if="resolvedIconKey === 'satellite'">
        <rect x="10.15" y="9.75" width="3.7" height="4.5" rx="0.85" stroke="currentColor" stroke-width="1.6" />
        <path
          d="M13.85 10.85L17.1 7.6L18.9 9.4L15.65 12.65M10.15 13.15L6.9 16.4L5.1 14.6L8.35 11.35"
          stroke="currentColor"
          stroke-width="1.6"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <path d="M8.7 8.2L10.2 9.7M13.8 14.3L15.3 15.8" stroke="currentColor" stroke-width="1.45" stroke-linecap="round" />
      </template>

      <template v-else>
        <path
          d="M15.95 7.3C17.6 7.3 18.95 8.65 18.95 10.3C18.95 11.1 18.62 11.9 18.05 12.45L12.2 18.3C11.62 18.88 10.85 19.2 10.05 19.2C8.4 19.2 7.05 17.85 7.05 16.2C7.05 15.4 7.38 14.63 7.95 14.05L13.8 8.2C14.38 7.63 15.15 7.3 15.95 7.3Z"
          stroke="currentColor"
          stroke-width="1.7"
          stroke-linejoin="round"
        />
        <path d="M6.45 7.1H4.2M8.05 5.7L6.45 4.1M10 6.2L8.95 4.35" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
      </template>
    </svg>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  AVATAR_COLORS,
  AVATAR_ICONS,
  LEGACY_AVATAR_ICON_COUNT,
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

  return pickDeterministicAvatarIndex(
    resolvedSeed.value,
    'icon',
    Math.min(LEGACY_AVATAR_ICON_COUNT, AVATAR_ICONS.length),
  )
})

const resolvedIconKey = computed(() => AVATAR_ICONS[resolvedIconIndex.value] || AVATAR_ICONS[0] || 'planet')

const avatarStyle = computed(() => ({
  '--default-avatar-prop-size': `${Math.max(1, Number(props.size) || 40)}px`,
  backgroundColor: AVATAR_COLORS[resolvedColorIndex.value],
}))
</script>

<style scoped>
.default-avatar {
  position: relative;
  width: var(--default-avatar-size, var(--default-avatar-prop-size));
  height: var(--default-avatar-size, var(--default-avatar-prop-size));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-white);
  overflow: hidden;
  flex-shrink: 0;
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.24),
    inset 0 -12px 18px rgb(0 0 0 / 0.08);
}

.default-avatar::after {
  content: '';
  position: absolute;
  inset: 10% 18% auto;
  height: 34%;
  border-radius: 999px;
  background: radial-gradient(circle at 50% 50%, rgb(255 255 255 / 0.28), transparent 72%);
  pointer-events: none;
}

.default-avatar-icon {
  width: 56%;
  height: 56%;
  display: block;
  filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.16));
}
</style>
