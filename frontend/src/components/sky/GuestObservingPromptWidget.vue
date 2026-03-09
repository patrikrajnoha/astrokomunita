<template>
  <section class="card panel guestObservingPrompt">
    <h3 class="panelTitle sidebarSection__header">Astronomicke podmienky</h3>

    <AsyncState
      mode="info"
      title="Prihlaste sa pre presne podmienky"
      message="Nastavte polohu a zobrazime lokalne pocasie, nocnu oblohu a hodnotenie podmienok."
      action-label="Nastavit polohu"
      compact
      @action="goToProfileLocation"
    />
  </section>
</template>

<script setup>
import { useRouter } from 'vue-router'
import AsyncState from '@/components/ui/AsyncState.vue'

const router = useRouter()

function goToProfileLocation() {
  router.push({ name: 'profile.edit', hash: '#location' })
}
</script>

<style scoped>
.card {
  position: relative;
  border: 0;
  background: transparent;
  border-radius: 0;
  padding: 0;
  overflow: visible;
}

.panel {
  display: grid;
  gap: 0.28rem;
  min-width: 0;
}

.panelTitle {
  font-weight: 800;
  color: var(--color-surface);
  font-size: 0.84rem;
  line-height: 1.2;
  margin: 0;
}

.guestObservingPrompt :deep(.asyncState) {
  margin-top: 0;
  border-radius: 0.56rem;
  border-color: var(--divider-color);
  background: rgb(var(--color-bg-rgb) / 0.22);
  box-shadow: none;
}

.guestObservingPrompt :deep(.asyncState__message) {
  max-width: none;
}
</style>
