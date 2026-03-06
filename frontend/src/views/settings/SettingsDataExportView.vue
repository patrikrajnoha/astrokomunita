<template>
  <SettingsDetailShell
    title="Export dat"
    subtitle="Stiahnite profilove data vo formate JSON pre zalohu alebo GDPR poziadavky."
  >
    <InlineStatus
      v-if="exportState.success"
      variant="success"
      :message="exportState.success"
    />
    <InlineStatus
      v-if="exportState.error"
      variant="error"
      :message="exportState.error"
    />

    <div class="settings-form">
      <button
        id="settings-export-button"
        type="button"
        class="btn btn-primary"
        :disabled="exportState.loading"
        aria-label="Exportovat profilove data"
        @click="downloadProfileExport"
      >
        {{ exportState.loading ? 'Pripravujem export...' : 'Exportovat moj profil' }}
      </button>
      <p class="export-note">Obsahuje profil, prispevky, pozvanky a stav newslettera.</p>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { downloadProfileExport, exportState } = useSettingsContext()
</script>
