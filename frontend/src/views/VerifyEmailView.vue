<template>
  <div class="verifyPage">
    <section class="verifyCard">
      <h1>Overenie cez odkaz už nie je podporované</h1>
      <p class="muted">
        Použi overenie kódom v Nastavenia -> Účet -> E-mail.
      </p>

      <div class="actions">
        <button type="button" class="ui-pill ui-pill--primary" @click="goToSettingsEmail">
          Prejsť na overenie e-mailu
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

function goToSettingsEmail() {
  router.push({
    name: 'settings.email',
  })
}
</script>

<style scoped>
.verifyPage {
  min-height: calc(100vh - 120px);
  display: grid;
  place-items: center;
  padding: 16px;
}

.verifyCard {
  width: min(100%, 560px);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 18px;
  background: var(--bg-surface);
}

.verifyCard h1 {
  margin: 0;
  font-size: 1.3rem;
}

.muted {
  margin-top: 8px;
  color: var(--text-secondary);
}

.actions {
  margin-top: 14px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.actions button {
  flex-shrink: 0;
}
</style>
