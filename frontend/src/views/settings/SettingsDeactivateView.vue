<template>
  <SettingsDetailShell
    title="Deaktivácia účtu"
    subtitle="Táto akcia natrvalo odstráni účet, všetky príspevky a súvisiaci obsah."
    :danger="true"
  >
    <InlineStatus
      v-if="deactivateState.error"
      variant="error"
      :message="deactivateState.error"
    />

    <div class="settings-form">
      <label class="field-label" for="deactivate-password">Zadajte aktuálne heslo</label>
      <input
        id="deactivate-password"
        v-model="deactivateForm.password"
        type="password"
        autocomplete="current-password"
        placeholder="Aktuálne heslo"
        class="field-input field-input-danger"
        :disabled="deactivateState.loading"
        aria-label="Aktuálne heslo pre deaktiváciu"
        required
      />

      <p v-if="deactivateState.fieldError" class="field-error">
        {{ deactivateState.fieldError }}
      </p>

      <button
        type="button"
        class="btn btn-danger"
        :disabled="deactivateState.loading || !deactivateForm.password"
        aria-label="Deaktivovať účet"
        @click="confirmDeactivate"
      >
        {{ deactivateState.loading ? 'Deaktivujem...' : 'Deaktivovať účet' }}
      </button>
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useSettingsContext } from '@/composables/settingsContext'

const { deactivateForm, deactivateState, submitDeactivate } = useSettingsContext()
const { confirm } = useConfirm()

async function confirmDeactivate() {
  if (deactivateState.loading || !deactivateForm.password) return

  const approved = await confirm({
    title: 'Deaktivovať účet?',
    message: 'Účet a súvisiaci obsah sa natrvalo odstránia.',
    confirmText: 'Deaktivovať účet',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })

  if (!approved) return
  await submitDeactivate()
}
</script>
