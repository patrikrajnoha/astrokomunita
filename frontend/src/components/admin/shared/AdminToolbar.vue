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
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
  padding: 12px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.adminToolbar.is-loading {
  opacity: 0.8;
}

.adminToolbar__left {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
  flex: 1 1 auto;
}

.adminToolbar__right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.adminToolbar__slot {
  min-width: 180px;
}

@media (max-width: 768px) {
  .adminToolbar {
    align-items: stretch;
  }

  .adminToolbar__left,
  .adminToolbar__right {
    width: 100%;
  }
}
</style>
