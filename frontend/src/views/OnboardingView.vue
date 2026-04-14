<template>
  <div class="onboardingPage">
    <OnboardingModal
      :loading="saving"
      :widget-catalog="widgetCatalog"
      :initial-widget-keys="initialWidgetKeys"
      :initial-location="initialLocation"
      @finish="handleFinish"
      @skip="handleSkip"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import { DEFAULT_SIDEBAR_SCOPE } from '@/generated/sidebarScopes'
import OnboardingModal from '@/components/onboarding/OnboardingModal.vue'
import { useOnboardingTourStore } from '@/stores/onboardingTour'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const route = useRoute()
const preferences = useEventPreferencesStore()
const sidebarConfigStore = useSidebarConfigStore()
const onboardingTour = useOnboardingTourStore()
const { warn } = useToast()

const saving = ref(false)
const defaultScopeItems = ref([])
const shouldStartTourAfterFlow = computed(() => route.query.start_tour === '1')

const widgetDescriptions = {
  next_event: 'Najbližšia astronomická udalosť na jednom mieste.',
  nasa_apod: 'Denný výber astrofotografie z NASA APOD.',
  search: 'Rýchle vyhľadávanie obsahu naprieč aplikáciou.',
  latest_articles: 'Čerstvé články a novinky zo sveta astronómie.',
  upcoming_events: 'Nadchádzajúce eventy, ktoré sa oplatí sledovať.',
  moon_phases: 'Aktuálna fáza Mesiaca a najbližšie zmeny.',
  moon_events: 'Mesiac, východy/západy a pozorovacie okná.',
  constellations_now: 'Súhvezdia viditeľné práve teraz.',
  aurora_watch: 'Aurora index a šanca pozorovania polárnej žiary.',
  space_weather: 'Dôležité ukazovatele vesmírneho počasia.',
  neo_watchlist: 'Blízke asteroidy a ich bezpečné prelety.',
  iss_pass: 'Najbližšie prelety ISS nad tvojou lokalitou.',
}

const initialLocation = computed(() => ({
  location_label: preferences.locationLabel || '',
  location_place_id: preferences.locationPlaceId || null,
  location_lat: preferences.locationLat,
  location_lon: preferences.locationLon,
}))

const sidebarDefaultWidgetKeys = computed(() => {
  return defaultScopeItems.value
    .filter((item) => item?.kind === 'builtin' && item?.is_enabled)
    .sort((a, b) => Number(a?.order ?? 0) - Number(b?.order ?? 0))
    .map((item) => String(item?.section_key || '').trim())
    .filter(Boolean)
    .slice(0, 3)
})

const widgetCatalog = computed(() => {
  const orderByKey = new Map()
  defaultScopeItems.value.forEach((item, index) => {
    const key = String(item?.section_key || '').trim()
    if (!key || orderByKey.has(key)) return
    const order = Number.isFinite(Number(item?.order)) ? Number(item.order) : index
    orderByKey.set(key, order)
  })

  const rows = Array.isArray(preferences.supportedSidebarWidgets)
    ? preferences.supportedSidebarWidgets
    : []

  const fallbackRows = rows.length > 0
    ? rows
    : defaultScopeItems.value.map((item) => ({
        section_key: item?.section_key,
        title: item?.title,
      }))

  return fallbackRows
    .map((row, index) => {
      const key = String(row?.section_key || '').trim()
      const label = String(row?.title || '').trim()
      if (!key || !label) return null

      const order = orderByKey.has(key) ? orderByKey.get(key) : 1000 + index
      return {
        key,
        label,
        description: widgetDescriptions[key] || 'Prispôsob si sidebar podľa toho, čo chceš sledovať najčastejšie.',
        order,
      }
    })
    .filter(Boolean)
    .sort((a, b) => a.order - b.order)
})

const initialWidgetKeys = computed(() => {
  const explicitHomeKeys = Array.isArray(preferences.sidebarWidgetOverrides?.[DEFAULT_SIDEBAR_SCOPE])
    ? preferences.sidebarWidgetOverrides[DEFAULT_SIDEBAR_SCOPE]
    : []

  if (explicitHomeKeys.length > 0) {
    return explicitHomeKeys.slice(0, 3)
  }

  if (Array.isArray(preferences.sidebarWidgetKeys) && preferences.sidebarWidgetKeys.length > 0) {
    return preferences.sidebarWidgetKeys.slice(0, 3)
  }

  if (sidebarDefaultWidgetKeys.value.length > 0) {
    return sidebarDefaultWidgetKeys.value.slice(0, 3)
  }

  return widgetCatalog.value.map((item) => item.key).slice(0, 3)
})

function resolveRedirectTarget() {
  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
  if (!redirect.startsWith('/') || redirect.startsWith('/onboarding')) return '/'
  return redirect
}

async function handleFinish(payload) {
  if (saving.value) return
  saving.value = true
  try {
    const shouldStartTour = shouldStartTourAfterFlow.value
    await preferences.saveOnboarding(payload)
    await router.replace(resolveRedirectTarget())
    if (shouldStartTour) {
      onboardingTour.restartTour()
    }
  } catch (error) {
    warn(error?.userMessage || preferences.error || 'Nepodarilo sa uložiť úvodné nastavenie.')
  } finally {
    saving.value = false
  }
}

async function handleSkip() {
  if (saving.value) return
  saving.value = true
  try {
    const shouldStartTour = shouldStartTourAfterFlow.value
    await preferences.markOnboardingComplete()
    await router.replace(resolveRedirectTarget())
    if (shouldStartTour) {
      onboardingTour.restartTour()
    }
  } catch (error) {
    warn(error?.userMessage || preferences.error || 'Nepodarilo sa preskočiť úvodné nastavenie.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    await preferences.fetchPreferences()
  } catch {
    // Router guard already handles failures conservatively.
  }

  try {
    defaultScopeItems.value = await sidebarConfigStore.fetchScope(DEFAULT_SIDEBAR_SCOPE, { force: true })
  } catch {
    defaultScopeItems.value = []
  }

  if (preferences.isOnboardingCompleted) {
    await router.replace(resolveRedirectTarget())
  }
})
</script>

<style scoped>
.onboardingPage {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 1rem;
  background:
    radial-gradient(900px 440px at 10% -6%, rgb(15 115 255 / 0.17), transparent 65%),
    radial-gradient(860px 500px at 96% 118%, rgb(15 115 255 / 0.1), transparent 70%),
    #151d28;
}
</style>
