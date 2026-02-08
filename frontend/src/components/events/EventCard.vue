<template>
  <article class="event-card">
    <div class="hero-wrap">
      <img
        v-if="heroImage"
        class="hero-image"
        :src="heroImage"
        :alt="event?.title || 'Event image'"
        loading="lazy"
        decoding="async"
      />
      <div v-else class="hero-fallback" aria-hidden="true">Astro</div>
      <div class="hero-overlay"></div>
    </div>

    <div class="card-body">
      <h2 class="title">{{ event?.title || 'Bez nazvu' }}</h2>
      <p class="meta-row">{{ formattedTime }}</p>
      <p class="visibility-row">{{ `${SK_FLAG} ${visibilityIcon}` }}</p>

      <button type="button" class="bio-hit" @click="$emit('toggle-bio')">
        <transition name="bio-expand" mode="out-in">
          <p v-if="bioExpanded" key="expanded" class="bio bio-expanded">
            {{ description }}
            <span class="bio-action">Menej</span>
          </p>
          <p v-else key="collapsed" class="bio bio-collapsed">
            {{ description }}
          </p>
        </transition>
      </button>

      <button type="button" class="more-btn" @click="$emit('open-sheet')">Viac detailu</button>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  event: {
    type: Object,
    default: null,
  },
  formattedTime: {
    type: String,
    default: '-',
  },
  visibilityIcon: {
    type: String,
    default: '\u25d1',
  },
  bioExpanded: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['toggle-bio', 'open-sheet'])

const SK_FLAG = '\ud83c\uddf8\ud83c\uddf0'

const description = computed(() => props.event?.description || props.event?.short || 'Bez popisu.')
const heroImage = computed(
  () => props.event?.image || props.event?.image_url || props.event?.hero_image || props.event?.cover_image_url || ''
)
</script>

<style scoped>
.event-card {
  border-radius: 1.4rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  background: linear-gradient(
    160deg,
    rgb(var(--color-bg-rgb) / 0.95) 0%,
    rgb(var(--color-bg-rgb) / 0.86) 100%
  );
  overflow: hidden;
  box-shadow: 0 18px 48px rgb(5 12 30 / 0.4);
}

.hero-wrap {
  position: relative;
  height: 250px;
}

.hero-image,
.hero-fallback {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.hero-fallback {
  display: grid;
  place-items: center;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  font-weight: 700;
  color: rgb(var(--color-surface-rgb) / 0.8);
  background: radial-gradient(circle at 20% 20%, #1f4b87 0%, #0f172a 55%, #060b15 100%);
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 48%, rgb(3 8 22 / 0.74) 100%);
}

.card-body {
  padding: 1rem 1rem 1.1rem;
}

.title {
  font-size: 1.34rem;
  line-height: 1.2;
  font-weight: 700;
  color: var(--color-surface);
}

.meta-row,
.visibility-row {
  margin-top: 0.35rem;
  font-size: 0.9rem;
  color: rgb(var(--color-surface-rgb) / 0.78);
}

.visibility-row {
  font-weight: 600;
}

.bio-hit {
  width: 100%;
  margin-top: 0.8rem;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.bio {
  color: rgb(var(--color-surface-rgb) / 0.88);
  font-size: 0.92rem;
  line-height: 1.5;
  overflow: hidden;
}

.bio-collapsed {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  text-overflow: ellipsis;
}

.bio-expanded {
  display: -webkit-box;
  -webkit-line-clamp: 5;
  -webkit-box-orient: vertical;
}

.bio-action {
  margin-left: 0.45rem;
  color: var(--color-primary);
  font-weight: 600;
}

.more-btn {
  margin-top: 0.7rem;
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-size: 0.84rem;
  font-weight: 600;
  padding: 0;
}

.bio-expand-enter-active,
.bio-expand-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}

.bio-expand-enter-from,
.bio-expand-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>

