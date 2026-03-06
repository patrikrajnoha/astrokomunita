<template>
  <SettingsDetailShell
    title="Aktivita pouzivatela"
    subtitle="Sekcia je standardne skryta. Otvorte ju iba ked ju potrebujete."
  >
    <div class="settings-form">
      <button
        id="settings-activity-toggle"
        type="button"
        class="btn btn-ghost"
        :disabled="activityLoading"
        @click="toggleActivitySection"
      >
        {{ activityExpanded ? 'Skryt aktivitu' : 'Zobrazit aktivitu' }}
      </button>
    </div>

    <div v-if="activityExpanded" class="activity-panel">
      <InlineStatus
        v-if="activityError"
        variant="error"
        :message="activityError"
      />
      <UserActivityCard :loading="activityLoading && !activity" :activity="activity" />
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import UserActivityCard from '@/components/profile/UserActivityCard.vue'
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import InlineStatus from '@/components/ui/InlineStatus.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { activity, activityError, activityExpanded, activityLoading, toggleActivitySection } = useSettingsContext()
</script>
