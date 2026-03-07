<script setup>
import LoadingIndicator from '@/components/shared/LoadingIndicator.vue'

const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  rows: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  rowKey: {
    type: String,
    default: 'id',
  },
  emptyTitle: {
    type: String,
    default: 'Ziadne vysledky',
  },
  emptyDescription: {
    type: String,
    default: 'V tabulke nie su ziadne riadky.',
  },
  canClearFilters: {
    type: Boolean,
    default: false,
  },
  rowClass: {
    type: Function,
    default: null,
  },
})

defineEmits(['clear-filters'])

function textValue(value) {
  if (value === null || value === undefined || value === '') return '-'
  return value
}

function resolveRowClass(row) {
  if (typeof props.rowClass !== 'function') return ''
  return props.rowClass(row) || ''
}
</script>

<template>
  <div class="adminTableWrap">
    <table class="adminTable">
      <thead>
        <tr>
          <th
            v-for="column in columns"
            :key="column.key"
            class="adminTable__head"
            :class="[{ 'is-right': column.align === 'right' }, column.headerClass]"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="loading">
          <td :colspan="columns.length" class="adminTable__state">
            <LoadingIndicator :loading="true" text="Nacitavam..." align="center" />
          </td>
        </tr>

        <tr v-else-if="!rows.length">
          <td :colspan="columns.length" class="adminTable__state">
            <div class="adminTable__emptyTitle">{{ emptyTitle }}</div>
            <div class="adminTable__emptyText">{{ emptyDescription }}</div>
            <button
              v-if="canClearFilters"
              type="button"
              class="adminTable__clearBtn"
              @click="$emit('clear-filters')"
            >
              Vymazat filtre
            </button>
          </td>
        </tr>

        <tr
          v-for="row in rows"
          v-else
          :key="row[rowKey]"
          class="adminTable__row"
          :class="resolveRowClass(row)"
          :data-row-key="row[rowKey]"
        >
          <td
            v-for="column in columns"
            :key="`${row[rowKey]}-${column.key}`"
            class="adminTable__cell"
            :class="[{ 'is-right': column.align === 'right' }, column.cellClass]"
          >
            <slot
              :name="`cell(${column.key})`"
              :row="row"
              :value="row[column.key]"
              :column="column"
            >
              {{ textValue(row[column.key]) }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.adminTableWrap {
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-app-rgb) / 0.26);
  overflow: auto;
}

.adminTable {
  width: 100%;
  border-collapse: collapse;
  min-width: 760px;
}

.adminTable__head {
  text-align: left;
  padding: 12px;
  font-size: var(--font-size-xs);
  color: var(--text-secondary);
  background: rgb(var(--bg-app-rgb) / 0.5);
  border-bottom: 1px solid var(--divider-color);
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.adminTable__row {
  border-bottom: 1px solid var(--divider-color);
  transition: background-color var(--motion-fast);
}

.adminTable__row:hover {
  background: var(--interactive-hover);
}

.adminTable__row:last-child {
  border-bottom: none;
}

.adminTable__cell {
  padding: 12px;
  vertical-align: middle;
}

.adminTable__state {
  padding: 18px;
  text-align: center;
  color: var(--text-secondary);
}

.adminTable__emptyTitle {
  font-weight: 600;
}

.adminTable__emptyText {
  margin-top: 6px;
  opacity: 1;
}

.adminTable__clearBtn {
  margin-top: var(--space-2);
  border: 1px solid var(--border-default);
  background: transparent;
  color: var(--text-secondary);
  border-radius: var(--radius-sm);
  min-height: var(--control-height-sm);
  padding: 8px 14px;
  cursor: pointer;
  transition: border-color var(--motion-fast), background-color var(--motion-fast), color var(--motion-fast);
}

.adminTable__clearBtn:hover {
  border-color: rgb(var(--primary-rgb) / 0.42);
  background: var(--interactive-hover);
  color: var(--text-primary);
}

.is-right {
  text-align: right;
}
</style>
