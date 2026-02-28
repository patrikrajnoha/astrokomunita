<template>
  <div class="relative h-full w-full overflow-hidden rounded-xl border border-slate-700/40 bg-slate-950/60">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,rgba(251,191,36,0.12),transparent_45%),radial-gradient(circle_at_70%_72%,rgba(244,63,94,0.16),transparent_52%),linear-gradient(160deg,rgba(15,23,42,0.95),rgba(2,6,23,0.95))]"></div>
    <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(148,163,184,0.25)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.25)_1px,transparent_1px)] [background-size:22px_22px]"></div>

    <div class="absolute left-2 top-2 rounded-full bg-slate-900/80 px-2 py-1 text-[10px] text-slate-300">
      Bortle {{ safeBortle }} • Brightness {{ safeBrightness }}
    </div>

    <div class="absolute bottom-2 right-2 rounded-full bg-slate-900/80 px-2 py-1 text-[10px] text-slate-400">
      preview map
    </div>

    <div
      class="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border border-slate-100/80 bg-amber-200 shadow-[0_0_0_4px_rgba(251,191,36,0.28)]"
      :style="{ left: `${markerX}%`, top: `${markerY}%` }"
    ></div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  bortleClass: { type: [Number, String], default: null },
  brightnessValue: { type: [Number, String], default: null },
})

const safeBortle = computed(() => {
  const value = toFiniteNumber(props.bortleClass)
  if (value === null) return '-'
  return String(Math.max(1, Math.min(9, Math.round(value))))
})

const safeBrightness = computed(() => {
  const value = toFiniteNumber(props.brightnessValue)
  if (value === null) return '-'
  return value.toFixed(3)
})

const markerX = computed(() => {
  const lon = toFiniteNumber(props.lon)
  if (lon === null) return 50
  return Math.max(6, Math.min(94, ((lon + 180) / 360) * 100))
})

const markerY = computed(() => {
  const lat = toFiniteNumber(props.lat)
  if (lat === null) return 50
  return Math.max(6, Math.min(94, ((90 - lat) / 180) * 100))
})

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}
</script>
