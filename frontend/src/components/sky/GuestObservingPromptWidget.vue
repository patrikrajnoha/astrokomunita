<template>
  <section class="card panel guestObservingPrompt" aria-labelledby="observing-promo-title">
    <h3 class="panelTitle sidebarSection__header">Astronomicke podmienky</h3>

    <div class="promoCard">
      <p id="observing-promo-title" class="promoTitle">{{ promptTitle }}</p>
      <p class="promoText">{{ promptMessage }}</p>
      <button type="button" class="promoBtn" @click="handleAction">{{ actionLabel }}</button>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const isAuthed = computed(() => auth.isAuthed)

const promptTitle = computed(() => (
  isAuthed.value ? 'Pridaj si lokalitu' : 'Prihlas sa'
))

const promptMessage = computed(() => (
  isAuthed.value
    ? 'Pridaj si lokalitu, aby si videl astronomicke podmienky pre tvoje mesto.'
    : 'Prihlas sa, nastav polohu a zobrazime lokalne podmienky.'
))

const actionLabel = computed(() => (
  isAuthed.value ? 'Nastavit polohu' : 'Prihlasit sa'
))

function handleAction() {
  if (isAuthed.value) {
    router.push({ name: 'profile.edit', hash: '#location' })
    return
  }

  router.push({ name: 'login', query: { redirect: route.fullPath || '/' } })
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

.promoCard {
  border: 1px solid var(--divider-color);
  border-radius: 0.64rem;
  background: rgb(var(--color-bg-rgb) / 0.2);
  padding: 0.56rem 0.62rem;
  display: grid;
  gap: 0.36rem;
}

.promoTitle {
  margin: 0;
  font-size: 0.84rem;
  line-height: 1.2;
  font-weight: 700;
  color: var(--color-surface);
}

.promoText {
  margin: 0;
  font-size: 0.74rem;
  line-height: 1.3;
  color: var(--color-text-secondary);
}

.promoBtn {
  justify-self: start;
  min-height: 1.68rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.44);
  background: rgb(var(--color-primary-rgb) / 0.14);
  color: var(--color-surface);
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.24rem 0.62rem;
}

.promoBtn:hover {
  background: rgb(var(--color-primary-rgb) / 0.22);
}
</style>
