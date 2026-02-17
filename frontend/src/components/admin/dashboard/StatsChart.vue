<script setup>
import { computed } from 'vue'

const props = defineProps({
  points: { type: Array, default: () => [] },
  metricKey: { type: String, default: 'new_posts' },
})

const normalized = computed(() => {
  const safe = Array.isArray(props.points) ? props.points : []
  return safe.map((point) => {
    const value = Number(point?.[props.metricKey] || 0)
    return {
      date: String(point?.date || ''),
      value: Number.isFinite(value) ? value : 0,
    }
  })
})

const maxValue = computed(() => {
  const all = normalized.value.map((point) => point.value)
  const max = Math.max(0, ...all)
  return max > 0 ? max : 1
})
</script>

<template>
  <div class="chartRoot" role="img" aria-label="Trend chart">
    <div v-if="!normalized.length" class="chartEmpty">No trend data.</div>
    <div v-else class="bars">
      <div v-for="point in normalized" :key="point.date" class="barWrap">
        <div class="bar" :style="{ height: `${Math.max(4, (point.value / maxValue) * 100)}%` }" :title="`${point.date}: ${point.value}`"></div>
        <div class="xLabel">{{ point.date.slice(5) }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chartRoot {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 10px;
  background: rgb(var(--color-bg-rgb) / 0.4);
}

.chartEmpty {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.bars {
  min-height: 180px;
  display: grid;
  grid-template-columns: repeat(30, minmax(0, 1fr));
  gap: 3px;
  align-items: end;
}

.barWrap {
  display: grid;
  gap: 6px;
  align-items: end;
}

.bar {
  width: 100%;
  border-radius: 6px 6px 3px 3px;
  background: linear-gradient(180deg, rgb(var(--color-primary-rgb) / 0.85), rgb(var(--color-primary-rgb) / 0.35));
}

.xLabel {
  font-size: 10px;
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
  text-align: center;
}
</style>

