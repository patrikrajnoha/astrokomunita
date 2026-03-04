<template>
  <SettingsDetailShell
    title="Change password"
    subtitle="Set a new password for your account."
  >
    <div v-if="passwordState.success" class="status status-success" role="status">
      {{ passwordState.success }}
    </div>
    <div v-if="passwordState.error" class="status status-error" role="alert">
      {{ passwordState.error }}
    </div>

    <form class="settings-form" @submit.prevent="submitPassword">
      <label class="field-label" for="current-password">Current password</label>
      <input
        id="current-password"
        v-model="passwordForm.current"
        type="password"
        autocomplete="current-password"
        placeholder="********"
        class="field-input"
        :disabled="passwordState.loading"
        required
      />

      <label class="field-label" for="new-password">New password</label>
      <input
        id="new-password"
        v-model="passwordForm.password"
        type="password"
        autocomplete="new-password"
        placeholder="New password"
        class="field-input"
        :disabled="passwordState.loading"
        required
        minlength="8"
      />

      <label class="field-label" for="confirm-password">Confirm new password</label>
      <input
        id="confirm-password"
        v-model="passwordForm.confirm"
        type="password"
        autocomplete="new-password"
        placeholder="Confirm new password"
        class="field-input"
        :disabled="passwordState.loading"
        required
        minlength="8"
      />

      <p v-if="passwordState.fieldError" class="field-error">
        {{ passwordState.fieldError }}
      </p>

      <button
        type="submit"
        class="btn btn-primary"
        :disabled="passwordState.loading"
        aria-label="Update password"
      >
        {{ passwordState.loading ? 'Updating...' : 'Update password' }}
      </button>
    </form>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { passwordForm, passwordState, submitPassword } = useSettingsContext()
</script>
