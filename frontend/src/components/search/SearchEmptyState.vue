<template>
  <section
    class="rounded-2xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.22)] bg-[color:rgb(var(--color-bg-rgb)/0.64)] p-5 text-center shadow-sm"
    role="status"
    aria-live="polite"
  >
    <span class="mx-auto mb-3 inline-flex h-11 w-11 items-center justify-center rounded-full border border-[color:rgb(var(--color-text-secondary-rgb)/0.26)] bg-[color:rgb(var(--color-bg-rgb)/0.84)] text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">
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

    <h3 class="text-base font-semibold text-[var(--color-surface)]">{{ title }}</h3>
    <p class="mt-1 text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]">{{ message }}</p>
    <p v-if="hint" class="mt-2 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.82)]">{{ hint }}</p>
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
