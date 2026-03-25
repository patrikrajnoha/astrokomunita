<template>
  <section class="panel observingSummary">
    <header class="summaryHead">
      <h3 class="panelTitle sidebarSection__header">Pozorovanie dnes</h3>
      <button type="button" class="locationBtn" @click="goToProfileLocation">
        {{ locationLabel }}
      </button>
    </header>

    <div v-if="showLoading" class="skeletonStack">
      <div class="skeleton skW60"></div>
      <div class="skeleton skW35"></div>
      <div class="skeleton skW50"></div>
    </div>

    <AsyncState
      v-else-if="canonicalLocationMissing"
      mode="empty"
      title="Poloha nie je nastavená"
      message="Nastav polohu v profile pre presné podmienky."
      action-label="Nastaviť polohu"
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
      <!-- 1: Verdict — primary, decision-focused -->
      <p class="verdict" :class="`verdict--${scoreTone}`">{{ verdictText }}</p>

      <!-- 2: Score — secondary, supporting info -->
      <p v-if="hasScore" class="score">
        {{ roundedScore }}<span class="scoreMax"> / 100</span>
      </p>

      <!-- 3: Compact weather data — no moon (handled elsewhere) -->
      <p v-if="compactDataLine" class="dataLine">{{ compactDataLine }}</p>

      <!-- 4: Best window signal -->
      <p
        v-if="windowStatusText"
        class="windowLine"
        :class="{ windowLineActive: windowActive }"
      >{{ windowStatusText }}</p>
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
  scoreTone,
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

// ── Loading / error gates ──────────────────────────────────────────────────────

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

// ── Verdict — proper Slovak with diacritics, mapped from tone ─────────────────

const VERDICT_BY_TONE = {
  excellent: 'Výborné podmienky',
  good:      'Dobré podmienky',
  fair:      'Priemerné podmienky',
  poor:      'Slabé podmienky',
  twilight:  'Súmrak',
  neutral:   'Podmienky nedostupné',
}

const verdictText = computed(() => VERDICT_BY_TONE[scoreTone.value] ?? 'Podmienky nedostupné')

// ── Score ──────────────────────────────────────────────────────────────────────

const hasScore = computed(() => Number.isFinite(observingScore.value))
const roundedScore = computed(() => hasScore.value ? String(Math.round(observingScore.value)) : '')

// ── Compact data line — weather only, no moon ─────────────────────────────────

const compactDataLine = computed(() => {
  const condition = String(formattedMetrics.value.conditionLabel || '').trim()
  const temp = String(formattedMetrics.value.temp || '').trim()
  if (!condition && !temp) return ''
  if (!condition) return temp
  if (!temp) return condition
  return `${condition} · ${temp}`
})

// ── Window status — clean Slovak, no broken diacritics ────────────────────────

const windowActive = computed(() => String(bestTimeLabel.value || '').trim() === 'Prave prebieha')

const windowStatusText = computed(() => {
  const raw = String(bestTimeLabel.value || '').trim()
  if (!raw) return ''
  if (raw.includes('Nastav') || raw.includes('nedostupne') || raw.includes('nie je dostupne')) return ''
  if (raw === 'Prave prebieha') return 'Práve prebieha'
  if (raw.startsWith('Noc začne:')) return `Noc začne: ${raw.slice('Noc začne: '.length).trim()}`
  if (raw === 'Najlepšie dnes: po zotmení') return 'Najlepšie po zotmení'
  // time window like "21:15 - 23:45"
  return `Okno: ${raw}`
})

// ── Location label ─────────────────────────────────────────────────────────────

const locationLabel = computed(() => {
  const label = String(props.locationName || '').trim()
  if (label) return label
  return hasLocationCoords.value ? 'Moja poloha' : 'Nastav polohu →'
})

function goToProfileLocation() {
  router.push('/profile/edit#location')
}
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.3rem;
  min-width: 0;
}

/* ── Header ── */
.summaryHead {
  display: grid;
  gap: 0.1rem;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
  margin: 0;
}

.locationBtn {
  border: 0;
  background: transparent;
  padding: 0;
  color: var(--color-text-secondary);
  font-size: 0.68rem;
  line-height: 1.3;
  text-align: left;
  cursor: pointer;
  opacity: 0.65;
  transition: opacity 0.14s ease;
  overflow-wrap: anywhere;
  justify-self: start;
}

.locationBtn:hover {
  opacity: 1;
}

/* ── Skeleton ── */
.skeletonStack {
  display: grid;
  gap: 0.28rem;
  margin-top: 0.1rem;
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

.skeleton.skW60 { width: 60%; height: 1.1rem; }
.skeleton.skW35 { width: 35%; }
.skeleton.skW50 { width: 50%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Body ── */
.summaryBody {
  display: grid;
  row-gap: 0;
  margin-top: 0.08rem;
}

/* ── 1: Verdict — the primary decision signal ── */
.verdict {
  margin: 0;
  font-size: 1.22rem;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.01em;
}

.verdict--excellent { color: rgb(52 211 153); }
.verdict--good      { color: rgb(74 222 128 / 0.92); }
.verdict--fair      { color: rgb(251 146 60); }
.verdict--poor      { color: var(--color-text-secondary); }
.verdict--twilight  { color: rgb(var(--color-primary-rgb) / 0.82); }
.verdict--neutral   { color: var(--color-text-secondary); opacity: 0.7; }

/* ── 2: Score — secondary, supporting ── */
.score {
  margin: 0;
  margin-top: 0.22rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: rgb(var(--color-surface-rgb) / 0.6);
  line-height: 1.2;
}

.scoreMax {
  font-weight: 400;
  opacity: 0.65;
}

/* ── 3: Compact data ── */
.dataLine {
  margin: 0;
  margin-top: 0.28rem;
  font-size: 0.72rem;
  color: var(--color-text-secondary);
  line-height: 1.26;
}

/* ── 4: Window status ── */
.windowLine {
  margin: 0;
  margin-top: 0.14rem;
  font-size: 0.72rem;
  color: var(--color-text-secondary);
  line-height: 1.26;
}

.windowLineActive {
  color: rgb(52 211 153 / 0.88);
  font-weight: 600;
}

.state {
  margin-top: 0.1rem;
}
</style>
