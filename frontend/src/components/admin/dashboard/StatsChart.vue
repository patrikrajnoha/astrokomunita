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
  const maxBars = 12
  if (safe.length <= maxBars) return safe

  const step = Math.ceil(safe.length / maxBars)
  const sampled = safe.filter((_, index) => index % step === 0)
  const last = safe[safe.length - 1]
  if (sampled[sampled.length - 1]?.date !== last?.date) {
    sampled.push(last)
  }

  return sampled
})

const labelStep = computed(() => {
  const count = displayed.value.length
  if (count <= 4) return 1
  return Math.ceil(count / 4)
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

function shouldShowLabel(index) {
  if (!Number.isFinite(index)) return false
  if (index === 0 || index === displayed.value.length - 1) return true
  return index % labelStep.value === 0
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
        <div v-for="(point, index) in displayed" :key="point.date" class="barWrap">
          <div
            class="bar"
            :style="{ height: `${Math.max(6, (point.value / maxValue) * 100)}%` }"
            :title="formatTooltip(point)"
          ></div>
          <div class="xLabel">{{ shouldShowLabel(index) ? formatShortDate(point.date) : '' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chartRoot {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.chartEmpty {
  color: var(--dashboard-muted, rgb(var(--color-text-secondary-rgb) / 0.88));
  font-size: 11px;
}

.chartGrid {
  display: grid;
  grid-template-columns: 26px minmax(0, 1fr);
  gap: 5px;
  align-items: stretch;
  min-width: 0;
}

.yAxis {
  min-height: 108px;
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
  min-height: 108px;
  display: grid;
  gap: 5px;
  align-items: end;
  padding-top: 4px;
  background-image: linear-gradient(
    to top,
    var(--divider-color) 1px,
    transparent 1px
  );
  background-size: 100% 33.33%;
  border-bottom: 1px solid var(--divider-color);
}

.barWrap {
  display: grid;
  gap: 3px;
  align-items: end;
}

.bar {
  width: 100%;
  border-radius: 999px 999px 2px 2px;
  background: rgb(var(--color-primary-rgb) / 0.78);
}

.xLabel {
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  font-size: 9px;
  text-align: center;
  white-space: nowrap;
  min-height: 12px;
}
</style>
