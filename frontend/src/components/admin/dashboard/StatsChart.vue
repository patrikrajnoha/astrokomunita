<script setup>
import { computed } from 'vue'

const props = defineProps({
  points: { type: Array, default: () => [] },
  metricKey: { type: String, default: 'new_posts' },
})

const PLOT_HEIGHT = 124
const PLOT_TOP_PADDING = 10
const PLOT_BOTTOM_PADDING = 12
const POINT_STEP = 100

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
  const maxPoints = 12
  if (safe.length <= maxPoints) return safe

  const step = Math.ceil(safe.length / maxPoints)
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

const xLabelIndices = computed(() => {
  const count = displayed.value.length
  if (!count) return new Set()
  if (count <= 4) return new Set(Array.from({ length: count }, (_, i) => i))

  const indices = new Set([0, count - 1])
  for (let i = labelStep.value; i < count - 1; i += labelStep.value) {
    indices.add(i)
  }

  // Avoid adjacent labels at the end (e.g. 21.03 and 23.03 on narrow widths).
  const sorted = Array.from(indices).sort((a, b) => a - b)
  const compact = new Set()
  let last = -10
  for (const idx of sorted) {
    if (idx === count - 1 || idx - last >= 2 || idx === 0) {
      compact.add(idx)
      last = idx
    }
  }
  return compact
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

const viewBoxWidth = computed(() => {
  if (displayed.value.length <= 1) {
    return POINT_STEP
  }

  return (displayed.value.length - 1) * POINT_STEP
})

const plotInnerHeight = computed(() => {
  return PLOT_HEIGHT - PLOT_TOP_PADDING - PLOT_BOTTOM_PADDING
})

const plottedPoints = computed(() => {
  const safe = displayed.value
  if (!safe.length) return []

  const width = viewBoxWidth.value
  const maxIndex = Math.max(1, safe.length - 1)

  return safe.map((point, index) => {
    const x = safe.length === 1 ? width / 2 : (width / maxIndex) * index
    const ratio = Math.max(0, Math.min(1, point.value / maxValue.value))
    const y = PLOT_TOP_PADDING + (1 - ratio) * plotInnerHeight.value

    return {
      ...point,
      x,
      y,
    }
  })
})

const linePath = computed(() => {
  if (!plottedPoints.value.length) return ''

  return plottedPoints.value
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
    .join(' ')
})

const areaPath = computed(() => {
  if (!plottedPoints.value.length) return ''

  const first = plottedPoints.value[0]
  const last = plottedPoints.value[plottedPoints.value.length - 1]
  const baselineY = PLOT_HEIGHT - PLOT_BOTTOM_PADDING

  return `${linePath.value} L ${last.x} ${baselineY} L ${first.x} ${baselineY} Z`
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
  return xLabelIndices.value.has(index)
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

      <div class="plotColumn">
        <div class="plotArea">
          <svg
            class="chartSvg"
            :viewBox="`0 0 ${viewBoxWidth} ${PLOT_HEIGHT}`"
            preserveAspectRatio="none"
          >
            <path v-if="plottedPoints.length > 1" class="chartArea" :d="areaPath" />
            <path
              v-if="plottedPoints.length > 1"
              class="chartLine"
              :d="linePath"
            />
            <circle
              v-for="point in plottedPoints"
              :key="`point-${point.date}`"
              class="chartPoint"
              :cx="point.x"
              :cy="point.y"
              r="4"
            >
              <title>{{ formatTooltip(point) }}</title>
            </circle>
          </svg>
        </div>

        <div
          class="xAxis"
          :style="{ gridTemplateColumns: `repeat(${displayed.length}, minmax(0, 1fr))` }"
        >
          <div v-for="(point, index) in displayed" :key="point.date" class="xLabel">
            {{ shouldShowLabel(index) ? formatShortDate(point.date) : '' }}
          </div>
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
  color: rgba(171, 184, 201, 0.88);
  font-size: 11px;
}

.chartGrid {
  display: grid;
  grid-template-columns: 26px minmax(0, 1fr);
  gap: 6px;
  align-items: start;
  min-width: 0;
}

.yAxis {
  height: 124px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-end;
}

.yTick {
  color: rgba(171, 184, 201, 0.76);
  font-size: 10px;
  font-variant-numeric: tabular-nums;
}

.plotColumn {
  display: grid;
  gap: 5px;
  min-width: 0;
}

.plotArea {
  position: relative;
  height: 124px;
  min-width: 0;
  border-bottom: 1px solid rgba(171, 184, 201, 0.08);
  background-image: linear-gradient(to top, rgba(171, 184, 201, 0.08) 1px, transparent 1px);
  background-size: 100% 33.33%;
  overflow: hidden;
}

.chartSvg {
  width: 100%;
  height: 100%;
  display: block;
  overflow: visible;
}

.chartArea {
  fill: rgba(15, 115, 255, 0.14);
}

.chartLine {
  fill: none;
  stroke: rgba(15, 115, 255, 0.95);
  stroke-width: 3;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.chartPoint {
  fill: #151d28;
  stroke: #0F73FF;
  stroke-width: 2.5;
}

.xAxis {
  display: grid;
  gap: 5px;
  min-width: 0;
}

.xLabel {
  color: rgba(171, 184, 201, 0.82);
  font-size: 9px;
  text-align: center;
  white-space: nowrap;
  min-height: 12px;
}
</style>
