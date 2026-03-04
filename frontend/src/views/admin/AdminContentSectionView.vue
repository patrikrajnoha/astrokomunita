<script setup>
import { RouterView } from 'vue-router'
import AdminSectionTabs from '@/components/admin/AdminSectionTabs.vue'
</script>

<template>
  <section class="adminSectionView">
    <AdminSectionTabs section="content" />
    <RouterView />
  </section>
</template>

<style scoped>
.adminSectionView {
  min-width: 0;
}
</style>
