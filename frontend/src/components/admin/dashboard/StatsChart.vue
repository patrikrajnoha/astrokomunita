<script setup>
import { computed } from 'vue'

const props = defineProps({
  points: { type: Array, default: () => [] },
  metricKey: { type: String, default: 'new_posts' },
})

const numberFormatter = new Intl.NumberFormat('sk-SK')
const dateFormatter = new Intl.DateTimeFormat('sk-SK', {
  day: '2-digit',
  month: '2-digit',
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
  const values = [maxValue.value, Math.round(maxValue.value / 2), 0]
  return values.filter((tick, index) => values.indexOf(tick) === index)
})

function formatValue(value) {
  return numberFormatter.format(Number(value || 0))
}

function formatShortDate(value) {
  if (!value) return ''

  const isoMatch = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (isoMatch) {
    const [, , month = '', day = ''] = isoMatch
    return day && month ? `${day}.${month}.` : String(value)
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return String(value)
  }

  return dateFormatter.format(date)
}

function formatTooltip(point) {
  return `${formatShortDate(point.date)}: ${formatValue(point.value)}`
}
</script>

<template>
  <div class="chartRoot" role="img" aria-label="Graf trendu">
    <div v-if="!displayed.length" class="chartEmpty">Trend nie je dostupný.</div>
    <div v-else class="chartGrid">
      <div class="yAxis">
        <span v-for="tick in yTicks" :key="`tick-${tick}`" class="yTick">{{
          formatValue(tick)
        }}</span>
      </div>
      <div
        class="bars"
        :style="{ gridTemplateColumns: `repeat(${displayed.length}, minmax(0, 1fr))` }"
      >
        <div v-for="point in displayed" :key="point.date" class="barWrap">
          <div
            class="bar"
            :style="{ height: `${Math.max(6, (point.value / maxValue) * 100)}%` }"
            :title="formatTooltip(point)"
          ></div>
          <div class="xLabel">{{ formatShortDate(point.date) }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chartRoot {
  display: grid;
  gap: 6px;
}

.chartEmpty {
  color: var(--dashboard-muted, rgb(var(--color-text-secondary-rgb) / 0.88));
  font-size: 12px;
}

.chartGrid {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr);
  gap: 8px;
  align-items: stretch;
}

.yAxis {
  min-height: 174px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-end;
}

.yTick {
  color: rgb(var(--color-text-secondary-rgb) / 0.76);
  font-size: 10px;
  font-variant-numeric: tabular-nums;
}

.bars {
  min-height: 174px;
  display: grid;
  gap: 5px;
  align-items: end;
  padding-top: 8px;
  background-image: linear-gradient(
    to top,
    var(--color-divider) 1px,
    transparent 1px
  );
  background-size: 100% 33.33%;
  border-bottom: 1px solid var(--color-divider);
}

.barWrap {
  display: grid;
  gap: 6px;
  align-items: end;
}

.bar {
  width: 100%;
  border-radius: 999px 999px 4px 4px;
  background: rgb(var(--color-primary-rgb) / 0.78);
}

.xLabel {
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  font-size: 10px;
  text-align: center;
  white-space: nowrap;
}
</style>
