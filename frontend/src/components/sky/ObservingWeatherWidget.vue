<template>
  <section class="card panel observingWeather">
    <h3 class="panelTitle sidebarSection__header">Pocasie pre pozorovanie</h3>

    <div v-if="showLoading" class="panelLoading">
      <div class="skeleton h-8 w-full"></div>
      <div class="skeleton h-8 w-full"></div>
    </div>

    <AsyncState
      v-else-if="!hasLocationCoords"
      mode="empty"
      title="Poloha nie je nastavena"
      message="Nastav polohu pre lokalne pocasie."
      compact
    />

    <section v-else-if="showError" class="state stateError">
      <InlineStatus
        variant="error"
        :message="errorMessage"
        action-label="Skusit znova"
        @action="refreshBlock('weather')"
      />
    </section>

    <div v-else class="weatherBody">
      <div class="metricGrid">
        <article v-for="metric in metrics" :key="metric.key" class="metricItem">
          <p class="metricLabel">{{ metric.label }}</p>
          <p class="metricValue">{{ metric.value }}</p>
        </article>
      </div>
      <p class="sourceLine">Zdroj: {{ weatherSourceLabel }} | Aktualizovane: {{ weatherUpdatedLabel }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, toRef } from 'vue'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSkyWidget } from '@/composables/useSkyWidget'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  date: { type: String, default: '' },
  tz: { type: String, default: '' },
})

const {
  weather,
  weatherLoading,
  weatherError,
  formattedMetrics,
  hasLocationCoords,
  weatherSourceLabel,
  weatherUpdatedLabel,
  refreshBlock,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
  includeAstronomy: false,
  includePlanets: false,
  includeIss: false,
  includeLightPollution: false,
})

const showLoading = computed(() => weatherLoading.value && !weather.value)
const showError = computed(() => Boolean(weatherError.value) && !weather.value)
const errorMessage = computed(() => {
  const value = String(weatherError.value || '').trim()
  return value || 'Nepodarilo sa nacitat pocasie.'
})

const metrics = computed(() => ([
  { key: 'cloud', label: 'Oblacnost', value: formattedMetrics.value.cloud },
  { key: 'wind', label: 'Vietor', value: formattedMetrics.value.wind },
  { key: 'temp', label: 'Teplota', value: formattedMetrics.value.temp },
  { key: 'humidity', label: 'Vlhkost', value: formattedMetrics.value.humidity },
]))
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

.weatherBody {
  display: grid;
  gap: 0.24rem;
}

.metricGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.24rem;
}

.metricItem {
  border: 1px solid var(--divider-color);
  border-radius: 0.56rem;
  background: rgb(var(--color-bg-rgb) / 0.22);
  padding: 0.34rem 0.4rem;
  min-width: 0;
}

.metricLabel,
.metricValue {
  margin: 0;
}

.metricLabel {
  font-size: 0.64rem;
  color: var(--color-text-secondary);
}

.metricValue {
  margin-top: 0.08rem;
  font-size: 0.77rem;
  line-height: 1.2;
  font-weight: 700;
  color: var(--color-surface);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sourceLine {
  margin: 0;
  font-size: 0.68rem;
  color: var(--color-text-secondary);
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
.w-full { width: 100%; }
</style>
