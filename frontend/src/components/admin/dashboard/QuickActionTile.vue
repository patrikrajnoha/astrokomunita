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
    <span v-if="badge !== null" class="quickActionBadge" :class="`tone-${badgeTone}`">{{
      badge
    }}</span>
  </RouterLink>
</template>

<style scoped>
.quickActionTile {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  min-height: 48px;
  padding: 8px 10px;
  border: 1px solid transparent;
  border-radius: 10px;
  background: transparent;
  color: inherit;
  text-decoration: none;
  transition:
    border-color 160ms ease,
    background-color 160ms ease,
    transform 120ms ease,
    box-shadow 120ms ease;
}

.quickActionTile:hover {
  border-color: var(--dashboard-border-strong, var(--color-border-strong));
  background: rgb(var(--color-bg-rgb) / 0.56);
  transform: translateY(-0.5px);
}

.quickActionTile:focus-visible {
  outline: none;
  border-color: rgb(var(--color-primary-rgb) / 0.32);
  background: rgb(var(--color-bg-rgb) / 0.46);
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
  letter-spacing: 0;
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
  min-width: 28px;
  height: 24px;
  padding: 0 8px;
  border-radius: 999px;
  border: 1px solid var(--dashboard-border-strong, var(--color-border-strong));
  background: rgb(var(--color-bg-rgb) / 0.56);
  color: var(--color-surface);
  font-size: 11px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.quickActionBadge.tone-accent,
.quickActionBadge.tone-attention {
  border-color: rgb(var(--color-primary-rgb) / 0.26);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.quickActionBadge.tone-warning {
  border-color: rgb(var(--color-warning-rgb) / 0.32);
  background: rgb(var(--color-warning-rgb) / 0.16);
  color: rgb(var(--color-warning-rgb));
}

.quickActionBadge.tone-danger {
  border-color: rgb(var(--color-danger-rgb) / 0.34);
  background: rgb(var(--color-danger-rgb) / 0.16);
  color: rgb(var(--color-danger-rgb));
}
</style>
