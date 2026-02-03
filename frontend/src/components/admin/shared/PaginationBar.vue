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
  border-top: 1px solid var(--color-border);
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
  padding: 0.5rem 1rem;
  border: 1px solid var(--color-border);
  background: var(--color-background);
  color: var(--color-text);
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s;
}

.paginationBtn:hover:not(:disabled) {
  background: var(--color-background-hover);
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
  padding: 0.25rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: 0.25rem;
  background: var(--color-background);
  color: var(--color-text);
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
