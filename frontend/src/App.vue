<template>
  <div v-if="showInitError" class="appInitScreen appInitScreen--error">
    <div class="card">
      <h1>Aplikacia sa nepodarila spustit</h1>
      <p>{{ initMessage }}</p>
      <pre v-if="showStack && initStack">{{ initStack }}</pre>
    </div>
  </div>

  <div v-else-if="showLoading" class="appInitScreen">
    <div class="card">
      <h1>Nacitavam aplikaciu...</h1>
      <p>Inicializujem relaciu a smerovanie.</p>
    </div>
  </div>

  <template v-else>
    <RouterView />
    <Toaster />
    <ConfirmDialog />
  </template>
</template>

<script setup>
import { computed } from 'vue'
import { RouterView } from 'vue-router'
import Toaster from '@/components/ui/Toaster.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { appInitState } from '@/bootstrap/appInitState'

const showInitError = computed(() => Boolean(appInitState.initError))
const showLoading = computed(() => appInitState.initializing && !showInitError.value)
const showStack = computed(() => import.meta.env.DEV)
const initMessage = computed(() => appInitState.initError?.message || 'Neznama chyba pri starte')
const initStack = computed(() => appInitState.initError?.stack || '')
</script>

<style scoped>
.appInitScreen {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 16px;
  background: var(--bg-app);
  color: var(--text-primary);
  transition: background-color 700ms;
}

.card {
  width: min(720px, 100%);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 16px;
  background: var(--bg-surface);
}

.card h1 {
  margin: 0 0 8px;
  font-size: 18px;
}

.card p {
  margin: 0;
  opacity: 0.9;
}

.card pre {
  margin-top: 12px;
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 46vh;
  overflow: auto;
  padding: 10px;
  border-radius: 8px;
  background: var(--bg-surface-2);
  font-size: 12px;
  line-height: 1.45;
}

.appInitScreen--error .card {
  border-color: var(--color-danger);
}
</style>
