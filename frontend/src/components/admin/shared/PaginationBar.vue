<template>
  <div v-if="pagination" class="paginationBar">
    <div class="paginationInfo">
      Zobrazujem {{ pagination.from }}-{{ pagination.to }} z {{ pagination.total }} položiek
    </div>
    
    <div class="paginationControls">
      <button
        class="paginationBtn"
        :disabled="!hasPrevPage"
        @click="prevPage"
      >
        &larr; Predchádzajúce
      </button>
      
      <span class="paginationPages">
        Strana {{ pagination.currentPage }} z {{ pagination.lastPage }}
      </span>
      
      <button
        class="paginationBtn"
        :disabled="!hasNextPage"
        @click="nextPage"
      >
        Ďalšie &rarr;
      </button>
    </div>
    
    <div class="paginationPerPage">
      <label>Zobraziť:</label>
      <select
        v-model="selectedPerPage"
        @change="onPerPageChange"
      >
        <option :value="10">10</option>
        <option :value="20">20</option>
        <option :value="50">50</option>
        <option :value="100">100</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  pagination: {
    type: Object,
    required: true
  },
  hasPrevPage: {
    type: Boolean,
    default: false
  },
  hasNextPage: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['prev-page', 'next-page', 'per-page-change']);

const selectedPerPage = ref(props.pagination?.perPage || 20);

const prevPage = () => {
  emit('prev-page');
};

const nextPage = () => {
  emit('next-page');
};

const onPerPageChange = () => {
  emit('per-page-change', parseInt(selectedPerPage.value));
};
</script>

<style scoped>
.paginationBar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 0;
  border-top: 1px solid var(--color-divider);
  margin-top: 1rem;
}

.paginationInfo {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.paginationControls {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.paginationBtn {
  padding: 8px 14px;
  border: 1px solid var(--color-border);
  background: rgb(var(--bg-surface-rgb) / 0.84);
  color: var(--color-text-primary);
  border-radius: var(--radius-pill);
  cursor: pointer;
  transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease;
}

.paginationBtn:hover:not(:disabled) {
  background: var(--interactive-hover);
}

.paginationBtn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.paginationPages {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  font-weight: 500;
}

.paginationPerPage {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.paginationPerPage select {
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-app-rgb) / 0.45);
  color: var(--color-text-primary);
}

@media (max-width: 768px) {
  .paginationBar {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  
  .paginationControls {
    justify-content: center;
  }
  
  .paginationPerPage {
    justify-content: center;
  }
}
</style>
