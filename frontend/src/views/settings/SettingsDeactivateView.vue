<template>
  <SettingsDetailShell
    title="Deaktivacia uctu"
    subtitle="Tato akcia natrvalo odstrani ucet a odhlasi vas."
    :danger="true"
  >
    <p class="danger-note">Zmazanie uctu natrvalo odstrani aj vase prispevky a suvisiaci obsah.</p>

    <InlineStatus
      v-if="deactivateState.error"
      variant="error"
      :message="deactivateState.error"
    />

    <div class="settings-form">
      <label class="field-label" for="deactivate-confirm">Napiste DEACTIVATE pre potvrdenie</label>
      <input
        id="deactivate-confirm"
        v-model.trim="deactivateForm.confirm"
        type="text"
        placeholder="DEACTIVATE"
        class="field-input field-input-danger"
        :disabled="deactivateState.loading"
        aria-label="Potvrdenie deaktivacie"
      />

      <button
        type="button"
        class="btn btn-danger"
        :disabled="deactivateState.loading || deactivateForm.confirm !== 'DEACTIVATE'"
        aria-label="Deaktivovat ucet"
        @click="confirmDeactivate"
      >
        {{ deactivateState.loading ? 'Deaktivujem...' : 'Deaktivovat ucet' }}
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
    title: 'Deaktivovat ucet?',
    message: 'Ucet a suvisiaci obsah sa natrvalo odstrania.',
    confirmText: 'Deaktivovat ucet',
    cancelText: 'Zrusit',
    variant: 'danger',
  })

  if (!approved) return
  await submitDeactivate()
}
</script>
