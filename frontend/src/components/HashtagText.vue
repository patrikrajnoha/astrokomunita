<template>
  <span class="hashtag-text">
    <template v-for="(segment, index) in segments" :key="index">
      <component 
        :is="segment.type === 'hashtag' ? 'router-link' : 'span'"
        v-if="segment.type === 'hashtag'"
        :to="`/hashtags/${segment.text}`"
        class="hashtag-link"
      >
        #{{ segment.text }}
      </component>
      <span v-else>{{ segment.text }}</span>
    </template>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  content: {
    type: String,
    default: ''
  }
})

const segments = computed(() => {
  if (!props.content) return []
  
  const result = []
  let lastIndex = 0
  const regex = /#([a-zA-Z0-9_]{1,32})/g
  let match

  while ((match = regex.exec(props.content)) !== null) {
    // Add text before hashtag
    if (match.index > lastIndex) {
      const textBefore = props.content.slice(lastIndex, match.index)
      if (textBefore) {
        result.push({ type: 'text', text: textBefore })
      }
    }

    // Add hashtag
    result.push({ type: 'hashtag', text: match[1] })
    lastIndex = regex.lastIndex
  }

  // Add remaining text
  if (lastIndex < props.content.length) {
    const remainingText = props.content.slice(lastIndex)
    if (remainingText) {
      result.push({ type: 'text', text: remainingText })
    }
  }

  return result
})
</script>

<style scoped>
.hashtag-text {
  white-space: pre-wrap;
  line-height: 1.6;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.hashtag-link {
  color: var(--color-primary);
  text-decoration: none;
  font-weight: 500;
  border-bottom: 1px solid transparent;
  transition: border-color 0.2s ease, color 0.2s ease;
}

.hashtag-link:hover {
  border-bottom-color: var(--color-primary);
  color: rgb(var(--color-primary-rgb) / 0.85);
}
</style>
