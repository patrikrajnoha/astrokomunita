<template>
  <article class="profileEventCard" @click="onOpen">
    <h3 class="profileEventTitle">{{ title }}</h3>
    <p v-if="dateLabel" class="profileEventDate">{{ dateLabel }}</p>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['open'])

const title = computed(() => String(props.event?.title || 'Udalost'))
const dateLabel = computed(() => String(props.event?.start_at || '').trim())

function onOpen() {
  emit('open', props.event)
}
</script>

<style scoped>
.profileEventCard {
  border: 1px solid rgb(var(--color-text-secondary-rgb, 148 163 184) / 0.25);
  border-radius: 0.85rem;
  padding: 0.75rem;
  background: rgb(var(--color-bg-rgb, 15 23 42) / 0.45);
  cursor: pointer;
}

.profileEventTitle {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 700;
}

.profileEventDate {
  margin: 0.3rem 0 0;
  font-size: 0.78rem;
  color: rgb(var(--color-text-secondary-rgb, 148 163 184) / 0.95);
}
</style>
