<template>
  <transition name="toast">
    <div
      v-if="toast.visible"
      class="appToast"
      :class="`appToast--${toast.type}`"
      role="status"
      aria-live="polite"
    >
      {{ toast.message }}
    </div>
  </transition>
</template>

<script setup>
import { useToast } from '@/composables/useToast'

const { toast } = useToast()
</script>

<style scoped>
.appToast {
  position: fixed;
  left: 50%;
  bottom: calc(env(safe-area-inset-bottom, 0px) + 1rem);
  z-index: 110;
  transform: translateX(-50%);
  max-width: min(92vw, 460px);
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.45);
  background: rgb(var(--color-bg-rgb) / 0.96);
  color: var(--color-surface);
  padding: 0.6rem 0.95rem;
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 16px 35px rgb(0 0 0 / 0.32);
  backdrop-filter: blur(6px);
}

.appToast--success {
  border-color: rgb(var(--color-success-rgb) / 0.55);
}

.appToast--error {
  border-color: rgb(var(--color-danger-rgb) / 0.6);
}

.toast-enter-active,
.toast-leave-active {
  transition: opacity 220ms ease, transform 220ms ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(10px) scale(0.98);
}
</style>
