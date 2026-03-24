<template>
  <div class="onboardingPage">
    <OnboardingModal
      :loading="saving"
      :interests-catalog="preferences.supportedInterests"
      :initial-interests="preferences.interests"
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
import OnboardingModal from '@/components/onboarding/OnboardingModal.vue'
import { useOnboardingTourStore } from '@/stores/onboardingTour'

const router = useRouter()
const route = useRoute()
const preferences = useEventPreferencesStore()
const onboardingTour = useOnboardingTourStore()
const saving = ref(false)
const shouldStartTourAfterFlow = computed(() => route.query.start_tour === '1')

const initialLocation = computed(() => ({
  location_label: preferences.locationLabel || '',
  location_place_id: preferences.locationPlaceId || null,
  location_lat: preferences.locationLat,
  location_lon: preferences.locationLon,
}))

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
  await preferences.ensureInterestsLoaded()

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
    radial-gradient(900px 420px at 12% -8%, rgb(var(--color-primary-rgb) / 0.18), transparent 65%),
    radial-gradient(800px 460px at 92% 115%, rgb(var(--color-success-rgb) / 0.1), transparent 65%),
    rgb(var(--color-bg-rgb));
}
</style>
