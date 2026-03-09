<template>
  <section class="card panel nightSky">
    <h3 class="panelTitle sidebarSection__header">Nocna obloha</h3>

    <div v-if="showLoading" class="panelLoading">
      <div class="skeleton h-8 w-full"></div>
      <div class="skeleton h-8 w-4/5"></div>
    </div>

    <AsyncState
      v-else-if="!hasLocationCoords"
      mode="empty"
      title="Poloha nie je nastavena"
      message="Nastav polohu pre nocnu oblohu."
      compact
    />

    <section v-else-if="showAstronomyError" class="state stateError">
      <InlineStatus
        variant="error"
        message="Nepodarilo sa nacitat nocnu oblohu."
        action-label="Skusit znova"
        @action="refreshBlock('astronomy')"
      />
    </section>

    <div v-else class="nightBody">
      <div class="statRow">
        <span>Mesiac</span>
        <strong>{{ moonLine }}</strong>
      </div>

      <div v-if="showBortle" class="statRow">
        <span>Svetelne znecistenie</span>
        <strong>{{ bortleLine }}</strong>
      </div>

      <div v-if="showPlanets" class="statRow">
        <span>Viditelne planety</span>
        <strong>{{ planetsLine }}</strong>
      </div>

      <div v-if="showIssPass" class="statRow">
        <span>ISS prelet</span>
        <strong>{{ issPassTime }}</strong>
      </div>
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
  astronomy,
  astronomyLoading,
  astronomyError,
  lightPollutionLine,
  lightPollutionMetaLine,
  planetsDisplayList,
  issPreview,
  hasLocationCoords,
  effectiveTz,
  refreshBlock,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
  includeWeather: false,
  includeIss: true,
})

const showLoading = computed(() => astronomyLoading.value && !astronomy.value)
const showAstronomyError = computed(() => Boolean(astronomyError.value) && !astronomy.value)

const moonLine = computed(() => {
  const phase = translateMoonPhase(astronomy.value?.moon_phase)
  const illumination = Number(astronomy.value?.moon_illumination_percent)
  if (!Number.isFinite(illumination)) return phase
  return `${phase} • ${Math.round(illumination)}%`
})

const showBortle = computed(() => {
  return String(lightPollutionLine.value || '').trim() !== '' || String(lightPollutionMetaLine.value || '').trim() !== ''
})

const bortleLine = computed(() => {
  const base = String(lightPollutionLine.value || '').trim()
  const meta = String(lightPollutionMetaLine.value || '').trim()

  if (base && meta) return `${base} • ${meta}`
  return base || meta || '-'
})

const visiblePlanetNames = computed(() => {
  const list = Array.isArray(planetsDisplayList.value) ? planetsDisplayList.value : []
  return list
    .filter((planet) => planet?.isVisible)
    .map((planet) => String(planet?.name || '').trim())
    .filter((name) => name)
    .slice(0, 4)
})

const showPlanets = computed(() => visiblePlanetNames.value.length > 0)
const planetsLine = computed(() => visiblePlanetNames.value.join(', '))

const issPassDate = computed(() => {
  const value = String(issPreview.value?.next_pass_at || '').trim()
  if (!value) return null

  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
})

const showIssPass = computed(() => Boolean(issPreview.value?.available) && issPassDate.value instanceof Date)
const issPassTime = computed(() => formatTime(issPassDate.value, effectiveTz.value))

function translateMoonPhase(value) {
  const map = {
    new_moon: 'Nov',
    waxing_crescent: 'Dorastajuci kosacik',
    first_quarter: 'Prva stvrt',
    waxing_gibbous: 'Dorastajuci mesiac',
    full_moon: 'Spln',
    waning_gibbous: 'Ubudajuci mesiac',
    last_quarter: 'Posledna stvrt',
    waning_crescent: 'Ubudajuci kosacik',
  }

  const key = String(value || '').trim().toLowerCase()
  return map[key] || 'Neznama faza'
}

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

.nightBody {
  display: grid;
  gap: 0.22rem;
}

.statRow {
  display: flex;
  gap: 0.45rem;
  align-items: baseline;
  justify-content: space-between;
  border-bottom: 1px solid var(--divider-color);
  padding-bottom: 0.2rem;
}

.statRow:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.statRow span {
  font-size: 0.68rem;
  color: var(--color-text-secondary);
}

.statRow strong {
  font-size: 0.74rem;
  line-height: 1.25;
  color: var(--color-surface);
  text-align: right;
  font-weight: 700;
  max-width: 72%;
  overflow-wrap: anywhere;
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
.w-4\/5 { width: 80%; }
.w-full { width: 100%; }
</style>
