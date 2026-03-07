<script setup>
defineEmits(['show-all', 'reset-filters'])
</script>

<template>
  <section class="future-empty">
    <div class="future-empty__content">
      <h3 class="future-empty__title">Najblizsie obdobie je zatial prazdne.</h3>
      <p class="future-empty__text">
        Skus rozsirit filtre alebo sa prepni na vsetky udalosti, ak chces pozriet aj archiv a
        starsie pozorovania.
      </p>

      <div class="future-empty__actions">
        <button class="future-empty__primary" type="button" @click="$emit('show-all')">
          Zobrazit vsetky
        </button>
        <button class="future-empty__secondary" type="button" @click="$emit('reset-filters')">
          Reset filtrov
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.future-empty {
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.16);
  background: var(--color-bg-main);
}

.future-empty__content {
  padding: 1.1rem;
  text-align: left;
}

.future-empty__title {
  margin: 0;
  font-size: 1.05rem;
  color: var(--color-surface);
}

.future-empty__text {
  margin: 0.45rem 0 0;
  max-width: 32rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-size: 0.84rem;
  line-height: 1.45;
}

.future-empty__actions {
  margin-top: 0.9rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.future-empty__primary,
.future-empty__secondary {
  min-height: 2.45rem;
  border-radius: 0.8rem;
  padding: 0.52rem 0.85rem;
  font-size: 0.78rem;
  font-weight: 600;
}

.future-empty__primary {
  border: 1px solid rgb(var(--color-primary-rgb) / 0.2);
  background: rgb(var(--color-primary-rgb) / 0.12);
  color: var(--color-surface);
}

.future-empty__secondary {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  background: var(--color-bg-main);
  color: var(--color-surface);
}
</style>
