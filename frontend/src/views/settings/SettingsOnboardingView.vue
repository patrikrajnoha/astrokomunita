<template>
  <SettingsDetailShell
    title="Onboarding sprievodca"
    subtitle="Interaktivna prehliadka feedu, kalendara a pozorovacich podmienok."
  >
    <div class="settings-form">
      <button
        type="button"
        class="btn btn-primary"
        aria-label="Start onboarding tour"
        @click="startOnboardingTour"
      >
        Spustit sprievodcu
      </button>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { startOnboardingTour } = useSettingsContext()
</script>
