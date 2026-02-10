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
  padding: 24px 16px;
}

.adminPageShell__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.adminPageShell__title {
  margin: 0 0 6px;
  font-size: 1.75rem;
  line-height: 1.2;
}

.adminPageShell__subtitle {
  margin: 0;
  opacity: 0.8;
  font-size: 14px;
}

.adminPageShell__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.adminPageShell__content {
  display: grid;
  gap: 14px;
}

@media (max-width: 768px) {
  .adminPageShell__header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
