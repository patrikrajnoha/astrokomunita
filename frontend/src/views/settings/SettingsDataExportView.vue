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

    <div class="export-summary">
      <p
        v-if="exportSummaryState.loading"
        class="export-summary-loading"
      >
        Nacitavam rozsah exportu...
      </p>
      <p
        v-else-if="exportSummaryState.error"
        class="export-summary-error"
      >
        {{ exportSummaryState.error }}
      </p>
      <template v-else-if="exportSummaryState.loaded">
        <p class="export-summary-size">
          Odhad velkosti: <strong>{{ formatExportBytes(exportSummaryState.estimatedBytes) }}</strong>
        </p>
        <p class="export-summary-counts">
          Prispevky: {{ exportSummaryState.counts.posts_count }} |
          Pozvanky: {{ exportSummaryState.counts.invites_received_count + exportSummaryState.counts.invites_sent_count }} |
          Pripomienky: {{ exportSummaryState.counts.reminders_count }} |
          Sledovane udalosti: {{ exportSummaryState.counts.followed_events_count }} |
          Bookmarky: {{ exportSummaryState.counts.bookmarks_count }}
        </p>
      </template>
    </div>

    <div class="settings-form">
      <label
        class="export-password-label"
        for="settings-export-password"
      >
        Potvrdte aktualnym heslom
      </label>
      <input
        id="settings-export-password"
        v-model="exportForm.currentPassword"
        class="input"
        type="password"
        autocomplete="current-password"
        :disabled="exportState.loading"
        placeholder="Zadajte aktualne heslo"
      />
      <button
        id="settings-export-button"
        type="button"
        class="btn btn-primary"
        :disabled="exportState.loading"
        aria-label="Exportovat profilove data"
        @click="downloadProfileExport"
      >
        {{ exportState.loading ? exportState.phase || 'Pripravujem export...' : 'Exportovat moj profil' }}
      </button>
      <p class="export-note">
        Obsahuje profil, newsletter, notifikacne nastavenia, aktivitu, prispevky, prijate aj odoslane pozvanky,
        pripomienky, sledovane udalosti a bookmarky.
      </p>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import { onMounted } from 'vue'
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const {
  downloadProfileExport,
  exportForm,
  exportState,
  exportSummaryState,
  loadExportSummary,
  formatExportBytes,
} = useSettingsContext()

onMounted(() => {
  loadExportSummary()
})
</script>

<style scoped>
.export-summary {
  margin-bottom: 0.8rem;
}

.export-summary-loading,
.export-summary-size,
.export-summary-counts,
.export-summary-error {
  margin: 0;
  font-size: 0.92rem;
  color: #55606f;
}

.export-summary-size {
  font-weight: 600;
}

.export-summary-counts {
  margin-top: 0.2rem;
}

.export-summary-error {
  color: #b13030;
}

.export-note {
  margin-top: 0.55rem;
}

.export-password-label {
  display: block;
  margin: 0 0 0.35rem;
  font-size: 0.9rem;
  color: #55606f;
}

#settings-export-password {
  margin-bottom: 0.6rem;
  width: min(420px, 100%);
}
</style>
