<template>
  <section class="skyWidget ui-surface-card">
    <header class="skyWidget__head">
      <div class="skyWidget__titleWrap">
        <p class="skyWidget__eyebrow">Astronomicke podmienky</p>
        <button
          type="button"
          class="skyWidget__locationBtn"
          title="Upravit polohu"
          @click="goToProfileLocation"
        >
          Poloha: {{ locationLabel }}
        </button>
      </div>

      <div class="skyWidget__headActions">
        <span v-if="globalFreshnessLabel" class="skyWidget__freshness">{{ globalFreshnessLabel }}</span>

        <button
          v-if="isAdminUser"
          type="button"
          data-testid="sky-widget-reorder-toggle"
          class="skyWidget__iconBtn"
          :title="editMode ? 'Ukoncit upravu poradia' : 'Upravit poradie sekcii'"
          @click="toggleEditMode"
        >
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M4 14.5 5 11l7.7-7.7a1.8 1.8 0 0 1 2.6 0l1.4 1.4a1.8 1.8 0 0 1 0 2.6L9 15l-3.5 1Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M11.8 4.2 15.8 8.2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" />
          </svg>
        </button>

        <button
          type="button"
          class="skyWidget__iconBtn"
          title="Obnovit data"
          @click="refreshAll"
        >
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M3 10a7 7 0 0 1 12-4.9M17 10a7 7 0 0 1-12 4.9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" />
            <path d="M15 2.8v2.8h-2.8M5 17.2v-2.8h2.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </div>
    </header>

    <section v-if="showPrimaryLoading" class="skyWidget__loading">
      <div class="skySkeleton skySkeleton--hero"></div>
      <div class="skySkeleton"></div>
      <div class="skySkeleton"></div>
    </section>

    <AsyncState
      v-else-if="canonicalLocationMissing"
      mode="empty"
      title="Poloha nie je nastavena"
      message="Nastav si polohu v profile, aby sme vedeli vypocitat podmienky pre tvoju oblohu."
      action-label="Nastavit polohu"
      @action="goToProfileLocation"
    />

    <section v-else-if="hasPrimaryFetchError" class="skyWidget__stateWrap">
      <InlineStatus
        variant="error"
        message="Nepodarilo sa nacitat podmienky pre tuto lokalitu."
        action-label="Skusit znova"
        @action="retryAllData"
      />
      <button type="button" class="skyWidget__ghostAction" @click="goToProfileLocation">Upravit polohu</button>
    </section>

    <div v-else class="skyWidget__content">
      <section class="summaryCard">
        <div class="summaryCard__top">
          <div>
            <p class="summaryCard__caption">Dnes vecer</p>
            <p class="summaryCard__score">
              <template v-if="observingScoreValue === null">N/A</template>
              <template v-else>
                {{ observingScoreValue }}
                <span>/100</span>
              </template>
            </p>
            <p class="summaryCard__label">{{ scoreLabel }}</p>
          </div>

          <div class="summaryCard__meta">
            <span class="summaryCard__phase" :class="`summaryCard__phase--${scoreToneClass}`">{{ skyPhaseLabel }}</span>
            <p class="summaryCard__window">Najlepsie okno: {{ bestTimeLabel }}</p>
          </div>
        </div>

        <p class="summaryCard__recommendation">{{ recommendationLine }}</p>
        <p v-if="countdownToNightLabel" class="summaryCard__countdown">{{ countdownToNightLabel }}</p>

        <div class="summaryCard__barTrack">
          <div class="summaryCard__bar" :class="scoreBarClass" :style="{ width: `${observingScoreWidth}%` }"></div>
        </div>

        <button type="button" class="summaryCard__toggle" @click="scoreFactorsOpen = !scoreFactorsOpen">
          {{ scoreFactorsOpen ? 'Skryt faktory' : 'Zobrazit faktory skore' }}
        </button>

        <ul v-if="scoreFactorsOpen" class="factorList">
          <li v-for="factor in scoreFactors" :key="factor.key" class="factorList__item" :class="`factorList__item--${factor.tone}`">
            <div class="factorList__body">
              <p class="factorList__label">{{ factor.label }}</p>
              <p class="factorList__hint">{{ factor.hint }}</p>
            </div>
            <span class="factorList__value">{{ factor.value }}</span>
          </li>
        </ul>
      </section>

      <section v-for="sectionId in orderedSectionIds" :key="sectionId" class="infoSection">
        <header class="infoSection__head">
          <h4>{{ sectionTitle(sectionId) }}</h4>
          <div v-if="editMode" class="infoSection__reorder">
            <button type="button" class="skyWidget__iconBtn" :disabled="isFirstSection(sectionId)" @click="moveSectionById(sectionId, 'up')">↑</button>
            <button type="button" class="skyWidget__iconBtn" :disabled="isLastSection(sectionId)" @click="moveSectionById(sectionId, 'down')">↓</button>
          </div>
        </header>

        <template v-if="sectionId === 'weather'">
          <div class="weatherGrid">
            <div v-for="item in weatherRows" :key="item.key" class="metricItem">
              <p class="metricItem__label">{{ item.label }}</p>
              <p class="metricItem__value">{{ item.value }}</p>
            </div>
          </div>
          <p class="infoSection__line">{{ formattedMetrics.conditionLabel }}</p>
          <p class="infoSection__subline">Aktualizovane: {{ weatherUpdatedLabel }} | Zdroj: {{ weatherSourceLabel }}</p>
          <InlineStatus
            v-if="weatherError"
            variant="error"
            :message="weatherError"
            action-label="Skusit znova"
            @action="retryBlock('weather')"
          />
        </template>

        <template v-else-if="sectionId === 'moon'">
          <p class="infoSection__line">{{ moonSummaryLine }}</p>
          <p class="infoSection__subline">{{ moonInfluenceLine }}</p>
          <button type="button" class="summaryCard__toggle summaryCard__toggle--compact" @click="moonDetailsOpen = !moonDetailsOpen">
            {{ moonDetailsOpen ? 'Skryt detaily' : 'Zobrazit detaily' }}
          </button>
          <p v-if="moonDetailsOpen" class="infoSection__subline">{{ astronomyTimesLine }}</p>
          <InlineStatus
            v-if="astronomyError"
            variant="error"
            :message="astronomyError"
            action-label="Skusit znova"
            @action="retryBlock('astronomy')"
          />
        </template>

        <template v-else-if="sectionId === 'light_pollution'">
          <p class="infoSection__line">{{ lightPollutionLine || 'Svetelne znecistenie nie je dostupne.' }}</p>
          <p v-if="lightPollutionMetaLine" class="infoSection__subline">{{ lightPollutionMetaLine }}</p>
          <p v-if="lightPollutionImpactLine" class="infoSection__subline">{{ lightPollutionImpactLine }}</p>
          <p v-if="lightPollutionEstimateLine" class="infoSection__subline">{{ lightPollutionEstimateLine }}</p>
          <InlineStatus
            v-if="lightPollutionError"
            variant="error"
            :message="lightPollutionError"
            action-label="Skusit znova"
            @action="retryBlock('lightPollution')"
          />
        </template>

        <template v-else-if="sectionId === 'planets'">
          <p v-if="planetsContextLine" class="infoSection__subline">{{ planetsContextLine }}</p>

          <div v-if="shouldShowPlanetsList" class="planetList">
            <article v-for="planet in planetsDisplayList" :key="planet.name" class="planetRow">
              <div>
                <div class="planetRow__title">
                  <p>{{ planet.name }}</p>
                  <span :class="planet.visibilityToneClass">{{ planet.visibilityLabel }}</span>
                </div>
                <p class="planetRow__meta">Smer {{ planet.direction }} | Vyska {{ planet.altitudeLabel }} | Elongacia {{ planet.elongationLabel }}</p>
                <p v-if="planet.bestTimeWindow" class="planetRow__meta">Najlepsie: {{ planet.bestTimeWindow }}</p>
              </div>
            </article>
          </div>

          <p v-else class="infoSection__line">{{ planetsMessage }}</p>
          <p class="infoSection__subline">{{ planetsSourceLine }}</p>
          <InlineStatus
            v-if="planetsError"
            variant="error"
            :message="planetsError"
            action-label="Skusit znova"
            @action="retryBlock('planets')"
          />
        </template>

        <template v-else-if="sectionId === 'iss'">
          <p class="infoSection__line">{{ issPrimaryLine }}</p>
          <p class="infoSection__subline">{{ issSecondaryLine }}</p>
          <InlineStatus
            v-if="issError"
            variant="error"
            :message="issError"
            action-label="Skusit znova"
            @action="retryBlock('iss')"
          />
        </template>
      </section>
    </div>
  </section>
</template>
<script setup>
import { computed, ref, toRef } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AsyncState from '@/components/ui/AsyncState.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSkyWidget } from '@/composables/useSkyWidget'
import { SKY_WIDGET_SECTION_IDS, moveSection } from '@/utils/skyWidget'

const props = defineProps({
  lat: { type: [Number, String], default: null },
  lon: { type: [Number, String], default: null },
  tz: { type: String, default: '' },
  locationName: { type: String, default: '' },
})

const router = useRouter()
const auth = useAuthStore()
const editMode = ref(false)
const moonDetailsOpen = ref(false)
const scoreFactorsOpen = ref(false)
const sectionOrder = ref([...SKY_WIDGET_SECTION_IDS])

const isAdminUser = computed(() => Boolean(auth.isAdmin))

const {
  weather,
  astronomy,
  weatherLoading,
  astronomyLoading,
  weatherError,
  astronomyError,
  planetsError,
  issError,
  lightPollutionError,
  weatherFreshness,
  astronomyFreshness,
  planetsFreshness,
  issFreshness,
  lightPollutionFreshness,
  hasLocationCoords,
  skyPhaseLabel,
  observingScore,
  scoreLabel,
  scoreTone,
  scoreFactors,
  bestTimeLabel,
  countdownToNightLabel,
  recommendationLine,
  formattedMetrics,
  issPrimaryLine,
  issSecondaryLine,
  lightPollutionLine,
  lightPollutionMetaLine,
  lightPollutionImpactLine,
  lightPollutionEstimateLine,
  weatherUpdatedLabel,
  weatherSourceLabel,
  planetsDisplayList,
  planetsMessage,
  planetsContextLine,
  planetsSourceLine,
  shouldShowPlanetsList,
  refreshAll,
  refreshBlock,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
})

const showPrimaryLoading = computed(() => (
  (weatherLoading.value && !weather.value) || (astronomyLoading.value && !astronomy.value)
))

const hasCoords = computed(() => Boolean(hasLocationCoords.value))
const hasLabel = computed(() => sanitizeLabel(props.locationName).length > 0)
const canonicalLocationMissing = computed(() => !hasCoords.value)

const hasPrimaryFetchError = computed(() => (
  hasCoords.value
  && !showPrimaryLoading.value
  && !weather.value
  && !astronomy.value
  && Boolean(weatherError.value || astronomyError.value)
))

const orderedSectionIds = computed(() => sectionOrder.value.filter((sectionId) => SKY_WIDGET_SECTION_IDS.includes(sectionId)))

const locationLabel = computed(() => {
  if (hasLabel.value) return sanitizeLabel(props.locationName)
  return hasCoords.value ? 'nastavena' : 'nenastavena'
})

const observingScoreValue = computed(() => (observingScore.value === null ? null : observingScore.value))
const observingScoreWidth = computed(() => (observingScore.value === null ? 0 : Math.max(0, Math.min(100, observingScore.value))))

const weatherRows = computed(() => ([
  { key: 'cloud', label: 'Oblacnost', value: formattedMetrics.value.cloud },
  { key: 'humidity', label: 'Vlhkost', value: formattedMetrics.value.humidity },
  { key: 'wind', label: 'Vietor', value: formattedMetrics.value.wind },
  { key: 'temp', label: 'Teplota', value: formattedMetrics.value.temp },
]))

const moonSummaryLine = computed(() => {
  const phase = translateMoonPhase(astronomy.value?.moon_phase)
  const illumination = toFiniteNumber(astronomy.value?.moon_illumination_percent)
  return illumination === null ? `Mesiac: ${phase}` : `Mesiac: ${phase} | osvetlenie ${Math.round(illumination)}%`
})

const moonInfluenceLine = computed(() => {
  const illumination = toFiniteNumber(astronomy.value?.moon_illumination_percent)
  const altitude = toFiniteNumber(astronomy.value?.moon_altitude_deg)

  if (illumination === null || altitude === null) return 'Vplyv Mesiaca na oblohu nevieme presne urcit.'
  if (altitude <= 0) return 'Mesiac je pod obzorom, tmavu oblohu nerusi.'
  if (illumination >= 80) return 'Silny mesacny jas obmedzuje deep-sky objekty.'
  if (illumination >= 50) return 'Mesiac mierne rusi tmavu oblohu.'
  return 'Mesiac ma iba slabsi vplyv na tmavu oblohu.'
})

const astronomyTimesLine = computed(() => {
  const sunrise = formatIsoShort(astronomy.value?.sunrise_at)
  const sunset = formatIsoShort(astronomy.value?.sunset_at)
  const moonrise = formatIsoShort(astronomy.value?.moonrise_at)
  const moonset = formatIsoShort(astronomy.value?.moonset_at)
  return `Slnko ${sunrise}-${sunset} | Mesiac ${moonrise}-${moonset}`
})

const freshnessSources = computed(() => [
  weatherFreshness.value,
  astronomyFreshness.value,
  planetsFreshness.value,
  issFreshness.value,
  lightPollutionFreshness.value,
])

const globalFreshnessLabel = computed(() => {
  if (weatherUpdatedLabel.value && weatherUpdatedLabel.value !== '-') {
    return `Aktualizovane: ${weatherUpdatedLabel.value}`
  }

  const minutes = freshnessSources.value
    .map(parseFreshnessMinutes)
    .filter((value) => value !== null)
    .sort((a, b) => a - b)

  if (minutes.length === 0) return ''
  if (minutes[0] <= 0) return 'Aktualizovane prave teraz'
  return `Aktualizovane pred ${minutes[0]} min`
})

const scoreToneClass = computed(() => {
  if (scoreTone.value === 'excellent') return 'excellent'
  if (scoreTone.value === 'good') return 'good'
  if (scoreTone.value === 'fair') return 'fair'
  if (scoreTone.value === 'poor') return 'poor'
  if (scoreTone.value === 'day') return 'day'
  if (scoreTone.value === 'twilight') return 'twilight'
  return 'neutral'
})

const scoreBarClass = computed(() => {
  if (observingScoreValue.value === null) return 'summaryCard__bar--neutral'
  if (observingScoreValue.value < 40) return 'summaryCard__bar--poor'
  if (observingScoreValue.value < 65) return 'summaryCard__bar--fair'
  if (observingScoreValue.value < 85) return 'summaryCard__bar--good'
  return 'summaryCard__bar--excellent'
})

function sectionTitle(sectionId) {
  if (sectionId === 'weather') return 'Pocasie'
  if (sectionId === 'moon') return 'Mesiac'
  if (sectionId === 'light_pollution') return 'Svetelne znecistenie'
  if (sectionId === 'planets') return 'Viditelne planety'
  if (sectionId === 'iss') return 'ISS'
  return sectionId
}

function retryBlock(blockName) {
  refreshBlock(blockName)
}

function retryAllData() {
  refreshAll()
}

function toggleEditMode() {
  editMode.value = !editMode.value
}

function moveSectionById(sectionId, direction) {
  sectionOrder.value = moveSection(sectionOrder.value, sectionId, direction)
}

function isFirstSection(sectionId) {
  return orderedSectionIds.value[0] === sectionId
}

function isLastSection(sectionId) {
  return orderedSectionIds.value[orderedSectionIds.value.length - 1] === sectionId
}

function goToProfileLocation() {
  router.push('/profile/edit#location')
}
function formatIsoShort(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'
  return date.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit', hour12: false })
}

function toFiniteNumber(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : null
  }
  return null
}

function sanitizeLabel(value) {
  if (typeof value !== 'string') return ''
  return value.trim()
}

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

  const key = sanitizeLabel(value).toLowerCase()
  return map[key] || 'Neznama faza'
}

function parseFreshnessMinutes(value) {
  const text = sanitizeLabel(value).toLowerCase()
  if (!text) return null
  if (text.includes('prave teraz')) return 0

  const match = text.match(/pred\s+(\d+)\s+min/)
  if (!match) return null

  const parsed = Number(match[1])
  return Number.isFinite(parsed) ? parsed : null
}
</script>
<style scoped>
.skyWidget {
  width: 100%;
  min-width: 0;
  display: grid;
  gap: 0.9rem;
  padding: 1rem;
  background: linear-gradient(180deg, rgb(var(--bg-surface-rgb) / 0.96) 0%, rgb(var(--bg-surface-rgb) / 0.88) 100%);
  border-color: rgb(var(--border-rgb) / 0.88);
  color: var(--text-primary);
  box-shadow: 0 14px 34px rgb(var(--bg-app-rgb) / 0.24);
}

.skyWidget__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.7rem;
  min-width: 0;
}

.skyWidget__titleWrap {
  min-width: 0;
}

.skyWidget__eyebrow {
  margin: 0;
  font-size: 0.8rem;
  line-height: 1.3;
  letter-spacing: 0.015em;
  font-weight: 700;
  color: rgb(var(--text-primary-rgb) / 0.95);
}

.skyWidget__locationBtn {
  margin-top: 0.2rem;
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.79rem;
  line-height: 1.35;
  text-decoration: underline;
  text-underline-offset: 0.2rem;
  max-width: 100%;
  text-align: left;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.skyWidget__locationBtn:hover {
  color: var(--text-primary);
}

.skyWidget__headActions {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  min-width: 0;
  flex-shrink: 1;
  justify-content: flex-end;
}

.skyWidget__freshness {
  font-size: 0.67rem;
  line-height: 1.2;
  color: var(--text-secondary);
  border: 1px solid rgb(var(--border-rgb) / 0.82);
  border-radius: 999px;
  padding: 0.22rem 0.46rem;
  max-width: 6rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.skyWidget__iconBtn {
  width: 44px;
  height: 44px;
  border: 1px solid rgb(var(--border-rgb) / 0.9);
  border-radius: 0.8rem;
  background: rgb(var(--bg-app-rgb) / 0.2);
  color: rgb(var(--text-primary-rgb) / 0.92);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: border-color 160ms ease, background-color 160ms ease, transform 160ms ease;
}

.skyWidget__iconBtn:hover {
  border-color: rgb(var(--primary-rgb) / 0.42);
  background: rgb(var(--text-primary-rgb) / 0.08);
  transform: translateY(-1px);
}

.skyWidget__iconBtn:disabled {
  opacity: 0.4;
  transform: none;
}

.skyWidget__loading {
  display: grid;
  gap: 0.65rem;
}

.skySkeleton {
  height: 4.2rem;
  border-radius: 0.95rem;
  background: linear-gradient(90deg, rgb(var(--bg-surface-2-rgb) / 0.62), rgb(var(--text-primary-rgb) / 0.06), rgb(var(--bg-surface-2-rgb) / 0.62));
  background-size: 200% 100%;
  animation: sky-skeleton 1.5s linear infinite;
}

.skySkeleton--hero {
  height: 8rem;
}

.skyWidget__stateWrap {
  display: grid;
  gap: 0.55rem;
}

.skyWidget__ghostAction {
  justify-self: start;
  border: 0;
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.79rem;
  text-decoration: underline;
  text-underline-offset: 0.2rem;
}

.skyWidget__ghostAction:hover {
  color: var(--text-primary);
}

.skyWidget__content {
  display: grid;
  gap: 0.8rem;
}

.summaryCard {
  border: 1px solid rgb(var(--border-rgb) / 0.9);
  border-radius: 1rem;
  background: linear-gradient(180deg, rgb(var(--bg-app-rgb) / 0.28), rgb(var(--bg-app-rgb) / 0.18));
  padding: 0.85rem;
  display: grid;
  gap: 0.55rem;
}

.summaryCard__top {
  display: flex;
  justify-content: space-between;
  gap: 0.8rem;
  align-items: flex-start;
}

.summaryCard__top > * {
  min-width: 0;
}

.summaryCard__caption {
  margin: 0;
  font-size: 0.7rem;
  color: var(--text-secondary);
  letter-spacing: 0.02em;
}

.summaryCard__score {
  margin: 0.1rem 0 0;
  font-size: 2.1rem;
  line-height: 1.05;
  font-weight: 800;
  color: var(--text-primary);
}

.summaryCard__score span {
  font-size: 0.84rem;
  color: var(--text-secondary);
}

.summaryCard__label {
  margin: 0.15rem 0 0;
  font-size: 0.92rem;
  font-weight: 650;
  color: rgb(var(--text-primary-rgb) / 0.9);
}

.summaryCard__meta {
  display: grid;
  gap: 0.35rem;
  align-content: start;
  justify-items: end;
  min-width: 0;
  max-width: 11.5rem;
}

.summaryCard__phase {
  border-radius: 999px;
  border: 1px solid rgb(var(--border-rgb) / 0.9);
  padding: 0.24rem 0.56rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.015em;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.summaryCard__phase--excellent,
.summaryCard__bar--excellent {
  color: rgb(var(--success-rgb) / 0.98);
  background: rgb(var(--success-rgb) / 0.12);
  border-color: rgb(var(--success-rgb) / 0.42);
}

.summaryCard__phase--good,
.summaryCard__bar--good {
  color: rgb(var(--primary-rgb) / 0.98);
  background: rgb(var(--primary-rgb) / 0.16);
  border-color: rgb(var(--primary-rgb) / 0.46);
}

.summaryCard__phase--fair,
.summaryCard__bar--fair {
  color: rgb(var(--warning-rgb) / 0.95);
  background: rgb(var(--warning-rgb) / 0.16);
  border-color: rgb(var(--warning-rgb) / 0.45);
}

.summaryCard__phase--poor,
.summaryCard__bar--poor {
  color: rgb(var(--danger-rgb) / 0.95);
  background: rgb(var(--danger-rgb) / 0.16);
  border-color: rgb(var(--danger-rgb) / 0.45);
}

.summaryCard__phase--day,
.summaryCard__phase--twilight,
.summaryCard__phase--neutral,
.summaryCard__bar--neutral {
  color: var(--text-secondary);
  background: rgb(var(--bg-surface-2-rgb) / 0.62);
  border-color: rgb(var(--border-rgb) / 0.9);
}

.summaryCard__window,
.summaryCard__recommendation,
.summaryCard__countdown {
  margin: 0;
  font-size: 0.78rem;
  line-height: 1.45;
  color: var(--text-secondary);
  text-align: right;
  max-width: 100%;
  overflow-wrap: anywhere;
  white-space: normal;
}

.summaryCard__recommendation,
.summaryCard__countdown {
  text-align: left;
}

.summaryCard__barTrack {
  height: 0.42rem;
  border-radius: 999px;
  overflow: hidden;
  background: rgb(var(--bg-surface-2-rgb) / 0.74);
}

.summaryCard__bar {
  height: 100%;
  border-radius: inherit;
  border: 1px solid transparent;
  transition: width 220ms ease;
}

.summaryCard__toggle {
  border: 0;
  background: transparent;
  justify-self: start;
  padding: 0;
  font-size: 0.76rem;
  color: rgb(var(--primary-rgb) / 0.95);
  text-decoration: underline;
  text-underline-offset: 0.2rem;
}

.summaryCard__toggle--compact {
  margin-top: 0.1rem;
}

.factorList {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.42rem;
}

.factorList__item {
  border: 1px solid rgb(var(--border-rgb) / 0.9);
  border-radius: 0.78rem;
  padding: 0.48rem 0.58rem;
  background: rgb(var(--bg-app-rgb) / 0.16);
  display: flex;
  gap: 0.55rem;
  justify-content: space-between;
  align-items: flex-start;
  min-width: 0;
}

.factorList__label,
.factorList__hint,
.factorList__value {
  margin: 0;
}

.factorList__label {
  font-size: 0.76rem;
  color: rgb(var(--text-primary-rgb) / 0.95);
  font-weight: 650;
}

.factorList__hint {
  margin-top: 0.1rem;
  font-size: 0.69rem;
  color: var(--text-secondary);
  line-height: 1.35;
}

.factorList__value {
  font-size: 0.74rem;
  color: rgb(var(--text-primary-rgb) / 0.95);
  white-space: nowrap;
  max-width: 42%;
  overflow: hidden;
  text-overflow: ellipsis;
}

.factorList__item--positive {
  border-color: rgb(var(--success-rgb) / 0.36);
}

.factorList__item--negative {
  border-color: rgb(var(--danger-rgb) / 0.38);
}

.infoSection {
  border: 1px solid rgb(var(--border-rgb) / 0.88);
  border-radius: 0.95rem;
  background: rgb(var(--bg-app-rgb) / 0.18);
  padding: 0.72rem;
  display: grid;
  gap: 0.45rem;
}

.infoSection__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.infoSection__head h4 {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 700;
  color: rgb(var(--text-primary-rgb) / 0.95);
}

.infoSection__line,
.infoSection__subline {
  margin: 0;
  line-height: 1.45;
}

.infoSection__line {
  font-size: 0.82rem;
  color: rgb(var(--text-primary-rgb) / 0.95);
}

.infoSection__subline {
  font-size: 0.74rem;
  color: var(--text-secondary);
}

.infoSection__reorder {
  display: flex;
  gap: 0.35rem;
}

.weatherGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.metricItem {
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--border-rgb) / 0.86);
  background: rgb(var(--bg-surface-2-rgb) / 0.24);
  padding: 0.45rem 0.5rem;
}

.metricItem__label,
.metricItem__value {
  margin: 0;
}

.metricItem__label {
  font-size: 0.67rem;
  color: var(--text-secondary);
}

.metricItem__value {
  margin-top: 0.08rem;
  font-size: 0.8rem;
  color: rgb(var(--text-primary-rgb) / 0.96);
  font-weight: 650;
}

.planetList {
  display: grid;
  gap: 0.42rem;
}

.planetRow {
  border: 1px solid rgb(var(--border-rgb) / 0.9);
  border-radius: 0.78rem;
  background: rgb(var(--bg-surface-2-rgb) / 0.18);
  padding: 0.52rem;
}

.planetRow__title {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  align-items: flex-start;
  min-width: 0;
}

.planetRow__title p {
  margin: 0;
  font-size: 0.81rem;
  color: rgb(var(--text-primary-rgb) / 0.95);
  font-weight: 650;
  min-width: 0;
}

.planetRow__meta {
  margin: 0.2rem 0 0;
  font-size: 0.71rem;
  color: var(--text-secondary);
  line-height: 1.4;
}

.planetBadge {
  display: inline-flex;
  align-items: center;
  min-height: 1.35rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--border-rgb) / 0.9);
  padding: 0 0.5rem;
  font-size: 0.64rem;
  font-weight: 700;
  white-space: nowrap;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
}

.planetBadge--visible {
  border-color: rgb(var(--success-rgb) / 0.44);
  background: rgb(var(--success-rgb) / 0.16);
  color: rgb(var(--success-rgb) / 0.95);
}

.planetBadge--warning {
  border-color: rgb(var(--warning-rgb) / 0.44);
  background: rgb(var(--warning-rgb) / 0.16);
  color: rgb(var(--warning-rgb) / 0.95);
}

.infoSection :deep(.inlineStatus) {
  margin-top: 0.15rem;
}

@keyframes sky-skeleton {
  from { background-position: 200% 0; }
  to { background-position: -200% 0; }
}

@media (max-width: 640px) {
  .skyWidget {
    padding: 0.85rem;
  }

  .summaryCard__score {
    font-size: 1.85rem;
  }

  .summaryCard__top {
    flex-direction: column;
  }

  .summaryCard__meta {
    justify-items: start;
  }

  .summaryCard__window {
    text-align: left;
  }
}
</style>
