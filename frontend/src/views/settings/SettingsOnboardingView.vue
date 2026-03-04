<template>
  <SettingsDetailShell
    title="Onboarding tour"
    subtitle="Replay quick guide for feed, calendar, and observing conditions."
  >
    <div class="settings-form">
      <button
        type="button"
        class="btn btn-primary"
        aria-label="Start onboarding tour"
        @click="startOnboardingTour"
      >
        Spustit onboarding
      </button>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { startOnboardingTour } = useSettingsContext()
</script>
