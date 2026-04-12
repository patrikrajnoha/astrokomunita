<template>
  <SettingsDetailShell
    title="Onboarding sprievodca"
    subtitle="Interaktívna prehliadka feedu, kalendára a pozorovacích podmienok."
  >
    <div class="settings-form">
      <button
        type="button"
        class="btn btn-primary"
        aria-label="Spustiť onboarding tour"
        @click="startOnboardingTour"
      >
        Spustiť sprievodcu
      </button>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { startOnboardingTour } = useSettingsContext()
</script>
