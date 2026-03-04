<template>
  <div class="settings-page">
    <div class="settings-glow settings-glow-1" aria-hidden="true"></div>
    <div class="settings-glow settings-glow-2" aria-hidden="true"></div>

    <header class="settings-header">
      <p class="settings-eyebrow">Account</p>
      <h1 class="settings-title">Settings</h1>
      <p class="settings-subtitle">Manage your account details and security.</p>
    </header>

    <router-view />
  </div>
</template>

<script setup>
import { provide } from 'vue'
import { settingsContextKey } from '@/composables/settingsContext'
import { useSettingsState } from '@/composables/useSettingsState'
import '@/assets/settings.css'

const settingsState = useSettingsState()
provide(settingsContextKey, settingsState)
</script>
