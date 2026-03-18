<template>
  <SettingsDetailShell
    title="Zmena hesla"
    subtitle="Nastavte nové heslo pre svoj účet."
  >
    <InlineStatus
      v-if="passwordState.success"
      variant="success"
      :message="passwordState.success"
    />
    <InlineStatus
      v-else-if="passwordState.error"
      variant="error"
      :message="passwordState.error"
    />

    <form class="settings-form" @submit.prevent="submitPassword">
      <label class="field-label" for="current-password">Aktuálne heslo</label>
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

      <label class="field-label" for="new-password">Nové heslo</label>
      <input
        id="new-password"
        v-model="passwordForm.password"
        type="password"
        autocomplete="new-password"
        placeholder="Nové heslo"
        class="field-input"
        :disabled="passwordState.loading"
        required
        minlength="8"
      />

      <label class="field-label" for="confirm-password">Potvrďte nové heslo</label>
      <input
        id="confirm-password"
        v-model="passwordForm.confirm"
        type="password"
        autocomplete="new-password"
        placeholder="Potvrďte nové heslo"
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
        aria-label="Aktualizovať heslo"
      >
        {{ passwordState.loading ? 'Aktualizujem...' : 'Aktualizovať heslo' }}
      </button>
    </form>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { passwordForm, passwordState, submitPassword } = useSettingsContext()
</script>
