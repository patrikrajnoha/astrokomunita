<template>
  <div class="settings-page">
    <header class="settings-header">
      <p class="settings-eyebrow">Ucet</p>
      <h1 class="settings-title">Nastavenia</h1>
      <p class="settings-subtitle">Sprava uctu, bezpecnosti a sukromia.</p>
    </header>

    <router-view />
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, provide } from 'vue'
import { settingsContextKey } from '@/composables/settingsContext'
import { useSettingsState } from '@/composables/useSettingsState'
import '@/assets/settings.css'

const settingsState = useSettingsState()
provide(settingsContextKey, settingsState)

const SETTINGS_SCROLLBAR_CLASS = 'settings-hide-scrollbar'

function applyScrollbarClass() {
  if (typeof document === 'undefined') return
  document.documentElement.classList.add(SETTINGS_SCROLLBAR_CLASS)
  document.body.classList.add(SETTINGS_SCROLLBAR_CLASS)
}

function removeScrollbarClass() {
  if (typeof document === 'undefined') return
  document.documentElement.classList.remove(SETTINGS_SCROLLBAR_CLASS)
  document.body.classList.remove(SETTINGS_SCROLLBAR_CLASS)
}

onMounted(() => {
  applyScrollbarClass()
})

onBeforeUnmount(() => {
  removeScrollbarClass()
})
</script>
