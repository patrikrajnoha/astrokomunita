<script setup>
import AdminPageShell from '@/components/admin/shared/AdminPageShell.vue'
</script>

<template>
  <AdminPageShell title="Banned words" subtitle="Admin moderation dictionary.">
    <div class="placeholder">
      Banned words view placeholder.
    </div>
  </AdminPageShell>
</template>

<style scoped>
.placeholder {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 16px;
  opacity: 0.85;
}
</style>
