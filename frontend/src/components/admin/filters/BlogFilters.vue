<template>
  <div class="blogFilters">
    <div class="filtersHeader">
      <h3>Filtre</h3>
      <button class="ui-btn ui-btn--ghost btn-sm" @click="clearFilters">
        Vymazať filtre
      </button>
    </div>
    
    <div class="filtersGrid">
      <!-- Status filter -->
      <div class="filterField">
        <label class="filterLabel">Status</label>
        <select
          :value="modelValue.status"
          @change="$emit('update:modelValue', { ...modelValue, status: $event.target.value })"
          class="ui-select filterSelect"
        >
          <option value="">Všetky</option>
          <option value="draft">Koncept</option>
          <option value="scheduled">Naplánované</option>
          <option value="published">Publikované</option>
        </select>
      </div>
      
      <!-- Search filter -->
      <div class="filterField">
        <label class="filterLabel">Hľadať</label>
        <input
          :value="modelValue.search"
          @input="$emit('update:modelValue', { ...modelValue, search: $event.target.value })"
          type="text"
          placeholder="Hľadať v názve alebo obsahu..."
          class="ui-input filterInput"
        />
      </div>
    </div>
    
    <div class="filtersActions">
      <button class="ui-btn ui-btn--primary" @click="$emit('filter')">
        Filtrovať
      </button>
    </div>
  </div>
</template>

<script setup>
import { defineEmits } from 'vue';

const emit = defineEmits(['update:modelValue', 'filter', 'clear']);

function clearFilters() {
  emit('update:modelValue', {
    status: '',
    search: ''
  });
  emit('clear');
}
</script>

<style scoped>
.blogFilters {
  background: rgb(var(--bg-app-rgb) / 0.3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  margin-bottom: var(--space-4);
  box-shadow: var(--shadow-soft);
}

.filtersHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--space-3);
}

.filtersHeader h3 {
  margin: 0;
  font-size: var(--font-size-lg);
  font-weight: 700;
  color: var(--color-text-primary);
}

.filtersGrid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.filterField {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.filterLabel {
  font-size: var(--font-size-sm);
  font-weight: 500;
  color: var(--color-text-secondary);
}

.filterSelect, .filterInput {
  font-size: var(--font-size-sm);
}

.filterInput::placeholder {
  color: var(--color-text-muted);
}

.filtersActions {
  display: flex;
  justify-content: flex-end;
}

.btn-sm {
  min-height: 34px;
  padding: 8px 12px;
  font-size: 12px;
}

@media (max-width: 768px) {
  .blogFilters {
    padding: 1rem;
  }
  
  .filtersHeader {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  
  .filtersGrid {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
  
  .filtersActions {
    justify-content: stretch;
  }
  
  .btn {
    width: 100%;
  }
}
</style>
