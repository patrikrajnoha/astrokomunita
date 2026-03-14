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
  padding: 0;
  display: grid;
  gap: 10px;
  min-width: 0;
  container-type: inline-size;
}

.adminPageShell__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  border-radius: 11px;
  background: rgb(var(--color-bg-rgb) / 0.84);
  padding: 10px;
}

.adminPageShell__header > div {
  min-width: 0;
}

.adminPageShell__title {
  margin: 0;
  font-size: 1.15rem;
  line-height: 1.2;
  color: var(--color-text-primary);
}

.adminPageShell__subtitle {
  margin: 3px 0 0;
  color: var(--color-text-secondary);
  font-size: 12px;
  opacity: 0.82;
}

.adminPageShell__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  min-width: 0;
}

.adminPageShell__content {
  display: grid;
  gap: 10px;
  min-width: 0;
}

.adminPageShell__actions :deep(button),
.adminPageShell__actions :deep(a) {
  min-height: 34px;
  padding: 6px 10px;
  font-size: 12px;
  border-radius: 9px;
}

@media (max-width: 768px) {
  .adminPageShell__actions {
    width: 100%;
  }
}

@container (max-width: 760px) {
  .adminPageShell__header {
    align-items: stretch;
    flex-direction: column;
  }

  .adminPageShell__actions {
    width: 100%;
  }

  .adminPageShell__actions > * {
    flex: 1 1 auto;
    text-align: center;
  }
}
</style>
