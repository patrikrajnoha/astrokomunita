<template>
  <SettingsDetailShell
    title="User activity"
    subtitle="Hidden by default. Open only when you need it."
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
      <div v-if="activityError" class="status status-error" role="alert">
        {{ activityError }}
      </div>
      <UserActivityCard :loading="activityLoading && !activity" :activity="activity" />
    </div>
  </SettingsDetailShell>
</template>

<script setup>
import UserActivityCard from '@/components/profile/UserActivityCard.vue'
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useSettingsContext } from '@/composables/settingsContext'

const { activity, activityError, activityExpanded, activityLoading, toggleActivitySection } = useSettingsContext()
</script>
