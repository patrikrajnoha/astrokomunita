<template>
  <section class="bookmarks-view">
    <header class="bookmarks-header">
      <h1 class="bookmarks-title">Záložky</h1>
      <p class="bookmarks-subtitle">Príspevky, ktoré si si uložil na neskôr.</p>
    </header>

    <FeedList mode="bookmarks" />
  </section>
</template>

<script setup>
import FeedList from '@/components/FeedList.vue'
</script>

<style scoped>
.bookmarks-view {
  display: grid;
  gap: 0.75rem;
}

.bookmarks-header {
  padding: var(--space-1) var(--space-2);
}

.bookmarks-title {
  margin: 0;
  font-size: var(--page-heading-size);
  font-weight: 800;
  color: var(--color-text-primary);
}

.bookmarks-subtitle {
  margin: 0.25rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.92rem;
}

:deep(.feed-container) {
  border: 1px solid var(--border-default);
  border-radius: var(--radius-xl);
  overflow: hidden;
  background: var(--color-bg-main);
}

@media (max-width: 720px) {
  :deep(.feed-container) {
    border: 0;
    border-radius: 0;
  }
}
</style>
