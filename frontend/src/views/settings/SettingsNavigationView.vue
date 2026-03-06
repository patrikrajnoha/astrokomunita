<template>
  <div class="settings-nav-groups">
    <section
      v-for="group in settingsGroups"
      :key="group.label"
      class="settings-group"
      :aria-labelledby="`settings-group-${group.label.toLowerCase()}`"
    >
      <h2 :id="`settings-group-${group.label.toLowerCase()}`" class="settings-group-label">
        {{ group.label }}
      </h2>

      <div class="settings-nav-block">
        <ul class="settings-nav-list">
          <SettingsListItem
            v-for="item in group.items"
            :key="item.key"
            :title="item.title"
            :description="item.description"
            :icon-paths="item.iconPaths"
            :to="{ name: item.routeName }"
          />
        </ul>
      </div>
    </section>

    <section class="settings-group" aria-labelledby="settings-group-session">
      <h2 id="settings-group-session" class="settings-group-label">SESIA</h2>

      <div class="settings-nav-block">
        <button
          id="settings-logout-button"
          type="button"
          class="settings-logout-button"
          :disabled="logoutState.loading"
          @click="submitLogout"
        >
          <span class="settings-logout-icon" aria-hidden="true">L</span>
          <span class="settings-logout-label">{{ logoutState.loading ? 'Odhlasujem...' : 'Odhlasit sa' }}</span>
        </button>
      </div>
      <p v-if="logoutState.error" class="field-error">{{ logoutState.error }}</p>
    </section>
  </div>
</template>

<script setup>
import SettingsListItem from '@/components/settings/SettingsListItem.vue'
import { useSettingsContext } from '@/composables/settingsContext'
import { settingsGroups } from '@/views/settings/settingsSections'

const { logoutState, submitLogout } = useSettingsContext()
</script>
