<template>
  <section class="rounded-2xl border border-white/10 bg-slate-900/60 p-4 backdrop-blur">
    <header class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h3 class="text-base font-semibold text-slate-100">Astronomické podmienky</h3>
        <button
          type="button"
          class="mt-1 max-w-full cursor-pointer truncate text-left text-sm text-slate-300 transition hover:text-slate-100"
          title="Zmeniť lokalitu"
          @click="goToProfileLocation"
        >
          {{ locationLabel }}
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

    <section v-if="showAutoLocationCta" class="mt-4 rounded-xl border border-white/10 bg-white/5 px-3 py-3 text-sm text-slate-300">
      <p>Pre presnejšie podmienky si nastav lokalitu.</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-xl bg-white/10 px-3 py-2 text-slate-100 transition hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="autoLocationBusy"
          @click="useApproximateLocation"
        >
          {{ autoLocationBusy ? 'Zisťujem polohu...' : 'Použiť moju približnú polohu' }}
        </button>
        <button
          type="button"
          class="rounded-xl border border-white/10 px-3 py-2 text-slate-200 transition hover:bg-white/5"
          @click="goToProfileLocation"
        >
          Nastaviť manuálne
        </button>
      </div>
      <p v-if="autoLocationError" class="mt-2 text-xs text-rose-300">{{ autoLocationError }}</p>
    </section>

    <section v-if="showPrimaryLoading" class="mt-4 space-y-3">
      <div class="h-16 animate-pulse rounded-xl bg-white/5"></div>
      <div class="h-12 animate-pulse rounded-xl bg-white/5"></div>
      <div class="h-24 animate-pulse rounded-xl bg-white/5"></div>
    </section>

    <div v-else class="mt-4 divide-y divide-white/10">
      <section
        v-for="sectionId in orderedSectionIds"
        :key="sectionId"
        class="py-3 first:pt-0 last:pb-0"
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
              <p class="mt-2 text-sm text-slate-300">{{ heroSubtitle }}</p>
              <div class="mt-3 h-1.5 rounded-full bg-white/5">
                <div
                  class="h-1.5 rounded-full"
                  :class="scoreColorClass"
                  :style="{ width: `${observingScoreWidth}%` }"
                ></div>
              </div>
            </template>

            <template v-else-if="sectionId === 'best_time'">
              <p class="text-sm font-medium text-slate-100">Najlepšie dnes</p>
              <p class="mt-1 text-sm text-slate-300">{{ bestTimeLabel }}</p>
            </template>

            <template v-else-if="sectionId === 'weather_inline'">
              <p class="text-sm font-medium text-slate-100">Počasie teraz</p>
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
              <p class="text-sm font-medium text-slate-100">Mesiac</p>
              <p class="mt-1 text-sm text-slate-300">{{ moonLine }}</p>
              <p class="mt-1 text-sm text-slate-400">{{ astronomyTimesLine }}</p>
              <p v-if="astronomyError" class="mt-2 text-xs text-slate-400">
                {{ astronomyError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('astronomy')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'bortle'">
              <p class="text-sm font-medium text-slate-100">Obloha v okolí</p>
              <p class="mt-1 text-sm text-slate-300">{{ lightPollutionLine || 'Nedostupné' }}</p>
              <p v-if="lightPollutionMetaLine" class="mt-1 text-xs text-slate-400">{{ lightPollutionMetaLine }}</p>
              <p v-if="lightPollutionError" class="mt-2 text-xs text-slate-400">
                {{ lightPollutionError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('lightPollution')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'planets'">
              <p class="text-sm font-medium text-slate-100">Planéty</p>
              <div v-if="shouldShowPlanetsList" class="mt-3 space-y-2">
                <div
                  v-for="planet in planetsDisplayList"
                  :key="planet.name"
                  class="flex items-start justify-between gap-3"
                >
                  <div class="min-w-0">
                    <p class="text-sm text-slate-100">{{ planet.name }}</p>
                    <p class="text-xs text-slate-400">
                      {{ planet.direction }}
                      <span v-if="planet.bestTimeWindow"> · {{ planet.bestTimeWindow }}</span>
                      <span v-if="planet.horizonNote"> · {{ planet.horizonNote }}</span>
                    </p>
                  </div>
                  <span class="rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-xs text-slate-200">{{ planet.altitudeLabel }}</span>
                </div>
              </div>
              <p v-else class="mt-2 text-sm text-slate-300">{{ planetsMessage }}</p>
              <p v-if="planetsError" class="mt-2 text-xs text-slate-400">
                {{ planetsError }}
                <button type="button" class="ml-2 text-slate-200 underline underline-offset-2" @click="retryBlock('planets')">Skúsiť znova</button>
              </p>
            </template>

            <template v-else-if="sectionId === 'iss'">
              <p class="text-sm font-medium text-slate-100">ISS</p>
              <p class="mt-1 text-sm text-slate-300">{{ issLine }}</p>
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
import api from '@/services/api'
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
const autoLocationCandidate = ref(null)
const autoLocationLoading = ref(false)
const autoLocationSaving = ref(false)
const autoLocationError = ref('')
const editMode = ref(false)
const sectionOrder = ref([...SKY_WIDGET_SECTION_IDS])
const isAdminUser = computed(() => Boolean(auth.isAdmin || auth.user?.is_admin || auth.user?.role === 'admin'))

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
  planetsDisplayList,
  planetsMessage,
  shouldShowPlanetsList,
  refreshAll,
  refreshBlock,
} = useSkyWidget({
  lat: toRef(props, 'lat'),
  lon: toRef(props, 'lon'),
  tz: toRef(props, 'tz'),
})

const autoLocationBusy = computed(() => autoLocationLoading.value || autoLocationSaving.value)
const showPrimaryLoading = computed(() => (weatherLoading.value && !weather.value) || (astronomyLoading.value && !astronomy.value))
const showAutoLocationCta = computed(() => auth.initialized && auth.isAuthed && !hasLocationCoords.value)
const orderedSectionIds = computed(() => sectionOrder.value.filter((sectionId) => SKY_WIDGET_SECTION_IDS.includes(sectionId)))

const locationLabel = computed(() => {
  const direct = sanitizeLabel(props.locationName)
  if (direct) return direct

  if (autoLocationCandidate.value) {
    return `${autoLocationCandidate.value.city}, ${autoLocationCandidate.value.country}`
  }

  return hasLocationCoords.value ? 'Presná lokalita' : 'Lokalita nie je nastavená'
})

const observingScoreValue = computed(() => (observingScore.value === null ? '--' : observingScore.value))
const observingScoreWidth = computed(() => (observingScore.value === null ? 0 : observingScore.value))

const sunWindowLabel = computed(() => {
  const sunrise = formatIsoShort(astronomy.value?.sunrise_at)
  const sunset = formatIsoShort(astronomy.value?.sunset_at)
  if (sunrise === '-' && sunset === '-') return 'nedostupné'
  return `${sunrise} / ${sunset}`
})

const moonWindowLabel = computed(() => {
  const moonrise = formatIsoShort(astronomy.value?.moonrise_at)
  const moonset = formatIsoShort(astronomy.value?.moonset_at)
  if (moonrise === '-' && moonset === '-') return 'nedostupné'
  return `${moonrise} / ${moonset}`
})

const astronomyTimesLine = computed(() => `Slnko: ${sunWindowLabel.value} · Mesiac: ${moonWindowLabel.value}`)
const moonPhaseLabel = computed(() => translateMoonPhase(astronomy.value?.moon_phase))
const moonIlluminationLabel = computed(() => {
  const illumination = toFiniteNumber(astronomy.value?.moon_illumination_percent)
  if (illumination === null) return '-'
  return `${Math.round(illumination)}%`
})
const moonLine = computed(() => `${moonPhaseLabel.value} · ${moonIlluminationLabel.value}`)

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

async function fetchAutoLocation() {
  if (!auth.isAuthed) return

  autoLocationLoading.value = true
  autoLocationError.value = ''

  try {
    const response = await api.get('/me/location/auto', {
      meta: { requiresAuth: true, skipErrorToast: true },
    })
    autoLocationCandidate.value = response?.data || null
  } catch {
    autoLocationCandidate.value = null
    autoLocationError.value = 'Približná poloha je dočasne nedostupná.'
  } finally {
    autoLocationLoading.value = false
  }
}

async function useApproximateLocation() {
  if (autoLocationBusy.value || !auth.isAuthed) return

  await fetchAutoLocation()
  if (!autoLocationCandidate.value || autoLocationError.value) return

  await applyAutoLocation()
}

async function applyAutoLocation() {
  if (!autoLocationCandidate.value || !auth.isAuthed) return

  autoLocationSaving.value = true
  autoLocationError.value = ''

  try {
    const candidate = autoLocationCandidate.value
    await api.put('/me/location', {
      latitude: candidate.approx_lat,
      longitude: candidate.approx_lon,
      timezone: candidate.timezone,
      location_label: `${candidate.city}, ${candidate.country}`,
      location_source: 'gps',
    }, {
      meta: { requiresAuth: true, skipErrorToast: true },
    })

    if (typeof auth.fetchUser === 'function') {
      await auth.fetchUser({ source: 'sky-widget-auto-location', retry: false, markBootstrap: false })
    }

    autoLocationCandidate.value = null
    refreshAll({ silent: false })
  } catch {
    autoLocationError.value = 'Nepodarilo sa uložiť približnú polohu.'
  } finally {
    autoLocationSaving.value = false
  }
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
  return map[key] || 'Neznámy stav mesiaca'
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
