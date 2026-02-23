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
    <span class="quickActionHead">
      <span class="quickActionTitle">{{ title }}</span>
      <span v-if="badge !== null" class="quickActionBadge" :class="`tone-${badgeTone}`">{{ badge }}</span>
    </span>
    <span class="quickActionSubtitle">{{ subtitle }}</span>
  </RouterLink>
</template>

<style scoped>
.quickActionTile {
  display: grid;
  gap: 4px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  border-radius: 12px;
  padding: 12px;
  text-decoration: none;
  color: inherit;
  background: rgb(var(--color-bg-rgb) / 0.44);
  transition: border-color 0.18s ease, transform 0.18s ease, background-color 0.18s ease;
}

.quickActionTile:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.45);
  background: rgb(var(--color-primary-rgb) / 0.1);
  transform: translateY(-1px);
}

.quickActionTitle {
  font-size: 14px;
  font-weight: 700;
}

.quickActionHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.quickActionBadge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
}

.quickActionBadge.tone-attention {
  border-color: rgb(251 191 36 / 0.5);
  background: rgb(251 191 36 / 0.14);
}

.quickActionBadge.tone-danger {
  border-color: rgb(239 68 68 / 0.5);
  background: rgb(239 68 68 / 0.14);
}

.quickActionSubtitle {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}
</style>
