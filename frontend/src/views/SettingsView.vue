<template>
  <div class="settings-page">
    <div class="settings-glow settings-glow-1" aria-hidden="true"></div>
    <div class="settings-glow settings-glow-2" aria-hidden="true"></div>

    <header class="settings-header">
      <p class="settings-eyebrow">Ucet</p>
      <h1 class="settings-title">Nastavenia</h1>
      <p class="settings-subtitle">Sprava uctu, bezpecnosti a sukromia.</p>
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
