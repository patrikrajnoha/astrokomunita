<template>
  <SettingsDetailShell
    title="Weekly newsletter"
    subtitle="Receive weekly top events, articles, and one astronomy tip."
  >
    <div v-if="newsletterState.success" class="status status-success" role="status">
      {{ newsletterState.success }}
    </div>
    <div v-if="newsletterState.error" class="status status-error" role="alert">
      {{ newsletterState.error }}
    </div>

    <label class="toggle-box" for="settings-newsletter">
      <div class="toggle-meta">
        <p class="toggle-title">{{ newsletterSubscribed ? 'Subscribed' : 'Not subscribed' }}</p>
        <p class="toggle-hint">You can change this anytime.</p>
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
import { useSettingsContext } from '@/composables/settingsContext'

const { newsletterState, newsletterSubscribed, submitNewsletter } = useSettingsContext()
</script>
