<template>
  <section v-if="showLoading || shouldRender" class="card panel issPass">
    <h3 class="panelTitle sidebarSection__header">ISS prelet</h3>

    <div v-if="showLoading" class="panelLoading">
      <div class="skeleton h-8 w-3/4"></div>
    </div>

    <div v-else class="passBody">
      <p class="passTime">{{ passTimeLabel }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, toRef } from 'vue'
import { useSkyWidget } from '@/composables/useSkyWidget'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  date: { type: String, default: '' },
  tz: { type: String, default: '' },
})

const {
  issPreview,
  issLoading,
  hasLocationCoords,
  effectiveTz,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
  includeWeather: false,
  includeAstronomy: false,
  includePlanets: false,
  includeLightPollution: false,
})

const passDate = computed(() => {
  const value = String(issPreview.value?.next_pass_at || '').trim()
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
})

const hasVisiblePass = computed(() => {
  return Boolean(issPreview.value?.available) && passDate.value instanceof Date
})

const shouldRender = computed(() => hasLocationCoords.value && hasVisiblePass.value)

const showLoading = computed(() => {
  if (!hasLocationCoords.value) return false
  if (hasVisiblePass.value) return false
  return issLoading.value
})

const passTimeLabel = computed(() => formatTime(passDate.value, effectiveTz.value))

function formatTime(value, timeZone) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) return '-'

  try {
    return new Intl.DateTimeFormat('sk-SK', {
      timeZone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(value)
  } catch {
    return new Intl.DateTimeFormat('sk-SK', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(value)
  }
}
</script>

<style scoped>
.card {
  position: relative;
  border: 0;
  background: transparent;
  border-radius: 0;
  padding: 0;
  overflow: visible;
}

.panel {
  display: grid;
  gap: 0.28rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
  margin: 0;
}

.passBody {
  display: grid;
  gap: 0.18rem;
}

.passTime {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.15;
  color: var(--color-surface);
}

.panelLoading {
  display: grid;
  gap: 0.2rem;
}

.skeleton {
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.08),
    rgb(var(--color-text-secondary-rgb) / 0.16),
    rgb(var(--color-text-secondary-rgb) / 0.08)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
  border-radius: 0;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.h-8 { height: 2rem; }
.w-3\/4 { width: 75%; }
</style>
