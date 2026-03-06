<template>
  <SettingsDetailShell
    title="Tyzdenny newsletter"
    subtitle="Dostavajte tyzdenny vyber udalosti, clankov a jeden astronomicky tip."
  >
    <InlineStatus
      v-if="newsletterState.success"
      variant="success"
      :message="newsletterState.success"
    />
    <InlineStatus
      v-if="newsletterState.error"
      variant="error"
      :message="newsletterState.error"
    />

    <label class="toggle-box" for="settings-newsletter">
      <div class="toggle-meta">
        <p class="toggle-title">{{ newsletterSubscribed ? 'Prihlaseny' : 'Neprihlaseny' }}</p>
        <p class="toggle-hint">Toto mozete kedykolvek zmenit.</p>
      </div>
      <input
        id="settings-newsletter"
        type="checkbox"
        :checked="newsletterSubscribed"
        :disabled="newsletterState.loading"
        @change="submitNewsletter($event.target.checked)"
      />
    </label>
  </SettingsDetailShell>
</template>

<script setup>
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { newsletterState, newsletterSubscribed, submitNewsletter } = useSettingsContext()
</script>
