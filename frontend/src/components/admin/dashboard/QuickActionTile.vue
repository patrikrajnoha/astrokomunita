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
  background: #151d28;
  color: inherit;
  text-decoration: none;
  transition: background-color 160ms ease, transform 120ms ease, box-shadow 120ms ease;
}

.quickActionTile:hover {
  background: #1c2736;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}

.quickActionTile:focus-visible {
  outline: none;
  background: #1c2736;
  box-shadow: 0 0 0 2px #0F73FF;
}

.quickActionBody {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.quickActionTitle {
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
}

.quickActionSubtitle {
  color: rgba(171, 184, 201, 0.88);
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
  background: #222E3F;
  color: #ABB8C9;
  font-size: 11px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.quickActionBadge.tone-accent,
.quickActionBadge.tone-attention {
  background: rgba(15, 115, 255, 0.18);
  color: #7EB8FF;
}

.quickActionBadge.tone-warning {
  background: rgba(245, 158, 11, 0.16);
  color: rgb(245, 158, 11);
}

.quickActionBadge.tone-danger {
  background: rgba(235, 36, 82, 0.16);
  color: #EB2452;
}
</style>
