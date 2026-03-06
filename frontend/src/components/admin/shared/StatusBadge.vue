<template>
  <span
    :class="[
      'statusBadge',
      `statusBadge--${badgeType}`,
      { 'statusBadge--small': small }
    ]"
  >
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';
import { STATUS_COLORS, STATUS_LABELS } from '@/utils/constants.js';

const props = defineProps({
  status: {
    type: String,
    required: true
  },
  type: {
    type: String,
    default: 'default'
  },
  small: {
    type: Boolean,
    default: false
  },
  customLabel: {
    type: String,
    default: ''
  }
});

const label = computed(() => {
  if (props.customLabel) return props.customLabel;
  
  // Skúsiť nájsť label v STATUS_LABELS
  if (STATUS_LABELS[props.status]) {
    return STATUS_LABELS[props.status];
  }
  
  // Fallback na capitalize status
  return props.status.charAt(0).toUpperCase() + props.status.slice(1).replace('_', ' ');
});

const badgeType = computed(() => {
  if (props.type !== 'default') return props.type;
  
  // Automatická detekcia farby podľa STATUS_COLORS
  return STATUS_COLORS[props.status] || 'gray';
});
</script>

<style scoped>
.statusBadge {
  display: inline-flex;
  align-items: center;
  min-height: 1.75rem;
  padding: 0.2rem 0.7rem;
  border-radius: 9999px;
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1;
  text-transform: none;
  white-space: nowrap;
  border: 1px solid var(--border);
  background: rgb(var(--bg-surface-2-rgb) / 0.74);
  color: var(--text-secondary);
}

.statusBadge--small {
  min-height: 1.4rem;
  padding: 0.125rem 0.5rem;
  font-size: 0.75rem;
}

.statusBadge--green {
  border-color: rgb(var(--color-success-rgb) / 0.4);
  background: rgb(var(--color-success-rgb) / 0.14);
  color: var(--color-success);
}

.statusBadge--blue {
  border-color: rgb(var(--color-primary-rgb) / 0.4);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: var(--color-primary);
}

.statusBadge--orange,
.statusBadge--yellow {
  border-color: rgb(var(--color-warning-rgb) / 0.45);
  background: rgb(var(--color-warning-rgb) / 0.14);
  color: var(--color-warning);
}

.statusBadge--red,
.statusBadge--purple {
  border-color: rgb(var(--color-danger-rgb) / 0.45);
  background: rgb(var(--color-danger-rgb) / 0.14);
  color: var(--color-danger);
}

.statusBadge--gray {
  border-color: var(--border);
  background: rgb(var(--bg-app-rgb) / 0.24);
  color: var(--text-secondary);
}
</style>
