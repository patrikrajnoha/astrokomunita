<template>
  <div class="event-actions">
    <button
      type="button"
      class="action action-ignore"
      :disabled="disabled"
      title="Predosla udalost (sipka vlavo)"
      aria-label="Predosla udalost, klavesa sipka vlavo"
      @click="$emit('dismiss')"
    >
      {{ ICONS.dismiss }}
    </button>
    <button
      type="button"
      class="action action-star"
      :disabled="disabled"
      title="Dalsia udalost (sipka vpravo)"
      aria-label="Dalsia udalost, klavesa sipka vpravo"
      @click="$emit('favorite')"
    >
      {{ ICONS.star }}
    </button>
    <button
      type="button"
      class="action action-calendar"
      :disabled="disabled"
      title="Pridat do kalendara"
      aria-label="Pridat udalost do kalendara"
      @click="$emit('calendar')"
    >
      {{ ICONS.calendar }}
    </button>
  </div>
</template>

<script setup>
const ICONS = {
  dismiss: '\u2190',
  star: '\u2192',
  calendar: '\ud83d\udcc5',
}

defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['dismiss', 'favorite', 'calendar'])
</script>

<style scoped>
.event-actions {
  display: flex;
  justify-content: center;
  gap: 1rem;
}

.action {
  width: 3.7rem;
  height: 3.7rem;
  border-radius: 9999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.26);
  background: rgb(var(--color-bg-rgb) / 0.82);
  font-size: 1.25rem;
  display: grid;
  place-items: center;
  box-shadow: 0 10px 24px rgb(6 12 26 / 0.25);
  transition: transform 140ms ease, background-color 140ms ease, box-shadow 140ms ease;
}

.action:active {
  transform: scale(0.93);
}

.action:hover {
  box-shadow: 0 14px 28px rgb(6 12 26 / 0.32);
}

.action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-ignore:hover {
  background: rgb(var(--color-danger-rgb) / 0.14);
}

.action-star:hover {
  background: rgb(var(--color-primary-rgb) / 0.18);
}

.action-calendar:hover {
  background: rgb(var(--color-primary-rgb) / 0.12);
}
</style>

