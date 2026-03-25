<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '-' },
  viewTo: { type: [String, Object], default: null },
  weight: { type: String, default: 'default' },
})

const rootComponent = computed(() => (props.viewTo ? RouterLink : 'article'))
const rootProps = computed(() => (props.viewTo ? { to: props.viewTo } : {}))
</script>

<template>
  <component
    :is="rootComponent"
    v-bind="rootProps"
    class="kpiCard"
    :class="[`weight-${props.weight}`, { linked: Boolean(props.viewTo) }]"
  >
    <span class="kpiLabel">{{ props.label }}</span>
    <strong class="kpiValue">{{ props.value }}</strong>
  </component>
</template>

<style scoped>
.kpiCard {
  display: grid;
  gap: 6px;
  min-height: 92px;
  padding: 12px;
  border-radius: 12px;
  background: #1c2736;
  color: inherit;
  text-decoration: none;
  transition: background-color 160ms ease, transform 120ms ease, box-shadow 120ms ease;
}

.kpiCard.linked:hover {
  background: #222E3F;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}

.kpiCard.linked:focus-visible {
  outline: none;
  background: #222E3F;
  box-shadow: 0 0 0 2px #0F73FF;
}

.kpiLabel {
  color: rgba(171, 184, 201, 0.86);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.kpiValue {
  color: #ffffff;
  font-size: clamp(1.55rem, 2.2vw, 2rem);
  font-weight: 700;
  line-height: 1;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
}

.kpiCard.weight-primary {
  background: rgba(15, 115, 255, 0.13);
}

.kpiCard.weight-primary .kpiValue {
  font-size: clamp(1.8rem, 2.9vw, 2.35rem);
}
</style>
