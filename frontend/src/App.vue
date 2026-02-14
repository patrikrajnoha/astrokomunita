<template>
  <div v-if="showInitError" class="appInitScreen appInitScreen--error">
    <div class="card">
      <h1>App failed to start</h1>
      <p>{{ initMessage }}</p>
      <pre v-if="showStack && initStack">{{ initStack }}</pre>
    </div>
  </div>

  <div v-else-if="showLoading" class="appInitScreen">
    <div class="card">
      <h1>Loading app...</h1>
      <p>Initializing session and router.</p>
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
const initMessage = computed(() => appInitState.initError?.message || 'Unknown startup error')
const initStack = computed(() => appInitState.initError?.stack || '')
</script>

<style scoped>
.appInitScreen {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 16px;
  background: linear-gradient(160deg, rgb(7 13 24), rgb(16 22 34));
  color: #f8fafc;
}

.card {
  width: min(720px, 100%);
  border: 1px solid rgb(148 163 184 / 0.3);
  border-radius: 14px;
  padding: 16px;
  background: rgb(15 23 42 / 0.78);
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
  background: rgb(2 6 23 / 0.8);
  font-size: 12px;
  line-height: 1.45;
}

.appInitScreen--error .card {
  border-color: rgb(248 113 113 / 0.55);
}
</style>
