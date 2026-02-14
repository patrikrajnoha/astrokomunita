<template>
  <span class="typingText" :aria-label="text">
    <span class="typingText__content">{{ visibleText }}</span>
    <span v-if="showCursor" class="typingText__cursor" aria-hidden="true">|</span>
  </span>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  text: {
    type: String,
    required: true,
  },
  speedMs: {
    type: Number,
    default: 56,
  },
  startDelayMs: {
    type: Number,
    default: 150,
  },
  showCursor: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['done'])
const visibleText = ref('')

let startDelayTimer = null
let typingInterval = null

const clearTimers = () => {
  if (startDelayTimer !== null) {
    window.clearTimeout(startDelayTimer)
    startDelayTimer = null
  }
  if (typingInterval !== null) {
    window.clearInterval(typingInterval)
    typingInterval = null
  }
}

const restartTyping = () => {
  clearTimers()
  visibleText.value = ''

  const source = String(props.text || '')
  if (!source) {
    emit('done')
    return
  }

  const speed = Math.max(16, Number(props.speedMs) || 56)
  const startDelay = Math.max(0, Number(props.startDelayMs) || 0)
  let index = 0

  startDelayTimer = window.setTimeout(() => {
    typingInterval = window.setInterval(() => {
      index += 1
      visibleText.value = source.slice(0, index)

      if (index >= source.length) {
        clearTimers()
        emit('done')
      }
    }, speed)
  }, startDelay)
}

watch(
  () => props.text,
  () => {
    restartTyping()
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  clearTimers()
})
</script>

<style scoped>
.typingText {
  display: inline-flex;
  align-items: baseline;
  white-space: nowrap;
}

.typingText__content {
  white-space: nowrap;
}

.typingText__cursor {
  margin-left: 0.06em;
  opacity: 0.9;
  animation: typingCursorBlink 1s steps(1, end) infinite;
}

@keyframes typingCursorBlink {
  0%,
  49% {
    opacity: 0.95;
  }
  50%,
  100% {
    opacity: 0;
  }
}
</style>
