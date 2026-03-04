<template>
  <SettingsDetailShell
    title="Deactivate account"
    subtitle="This action permanently removes your account and signs you out."
    :danger="true"
  >
    <p class="danger-note">Deleting your account also permanently removes your posts and related content.</p>

    <div v-if="deactivateState.error" class="status status-error" role="alert">
      {{ deactivateState.error }}
    </div>

    <div class="settings-form">
      <label class="field-label" for="deactivate-confirm">Type DEACTIVATE to confirm</label>
      <input
        id="deactivate-confirm"
        v-model.trim="deactivateForm.confirm"
        type="text"
        placeholder="DEACTIVATE"
        class="field-input field-input-danger"
        :disabled="deactivateState.loading"
        aria-label="Confirm deactivation"
      />

      <button
        type="button"
        class="btn btn-danger"
        :disabled="deactivateState.loading || deactivateForm.confirm !== 'DEACTIVATE'"
        aria-label="Deactivate account"
        @click="submitDeactivate"
      >
        {{ deactivateState.loading ? 'Deactivating...' : 'Deactivate account' }}
      </button>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { deactivateForm, deactivateState, submitDeactivate } = useSettingsContext()
</script>
