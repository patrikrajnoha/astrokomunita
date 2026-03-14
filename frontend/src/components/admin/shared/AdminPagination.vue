<script setup>
import { computed } from 'vue'

const props = defineProps({
  meta: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['pageChange'])

const hasMeta = computed(() => {
  return Boolean(props.meta && props.meta.current_page && props.meta.last_page)
})

const canPrev = computed(() => hasMeta.value && props.meta.current_page > 1)
const canNext = computed(() => hasMeta.value && props.meta.current_page < props.meta.last_page)

function setPage(page) {
  if (!hasMeta.value) return
  if (page < 1 || page > props.meta.last_page || page === props.meta.current_page) return
  emit('pageChange', page)
}
</script>

<template>
  <div v-if="hasMeta" class="adminPagination">
    <div class="adminPagination__info">
      Strana {{ meta.current_page }} / {{ meta.last_page }} (spolu {{ meta.total ?? 0 }})
    </div>

    <div class="adminPagination__controls">
      <button type="button" :disabled="!canPrev" class="adminPagination__btn" @click="setPage(meta.current_page - 1)">
        Predosla
      </button>
      <button type="button" :disabled="!canNext" class="adminPagination__btn" @click="setPage(meta.current_page + 1)">
        Dalsia
      </button>
    </div>
  </div>
</template>

<style scoped>
.adminPagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.adminPagination__info {
  opacity: 0.9;
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
}

.adminPagination__controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.adminPagination__btn {
  min-height: 34px;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: rgb(var(--bg-surface-rgb) / 0.84);
  color: var(--color-text-primary);
  cursor: pointer;
  font-size: var(--font-size-xs);
}

.adminPagination__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 767px) {
  .adminPagination {
    align-items: stretch;
  }

  .adminPagination__controls {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr;
  }
}
</style>
