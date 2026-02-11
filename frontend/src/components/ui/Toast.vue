<template>
  <article
    class="toast"
    :class="`toast--${item.type}`"
    role="status"
    :aria-live="item.type === 'error' ? 'assertive' : 'polite'"
    aria-atomic="true"
  >
    <span class="toastIcon" aria-hidden="true">
      <svg v-if="item.type === 'success'" viewBox="0 0 20 20" fill="none">
        <path d="M4 10.5l3.4 3.4L16 5.8" />
      </svg>
      <svg v-else-if="item.type === 'error'" viewBox="0 0 20 20" fill="none">
        <path d="M10 3.5v7" />
        <circle cx="10" cy="14.2" r="0.9" fill="currentColor" />
        <path d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" />
      </svg>
      <svg v-else-if="item.type === 'warn'" viewBox="0 0 20 20" fill="none">
        <path d="M10 2.8 2.8 16.8h14.4L10 2.8Z" />
        <path d="M10 7.5v4.2" />
        <circle cx="10" cy="14.1" r="0.85" fill="currentColor" />
      </svg>
      <svg v-else viewBox="0 0 20 20" fill="none">
        <circle cx="10" cy="10" r="8" />
        <path d="M10 8v5" />
        <circle cx="10" cy="5.6" r="0.85" fill="currentColor" />
      </svg>
    </span>

    <div class="toastBody">
      <p v-if="item.title" class="toastTitle">{{ item.title }}</p>
      <p class="toastMessage">{{ item.message }}</p>
    </div>

    <button
      v-if="item.action"
      type="button"
      class="toastAction"
      @click="$emit('action', item.id)"
    >
      {{ item.action.label }}
    </button>

    <button
      v-if="item.dismissible"
      type="button"
      class="toastClose"
      aria-label="Dismiss notification"
      @click="$emit('dismiss', item.id)"
    >
      <svg viewBox="0 0 20 20" fill="none">
        <path d="M5 5l10 10" />
        <path d="M15 5 5 15" />
      </svg>
    </button>
  </article>
</template>

<script setup>
defineProps({
  item: {
    type: Object,
    required: true,
  },
})

defineEmits(['dismiss', 'action'])
</script>

<style scoped>
.toast {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  align-items: start;
  gap: 0.65rem;
  border-radius: 0.95rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.34);
  background: rgb(var(--color-bg-rgb) / 0.96);
  color: var(--color-surface);
  padding: 0.72rem 0.72rem;
  box-shadow: 0 18px 40px rgb(0 0 0 / 0.34);
  backdrop-filter: blur(8px);
}

.toast--success {
  border-color: rgb(var(--color-success-rgb) / 0.5);
}

.toast--warn {
  border-color: rgb(245 158 11 / 0.5);
}

.toast--error {
  border-color: rgb(var(--color-danger-rgb) / 0.58);
}

.toastIcon {
  width: 1.1rem;
  height: 1.1rem;
  margin-top: 0.08rem;
  color: rgb(var(--color-primary-rgb) / 0.9);
}

.toast--success .toastIcon {
  color: var(--color-success);
}

.toast--warn .toastIcon {
  color: rgb(245 158 11);
}

.toast--error .toastIcon {
  color: var(--color-danger);
}

.toastIcon svg {
  width: 100%;
  height: 100%;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.toastBody {
  min-width: 0;
}

.toastTitle {
  margin: 0;
  font-size: 0.79rem;
  font-weight: 800;
}

.toastMessage {
  margin: 0;
  font-size: 0.84rem;
  line-height: 1.3;
  color: rgb(var(--color-surface-rgb) / 0.95);
}

.toastAction,
.toastClose {
  border: 0;
  background: transparent;
  color: inherit;
}

.toastAction {
  align-self: center;
  font-size: 0.76rem;
  font-weight: 700;
  color: var(--color-primary);
  padding: 0.2rem 0.3rem;
  border-radius: 0.45rem;
}

.toastClose {
  width: 1.65rem;
  height: 1.65rem;
  border-radius: 0.5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  opacity: 0.72;
}

.toastClose svg {
  width: 1rem;
  height: 1rem;
  stroke: currentColor;
  stroke-width: 1.9;
}

.toastAction:hover,
.toastClose:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.18);
  opacity: 1;
}

.toastAction:focus-visible,
.toastClose:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}
</style>
