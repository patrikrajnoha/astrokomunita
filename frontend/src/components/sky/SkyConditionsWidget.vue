<template>
  <section class="sidebarDenseCard skyDense rounded-xl bg-slate-900/35 p-4 backdrop-blur">
    <header class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="sidebarSection__header text-[0.88rem] font-semibold leading-tight text-slate-100">Astronomicke podmienky</h3>
        <button
          type="button"
          class="mt-0.5 max-w-full cursor-pointer truncate text-left text-xs leading-tight text-slate-300/80 underline-offset-2 transition hover:text-slate-100 hover:underline"
          title="Zmeniť lokalitu"
          @click="goToProfileLocation"
        >
          Poloha: {{ locationLabel }}
        </button>
      </div>

      <div class="flex items-center gap-1.5">
        <span
          v-if="globalFreshnessLabel"
          class="rounded-full border border-white/5 bg-white/5 px-1.5 py-0.5 text-[9px] leading-tight text-slate-300"
        >
          {{ globalFreshnessLabel }}
        </span>

        <button
          v-if="isAdminUser"
          type="button"
          data-testid="sky-widget-reorder-toggle"
          class="flex h-7 w-7 items-center justify-center rounded-full text-slate-200 transition hover:bg-white/10"
          :title="editMode ? 'Ukoncit upravu widgetu' : 'Upravit poradie sekcii'"
          @click="toggleEditMode"
        >
          <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M4 14.5 5 11l7.7-7.7a1.8 1.8 0 0 1 2.6 0l1.4 1.4a1.8 1.8 0 0 1 0 2.6L9 15l-3.5 1Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M11.8 4.2 15.8 8.2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" />
          </svg>
        </button>

        <button
          type="button"
          class="flex h-7 w-7 items-center justify-center rounded-full text-slate-200 transition hover:bg-white/10"
          title="Obnovit"
          @click="refreshAll"
        >
          <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M3 10a7 7 0 0 1 12-4.9M17 10a7 7 0 0 1-12 4.9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" />
            <path d="M15 2.8v2.8h-2.8M5 17.2v-2.8h2.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </div>
    </header>

    <section v-if="showPrimaryLoading" class="mt-1.5 space-y-1.5">
      <div class="h-16 animate-pulse rounded-xl bg-white/5"></div>
      <div class="h-12 animate-pulse rounded-xl bg-white/5"></div>
      <div class="h-24 animate-pulse rounded-xl bg-white/5"></div>
    </section>

    <section
      v-else-if="canonicalLocationMissing"
      class="mt-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2.5"
    >
      <p class="text-sm font-medium leading-tight text-slate-100">Poloha nie je nastavená.</p>
      <p class="mt-1 text-xs leading-tight text-slate-300">
        Nastav si polohu v profile, aby sme vedeli zobraziť presné podmienky pre tvoju oblohu.
      </p>
      <button
        type="button"
        class="mt-2 inline-flex rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-950 transition hover:bg-white"
        @click="goToProfileLocation"
      >
        Nastaviť polohu
      </button>
    </section>

    <section
      v-else-if="hasPrimaryFetchError"
      class="mt-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2.5"
    >
      <p class="text-sm font-medium leading-tight text-slate-100">Nepodarilo sa načítať podmienky.</p>
      <p class="mt-1 text-xs leading-tight text-slate-300">
        Skontroluj pripojenie alebo to skús znova.
      </p>
      <div class="mt-2 flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-950 transition hover:bg-white"
          @click="retryAllData"
        >
          Skúsiť znova
        </button>
        <button
          type="button"
          class="text-xs leading-tight text-slate-300 underline underline-offset-2 transition hover:text-slate-100"
          @click="goToProfileLocation"
        >
          Upraviť polohu
        </button>
      </div>
    </section>

    <div v-else class="mt-3 divide-y divide-white/5">
      <section
        v-for="sectionId in orderedSectionIds"
        :key="sectionId"
        class="py-2.5 first:pt-0 last:pb-0"
      >
        <div class="flex items-start gap-2">
          <div class="min-w-0 flex-1">
            <template v-if="sectionId === 'hero_score'">
              <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ heroTitle }}</p>
              <p class="mt-1.5 text-3xl font-semibold tracking-tight text-slate-100">
                <template v-if="observingScoreValue === null">N/A</template>
                <template v-else>
                  {{ observingScoreValue }}
                  <span class="text-sm text-slate-400">/100</span>
                </template>
              </p>
              <p class="mt-1 text-base leading-tight text-slate-100">{{ scoreEmoji }} {{ scoreLabel }}</p>
              <p
                class="mt-1"
                :class="isDaylight ? 'text-sm font-medium leading-tight text-slate-100' : 'text-xs leading-tight text-slate-300'"
              >
                {{ heroSubtitle }}
              </p>
              <button
                v-if="scoreReasons.length > 0"
                type="button"
                class="mt-1 text-[11px] leading-tight text-slate-400 underline underline-offset-2 transition hover:text-slate-200"
                @click="scoreReasonsOpen = !scoreReasonsOpen"
              >
                {{ scoreReasonsOpen ? 'Skryt preco' : 'Preco?' }}
              </button>
              <ul v-if="scoreReasonsOpen && scoreReasons.length > 0" class="mt-1 space-y-0.5 text-[11px] leading-tight text-slate-300">
                <li v-for="reason in scoreReasons" :key="reason">- {{ reason }}</li>
              </ul>
              <div class="mt-2 h-1.5 rounded-full bg-white/5">
                <div
                  class="h-1.5 rounded-full"
                  :class="scoreColorClass"
                  :style="{ width: `${observingScoreWidth}%` }"
                ></div>
              </div>
            </template>

            <template v-else-if="sectionId === 'best_time'">
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Najlepsie dnes</p>
              <p class="mt-1 text-xs leading-tight text-slate-300">{{ bestTimeLabel }}</p>
            </template>

            <template v-else-if="sectionId === 'weather_inline'">
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Pocasie teraz</p>
              <div class="weatherGrid mt-1.5 text-center">
                <div class="weatherMetric">
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Oblaky</p>
                  <p class="mt-0.5 text-xs font-medium leading-tight text-slate-100">{{ formattedMetrics.cloud }}</p>
                </div>
                <div class="weatherMetric">
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Vlhkost</p>
                  <p class="mt-0.5 text-xs font-medium leading-tight text-slate-100">{{ formattedMetrics.humidity }}</p>
                </div>
                <div class="weatherMetric">
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Vietor</p>
                  <p class="mt-0.5 text-xs font-medium leading-tight text-slate-100">{{ formattedMetrics.wind }}</p>
                </div>
                <div class="weatherMetric">
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Teplota</p>
                  <p class="mt-0.5 text-xs font-medium leading-tight text-slate-100">{{ formattedMetrics.temp }}</p>
                </div>
              </div>
              <p class="mt-1.5 text-xs leading-tight text-slate-300">{{ formattedMetrics.conditionLabel }}</p>
              <p class="mt-1 text-[11px] leading-tight text-slate-400">
                Aktualizovane: {{ weatherUpdatedLabel }} · Zdroj: {{ weatherSourceLabel }}
              </p>
              <p v-if="weatherError" class="mt-1.5 text-[11px] leading-tight text-slate-400">
                {{ weatherError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('weather')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'moon'">
              <p class="text-xs leading-tight text-slate-100">{{ moonSummaryLine }}</p>
              <button
                type="button"
                class="mt-1 text-[11px] leading-tight text-slate-400 underline underline-offset-2 transition hover:text-slate-200"
                @click="moonDetailsOpen = !moonDetailsOpen"
              >
                {{ moonDetailsOpen ? 'Skryt detaily' : 'Detaily' }}
              </button>
              <p v-if="moonDetailsOpen" class="mt-1 text-[11px] leading-tight text-slate-400">{{ astronomyTimesLine }}</p>
              <p v-if="astronomyError" class="mt-1 text-[11px] leading-tight text-slate-400">
                {{ astronomyError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('astronomy')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'bortle'">
              <p class="text-xs leading-tight text-slate-100">{{ lightPollutionLine || 'Svetelne znecistenie: nedostupne' }}</p>
              <p v-if="lightPollutionMetaLine" class="mt-0.5 text-xs leading-tight text-slate-300">{{ lightPollutionMetaLine }}</p>
              <p v-if="lightPollutionEstimateLine" class="mt-0.5 text-[11px] leading-tight text-slate-400">{{ lightPollutionEstimateLine }}</p>
              <p v-if="lightPollutionError" class="mt-1 text-[11px] leading-tight text-slate-400">
                {{ lightPollutionError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('lightPollution')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'planets'">
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Planéty</p>
              <p v-if="planetsContextLine" class="mt-1 text-xs leading-tight text-slate-300">{{ planetsContextLine }}</p>

              <div v-if="shouldShowPlanetsList" class="mt-1.5 space-y-1.5">
                <div
                  v-for="planet in planetsDisplayList"
                  :key="planet.name"
                  class="rounded-lg border border-white/5 bg-white/5 px-2.5 py-2"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <p class="text-[13px] leading-tight text-slate-100">
                        {{ planet.name }} · {{ planet.direction }} · {{ planet.altitudeLabel }}
                      </p>
                      <p class="mt-0.5 text-[11px] leading-tight text-slate-400">elongácia: {{ planet.elongationLabel }}</p>
                      <p v-if="planet.bestTimeWindow" class="mt-0.5 text-[11px] leading-tight text-slate-400">najlepšie: {{ planet.bestTimeWindow }}</p>
                    </div>
                    <span
                      class="shrink-0 rounded-full border px-1.5 py-0.5 text-[9px] font-medium"
                      :class="planet.visibilityToneClass"
                    >
                      {{ planet.visibilityLabel }}
                    </span>
                  </div>
                </div>
              </div>

              <p v-else class="mt-1 text-xs leading-tight text-slate-300">{{ planetsMessage }}</p>
              <p class="mt-1 text-[11px] leading-tight text-slate-500">{{ planetsSourceLine }}</p>
              <p v-if="planetsError" class="mt-1 text-[11px] leading-tight text-slate-400">
                {{ planetsError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('planets')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'iss'">
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">ISS</p>
              <p class="mt-1 text-xs leading-tight text-slate-300">{{ issLine }}</p>
              <p v-if="issError" class="mt-1 text-[11px] leading-tight text-slate-400">
                {{ issError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('iss')">Skúsiť znova</button>
              </p>
            </template>
          </div>

          <div v-if="editMode" class="flex shrink-0 flex-col gap-1">
            <button
              type="button"
              class="rounded-lg border border-white/5 px-2 py-1 text-xs text-slate-200 transition hover:bg-white/10 disabled:opacity-30"
              :disabled="isFirstSection(sectionId)"
              @click="moveSectionById(sectionId, 'up')"
            >
              ↑
            </button>
            <button
              type="button"
              class="rounded-lg border border-white/5 px-2 py-1 text-xs text-slate-200 transition hover:bg-white/10 disabled:opacity-30"
              :disabled="isLastSection(sectionId)"
              @click="moveSectionById(sectionId, 'down')"
            >
              ↓
            </button>
          </div>
        </div>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, toRef } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
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
const scoreReasonsOpen = ref(false)
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
  observingScore,
  scoreReasons,
  scoreLabel,
  scoreEmoji,
  scoreColorClass,
  heroTitle,
  heroSubtitle,
  bestTimeLabel,
  formattedMetrics,
  issLine,
  lightPollutionLine,
  lightPollutionMetaLine,
  lightPollutionEstimateLine,
  weatherUpdatedLabel,
  weatherSourceLabel,
  planetsDisplayList,
  planetsMessage,
  planetsContextLine,
  planetsSourceLine,
  shouldShowPlanetsList,
  isDaylight,
  refreshAll,
  refreshBlock,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
})

const showPrimaryLoading = computed(() => (weatherLoading.value && !weather.value) || (astronomyLoading.value && !astronomy.value))
const hasCoords = computed(() => Boolean(hasLocationCoords.value))
const hasLabel = computed(() => sanitizeLabel(props.locationName).length > 0)
const canonicalLocationMissing = computed(() => !hasCoords.value)
const hasPrimaryFetchError = computed(() => {
  if (!hasCoords.value || showPrimaryLoading.value) return false

  const coreError = Boolean(weatherError.value || astronomyError.value)
  const hasCoreData = Boolean(weather.value || astronomy.value)
  return coreError && !hasCoreData
})
const orderedSectionIds = computed(() => sectionOrder.value.filter((sectionId) => {
  if (!SKY_WIDGET_SECTION_IDS.includes(sectionId)) return false
  if (sectionId === 'best_time' && isDaylight.value) return false
  return true
}))

const locationLabel = computed(() => {
  if (hasLabel.value) return sanitizeLabel(props.locationName)
  return hasCoords.value ? 'nastavená' : 'nenastavená'
})

const observingScoreValue = computed(() => (observingScore.value === null ? null : observingScore.value))
const observingScoreWidth = computed(() => (observingScore.value === null ? 0 : observingScore.value))

const sunWindowLabel = computed(() => {
  const sunrise = formatIsoShort(astronomy.value?.sunrise_at)
  const sunset = formatIsoShort(astronomy.value?.sunset_at)
  if (sunrise === '-' && sunset === '-') return 'nedostupne'
  return `${sunrise}-${sunset}`
})

const moonWindowLabel = computed(() => {
  const moonrise = formatIsoShort(astronomy.value?.moonrise_at)
  const moonset = formatIsoShort(astronomy.value?.moonset_at)
  if (moonrise === '-' && moonset === '-') return 'nedostupne'
  return `${moonrise}-${moonset}`
})

const astronomyTimesLine = computed(() => `Slnko: ${sunWindowLabel.value} · Mesiac: ${moonWindowLabel.value}`)
const moonPhaseLabel = computed(() => translateMoonPhase(astronomy.value?.moon_phase))
const moonPhaseIcon = computed(() => getMoonPhaseIcon(astronomy.value?.moon_phase))
const moonIlluminationLabel = computed(() => {
  const illumination = toFiniteNumber(astronomy.value?.moon_illumination_percent)
  if (illumination === null) return '-'
  return `${Math.round(illumination)}%`
})
const moonSummaryLine = computed(() => `${moonPhaseIcon.value} Mesiac: ${moonPhaseLabel.value} · ${moonIlluminationLabel.value}`)

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

function getMoonPhaseIcon(value) {
  const map = {
    new_moon: '🌑',
    waxing_crescent: '🌒',
    first_quarter: '🌓',
    waxing_gibbous: '🌔',
    full_moon: '🌕',
    waning_gibbous: '🌖',
    last_quarter: '🌗',
    waning_crescent: '🌘',
  }
  const key = sanitizeLabel(value).toLowerCase()
  return map[key] || '🌙'
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
.skyDense {
  --sb-gap-xs: var(--sb-gap-xs, 0.3rem);
  --sb-gap-sm: var(--sb-gap-sm, 0.5rem);
}

.weatherGrid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(64px, 1fr));
  gap: var(--sb-gap-xs);
}

.weatherMetric {
  min-width: 0;
}

@media (max-width: 360px) {
  .weatherGrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
