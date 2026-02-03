<template>
  <div class="blogFilters">
    <div class="filtersHeader">
      <h3>Filtre</h3>
      <button class="btn btn-ghost btn-sm" @click="clearFilters">
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
          class="filterSelect"
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
          class="filterInput"
        />
      </div>
    </div>
    
    <div class="filtersActions">
      <button class="btn btn-primary" @click="$emit('filter')">
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
  background: var(--color-background);
  border: 1px solid var(--color-border);
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.filtersHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.filtersHeader h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text);
}

.filtersGrid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.filterField {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filterLabel {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text);
}

.filterSelect, .filterInput {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 0.375rem;
  background: var(--color-background);
  color: var(--color-text);
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.filterSelect:focus, .filterInput:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filterInput::placeholder {
  color: var(--color-text-secondary);
}

.filtersActions {
  display: flex;
  justify-content: flex-end;
}

.btn {
  padding: 0.5rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-primary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.btn-primary:hover {
  background: var(--color-primary-hover);
  border-color: var(--color-primary-hover);
}

.btn-ghost {
  background: transparent;
  color: var(--color-text-secondary);
  border-color: transparent;
}

.btn-ghost:hover {
  background: var(--color-background-hover);
  color: var(--color-text);
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
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
