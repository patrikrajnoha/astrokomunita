<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <section class="adminPageShell">
    <header class="adminPageShell__header">
      <div>
        <h1 class="adminPageShell__title">{{ title }}</h1>
        <p v-if="subtitle" class="adminPageShell__subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="$slots['right-actions']" class="adminPageShell__actions">
        <slot name="right-actions" />
      </div>
    </header>

    <div class="adminPageShell__content">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.adminPageShell {
  width: 100%;
  margin: 0 auto;
  padding: var(--space-6) var(--space-5);
}

.adminPageShell__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-4);
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--color-divider);
}

.adminPageShell__title {
  margin: 0 0 4px;
  font-size: clamp(1.2rem, 1.75vw, 1.5rem);
  line-height: 1.2;
  color: var(--color-text-primary);
}

.adminPageShell__subtitle {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
}

.adminPageShell__actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.adminPageShell__content {
  display: grid;
  gap: var(--space-4);
}

@media (max-width: 768px) {
  .adminPageShell__header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
