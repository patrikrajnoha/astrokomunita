<template>
  <section class="bookmarks-view">
    <header class="bookmarks-header">
      <h1 class="bookmarks-title">Zalozky</h1>
      <p class="bookmarks-subtitle">Prispevky, ktore si si ulozil na neskor.</p>
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
  padding: 0.2rem 0.25rem;
}

.bookmarks-title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--color-surface);
}

.bookmarks-subtitle {
  margin: 0.25rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.92rem;
}
</style>
