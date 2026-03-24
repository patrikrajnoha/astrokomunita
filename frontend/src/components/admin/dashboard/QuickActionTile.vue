<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  to: { type: [String, Object], required: true },
  badge: { type: [String, Number], default: null },
  badgeTone: { type: String, default: 'neutral' },
})
</script>

<template>
  <RouterLink :to="to" class="quickActionTile">
    <span class="quickActionBody">
      <span class="quickActionTitle">{{ title }}</span>
      <span class="quickActionSubtitle">{{ subtitle }}</span>
    </span>
    <span v-if="badge !== null" class="quickActionBadge" :class="`tone-${badgeTone}`">{{ badge }}</span>
  </RouterLink>
</template>

<style scoped>
.quickActionTile {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  min-height: 52px;
  padding: 9px 10px;
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: inherit;
  text-decoration: none;
  transition:
    background-color 160ms ease,
    transform 120ms ease,
    box-shadow 120ms ease;
}

.quickActionTile:hover {
  background: rgb(var(--color-bg-rgb) / 0.58);
  transform: translateY(-1px);
  box-shadow: var(--shadow-soft);
}

.quickActionTile:focus-visible {
  outline: none;
  background: rgb(var(--color-bg-rgb) / 0.58);
  box-shadow: var(--focus-ring);
}

.quickActionBody {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.quickActionTitle {
  color: var(--color-surface);
  font-size: 13px;
  font-weight: 600;
}

.quickActionSubtitle {
  color: var(--dashboard-muted, rgb(var(--color-text-secondary-rgb) / 0.88));
  font-size: 11px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.quickActionBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 30px;
  height: 24px;
  padding: 0 8px;
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.7);
  color: var(--color-surface);
  font-size: 11px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.quickActionBadge.tone-accent,
.quickActionBadge.tone-attention {
  background: rgb(var(--color-primary-rgb) / 0.18);
}

.quickActionBadge.tone-warning {
  background: rgb(var(--color-warning-rgb) / 0.16);
  color: rgb(var(--color-warning-rgb));
}

.quickActionBadge.tone-danger {
  background: rgb(var(--color-danger-rgb) / 0.16);
  color: rgb(var(--color-danger-rgb));
}
</style>
