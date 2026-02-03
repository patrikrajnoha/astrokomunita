<template>
  <div class="candidatesTable">
    <!-- Loading state -->
    <LoadingSpinner v-if="loading" text="Načítavam kandidátov..." />
    
    <!-- Error state -->
    <div v-else-if="error" class="errorState">
      <div class="errorIcon">⚠️</div>
      <div class="errorText">{{ error }}</div>
      <button class="btn btn-outline" @click="$emit('refresh')">Skúsiť znova</button>
    </div>
    
    <!-- Empty state -->
    <div v-else-if="!data?.data?.length" class="emptyState">
      <div class="emptyIcon">📋</div>
      <div class="emptyText">Žiadni kandidáti nenájdení</div>
    </div>
    
    <!-- Table -->
    <div v-else class="tableContainer">
      <table class="table">
        <thead>
          <tr>
            <th class="tableHeader">Názov</th>
            <th class="tableHeader">Typ</th>
            <th v-if="showSource" class="tableHeader">Zdroj</th>
            <th class="tableHeader">Status</th>
            <th class="tableHeader">Dátum</th>
            <th v-if="showActions" class="tableHeader">Akcie</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="candidate in data.data"
            :key="candidate.id"
            class="tableRow"
            @click="$emit('row-click', candidate)"
          >
            <td class="tableCell">
              <div class="candidateTitle">
                {{ candidate.title }}
                <span v-if="candidate.description" class="candidateDescription">
                  {{ truncate(candidate.description, 100) }}
                </span>
              </div>
            </td>
            <td class="tableCell">
              <StatusBadge :status="candidate.event_type" />
            </td>
            <td v-if="showSource" class="tableCell">
              <span class="sourceText">{{ candidate.source || 'Manual' }}</span>
            </td>
            <td class="tableCell">
              <StatusBadge :status="candidate.status" />
            </td>
            <td class="tableCell">
              <div class="dateInfo">
                <div>{{ formatDate(candidate.starts_at) }}</div>
                <div v-if="candidate.ends_at" class="endDate">
                  do {{ formatDate(candidate.ends_at) }}
                </div>
              </div>
            </td>
            <td v-if="showActions" class="tableCell tableActions">
              <div class="actionButtons" @click.stop>
                <button
                  class="actionBtn actionBtn--primary"
                  @click="$emit('approve', candidate)"
                  v-if="candidate.status === 'pending'"
                >
                  Schváliť
                </button>
                <button
                  class="actionBtn actionBtn--secondary"
                  @click="$emit('reject', candidate)"
                  v-if="candidate.status === 'pending'"
                >
                  Zamietnuť
                </button>
                <button
                  class="actionBtn actionBtn--success"
                  @click="$emit('publish', candidate)"
                  v-if="candidate.status === 'approved'"
                >
                  Publikovať
                </button>
                <button
                  class="actionBtn actionBtn--edit"
                  @click="$emit('edit', candidate)"
                  v-if="candidate.source === 'manual'"
                >
                  Upraviť
                </button>
                <button
                  class="actionBtn actionBtn--danger"
                  @click="$emit('delete', candidate)"
                  v-if="candidate.source === 'manual'"
                >
                  Vymazať
                </button>
                <button
                  class="actionBtn actionBtn--secondary"
                  @click="$emit('unreview', candidate)"
                  v-if="candidate.status === 'approved'"
                >
                  Zrušiť schválenie
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { formatDate } from '@/utils/dateUtils.js';
import { truncate } from '@/utils/textUtils.js';
import StatusBadge from '@/components/admin/shared/StatusBadge.vue';
import LoadingSpinner from '@/components/admin/shared/LoadingSpinner.vue';

defineProps({
  data: {
    type: Object,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: null
  },
  showSource: {
    type: Boolean,
    default: true
  },
  showActions: {
    type: Boolean,
    default: true
  }
});

defineEmits([
  'row-click',
  'approve',
  'reject',
  'publish',
  'edit',
  'delete',
  'unreview',
  'refresh'
]);
</script>

<style scoped>
.candidatesTable {
  background: var(--color-background);
  border-radius: 0.5rem;
  overflow: hidden;
}

.errorState, .emptyState {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.errorIcon, .emptyIcon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.errorText, .emptyText {
  color: var(--color-text-secondary);
  margin-bottom: 1.5rem;
  font-size: 1.125rem;
}

.tableContainer {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: 0.5rem;
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.tableHeader {
  background: var(--color-background-secondary);
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.tableRow {
  border-bottom: 1px solid var(--color-border);
  cursor: pointer;
  transition: background-color 0.2s;
}

.tableRow:hover {
  background: var(--color-background-hover);
}

.tableRow:last-child {
  border-bottom: none;
}

.tableCell {
  padding: 1rem;
  vertical-align: top;
}

.candidateTitle {
  font-weight: 500;
  color: var(--color-text);
  margin-bottom: 0.25rem;
}

.candidateDescription {
  display: block;
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  margin-top: 0.25rem;
}

.sourceText {
  font-family: monospace;
  background: var(--color-background-secondary);
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  color: var(--color-text-secondary);
}

.dateInfo {
  font-size: 0.875rem;
  color: var(--color-text);
}

.endDate {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  margin-top: 0.125rem;
}

.tableActions {
  width: 1px; /* Minimize width */
  white-space: nowrap;
}

.actionButtons {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.actionBtn {
  padding: 0.25rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.actionBtn--primary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.actionBtn--primary:hover {
  background: var(--color-primary-hover);
}

.actionBtn--secondary {
  background: var(--color-background);
  color: var(--color-text);
  border-color: var(--color-border);
}

.actionBtn--secondary:hover {
  background: var(--color-background-hover);
}

.actionBtn--success {
  background: var(--color-success);
  color: white;
  border-color: var(--color-success);
}

.actionBtn--success:hover {
  background: var(--color-success-hover);
}

.actionBtn--danger {
  background: var(--color-danger);
  color: white;
  border-color: var(--color-danger);
}

.actionBtn--danger:hover {
  background: var(--color-danger-hover);
}

.actionBtn--edit {
  background: var(--color-warning);
  color: white;
  border-color: var(--color-warning);
}

.actionBtn--edit:hover {
  background: var(--color-warning-hover);
}

@media (max-width: 768px) {
  .tableContainer {
    border-radius: 0;
  }
  
  .tableHeader, .tableCell {
    padding: 0.5rem;
  }
  
  .actionButtons {
    flex-direction: column;
    gap: 0.125rem;
  }
  
  .actionBtn {
    font-size: 0.625rem;
    padding: 0.1875rem 0.375rem;
  }
}
</style>
