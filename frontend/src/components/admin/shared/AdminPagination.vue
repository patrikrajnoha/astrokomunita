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
      Page {{ meta.current_page }} / {{ meta.last_page }} (total {{ meta.total ?? 0 }})
    </div>

    <div class="adminPagination__controls">
      <button type="button" :disabled="!canPrev" class="adminPagination__btn" @click="setPage(meta.current_page - 1)">
        Prev
      </button>
      <button type="button" :disabled="!canNext" class="adminPagination__btn" @click="setPage(meta.current_page + 1)">
        Next
      </button>
    </div>
  </div>
</template>

<style scoped>
.adminPagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.adminPagination__info {
  opacity: 0.85;
  font-size: 14px;
}

.adminPagination__controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.adminPagination__btn {
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.adminPagination__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
