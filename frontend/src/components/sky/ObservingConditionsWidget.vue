<template>
  <section class="card panel observingSummary">
    <header class="summaryHead">
      <h3 class="panelTitle sidebarSection__header">Astronomicke podmienky</h3>
      <button type="button" class="locationBtn" @click="goToProfileLocation">
        Poloha: {{ locationLabel }}
      </button>
    </header>

    <div v-if="showLoading" class="panelLoading">
      <div class="skeleton h-10 w-1/2"></div>
      <div class="skeleton h-4 w-full"></div>
      <div class="skeleton h-4 w-4/5"></div>
    </div>

    <AsyncState
      v-else-if="canonicalLocationMissing"
      mode="empty"
      title="Poloha nie je nastavena"
      message="Nastav polohu v profile pre presne podmienky."
      action-label="Nastavit polohu"
      compact
      @action="goToProfileLocation"
    />

    <section v-else-if="hasPrimaryFetchError" class="state stateError">
      <InlineStatus
        variant="error"
        :message="primaryErrorMessage"
        action-label="Skúsiť znova"
        @action="refreshAll"
      />
    </section>

    <div v-else class="summaryBody">
      <p class="scoreLine">
        {{ scoreLine }}
        <span v-if="hasScore">/100</span>
      </p>
      <p class="verdictLine">{{ verdictLine }}</p>
      <p class="metaLine">{{ summaryMetaLine }}</p>
      <p class="windowLine">Najlepsie okno: {{ bestTimeLabel }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, toRef } from 'vue'
import { useRouter } from 'vue-router'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSkyWidget } from '@/composables/useSkyWidget'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  date: { type: String, default: '' },
  tz: { type: String, default: '' },
  locationName: { type: String, default: '' },
  initialPayload: { type: Object, default: undefined },
  bundlePending: { type: Boolean, default: false },
})

const router = useRouter()

const {
  weather,
  astronomy,
  weatherLoading,
  astronomyLoading,
  weatherError,
  astronomyError,
  hasLocationCoords,
  observingScore,
  scoreLabel,
  bestTimeLabel,
  formattedMetrics,
  refreshAll,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
  initialPayload: toRef(props, 'initialPayload'),
  bundlePending: toRef(props, 'bundlePending'),
  includePlanets: false,
  includeIss: false,
  includeLightPollution: false,
})

const showLoading = computed(() => (
  (weatherLoading.value && !weather.value) || (astronomyLoading.value && !astronomy.value)
))

const canonicalLocationMissing = computed(() => !hasLocationCoords.value)

const hasPrimaryFetchError = computed(() => (
  hasLocationCoords.value
  && !showLoading.value
  && !weather.value
  && !astronomy.value
  && Boolean(weatherError.value || astronomyError.value)
))
const primaryErrorMessage = computed(() => {
  const astronomyMessage = String(astronomyError.value || '').trim()
  if (astronomyMessage) return astronomyMessage

  const weatherMessage = String(weatherError.value || '').trim()
  if (weatherMessage) return weatherMessage

  return 'Nepodarilo sa načítať astronomické podmienky.'
})

const hasScore = computed(() => Number.isFinite(observingScore.value))
const scoreLine = computed(() => (hasScore.value ? String(Math.round(observingScore.value)) : 'N/A'))
const verdictLine = computed(() => {
  const value = String(scoreLabel.value || '').trim()
  if (!value) return 'Podmienky nedostupne'
  return `${value} podmienky`
})

const moonPercentLabel = computed(() => {
  const value = toFiniteNumber(astronomy.value?.moon_illumination_percent)
  return value === null ? '-' : `${Math.round(value)}%`
})

const summaryMetaLine = computed(() => {
  const condition = String(formattedMetrics.value.conditionLabel || 'Bez popisu').trim()
  const temp = String(formattedMetrics.value.temp || '-').trim()
  return `${condition} | ${temp} | Mesiac ${moonPercentLabel.value}`
})

const locationLabel = computed(() => {
  const label = String(props.locationName || '').trim()
  if (label) return label
  return hasLocationCoords.value ? 'nastavena' : 'nenastavena'
})

function goToProfileLocation() {
  router.push('/profile/edit#location')
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
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
  gap: 0.3rem;
  min-width: 0;
}

.summaryHead {
  display: grid;
  gap: 0.16rem;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
  margin: 0;
}

.locationBtn {
  border: 0;
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 0.7rem;
  line-height: 1.26;
  text-decoration: underline;
  text-underline-offset: 0.16rem;
  max-width: 100%;
  text-align: left;
  white-space: normal;
  overflow: visible;
  text-overflow: clip;
  overflow-wrap: anywhere;
  justify-self: start;
}

.locationBtn:hover {
  color: var(--color-surface);
}

.summaryBody {
  display: grid;
  gap: 0.18rem;
}

.scoreLine {
  margin: 0;
  font-size: 1.65rem;
  line-height: 1.02;
  font-weight: 800;
  color: #0f73ff;
}

.scoreLine span {
  margin-left: 0.15rem;
  font-size: 0.74rem;
  color: var(--color-text-secondary);
}

.verdictLine {
  margin: 0;
  font-size: 0.76rem;
  font-weight: 700;
  color: rgb(var(--color-surface-rgb) / 0.92);
}

.metaLine,
.windowLine {
  margin: 0;
  font-size: 0.72rem;
  line-height: 1.26;
  color: var(--color-text-secondary);
}

.panelLoading {
  display: grid;
  gap: 0.24rem;
}

.state {
  margin-top: 0.1rem;
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

.h-10 { height: 2.5rem; }
.h-4 { height: 1rem; }
.w-1\/2 { width: 50%; }
.w-4\/5 { width: 80%; }
.w-full { width: 100%; }
</style>
