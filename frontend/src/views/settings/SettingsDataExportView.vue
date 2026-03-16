<template>
  <SettingsDetailShell
    title="Export dat"
    subtitle="Stiahnite profilové dáta vo formáte JSON pre zálohu alebo GDPR požiadavky."
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
        Načítavam rozsah exportu...
      </p>
      <p
        v-else-if="exportSummaryState.error"
        class="export-summary-error"
      >
        {{ exportSummaryState.error }}
      </p>
      <template v-else-if="exportSummaryState.loaded">
        <p class="export-summary-size">
          Odhad veľkosti: <strong>{{ formatExportBytes(exportSummaryState.estimatedBytes) }}</strong>
        </p>
        <p class="export-summary-counts">
          Prispevky: {{ exportSummaryState.counts.posts_count }} |
          Pozvánky: {{ exportSummaryState.counts.invites_received_count + exportSummaryState.counts.invites_sent_count }} |
          Pripomienky: {{ exportSummaryState.counts.reminders_count }} |
          Sledované udalosti: {{ exportSummaryState.counts.followed_events_count }} |
          Bookmarky: {{ exportSummaryState.counts.bookmarks_count }}
        </p>
      </template>
    </div>

    <div class="settings-form">
      <label
        class="field-label"
        for="settings-export-password"
      >
        Potvrďte aktuálnym heslom
      </label>
      <input
        id="settings-export-password"
        v-model="exportForm.currentPassword"
        class="field-input"
        type="password"
        autocomplete="current-password"
        :disabled="exportState.loading"
        placeholder="Zadajte aktuálne heslo"
      />
      <button
        id="settings-export-button"
        type="button"
        class="btn btn-primary"
        :disabled="exportState.loading || exportState.retryAfterSeconds > 0"
        aria-label="Exportovať profilové dáta"
        @click="downloadProfileExport"
      >
        {{
          exportState.loading
            ? exportState.phase || 'Pripravujem export...'
            : exportState.retryAfterSeconds > 0
              ? `Skúste znova o ${exportState.retryAfterSeconds}s`
              : 'Exportovať môj profil'
        }}
      </button>
      <p class="export-note">
        Obsahuje profil, newsletter, notifikačné nastavenia, aktivitu, príspevky, prijaté aj odoslané pozvánky,
        pripomienky, sledované udalosti a záložky.
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
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.export-summary-size {
  font-weight: 600;
}

.export-summary-size strong {
  color: var(--color-text-primary);
}

.export-summary-counts {
  margin-top: 0.2rem;
}

.export-summary-error {
  color: rgb(var(--color-danger-rgb) / 0.95);
}

.export-note {
  margin-top: 0.55rem;
}

#settings-export-password {
  margin-bottom: 0.6rem;
  width: min(420px, 100%);
}
</style>
