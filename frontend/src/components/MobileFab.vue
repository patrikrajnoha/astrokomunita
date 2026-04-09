<template>
  <button
    type="button"
    class="mobileFab"
    data-tour="conditions"
    :style="fabStyle"
    :aria-label="label"
    :title="label"
    @click="$emit('widgets')"
  >
    <svg class="fabIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <g transform="rotate(-45 12 12)">
        <path
          d="M12 2.05c3.08 1.52 5.05 4.7 5.05 8.35v3.15c0 .55-.22 1.08-.61 1.47l-2.97 2.97a2.08 2.08 0 0 1-2.94 0l-2.97-2.97c-.39-.39-.61-.92-.61-1.47V10.4c0-3.65 1.97-6.83 5.05-8.35Z"
          fill="currentColor"
        />
        <path
          d="M8.05 12.22 4.15 13.62a.96.96 0 0 0-.33 1.6l1.86 1.86a.96.96 0 0 0 1.6-.33l1.4-3.9-1.63-.63Zm7.9 0 3.9 1.4a.96.96 0 0 1 .33 1.6l-1.86 1.86a.96.96 0 0 1-1.6-.33l-1.4-3.9 1.63-.63Z"
          fill="currentColor"
        />
        <path
          d="M10.2 17.92 8.92 21.42c-.18.49.31.98.8.8L12 21.1l2.28 1.12c.49.18.98-.31.8-.8l-1.28-3.5H10.2Z"
          fill="currentColor"
        />
        <path
          d="M10.52 14.72 8.98 16.26"
          stroke="var(--fab-detail-color)"
          stroke-width="1.2"
          stroke-linecap="round"
        />
        <path
          d="M13.48 14.72 15.02 16.26"
          stroke="var(--fab-detail-color)"
          stroke-width="1.2"
          stroke-linecap="round"
        />
        <circle cx="12" cy="8.8" r="2.15" fill="var(--fab-detail-color)" />
        <path
          d="M13.85 5.3h2.25"
          stroke="var(--fab-detail-color)"
          stroke-width="1.45"
          stroke-linecap="round"
        />
      </g>
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
  --fab-detail-color: rgb(var(--color-primary-rgb) / 0.96);

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
  display: block;
  width: 1.72rem;
  height: 1.72rem;
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
