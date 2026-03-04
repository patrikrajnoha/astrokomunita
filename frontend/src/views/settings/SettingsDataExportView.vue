<template>
  <SettingsDetailShell
    title="Data export"
    subtitle="Download your profile data as JSON for backup or GDPR requests."
  >
    <div v-if="exportState.success" class="status status-success" role="status">
      {{ exportState.success }}
    </div>
    <div v-if="exportState.error" class="status status-error" role="alert">
      {{ exportState.error }}
    </div>

    <div class="settings-form">
      <button
        id="settings-export-button"
        type="button"
        class="btn btn-primary"
        :disabled="exportState.loading"
        aria-label="Export profile data"
        @click="downloadProfileExport"
      >
        {{ exportState.loading ? 'Preparing export...' : 'Export my profile' }}
      </button>
      <p class="export-note">Includes user profile, posts, invites, and newsletter status.</p>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { downloadProfileExport, exportState } = useSettingsContext()
</script>
