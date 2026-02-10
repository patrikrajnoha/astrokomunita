<template>
  <button
    v-if="isAuthenticated"
    type="button"
    class="mobileFab"
    :style="fabStyle"
    aria-label="Vytvorit prispevok"
    title="Vytvorit prispevok"
    @click="$emit('click')"
  >
    <svg
      class="fabIcon"
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2.3"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <path d="M12 5v14" />
      <path d="M5 12h14" />
    </svg>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  isAuthenticated: { type: Boolean, default: false },
  bottomOffset: { type: Number, default: 16 },
})

defineEmits(['click'])

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
