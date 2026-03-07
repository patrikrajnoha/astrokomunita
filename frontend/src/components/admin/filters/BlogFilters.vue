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
  background: rgb(var(--bg-app-rgb) / 0.3);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  padding: var(--space-4);
  margin-bottom: var(--space-4);
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
  font-weight: 600;
  color: var(--text-primary);
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
  color: var(--text-secondary);
}

.filterSelect, .filterInput {
  padding: 0.58rem 0.75rem;
  border: 1px solid var(--border-default);
  border-radius: var(--radius-sm);
  background: rgb(var(--bg-app-rgb) / 0.4);
  color: var(--text-primary);
  font-size: var(--font-size-sm);
  transition: border-color var(--motion-fast), box-shadow var(--motion-fast), background-color var(--motion-fast);
}

.filterSelect:focus, .filterInput:focus {
  outline: none;
  border-color: rgb(var(--primary-rgb) / 0.8);
  box-shadow: var(--focus-ring);
}

.filterInput::placeholder {
  color: var(--text-muted);
}

.filtersActions {
  display: flex;
  justify-content: flex-end;
}

.btn {
  min-height: var(--control-height-sm);
  padding: 0 1rem;
  border: 1px solid var(--border-default);
  border-radius: var(--radius-sm);
  font-size: var(--font-size-sm);
  font-weight: 500;
  cursor: pointer;
  transition: border-color var(--motion-fast), background-color var(--motion-fast), color var(--motion-fast);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-primary {
  background: var(--accent-primary);
  color: var(--text-primary);
  border-color: rgb(var(--primary-rgb) / 0.42);
}

.btn-primary:hover {
  background: var(--accent-primary-hover);
  border-color: rgb(var(--primary-hover-rgb) / 0.5);
}

.btn-ghost {
  background: transparent;
  color: var(--text-secondary);
  border-color: transparent;
}

.btn-ghost:hover {
  border-color: var(--border-default);
  background: var(--interactive-hover);
  color: var(--text-primary);
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
