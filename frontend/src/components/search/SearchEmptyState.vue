<template>
  <section
    class="searchEmptyState"
    role="status"
    aria-live="polite"
  >
    <span class="searchEmptyState__icon" aria-hidden="true">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          v-for="(path, index) in iconPaths"
          :key="`empty-icon-${index}`"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="1.8"
          :d="path"
        />
      </svg>
    </span>

    <h3 class="searchEmptyState__title">{{ title }}</h3>
    <p class="searchEmptyState__message">{{ message }}</p>
    <p v-if="hint" class="searchEmptyState__hint">{{ hint }}</p>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  message: {
    type: String,
    required: true,
  },
  hint: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'search',
  },
})

const iconPaths = computed(() => {
  if (props.type === 'discovery') {
    return [
      'M12 3l2.4 4.86 5.36.78-3.88 3.78.92 5.34L12 15.3l-4.8 2.46.92-5.34-3.88-3.78 5.36-.78L12 3z',
    ]
  }

  return [
    'M10.5 17a6.5 6.5 0 116.5-6.5 6.5 6.5 0 01-6.5 6.5z',
    'M21 21l-4.35-4.35',
  ]
})
</script>

<style scoped>
.searchEmptyState {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-card);
  padding: 1rem 0.9rem;
  display: grid;
  justify-items: center;
  gap: 0.35rem;
  text-align: center;
}

.searchEmptyState__icon {
  width: 2.4rem;
  height: 2.4rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--bg-app-rgb) / 0.5);
}

.searchEmptyState__title {
  margin: 0;
  font-size: var(--font-size-base);
  font-weight: 650;
  color: var(--color-text-primary);
}

.searchEmptyState__message {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
  line-height: 1.45;
  max-width: 52ch;
}

.searchEmptyState__hint {
  margin: 0;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  font-size: 0.74rem;
  line-height: 1.4;
}
</style>
