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
          @click="confirmLogout"
        >
          <span class="settings-logout-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </span>
          <span class="settings-logout-label">{{ logoutState.loading ? 'Odhlasujem...' : 'Odhlásiť sa' }}</span>
        </button>
      </div>
      <p v-if="logoutState.error" class="field-error">{{ logoutState.error }}</p>
    </section>
  </div>
</template>

<script setup>
import SettingsListItem from '@/components/settings/SettingsListItem.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useSettingsContext } from '@/composables/settingsContext'
import { settingsGroups } from '@/views/settings/settingsSections'

const { logoutState, submitLogout } = useSettingsContext()
const { confirm } = useConfirm()

async function confirmLogout() {
  if (logoutState.loading) return

  const approved = await confirm({
    title: 'Odhlásiť sa?',
    message: 'Budete odhlásený z tohto zariadenia.',
    confirmText: 'Odhlásiť sa',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })

  if (!approved) return
  await submitLogout()
}
</script>
