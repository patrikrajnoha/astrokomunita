<template>
  <div class="candidatesFilters">
    <div class="filtersHeader">
      <h3>Filtre</h3>
      <button class="btn btn-ghost btn-sm" @click="clearFilters">
        Vymazať filtre
      </button>
    </div>
    
    <div class="filtersGrid">
      <!-- Status filter -->
      <div v-if="showStatus" class="filterField">
        <label class="filterLabel">Status</label>
        <select
          :value="modelValue.status"
          @change="$emit('update:modelValue', { ...modelValue, status: $event.target.value })"
          class="filterSelect"
        >
          <option value="">Všetky</option>
          <option value="pending">Čaká na schválenie</option>
          <option value="approved">Schválené</option>
          <option value="rejected">Zamietnuté</option>
          <option value="published">Publikované</option>
          <option value="draft">Koncept</option>
        </select>
      </div>
      
      <!-- Event type filter -->
      <div class="filterField">
        <label class="filterLabel">Typ eventu</label>
        <select
          :value="modelValue.type"
          @change="$emit('update:modelValue', { ...modelValue, type: $event.target.value })"
          class="filterSelect"
        >
          <option value="">Všetky typy</option>
          <option value="meteor_shower">Meteorický dážď</option>
          <option value="eclipse">Zatmenie</option>
          <option value="comet">Kométa</option>
          <option value="planetary">Planetárny úkaz</option>
          <option value="aurora">Polárna žiara</option>
          <option value="other">Iné</option>
        </select>
      </div>
      
      <!-- Source filter -->
      <div v-if="showSource" class="filterField">
        <label class="filterLabel">Zdroj</label>
        <input
          :value="modelValue.source"
          @input="$emit('update:modelValue', { ...modelValue, source: $event.target.value })"
          type="text"
          placeholder="Zadajte zdroj..."
          class="filterInput"
        />
      </div>
      
      <!-- Search filter -->
      <div class="filterField">
        <label class="filterLabel">Hľadať</label>
        <input
          :value="modelValue.search"
          @input="$emit('update:modelValue', { ...modelValue, search: $event.target.value })"
          type="text"
          placeholder="Hľadať v názve..."
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

defineProps({
  modelValue: {
    type: Object,
    required: true
  },
  showStatus: {
    type: Boolean,
    default: true
  },
  showSource: {
    type: Boolean,
    default: true
  }
});

function clearFilters() {
  emit('update:modelValue', {
    status: '',
    type: '',
    source: '',
    search: ''
  });
  emit('clear');
}
</script>

<style scoped>
.candidatesFilters {
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
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
  .candidatesFilters {
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
