<template>
  <SettingsDetailShell
    :title="ui.title"
    :subtitle="ui.subtitle"
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

    <section class="export-size">
      <p
        v-if="exportSummaryState.loading"
        class="section-text"
      >
        {{ ui.summaryLoading }}
      </p>
      <p
        v-else-if="exportSummaryState.error"
        class="section-text section-text-error"
      >
        {{ ui.summaryError }}
      </p>
      <p
        v-else
        class="section-text"
      >
        <template v-if="exportSummaryState.loaded">
          {{ ui.sizeLabel }} <strong>{{ formatExportBytes(exportSummaryState.estimatedBytes) }}</strong>
        </template>
        <template v-else>
          {{ ui.summaryLoading }}
        </template>
      </p>
    </section>

    <section class="export-content">
      <h3 class="section-title">{{ ui.contentTitle }}</h3>
      <ul class="content-list">
        <li
          v-for="item in exportContentItems"
          :key="item"
          class="content-list-item"
        >
          {{ item }}
        </li>
      </ul>
    </section>

    <section class="settings-form export-confirmation">
      <h3 class="section-title">{{ ui.passwordSectionTitle }}</h3>
      <label
        class="field-label"
        for="settings-export-password"
      >
        {{ ui.passwordLabel }}
      </label>
      <input
        id="settings-export-password"
        v-model="exportForm.currentPassword"
        class="field-input"
        type="password"
        autocomplete="current-password"
        :disabled="exportState.loading"
        :placeholder="ui.passwordPlaceholder"
      />
      <button
        id="settings-export-button"
        type="button"
        class="btn btn-primary"
        :disabled="isSubmitDisabled"
        :aria-label="ui.primaryCta"
        @click="downloadProfileExport"
      >
        {{
          exportState.loading
            ? exportState.phase || ui.loadingFallback
            : exportState.retryAfterSeconds > 0
              ? `${ui.retryPrefix} ${exportState.retryAfterSeconds}s`
              : ui.primaryCta
        }}
      </button>
    </section>
  </SettingsDetailShell>
</template>

<script setup>
import { computed, onMounted } from 'vue'
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

const ui = Object.freeze({
  title: 'Export d\u00E1t',
  subtitle: 'Stiahnite si k\u00F3piu svojich \u00FAdajov',
  summaryLoading: 'Pripravujem preh\u013Ead exportu...',
  summaryError: 'Preh\u013Ead exportu sa nepodarilo na\u010D\u00EDta\u0165.',
  sizeLabel: 'Ve\u013Ekos\u0165 exportu:',
  contentTitle: 'Obsah exportu',
  passwordSectionTitle: 'Potvrdenie heslom',
  passwordLabel: 'Aktu\u00E1lne heslo',
  passwordPlaceholder: 'Zadajte heslo',
  primaryCta: 'Stiahnu\u0165 moje d\u00E1ta',
  loadingFallback: 'Pripravujem export...',
  retryPrefix: 'Sk\u00FAste znova o',
})

const exportContentItems = Object.freeze([
  'Profil a nastavenia',
  'Aktivita a pr\u00EDspevky',
  'Newsletter a notifik\u00E1cie',
  'Udalosti a z\u00E1lo\u017Eky',
])

const isSubmitDisabled = computed(() => {
  const hasPassword = String(exportForm.currentPassword || '').trim() !== ''
  return exportState.loading || exportState.retryAfterSeconds > 0 || !hasPassword
})

onMounted(() => {
  loadExportSummary()
})
</script>

<style scoped>
.export-size {
  margin-top: 0.15rem;
}

.section-title {
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.28;
  color: var(--color-surface);
  font-weight: 700;
}

.section-text {
  margin: 0;
  font-size: 0.92rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
}

.section-text strong {
  color: var(--color-text-primary);
  font-weight: 700;
}

.section-text-error {
  color: rgb(var(--color-danger-rgb) / 0.95);
}

.export-content {
  margin-top: 1rem;
  display: grid;
  gap: 0.5rem;
}

.content-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.34rem;
}

.content-list-item {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.34;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
}

.export-confirmation {
  margin-top: 1rem;
}

#settings-export-password {
  margin-bottom: 0.45rem;
  width: min(420px, 100%);
}
</style>
