<template>
  <SettingsDetailShell
    title="Týždenný newsletter"
    subtitle="Dostávajte týždenný výber udalostí, článkov a jeden astronomický tip."
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
        <p class="toggle-title">{{ newsletterSubscribed ? 'Prihlásený' : 'Neprihlásený' }}</p>
        <p class="toggle-hint">Toto môžete kedykoľvek zmeniť.</p>
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
