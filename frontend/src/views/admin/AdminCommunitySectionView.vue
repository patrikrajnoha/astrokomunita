<script setup>
import { RouterView } from 'vue-router'
import AdminSectionTabs from '@/components/admin/AdminSectionTabs.vue'
</script>

<template>
  <section class="adminSectionView">
    <AdminSectionTabs section="community" />
    <div class="adminSectionView__body">
      <RouterView />
    </div>
  </section>
</template>

<style scoped>
.adminSectionView {
  display: grid;
  gap: 10px;
  min-width: 0;
}

.adminSectionView__body {
  min-width: 0;
  padding-top: 0;
}

@media (max-width: 767px) {
  .adminSectionView__body {
    padding-top: 0;
  }
}
</style>
