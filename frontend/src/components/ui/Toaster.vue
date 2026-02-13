<template>
  <teleport to="body">
    <section class="toaster" aria-label="Notifications" aria-live="off">
      <transition-group name="toast-stack" tag="div" class="toasterList">
        <Toast
          v-for="item in toasts.visible"
          :key="item.id"
          :item="item"
          @dismiss="dismiss"
          @action="triggerAction"
        />
      </transition-group>
    </section>
  </teleport>
</template>

<script setup>
defineOptions({
  name: 'UiToaster',
})

import Toast from '@/components/ui/Toast.vue'
import { useToast } from '@/composables/useToast'

const { toasts, dismiss, triggerAction } = useToast()
</script>

<style scoped>
.toaster {
  position: fixed;
  z-index: 1250;
  inset: auto 0 0;
  pointer-events: none;
  padding: 0 0.8rem calc(env(safe-area-inset-bottom, 0px) + 4.9rem);
}

.toasterList {
  display: grid;
  gap: 0.55rem;
  max-width: 100%;
}

.toasterList > * {
  pointer-events: auto;
}

@media (min-width: 768px) {
  .toaster {
    inset: 1rem 1rem auto auto;
    padding: 0;
    width: min(460px, calc(100vw - 2rem));
  }
}

.toast-stack-enter-active,
.toast-stack-leave-active {
  transition: opacity 190ms ease, transform 190ms ease;
}

.toast-stack-enter-from,
.toast-stack-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

@media (min-width: 768px) {
  .toast-stack-enter-from,
  .toast-stack-leave-to {
    transform: translateX(12px);
  }
}
</style>
