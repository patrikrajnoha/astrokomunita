<template>
  <SettingsDetailShell
    title="Úvodný sprievodca"
    subtitle="Krátka interaktívna prehliadka feedu, kalendára a tvojich widgetov."
  >
    <div class="settings-form">
      <button
        type="button"
        class="btn btn-primary"
        aria-label="Spustiť úvodného sprievodcu"
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
