<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '-' },
  delta: { type: [String, Number], default: null },
  hint: { type: String, default: '' },
  viewLabel: { type: String, default: 'Zobrazit' },
  viewTo: { type: [String, Object], default: null },
  tone: { type: String, default: 'default' },
})

const rootComponent = computed(() => (props.viewTo ? RouterLink : 'article'))
const rootProps = computed(() => (props.viewTo ? { to: props.viewTo } : {}))
</script>

<template>
  <component
    :is="rootComponent"
    v-bind="rootProps"
    class="kpiCard"
    :class="[`tone-${props.tone}`, { linked: Boolean(props.viewTo) }]"
  >
    <span class="kpiLabel">{{ props.label }}</span>
    <strong class="kpiValue">{{ props.value }}</strong>
  </component>
</template>

<style scoped>
.kpiCard {
  display: grid;
  gap: 6px;
  min-height: 82px;
  padding: 10px;
  border: 1px solid var(--dashboard-border, var(--color-border));
  border-radius: var(--dashboard-radius, 11px);
  background: var(--dashboard-panel, rgb(var(--color-bg-rgb) / 0.34));
  color: inherit;
  text-decoration: none;
  transition:
    border-color 160ms ease,
    background-color 160ms ease,
    transform 120ms ease,
    box-shadow 120ms ease;
}

.kpiCard.linked:hover {
  border-color: rgb(var(--color-primary-rgb) / 0.26);
  background: var(--dashboard-panel-strong, rgb(var(--color-bg-rgb) / 0.48));
  transform: translateY(-0.5px);
  box-shadow: var(--shadow-soft);
}

.kpiCard.linked:focus-visible {
  outline: none;
  border-color: rgb(var(--color-primary-rgb) / 0.34);
  background: var(--dashboard-panel-strong, rgb(var(--color-bg-rgb) / 0.48));
  box-shadow: var(--focus-ring);
}

.kpiLabel {
  color: var(--dashboard-muted, rgb(var(--color-text-secondary-rgb) / 0.88));
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.kpiValue {
  color: var(--color-surface);
  font-size: clamp(1.5rem, 2.2vw, 2rem);
  font-weight: 700;
  line-height: 1;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
}

.tone-accent {
  border-color: rgb(var(--color-primary-rgb) / 0.18);
  background: rgb(var(--color-primary-rgb) / 0.1);
}
</style>
