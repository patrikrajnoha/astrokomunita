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
  gap: 12px;
  min-height: 60px;
  padding: 10px 12px;
  border: 1px solid transparent;
  border-radius: 16px;
  background: transparent;
  color: inherit;
  text-decoration: none;
  transition:
    border-color 160ms ease,
    background-color 160ms ease;
}

.quickActionTile:hover {
  border-color: var(--dashboard-border-strong, rgb(var(--color-surface-rgb) / 0.15));
  background: rgb(var(--color-bg-rgb) / 0.42);
}

.quickActionBody {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.quickActionTitle {
  color: var(--color-surface);
  font-size: 14px;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.quickActionSubtitle {
  color: var(--dashboard-muted, rgb(var(--color-text-secondary-rgb) / 0.88));
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.quickActionBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  border: 1px solid var(--dashboard-border-strong, rgb(var(--color-surface-rgb) / 0.15));
  background: rgb(var(--color-bg-rgb) / 0.56);
  color: var(--color-surface);
  font-size: 12px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.quickActionBadge.tone-accent,
.quickActionBadge.tone-attention,
.quickActionBadge.tone-danger {
  border-color: rgb(var(--color-primary-rgb) / 0.26);
  background: rgb(var(--color-primary-rgb) / 0.16);
}
</style>
