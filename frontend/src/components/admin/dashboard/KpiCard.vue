<script setup>
const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '-' },
  delta: { type: Number, default: null },
  hint: { type: String, default: '' },
  help: { type: String, default: '' },
  tone: { type: String, default: 'default' },
})

function deltaClass(delta) {
  if (delta === null || Number.isNaN(delta)) return 'neutral'
  if (delta > 0) return 'up'
  if (delta < 0) return 'down'
  return 'neutral'
}

function deltaText(delta) {
  if (delta === null || Number.isNaN(delta)) return 'n/a'
  const rounded = Math.round(delta)
  const sign = rounded > 0 ? '+' : ''
  return `${sign}${rounded}%`
}
</script>

<template>
  <article class="kpiCard" :class="`tone-${props.tone}`">
    <header class="kpiHead">
      <h3 class="kpiLabel">{{ props.label }}</h3>
      <span v-if="props.help" class="kpiHelp" :title="props.help" aria-label="KPI info">?</span>
    </header>

    <div class="kpiValue">{{ props.value }}</div>

    <div class="kpiMeta">
      <span class="kpiDelta" :class="deltaClass(props.delta)">{{ deltaText(props.delta) }}</span>
      <span class="kpiHint">{{ props.hint || '-' }}</span>
    </div>
  </article>
</template>

<style scoped>
.kpiCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 14px;
  padding: 12px;
  display: grid;
  gap: 10px;
  background: rgb(var(--color-bg-rgb) / 0.42);
}

.kpiHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.kpiLabel {
  margin: 0;
  font-size: 12px;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.kpiHelp {
  width: 18px;
  height: 18px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  display: inline-grid;
  place-items: center;
  font-size: 11px;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.kpiValue {
  font-size: clamp(1.4rem, 2.6vw, 2rem);
  font-weight: 800;
  color: var(--color-surface);
  line-height: 1.05;
}

.kpiMeta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.kpiDelta {
  font-size: 12px;
  font-weight: 700;
}

.kpiDelta.up { color: rgb(34 197 94); }
.kpiDelta.down { color: rgb(239 68 68); }
.kpiDelta.neutral { color: rgb(var(--color-text-secondary-rgb) / 0.92); }

.kpiHint {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
  white-space: nowrap;
}

.tone-attention {
  border-color: rgb(251 191 36 / 0.45);
  background: rgb(251 191 36 / 0.06);
}

.tone-danger {
  border-color: rgb(239 68 68 / 0.4);
  background: rgb(239 68 68 / 0.06);
}
</style>
