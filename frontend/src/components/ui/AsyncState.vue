<template>
  <section
    class="asyncState"
    :class="[`asyncState--${mode}`]"
    :role="mode === 'error' ? 'alert' : 'status'"
    data-testid="async-state"
  >
    <div v-if="mode === 'loading'" class="asyncState__spinner" data-testid="async-state-spinner" aria-hidden="true"></div>
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
})

defineEmits(['action'])
</script>

<style scoped>
.asyncState {
  margin-top: 0.9rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--text-secondary-rgb) / 0.22);
  padding: 1rem 1rem 0.95rem;
  background: rgb(var(--bg-surface-rgb) / 0.74);
  color: var(--text-primary);
  display: grid;
  justify-items: center;
  text-align: center;
  gap: 0.45rem;
}

.asyncState__spinner {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  border: 2px solid rgb(var(--primary-rgb) / 0.24);
  border-top-color: rgb(var(--primary-rgb) / 0.95);
  animation: async-state-spin 1s linear infinite;
}

.asyncState__icon {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgb(var(--text-secondary-rgb) / 0.4);
  color: rgb(var(--text-secondary-rgb) / 0.95);
  font-size: 0.85rem;
  font-weight: 700;
}

.asyncState__icon--error {
  border-color: rgb(var(--danger-rgb) / 0.55);
  color: rgb(var(--danger-rgb) / 0.95);
}

.asyncState__title {
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.3;
  font-weight: 700;
}

.asyncState__message {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.84rem;
  line-height: 1.45;
  max-width: 44ch;
}

.asyncState__action {
  margin-top: 0.1rem;
  min-width: 10rem;
}

.asyncState--error {
  border-color: rgb(var(--danger-rgb) / 0.28);
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
