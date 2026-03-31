<template>
  <article
    class="observation-card"
    :class="{
      'observation-card--compact': compact,
      'observation-card--clickable': clickable,
    }"
    @click="onOpen"
  >
    <header class="observation-header">
      <div class="observation-heading">
        <h3 class="observation-title">{{ titleLabel }}</h3>
        <p class="observation-time">{{ observedAtLabel }}</p>
      </div>
      <span v-if="ratingLabel" class="observation-rating">{{ ratingLabel }}</span>
    </header>

    <p v-if="descriptionLabel" class="observation-description">{{ descriptionLabel }}</p>

    <div v-if="images.length > 0" class="observation-media">
      <img
        v-for="mediaItem in images"
        :key="mediaItem.id || mediaItem.resolvedUrl"
        class="observation-image"
        :src="mediaItem.resolvedUrl"
        :alt="mediaItem.alt || 'Observation image'"
        loading="lazy"
      >
    </div>

    <footer class="observation-meta">
      <span v-if="showAuthor && authorLabel" class="observation-meta-item">{{ authorLabel }}</span>
      <span v-if="locationLabel" class="observation-meta-item">{{ locationLabel }}</span>
      <span v-if="equipmentLabel" class="observation-meta-item">{{ equipmentLabel }}</span>
      <RouterLink
        v-if="showEventLink && eventId > 0"
        class="observation-event-link"
        :to="`/events/${eventId}`"
        @click.stop
      >
        {{ eventLabel }}
      </RouterLink>
    </footer>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { formatDateTimeCompact } from '@/utils/dateUtils'
import { normalizeObservationMediaItems } from '@/utils/observationMedia'

const props = defineProps({
  observation: {
    type: Object,
    required: true,
  },
  showAuthor: {
    type: Boolean,
    default: true,
  },
  showEventLink: {
    type: Boolean,
    default: true,
  },
  compact: {
    type: Boolean,
    default: false,
  },
  clickable: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['open'])

const titleLabel = computed(() => String(props.observation?.title || 'Pozorovanie'))
const descriptionLabel = computed(() => String(props.observation?.description || '').trim())
const observedAtLabel = computed(() => formatDateTimeCompact(props.observation?.observed_at))

const images = computed(() => {
  return normalizeObservationMediaItems(props.observation?.media)
})

const ratingLabel = computed(() => {
  const rating = Number(props.observation?.visibility_rating || 0)
  if (!Number.isInteger(rating) || rating < 1 || rating > 5) return ''
  return `Seeing ${rating}/5`
})

const authorLabel = computed(() => {
  const username = String(props.observation?.user?.username || '').trim()
  if (!username) return ''
  return `@${username}`
})

const locationLabel = computed(() => String(props.observation?.location_name || '').trim())
const equipmentLabel = computed(() => String(props.observation?.equipment || '').trim())

const eventId = computed(() => Number(props.observation?.event?.id || props.observation?.event_id || 0))
const eventLabel = computed(() => {
  const title = String(props.observation?.event?.title || '').trim()
  return title ? `Udalosť: ${title}` : 'Otvoriť udalosť'
})

function onOpen() {
  if (!props.clickable) return
  emit('open', props.observation)
}
</script>

<style scoped>
.observation-card {
  border: 1px solid var(--color-border);
  background: rgb(var(--bg-surface-rgb) / 0.8);
  border-radius: var(--radius-md);
  padding: 0.78rem;
  display: grid;
  gap: 0.5rem;
}

.observation-card--compact {
  gap: 0.45rem;
  padding: 0.65rem;
}

.observation-card--clickable {
  cursor: pointer;
}

.observation-card--clickable:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.48);
  background: rgb(var(--bg-surface-rgb) / 0.92);
}

.observation-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
}

.observation-heading {
  min-width: 0;
}

.observation-title {
  margin: 0;
  color: var(--color-text-primary);
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.3;
}

.observation-time {
  margin: 0.2rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

.observation-rating {
  border: 1px solid rgb(var(--color-accent-rgb) / 0.45);
  border-radius: 999px;
  padding: 0.14rem 0.48rem;
  font-size: 0.7rem;
  color: var(--color-accent);
  background: rgb(var(--color-accent-rgb) / 0.16);
  white-space: nowrap;
}

.observation-description {
  margin: 0;
  color: rgb(var(--color-text-primary-rgb) / 0.92);
  line-height: 1.48;
  white-space: pre-wrap;
}

.observation-media {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 0.4rem;
}

.observation-image {
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-subtle);
  background: rgb(var(--bg-app-rgb) / 0.5);
}

.observation-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  align-items: center;
}

.observation-meta-item {
  font-size: 0.74rem;
  color: var(--color-text-secondary);
  border: 1px solid var(--color-border);
  border-radius: 999px;
  padding: 0.12rem 0.45rem;
}

.observation-event-link {
  font-size: 0.74rem;
  color: var(--color-accent);
  text-decoration: none;
  border: 1px solid rgb(var(--color-accent-rgb) / 0.35);
  border-radius: 999px;
  padding: 0.12rem 0.45rem;
}

.observation-event-link:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.65);
  background: rgb(var(--color-accent-rgb) / 0.14);
}
</style>
