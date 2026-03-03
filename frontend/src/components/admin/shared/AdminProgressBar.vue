<script setup>
import { computed } from 'vue'

const props = defineProps({
  active: {
    type: Boolean,
    default: false,
  },
  progressPercent: {
    type: Number,
    default: null,
  },
})

const normalizedPercent = computed(() => {
  if (!Number.isFinite(props.progressPercent)) return null
  return Math.max(0, Math.min(100, Math.round(Number(props.progressPercent))))
})

const isIndeterminate = computed(() => props.active && normalizedPercent.value === null)
</script>

<template>
  <div class="adminProgress" :class="{ 'adminProgress--active': active }">
    <div
      class="adminProgress__track"
      role="progressbar"
      :aria-valuenow="normalizedPercent === null ? undefined : normalizedPercent"
      aria-valuemin="0"
      aria-valuemax="100"
    >
      <div
        class="adminProgress__fill"
        :class="{ 'adminProgress__fill--indeterminate': isIndeterminate }"
        :style="normalizedPercent !== null ? { width: `${normalizedPercent}%` } : null"
      ></div>
    </div>
  </div>
</template>

<style scoped>
.adminProgress {
  width: 100%;
}

.adminProgress__track {
  position: relative;
  overflow: hidden;
  width: 100%;
  height: 8px;
  border-radius: 999px;
  background: rgb(var(--color-surface-rgb) / 0.16);
}

.adminProgress__fill {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, rgb(var(--color-primary-rgb) / 0.72), rgb(var(--color-primary-rgb) / 1));
  transition: width 0.24s ease;
}

.adminProgress__fill--indeterminate {
  position: absolute;
  left: -40%;
  width: 40%;
  animation: admin-progress-indeterminate 1.05s ease-in-out infinite;
}

@keyframes admin-progress-indeterminate {
  0% {
    left: -42%;
  }
  100% {
    left: 102%;
  }
}
</style>
