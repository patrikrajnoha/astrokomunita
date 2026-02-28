<template>
  <section class="rounded-2xl border border-white/10 bg-slate-900/60 p-5 backdrop-blur">
    <header class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h3 class="text-sm font-semibold text-slate-100">Astronomické podmienky</h3>
        <button
          type="button"
          class="mt-1 max-w-full cursor-pointer truncate text-left text-sm text-slate-300/80 underline-offset-4 transition hover:underline hover:text-slate-100"
          title="Zmeniť lokalitu"
          @click="goToProfileLocation"
        >
          Poloha: {{ locationLabel }}
        </button>
        <button
          v-if="!hasLocationCoords"
          type="button"
          class="mt-3 inline-flex rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-950 transition hover:bg-white"
          @click="goToProfileLocation"
        >
          Nastaviť polohu
        </button>
      </div>

      <div class="flex items-center gap-2">
        <span
          v-if="globalFreshnessLabel"
          class="rounded-full border border-white/10 bg-white/5 px-2 py-1 text-[10px] text-slate-300"
        >
          {{ globalFreshnessLabel }}
        </span>

        <button
          v-if="isAdminUser"
          type="button"
          data-testid="sky-widget-reorder-toggle"
          class="flex h-8 w-8 items-center justify-center rounded-full text-slate-200 transition hover:bg-white/10"
          :title="editMode ? 'Ukončiť úpravu widgetu' : 'Upraviť poradie sekcií'"
          @click="toggleEditMode"
        >
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M4 14.5 5 11l7.7-7.7a1.8 1.8 0 0 1 2.6 0l1.4 1.4a1.8 1.8 0 0 1 0 2.6L9 15l-3.5 1Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M11.8 4.2 15.8 8.2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" />
          </svg>
        </button>

        <button
          type="button"
          class="flex h-8 w-8 items-center justify-center rounded-full text-slate-200 transition hover:bg-white/10"
          title="Obnoviť"
          @click="refreshAll"
        >
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M3 10a7 7 0 0 1 12-4.9M17 10a7 7 0 0 1-12 4.9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" />
            <path d="M15 2.8v2.8h-2.8M5 17.2v-2.8h2.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </div>
    </header>

    <section v-if="showPrimaryLoading" class="mt-5 space-y-3">
      <div class="h-16 animate-pulse rounded-xl bg-white/5"></div>
      <div class="h-12 animate-pulse rounded-xl bg-white/5"></div>
      <div class="h-24 animate-pulse rounded-xl bg-white/5"></div>
    </section>

    <div v-else class="mt-5 divide-y divide-white/10">
      <section
        v-for="sectionId in orderedSectionIds"
        :key="sectionId"
        class="py-4 first:pt-0 last:pb-0"
      >
        <div class="flex items-start gap-3">
          <div class="min-w-0 flex-1">
            <template v-if="sectionId === 'hero_score'">
              <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ heroTitle }}</p>
              <p class="mt-2 text-4xl font-semibold tracking-tight text-slate-100">
                {{ observingScoreValue }}
                <span class="text-base text-slate-400">/100</span>
              </p>
              <p class="mt-2 text-lg text-slate-100">{{ scoreEmoji }} {{ scoreLabel }}</p>
              <p
                class="mt-2"
                :class="isDaylight ? 'text-base font-medium text-slate-100' : 'text-sm text-slate-300'"
              >
                {{ heroSubtitle }}
              </p>
              <div class="mt-3 h-1.5 rounded-full bg-white/5">
                <div
                  class="h-1.5 rounded-full"
                  :class="scoreColorClass"
                  :style="{ width: `${observingScoreWidth}%` }"
                ></div>
              </div>
            </template>

            <template v-else-if="sectionId === 'best_time'">
              <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Najlepšie dnes</p>
              <p class="mt-2 text-sm text-slate-300">{{ bestTimeLabel }}</p>
            </template>

            <template v-else-if="sectionId === 'weather_inline'">
              <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Počasie teraz</p>
              <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                <div>
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Oblaky</p>
                  <p class="mt-1 text-sm font-medium text-slate-100">{{ formattedMetrics.cloud }}</p>
                </div>
                <div>
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Vlhkosť</p>
                  <p class="mt-1 text-sm font-medium text-slate-100">{{ formattedMetrics.humidity }}</p>
                </div>
                <div>
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Vietor</p>
                  <p class="mt-1 text-sm font-medium text-slate-100">{{ formattedMetrics.wind }}</p>
                </div>
                <div>
                  <p class="text-[10px] uppercase tracking-wide text-slate-500">Teplota</p>
                  <p class="mt-1 text-sm font-medium text-slate-100">{{ formattedMetrics.temp }}</p>
                </div>
              </div>
              <p class="mt-2 text-sm text-slate-300">{{ formattedMetrics.conditionLabel }}</p>
              <p v-if="weatherError" class="mt-2 text-xs text-slate-400">
                {{ weatherError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('weather')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'moon'">
              <p class="text-sm text-slate-100">{{ moonSummaryLine }}</p>
              <button
                type="button"
                class="mt-2 text-xs text-slate-400 underline underline-offset-4 transition hover:text-slate-200"
                @click="moonDetailsOpen = !moonDetailsOpen"
              >
                {{ moonDetailsOpen ? 'Skryť detaily' : 'Detaily' }}
              </button>
              <p v-if="moonDetailsOpen" class="mt-2 text-xs text-slate-400">{{ astronomyTimesLine }}</p>
              <p v-if="astronomyError" class="mt-2 text-xs text-slate-400">
                {{ astronomyError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('astronomy')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'bortle'">
              <p class="text-sm text-slate-100">{{ lightPollutionLine || 'Svetelné znečistenie: nedostupné' }}</p>
              <p v-if="lightPollutionMetaLine" class="mt-1 text-sm text-slate-300">{{ lightPollutionMetaLine }}</p>
              <p v-if="lightPollutionEstimateLine" class="mt-1 text-xs text-slate-400">{{ lightPollutionEstimateLine }}</p>
              <p v-if="lightPollutionError" class="mt-2 text-xs text-slate-400">
                {{ lightPollutionError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('lightPollution')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'planets'">
              <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Planéty</p>
              <p v-if="planetsContextLine" class="mt-2 text-sm text-slate-300">{{ planetsContextLine }}</p>

              <div v-if="shouldShowPlanetsList" class="mt-3 space-y-2">
                <div
                  v-for="planet in planetsDisplayList"
                  :key="planet.name"
                  class="rounded-xl border border-white/10 bg-white/5 px-3 py-3"
                >
                  <p class="text-sm text-slate-100">
                    {{ planet.name }} · {{ planet.direction }} · {{ planet.altitudeLabel }}
                  </p>
                  <p v-if="planet.bestTimeWindow" class="mt-1 text-xs text-slate-400">najlepšie: {{ planet.bestTimeWindow }}</p>
                </div>
              </div>

              <p v-else class="mt-2 text-sm text-slate-300">{{ planetsMessage }}</p>
              <p class="mt-2 text-xs text-slate-500">{{ planetsSourceLine }}</p>
              <p v-if="planetsError" class="mt-2 text-xs text-slate-400">
                {{ planetsError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('planets')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'iss'">
              <p class="text-xs font-medium uppercase tracking-wide text-slate-500">ISS</p>
              <p class="mt-2 text-sm text-slate-300">{{ issLine }}</p>
              <p v-if="issError" class="mt-2 text-xs text-slate-400">
                {{ issError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('iss')">Skúsiť znova</button>
              </p>
            </template>
          </div>

          <div v-if="editMode" class="flex shrink-0 flex-col gap-1">
            <button
              type="button"
              class="rounded-lg border border-white/10 px-2 py-1 text-xs text-slate-200 transition hover:bg-white/10 disabled:opacity-30"
              :disabled="isFirstSection(sectionId)"
              @click="moveSectionById(sectionId, 'up')"
            >
              ↑
            </button>
            <button
              type="button"
              class="rounded-lg border border-white/10 px-2 py-1 text-xs text-slate-200 transition hover:bg-white/10 disabled:opacity-30"
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
const orderedSectionIds = computed(() => sectionOrder.value.filter((sectionId) => {
  if (!SKY_WIDGET_SECTION_IDS.includes(sectionId)) return false
  if (sectionId === 'best_time' && isDaylight.value) return false
  return true
}))

const locationLabel = computed(() => {
  const direct = sanitizeLabel(props.locationName)
  if (direct) return direct
  return hasLocationCoords.value ? 'nastavená' : 'nenastavená'
})

const observingScoreValue = computed(() => (observingScore.value === null ? '--' : observingScore.value))
const observingScoreWidth = computed(() => (observingScore.value === null ? 0 : observingScore.value))

const sunWindowLabel = computed(() => {
  const sunrise = formatIsoShort(astronomy.value?.sunrise_at)
  const sunset = formatIsoShort(astronomy.value?.sunset_at)
  if (sunrise === '-' && sunset === '-') return 'nedostupné'
  return `${sunrise}-${sunset}`
})

const moonWindowLabel = computed(() => {
  const moonrise = formatIsoShort(astronomy.value?.moonrise_at)
  const moonset = formatIsoShort(astronomy.value?.moonset_at)
  if (moonrise === '-' && moonset === '-') return 'nedostupné'
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
  const minutes = freshnessSources.value
    .map(parseFreshnessMinutes)
    .filter((value) => value !== null)
    .sort((a, b) => a - b)

  if (minutes.length === 0) return ''
  if (minutes[0] <= 0) return 'Aktualizované práve teraz'
  return `Aktualizované pred ${minutes[0]} min`
})

function retryBlock(blockName) {
  refreshBlock(blockName)
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
  router.push('/profile/edit')
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
    waxing_crescent: 'Dorastajúci kosáčik',
    first_quarter: 'Prvá štvrť',
    waxing_gibbous: 'Dorastajúci mesiac',
    full_moon: 'Spln',
    waning_gibbous: 'Ubúdajúci mesiac',
    last_quarter: 'Posledná štvrť',
    waning_crescent: 'Ubúdajúci kosáčik',
  }
  const key = sanitizeLabel(value).toLowerCase()
  return map[key] || 'Neznáma fáza'
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
  if (text.includes('práve teraz')) return 0
  const match = text.match(/pred\s+(\d+)\s+min/)
  if (!match) return null
  const parsed = Number(match[1])
  return Number.isFinite(parsed) ? parsed : null
}
</script>
