<script setup>
const props = defineProps({
  text: {
    type: String,
    default: 'Loading...',
  },
  fullWidth: {
    type: Boolean,
    default: true,
  },
  align: {
    type: String,
    default: 'left',
    validator: (value) => ['left', 'center', 'right'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: true,
  },
})

const alignClass = {
  left: 'justify-start',
  center: 'justify-center',
  right: 'justify-end',
}[props.align]

const sizeClass = {
  sm: 'h-3 w-3 border-2',
  md: 'h-4 w-4 border-2',
  lg: 'h-5 w-5 border-[3px]',
}[props.size]
</script>

<template>
  <div
    v-if="loading"
    class="loadingWrap"
    :class="[fullWidth ? 'w-full' : 'w-auto', alignClass, { 'is-disabled': disabled }]"
  >
    <span class="loadingBody">
      <span class="spinner" :class="sizeClass" aria-hidden="true"></span>
      <span>{{ text }}</span>
    </span>
  </div>
</template>

<style scoped>
.loadingWrap {
  display: flex;
  margin-top: 0.75rem;
  opacity: 0.85;
}

.loadingWrap.is-disabled {
  opacity: 0.6;
}

.loadingBody {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: rgb(var(--color-surface-rgb) / 0.9);
}

.spinner {
  border-radius: 999px;
  border-color: rgb(var(--color-surface-rgb) / 0.35);
  border-top-color: rgb(var(--color-primary-rgb) / 1);
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
