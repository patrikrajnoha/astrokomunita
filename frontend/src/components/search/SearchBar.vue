<template>
  <form class="space-y-2" role="search" @submit.prevent="emit('submit')">
    <label :for="inputId" class="block text-xs font-semibold uppercase tracking-wide text-[color:rgb(var(--color-text-secondary-rgb)/0.92)]">
      Prehladavat Astrokomunitu
    </label>

    <div class="relative">
      <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[color:rgb(var(--color-text-secondary-rgb)/0.78)]" aria-hidden="true">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M21 21l-6-6m1-4a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </span>

      <input
        :id="inputId"
        ref="inputRef"
        v-model="localQuery"
        type="search"
        :placeholder="placeholder"
        class="w-full rounded-xl border border-[color:rgb(var(--color-text-secondary-rgb)/0.36)] bg-[color:rgb(var(--color-bg-rgb)/0.88)] py-3 pl-9 pr-20 text-sm text-[var(--color-surface)] placeholder-[color:rgb(var(--color-text-secondary-rgb)/0.85)] shadow-sm transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[color:rgb(var(--color-primary-rgb)/0.24)]"
        autocomplete="off"
        aria-label="Hladat"
      />

      <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1.5">
        <button
          v-if="hasQuery"
          type="button"
          class="rounded-md p-1 text-[color:rgb(var(--color-text-secondary-rgb)/0.82)] transition hover:text-[var(--color-primary)]"
          aria-label="Vymazat hladanie"
          @click="clearQuery"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <span
          v-if="loading"
          class="h-4 w-4 animate-spin rounded-full border-2 border-[color:rgb(var(--color-text-secondary-rgb)/0.34)] border-t-[var(--color-primary)]"
          aria-hidden="true"
        ></span>
      </div>
    </div>
  </form>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  placeholder: {
    type: String,
    default: 'Prehladavat',
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const inputRef = ref(null)
const inputId = `global-search-${Math.random().toString(36).slice(2, 9)}`

const localQuery = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const hasQuery = computed(() => String(props.modelValue || '').trim().length > 0)

const clearQuery = () => {
  emit('update:modelValue', '')
  inputRef.value?.focus()
}
</script>
