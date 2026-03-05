<template>
  <span class="defaultAvatar" :style="avatarStyle" role="img" :aria-label="labelText">
    <span class="defaultAvatarIcon" aria-hidden="true">{{ iconGlyph }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { AVATAR_COLORS, AVATAR_ICONS, pickDeterministicAvatarIndex } from '@/constants/avatar'

const props = defineProps({
  size: {
    type: [Number, String],
    default: 40,
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
  label: {
    type: String,
    default: 'User avatar',
  },
})

function clampIndex(value, maxIndex) {
  const parsed = Number(value)
  if (!Number.isInteger(parsed)) return null
  if (parsed < 0 || parsed > maxIndex) return null
  return parsed
}

const resolvedSeed = computed(() => String(props.seed || 'avatar'))
const resolvedColorIndex = computed(() => {
  const fromProps = clampIndex(props.colorIndex, AVATAR_COLORS.length - 1)
  if (fromProps !== null) return fromProps
  return pickDeterministicAvatarIndex(resolvedSeed.value, 'color', AVATAR_COLORS.length)
})
const resolvedIconIndex = computed(() => {
  const fromProps = clampIndex(props.iconIndex, AVATAR_ICONS.length - 1)
  if (fromProps !== null) return fromProps
  return pickDeterministicAvatarIndex(resolvedSeed.value, 'icon', AVATAR_ICONS.length)
})

const resolvedSize = computed(() => {
  const parsed = Number(props.size)
  if (!Number.isFinite(parsed) || parsed <= 0) return 40
  return parsed
})

const iconGlyph = computed(() => {
  const icon = AVATAR_ICONS[resolvedIconIndex.value] || 'planet'
  const glyphByIcon = {
    planet: 'O',
    star: '*',
    comet: 'C',
    constellation: 'X',
    moon: 'M',
  }

  return glyphByIcon[icon] || 'A'
})

const labelText = computed(() => String(props.label || 'User avatar'))
const avatarStyle = computed(() => ({
  '--avatar-size': `${resolvedSize.value}px`,
  '--avatar-bg': AVATAR_COLORS[resolvedColorIndex.value] || AVATAR_COLORS[0],
}))
</script>

<style scoped>
.defaultAvatar {
  width: var(--avatar-size);
  height: var(--avatar-size);
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--avatar-bg);
  color: #ffffff;
  font-weight: 700;
  line-height: 1;
  user-select: none;
}

.defaultAvatarIcon {
  font-size: calc(var(--avatar-size) * 0.42);
}
</style>
