<script setup>
defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <div class="adminToolbar" :class="{ 'is-loading': loading }">
    <div class="adminToolbar__left">
      <div v-if="$slots.search" class="adminToolbar__slot">
        <slot name="search" />
      </div>
      <div v-if="$slots.filters" class="adminToolbar__slot">
        <slot name="filters" />
      </div>
    </div>

    <div v-if="$slots.actions" class="adminToolbar__right">
      <slot name="actions" />
    </div>
  </div>
</template>

<style scoped>
.adminToolbar {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 10px;
  background: rgb(var(--bg-app-rgb) / 0.28);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  box-shadow: var(--shadow-soft);
}

.adminToolbar.is-loading {
  opacity: 0.8;
}

.adminToolbar__left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  flex: 1 1 auto;
}

.adminToolbar__right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.adminToolbar__slot {
  min-width: 160px;
}

@media (max-width: 768px) {
  .adminToolbar {
    align-items: stretch;
  }

  .adminToolbar__left,
  .adminToolbar__right {
    width: 100%;
  }

  .adminToolbar__slot {
    min-width: 0;
    width: 100%;
  }
}
</style>
