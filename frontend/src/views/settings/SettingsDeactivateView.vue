<template>
  <SettingsDetailShell
    title="Deaktivácia účtu"
    subtitle="Táto akcia natrvalo odstráni účet a odhlási vás."
    :danger="true"
  >
    <p class="danger-note">Zmazanie účtu natrvalo odstráni aj vaše príspevky a súvisiaci obsah.</p>

    <InlineStatus
      v-if="deactivateState.error"
      variant="error"
      :message="deactivateState.error"
    />

    <div class="settings-form">
      <label class="field-label" for="deactivate-confirm">Napíšte DEACTIVATE pre potvrdenie</label>
      <input
        id="deactivate-confirm"
        v-model.trim="deactivateForm.confirm"
        type="text"
        placeholder="DEACTIVATE"
        class="field-input field-input-danger"
        :disabled="deactivateState.loading"
        aria-label="Potvrdenie deaktivácie"
      />

      <button
        type="button"
        class="btn btn-danger"
        :disabled="deactivateState.loading || deactivateForm.confirm !== 'DEACTIVATE'"
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
  if (deactivateState.loading || deactivateForm.confirm !== 'DEACTIVATE') return

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
