<template>
  <section class="panel">
    <h3 class="panelTitle sidebarSection__header">Počasie na pozorovanie</h3>

    <!-- Loading -->
    <div v-if="showLoading" class="skeletonStack">
      <div class="skeleton skW55"></div>
      <div class="skeleton skW40"></div>
    </div>

    <!-- No location -->
    <div v-else-if="!hasLocationCoords" class="stateBox">
      <div class="stateName">Poloha nie je nastavená</div>
      <div class="stateSub">Nastav polohu pre lokálne počasie.</div>
    </div>

    <!-- Error -->
    <div v-else-if="showError" class="stateBox">
      <div class="stateError">{{ errorMessage }}</div>
      <button type="button" class="retryBtn" @click="refreshBlock('weather')">Skúsiť znova</button>
    </div>

    <!-- Data -->
    <div v-else class="weatherBody">
      <!-- Verdict: primary, cloud-based -->
      <div class="verdict" :class="`verdict--${verdictTone}`">{{ verdictText }}</div>

      <!-- Compact data line: cloud · wind · temp -->
      <div class="dataLine">
        <span>☁️ {{ formattedMetrics.cloud }}</span>
        <span class="sep" aria-hidden="true">·</span>
        <span>💨 {{ formattedMetrics.wind }}</span>
        <span class="sep" aria-hidden="true">·</span>
        <span>🌡️ {{ formattedMetrics.temp }}</span>
      </div>

      <!-- Updated -->
      <div v-if="weatherUpdatedLabel" class="updatedLine">Aktualizované {{ weatherUpdatedLabel }}</div>
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
  initialPayload: { type: Object, default: undefined },
  bundlePending: { type: Boolean, default: false },
})

const {
  weather,
  weatherLoading,
  weatherError,
  formattedMetrics,
  hasLocationCoords,
  weatherUpdatedLabel,
  refreshBlock,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
  initialPayload: toRef(props, 'initialPayload'),
  bundlePending: toRef(props, 'bundlePending'),
  includeAstronomy: false,
  includePlanets: false,
  includeIss: false,
  includeLightPollution: false,
})

const showLoading = computed(() => weatherLoading.value && !weather.value)
const showError = computed(() => Boolean(weatherError.value) && !weather.value)
const errorMessage = computed(() => String(weatherError.value || '').trim() || 'Nepodarilo sa načítať počasie.')

const verdictTone = computed(() => {
  const cloud = weather.value?.cloud_percent
  if (cloud === null || cloud === undefined || !Number.isFinite(Number(cloud))) return 'neutral'
  const c = Number(cloud)
  if (c <= 20) return 'excellent'
  if (c <= 40) return 'good'
  if (c <= 65) return 'fair'
  return 'poor'
})

const VERDICT_TEXT = {
  excellent: 'Jasná obloha',
  good:      'Väčšinou jasno',
  fair:      'Čiastočne oblačno',
  poor:      'Zamračené',
  neutral:   'Dáta nedostupné',
}

const verdictText = computed(() => VERDICT_TEXT[verdictTone.value] ?? 'Dáta nedostupné')
</script>

<style scoped>
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

/* ── Skeleton ── */
.skeletonStack {
  display: grid;
  gap: 0.28rem;
}

.skeleton {
  height: 0.72rem;
  border-radius: 0.25rem;
  background: linear-gradient(
    90deg,
    rgb(var(--color-text-secondary-rgb) / 0.07),
    rgb(var(--color-text-secondary-rgb) / 0.14),
    rgb(var(--color-text-secondary-rgb) / 0.07)
  );
  background-size: 200% 100%;
  animation: shimmer 1.2s infinite;
}

.skW55 { width: 55%; }
.skW40 { width: 40%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── State ── */
.stateBox {
  display: grid;
  gap: 0.16rem;
}

.stateName {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--color-surface);
  line-height: 1.22;
}

.stateSub {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
}

.stateError {
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--color-danger, #f87171);
  line-height: 1.3;
}

.retryBtn {
  display: inline;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  color: rgb(var(--color-primary-rgb) / 0.85);
  font-size: 0.72rem;
  font-weight: 600;
  text-align: left;
}

.retryBtn:hover {
  color: var(--color-primary);
  text-decoration: underline;
}

/* ── Weather body ── */
.weatherBody {
  display: grid;
  gap: 0.18rem;
}

/* Verdict */
.verdict {
  font-size: 1.08rem;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.01em;
}

.verdict--excellent { color: rgb(52 211 153); }
.verdict--good      { color: rgb(110 231 183); }
.verdict--fair      { color: rgb(251 146 60); }
.verdict--poor      { color: rgb(248 113 113); }
.verdict--neutral   { color: var(--color-text-secondary); }

/* Compact data line */
.dataLine {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 0.22rem;
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  line-height: 1.3;
}

.sep {
  opacity: 0.35;
  font-size: 0.66rem;
}

/* Updated */
.updatedLine {
  color: var(--color-text-secondary);
  font-size: 0.66rem;
  opacity: 0.6;
  line-height: 1.2;
}
</style>
