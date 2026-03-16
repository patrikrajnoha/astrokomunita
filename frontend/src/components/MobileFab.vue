<template>
  <button
    v-if="isAuthenticated"
    type="button"
    class="mobileFab"
    data-tour="conditions"
    :style="fabStyle"
    :aria-label="label"
    :title="label"
    @click="$emit('widgets')"
  >
    <svg
      class="fabIcon"
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="currentColor"
      aria-hidden="true"
    >
      <path fill-rule="evenodd" clip-rule="evenodd" d="M14.338 3.038C15.865 1.772 17.98 1.25 19.98 1.25c.414 0 .77.336.77.75 0 2-.522 4.115-1.788 5.642-.677.817-1.563 1.42-2.587 1.726l.006.027c.238 1.042.104 2.122-.44 3.034l-2.633 4.39a.75.75 0 0 1-1.178.13l-1.33-1.451-3.189 3.19a.75.75 0 0 1-1.06-1.061l3.19-3.19-1.452-1.33a.75.75 0 0 1 .13-1.178l4.39-2.633c.912-.546 1.992-.679 3.034-.44l.027.005c.306-1.024.91-1.91 1.726-2.587-.677.817-1.563 1.42-2.587 1.726ZM4.97 14.97a.75.75 0 0 1 1.06 0l3 3a.75.75 0 0 1-1.06 1.06l-3-3a.75.75 0 0 1 0-1.06ZM3.22 13.22a.75.75 0 0 1 1.06 0l2.5 2.5a.75.75 0 0 1-1.06 1.06l-2.5-2.5a.75.75 0 0 1 0-1.06ZM6.72 16.72a.75.75 0 0 1 1.06 0l1.5 1.5a.75.75 0 0 1-1.06 1.06l-1.5-1.5a.75.75 0 0 1 0-1.06Z" />
    </svg>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  isAuthenticated: { type: Boolean, default: false },
  bottomOffset: { type: Number, default: 16 },
  label: { type: String, default: 'Widgety' },
})

defineEmits(['widgets'])

const fabStyle = computed(() => ({
  '--fab-bottom-offset': `${Math.max(0, Number(props.bottomOffset) || 0)}px`,
}))
</script>

<style scoped>
.mobileFab {
  position: fixed;
  right: max(1rem, env(safe-area-inset-right));
  bottom: calc(
    env(safe-area-inset-bottom, 0px) +
    var(--mobile-bottom-nav-offset, 0px) +
    var(--fab-bottom-offset)
  );
  z-index: 45;
  display: none;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.95);
  border-radius: 999px;
  background: rgb(var(--color-primary-rgb) / 0.96);
  color: rgb(255 255 255 / 0.98);
  box-shadow:
    0 14px 28px rgb(var(--color-bg-rgb) / 0.45),
    0 5px 14px rgb(var(--color-primary-rgb) / 0.4);
  transition: transform 160ms ease, box-shadow 160ms ease, filter 160ms ease;
}

.fabIcon {
  pointer-events: none;
}

.mobileFab:hover {
  filter: brightness(1.05);
  box-shadow:
    0 18px 34px rgb(var(--color-bg-rgb) / 0.5),
    0 8px 18px rgb(var(--color-primary-rgb) / 0.48);
}

.mobileFab:active {
  transform: scale(0.95);
}

.mobileFab:focus-visible {
  outline: 3px solid rgb(var(--color-surface-rgb) / 0.95);
  outline-offset: 3px;
}

@media (max-width: 767px) {
  .mobileFab {
    display: inline-flex;
  }
}
</style>
