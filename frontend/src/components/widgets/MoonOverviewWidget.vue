<template>
  <section class="panel moonNow">
    <div class="panelTitle sidebarSection__header">{{ title }}</div>

    <!-- Loading -->
    <div v-if="loading" class="skeletonStack">
      <div class="skeleton skW55"></div>
      <div class="skeleton skW28"></div>
      <div class="skeleton skW42"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="errorState">
      <span class="errorText">{{ error }}</span>
      <button type="button" class="retryBtn" @click="fetchOverview">Skúsiť znova</button>
    </div>

    <!-- No data -->
    <p v-else-if="!rawData" class="emptyText">Dáta Mesiaca sú nedostupné.</p>

    <!-- Content -->
    <div v-else class="moonBody">
      <!-- 1: Phase emoji + name (primary) -->
      <div class="moonHero">
        <span class="moonEmoji" aria-hidden="true">{{ phaseEmoji }}</span>
        <span class="moonPhase">{{ phaseName }}</span>
      </div>

      <!-- 2: Illumination (secondary) -->
      <p v-if="illuminationLabel" class="moonIllum">{{ illuminationLabel }}</p>

      <!-- 3: Visibility status — key differentiator from MoonPhasesWidget -->
      <p v-if="visibilityLabel" class="moonVis">{{ visibilityLabel }}</p>

      <!-- 4: One closest upcoming major event -->
      <p v-if="nextEventLabel" class="moonNext">{{ nextEventLabel }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { getMoonOverviewWidget } from '@/services/widgets'

const PHASE_LABELS = {
  new_moon:        'Nov',
  waxing_crescent: 'Dorastajúci kosáčik',
  first_quarter:   'Prvá štvrt',
  waxing_gibbous:  'Dorastajúci mesiac',
  full_moon:       'Spln',
  waning_gibbous:  'Ubúdajúci mesiac',
  last_quarter:    'Posledná štvrt',
  waning_crescent: 'Ubúdajúci kosáčik',
}

const PHASE_EMOJIS = {
  new_moon:        '🌑',
  waxing_crescent: '🌒',
  first_quarter:   '🌓',
  waxing_gibbous:  '🌔',
  full_moon:       '🌕',
  waning_gibbous:  '🌖',
  last_quarter:    '🌗',
  waning_crescent: '🌘',
}

const SHORT_DATE = new Intl.DateTimeFormat('sk-SK', { day: 'numeric', month: 'short' })

const props = defineProps({
  title: { type: String, default: 'Mesiac teraz' },
  lat:   { type: [Number, String], default: null },
  lon:   { type: [Number, String], default: null },
  tz:    { type: String, default: '' },
  date:  { type: String, default: '' },
})

const rawData = ref(null)
const loading = ref(true)
const error   = ref('')

async function fetchOverview() {
  loading.value = true
  error.value   = ''

  const query = {}
  const lat = Number(props.lat)
  const lon = Number(props.lon)
  const tz  = String(props.tz   || '').trim()
  const date = String(props.date || '').trim()

  if (Number.isFinite(lat)) query.lat  = lat
  if (Number.isFinite(lon)) query.lon  = lon
  if (tz)                   query.tz   = tz
  if (/^\d{4}-\d{2}-\d{2}$/.test(date)) query.date = date

  try {
    rawData.value = await getMoonOverviewWidget(query)
  } catch (err) {
    rawData.value = null
    error.value   = err?.response?.data?.message || err?.message || 'Skús obnoviť widget neskôr.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchOverview)

// ── Phase ──────────────────────────────────────────────────────────────────────

const phaseKey = computed(() => String(rawData.value?.moon_phase || '').trim().toLowerCase())
const phaseEmoji = computed(() => PHASE_EMOJIS[phaseKey.value] ?? '🌙')
const phaseName  = computed(() => PHASE_LABELS[phaseKey.value]  ?? 'Neznáma fáza')

// ── Illumination ───────────────────────────────────────────────────────────────

const illuminationLabel = computed(() => {
  const v = toFiniteNumber(rawData.value?.moon_illumination_percent)
  return v === null ? '' : `${Math.round(v)}%`
})

// ── Visibility — altitude-based, unique to this widget ────────────────────────

const visibilityLabel = computed(() => {
  const alt = toFiniteNumber(rawData.value?.moon_altitude_deg)
  if (alt === null) return ''
  if (alt > 5)  return 'Nad obzorom'
  if (alt >= 0) return 'Nízko nad obzorom'
  return 'Pod horizontom'
})

// ── Next closest major event (new or full moon — whichever comes first) ────────

const nextEventLabel = computed(() => {
  const newRaw  = String(rawData.value?.next_new_moon_at  || '').trim()
  const fullRaw = String(rawData.value?.next_full_moon_at || '').trim()

  const validDate = (s) => {
    if (!s) return null
    const d = new Date(s)
    return Number.isNaN(d.getTime()) ? null : d
  }

  const newMoon  = validDate(newRaw)
  const fullMoon = validDate(fullRaw)

  if (!newMoon && !fullMoon) return ''

  let closer, label
  if (!newMoon)            { closer = fullMoon; label = 'Spln' }
  else if (!fullMoon)      { closer = newMoon;  label = 'Nov'  }
  else if (newMoon <= fullMoon) { closer = newMoon;  label = 'Nov'  }
  else                     { closer = fullMoon; label = 'Spln' }

  try {
    return `${label} · ${SHORT_DATE.format(closer)}`
  } catch {
    return ''
  }
})

// ── Helper ─────────────────────────────────────────────────────────────────────

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
.panel {
  display: grid;
  gap: 0.3rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.88rem;
  line-height: 1.22;
  margin: 0;
}

/* ── Skeleton ── */
.skeletonStack {
  display: grid;
  gap: 0.28rem;
  margin-top: 0.08rem;
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

.skW55 { width: 55%; height: 1rem; }
.skW28 { width: 28%; }
.skW42 { width: 42%; }

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Error / empty ── */
.errorState {
  display: grid;
  gap: 0.2rem;
}

.errorText {
  font-size: 0.76rem;
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
  font-size: 0.74rem;
  font-weight: 600;
  text-align: left;
}

.retryBtn:hover {
  color: var(--color-primary);
  text-decoration: underline;
}

.emptyText {
  margin: 0;
  font-size: 0.76rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
}

/* ── Content ── */
.moonBody {
  display: grid;
  row-gap: 0;
  margin-top: 0.06rem;
}

/* 1: Phase hero */
.moonHero {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 0.16rem;
}

.moonEmoji {
  font-size: 1.48rem;
  line-height: 1;
  flex-shrink: 0;
}

.moonPhase {
  color: var(--color-surface);
  font-size: 1.05rem;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.01em;
}

/* 2: Illumination */
.moonIllum {
  margin: 0;
  font-size: 0.86rem;
  font-weight: 600;
  color: rgb(var(--color-surface-rgb) / 0.58);
  line-height: 1.2;
}

/* 3: Visibility */
.moonVis {
  margin: 0;
  margin-top: 0.22rem;
  font-size: 0.72rem;
  color: var(--color-text-secondary);
  line-height: 1.26;
}

/* 4: Next event */
.moonNext {
  margin: 0;
  margin-top: 0.1rem;
  font-size: 0.72rem;
  color: var(--color-text-secondary);
  line-height: 1.26;
  opacity: 0.75;
}
</style>
