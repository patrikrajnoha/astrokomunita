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
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1;
  text-transform: capitalize;
  white-space: nowrap;
  border: 1px solid var(--border);
  background: rgb(var(--bg-surface-2-rgb) / 0.74);
  color: var(--text-secondary);
}

.statusBadge--small {
  padding: 0.125rem 0.5rem;
  font-size: 0.75rem;
}

.statusBadge--green,
.statusBadge--blue,
.statusBadge--orange,
.statusBadge--yellow {
  border-color: rgb(var(--primary-rgb) / 0.28);
  background: rgb(var(--primary-rgb) / 0.12);
  color: var(--primary);
}

.statusBadge--red,
.statusBadge--purple {
  border-color: rgb(var(--primary-active-rgb) / 0.28);
  background: rgb(var(--primary-active-rgb) / 0.12);
  color: var(--primary-active);
}

.statusBadge--gray {
  border-color: var(--border);
  background: rgb(var(--bg-app-rgb) / 0.24);
  color: var(--text-secondary);
}
</style>
