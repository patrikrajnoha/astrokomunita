<script setup>
import { RouterView } from 'vue-router'
import AdminSectionTabs from '@/components/admin/AdminSectionTabs.vue'
</script>

<template>
  <section class="adminSectionView">
    <AdminSectionTabs section="events" />
    <RouterView />
  </section>
</template>

<style scoped>
.adminSectionView {
  display: grid;
  gap: 10px;
  min-width: 0;
}
</style>
