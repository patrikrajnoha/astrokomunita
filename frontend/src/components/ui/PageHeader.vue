<template>
  <header class="page-header" :class="{ 'page-header--row': $slots.actions }">
    <div class="page-header__body">
      <p v-if="eyebrow" class="page-header__eyebrow">{{ eyebrow }}</p>
      <h1 class="page-header__title">{{ title }}</h1>
      <p v-if="description" class="page-header__description">{{ description }}</p>
      <div v-if="$slots.meta" class="page-header__meta">
        <slot name="meta" />
      </div>
    </div>
    <div v-if="$slots.actions" class="page-header__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
})
</script>

<style scoped>
.page-header {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  padding: 1rem 1.25rem;
  background:
    radial-gradient(circle at 85% 10%, rgb(var(--color-primary-rgb) / 0.14), transparent 44%),
    rgb(var(--color-bg-main-rgb) / 0.6);
  overflow: hidden;
}

.page-header--row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.page-header__body {
  display: grid;
  gap: 0;
  min-width: 0;
  flex: 1;
}

.page-header__eyebrow {
  margin: 0;
  font-size: 0.68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: rgb(var(--color-text-secondary-rgb) / 0.88);
}

.page-header__title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1.18;
  color: var(--color-text-primary);
  text-wrap: balance;
}

.page-header__eyebrow + .page-header__title {
  margin-top: 0.2rem;
}

.page-header__description {
  margin: 0.35rem 0 0;
  font-size: var(--font-size-md);
  line-height: 1.45;
  color: rgb(var(--color-text-secondary-rgb) / 0.94);
  max-width: 52ch;
}

.page-header__meta {
  margin-top: 0.5rem;
}

.page-header__actions {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-top: 0.1rem;
}
</style>
