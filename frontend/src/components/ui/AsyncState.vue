<template>
  <section
    class="asyncState"
    :class="[`asyncState--${mode}`, { 'asyncState--compact': compact }]"
    :role="mode === 'error' ? 'alert' : 'status'"
    data-testid="async-state"
  >
    <div v-if="mode === 'loading' && loadingStyle === 'spinner'" class="asyncState__spinner" data-testid="async-state-spinner" aria-hidden="true"></div>
    <div v-else-if="mode === 'loading'" class="asyncState__skeleton" data-testid="async-state-skeleton" aria-hidden="true">
      <span
        v-for="index in skeletonRows"
        :key="`async-state-skeleton-${index}`"
        class="asyncState__skeletonLine ui-skeleton ui-skeleton--line"
      ></span>
    </div>
    <div v-else-if="mode === 'error'" class="asyncState__icon asyncState__icon--error" aria-hidden="true">!</div>
    <div v-else class="asyncState__icon" aria-hidden="true">i</div>

    <h3 v-if="title" class="asyncState__title">{{ title }}</h3>
    <p v-if="message" class="asyncState__message">{{ message }}</p>

    <button
      v-if="actionLabel"
      type="button"
      class="ui-pill ui-pill--secondary asyncState__action"
      data-testid="async-state-action"
      @click="$emit('action')"
    >
      {{ actionLabel }}
    </button>
  </section>
</template>

<script setup>
defineProps({
  mode: {
    type: String,
    default: 'error',
    validator: (value) => ['loading', 'error', 'empty', 'info'].includes(value),
  },
  title: {
    type: String,
    default: '',
  },
  message: {
    type: String,
    default: '',
  },
  actionLabel: {
    type: String,
    default: '',
  },
  loadingStyle: {
    type: String,
    default: 'spinner',
    validator: (value) => ['spinner', 'skeleton'].includes(value),
  },
  skeletonRows: {
    type: Number,
    default: 3,
  },
  compact: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['action'])
</script>

<style scoped>
.asyncState {
  margin-top: var(--space-4);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  padding: var(--space-4) var(--space-4) calc(var(--space-4) - 2px);
  background: var(--color-card);
  color: var(--color-text-primary);
  display: grid;
  justify-items: center;
  text-align: center;
  gap: 0.45rem;
  box-shadow: var(--shadow-soft);
}

.asyncState__spinner {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  border: 2px solid rgb(var(--color-accent-rgb) / 0.24);
  border-top-color: rgb(var(--color-accent-rgb) / 0.92);
  animation: async-state-spin 1s linear infinite;
}

.asyncState__skeleton {
  width: 100%;
  display: grid;
  gap: 0.48rem;
}

.asyncState__skeletonLine {
  width: 100%;
}

.asyncState__skeletonLine:nth-child(2n) {
  width: 78%;
}

.asyncState__icon {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border);
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-size: 0.85rem;
  font-weight: 700;
}

.asyncState__icon--error {
  border-color: rgb(var(--danger-rgb) / 0.55);
  color: var(--danger);
}

.asyncState__title {
  margin: 0;
  font-size: var(--font-size-base);
  line-height: 1.3;
  font-weight: 700;
}

.asyncState__message {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
  line-height: 1.45;
  max-width: 44ch;
}

.asyncState__action {
  margin-top: 0.1rem;
  min-width: 10rem;
}

.asyncState--error {
  border-color: rgb(var(--color-danger-rgb) / 0.36);
  background: rgb(var(--color-danger-rgb) / 0.08);
}

.asyncState--compact {
  margin-top: 0;
  border-radius: var(--radius-md);
  padding: var(--space-3);
  text-align: left;
  justify-items: start;
}

.asyncState--compact .asyncState__action {
  min-width: auto;
}

@keyframes async-state-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
