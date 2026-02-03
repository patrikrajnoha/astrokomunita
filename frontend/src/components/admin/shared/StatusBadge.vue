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
  font-weight: 500;
  line-height: 1;
  text-transform: capitalize;
  white-space: nowrap;
}

.statusBadge--small {
  padding: 0.125rem 0.5rem;
  font-size: 0.75rem;
}

/* Status farby */
.statusBadge--green {
  background-color: rgba(34, 197, 94, 0.1);
  color: rgb(21, 128, 61);
  border: 1px solid rgba(34, 197, 94, 0.2);
}

.statusBadge--blue {
  background-color: rgba(59, 130, 246, 0.1);
  color: rgb(29, 78, 216);
  border: 1px solid rgba(59, 130, 246, 0.2);
}

.statusBadge--orange {
  background-color: rgba(251, 146, 60, 0.1);
  color: rgb(194, 65, 12);
  border: 1px solid rgba(251, 146, 60, 0.2);
}

.statusBadge--red {
  background-color: rgba(239, 68, 68, 0.1);
  color: rgb(185, 28, 28);
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.statusBadge--gray {
  background-color: rgba(107, 114, 128, 0.1);
  color: rgb(55, 65, 81);
  border: 1px solid rgba(107, 114, 128, 0.2);
}

.statusBadge--yellow {
  background-color: rgba(250, 204, 21, 0.1);
  color: rgb(161, 98, 7);
  border: 1px solid rgba(250, 204, 21, 0.2);
}

.statusBadge--purple {
  background-color: rgba(168, 85, 247, 0.1);
  color: rgb(126, 34, 206);
  border: 1px solid rgba(168, 85, 247, 0.2);
}

/* Dark theme variant */
@media (prefers-color-scheme: dark) {
  .statusBadge--green {
    background-color: rgba(34, 197, 94, 0.2);
    color: rgb(134, 239, 172);
  }
  
  .statusBadge--blue {
    background-color: rgba(59, 130, 246, 0.2);
    color: rgb(147, 197, 253);
  }
  
  .statusBadge--orange {
    background-color: rgba(251, 146, 60, 0.2);
    color: rgb(251, 191, 36);
  }
  
  .statusBadge--red {
    background-color: rgba(239, 68, 68, 0.2);
    color: rgb(252, 165, 165);
  }
  
  .statusBadge--gray {
    background-color: rgba(107, 114, 128, 0.2);
    color: rgb(209, 213, 219);
  }
  
  .statusBadge--yellow {
    background-color: rgba(250, 204, 21, 0.2);
    color: rgb(254, 240, 138);
  }
  
  .statusBadge--purple {
    background-color: rgba(168, 85, 247, 0.2);
    color: rgb(233, 213, 255);
  }
}
</style>
