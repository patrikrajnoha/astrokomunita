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

const displayed = computed(() => {
  const safe = normalized.value
  const maxBars = 20
  if (safe.length <= maxBars) return safe

  const step = Math.ceil(safe.length / maxBars)
  const sampled = safe.filter((_, index) => index % step === 0)
  const last = safe[safe.length - 1]
  if (sampled[sampled.length - 1]?.date !== last?.date) {
    sampled.push(last)
  }

  return sampled
})

const maxValue = computed(() => {
  const all = displayed.value.map((point) => point.value)
  const max = Math.max(0, ...all)
  return max > 0 ? max : 1
})

const yTicks = computed(() => {
  const max = maxValue.value
  return [max, Math.round(max / 2), 0]
})
</script>

<template>
  <div class="chartRoot" role="img" aria-label="Trend chart">
    <div v-if="!displayed.length" class="chartEmpty">No trend data.</div>
    <div v-else class="chartGrid">
      <div class="yAxis">
        <span v-for="tick in yTicks" :key="`tick-${tick}`" class="yTick">{{ tick }}</span>
      </div>
      <div class="bars" :style="{ gridTemplateColumns: `repeat(${displayed.length}, minmax(0, 1fr))` }">
        <div v-for="point in displayed" :key="point.date" class="barWrap">
          <div class="bar" :style="{ height: `${Math.max(4, (point.value / maxValue) * 100)}%` }" :title="`${point.date}: ${point.value}`"></div>
          <div class="xLabel">{{ point.date.slice(5) }}</div>
        </div>
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

.chartGrid {
  display: grid;
  grid-template-columns: 36px 1fr;
  gap: 8px;
  align-items: stretch;
}

.yAxis {
  min-height: 180px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-end;
}

.yTick {
  font-size: 10px;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
}

.bars {
  min-height: 180px;
  display: grid;
  gap: 4px;
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
