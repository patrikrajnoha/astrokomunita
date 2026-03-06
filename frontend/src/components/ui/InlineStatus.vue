<template>
  <div
    class="inlineStatus"
    :class="`inlineStatus--${variant}`"
    :role="variant === 'error' ? 'alert' : 'status'"
    data-testid="inline-status"
  >
    <span class="inlineStatus__message">{{ message }}</span>
    <button
      v-if="actionLabel"
      type="button"
      class="ui-pill ui-pill--secondary inlineStatus__action"
      data-testid="inline-status-action"
      @click="$emit('action')"
    >
      {{ actionLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'info',
    validator: (value) => ['success', 'error', 'info'].includes(value),
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
.inlineStatus {
  margin-top: 0.75rem;
  border-radius: 0.75rem;
  border: 1px solid transparent;
  padding: 0.65rem 0.78rem;
  font-size: 0.88rem;
  line-height: 1.35;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
}

.inlineStatus__message {
  min-width: 0;
}

.inlineStatus__action {
  min-height: 44px;
  white-space: nowrap;
  font-size: 0.82rem;
  padding-inline: 0.9rem;
}

.inlineStatus--success {
  border-color: rgb(var(--success-rgb) / 0.45);
  background: rgb(var(--success-rgb) / 0.12);
  color: rgb(var(--success-rgb) / 0.98);
}

.inlineStatus--error {
  border-color: rgb(var(--danger-rgb) / 0.45);
  background: rgb(var(--danger-rgb) / 0.14);
  color: rgb(var(--text-primary-rgb) / 0.95);
}

.inlineStatus--info {
  border-color: rgb(var(--text-secondary-rgb) / 0.35);
  background: rgb(var(--bg-surface-2-rgb) / 0.58);
  color: var(--text-secondary);
}

@media (max-width: 640px) {
  .inlineStatus {
    align-items: stretch;
    flex-direction: column;
  }

  .inlineStatus__action {
    width: 100%;
  }
}
</style>
