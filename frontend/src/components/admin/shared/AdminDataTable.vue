<script setup>
import AsyncState from '@/components/ui/AsyncState.vue'

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
  loadingRows: {
    type: Number,
    default: 5,
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
        <template v-if="loading">
          <tr
            v-for="index in loadingRows"
            :key="`table-skeleton-${index}`"
            class="adminTable__row adminTable__row--skeleton"
          >
            <td
              v-for="column in columns"
              :key="`table-skeleton-cell-${index}-${column.key}`"
              class="adminTable__cell"
            >
              <span class="adminTable__skeleton ui-skeleton ui-skeleton--line"></span>
            </td>
          </tr>
        </template>

        <tr v-else-if="!rows.length">
          <td :colspan="columns.length" class="adminTable__state">
            <AsyncState
              mode="empty"
              :title="emptyTitle"
              :message="emptyDescription"
              :action-label="canClearFilters ? 'Vymazat filtre' : ''"
              compact
              @action="$emit('clear-filters')"
            />
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
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-app-rgb) / 0.28);
  overflow: auto;
}

.adminTable {
  width: 100%;
  border-collapse: collapse;
  min-width: 700px;
}

.adminTable__head {
  text-align: left;
  padding: 10px;
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
  background: rgb(var(--bg-app-rgb) / 0.58);
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
  padding: 10px;
  vertical-align: middle;
  font-size: var(--font-size-sm);
}

.adminTable__state {
  padding: 14px;
  text-align: center;
  color: var(--color-text-secondary);
}

.adminTable__row--skeleton:hover {
  background: transparent;
  transform: none;
}

.adminTable__skeleton {
  display: block;
  width: 100%;
  height: 0.72rem;
}

.adminTable__skeleton:nth-child(2n) {
  width: 76%;
}

.is-right {
  text-align: right;
}

@media (max-width: 900px) {
  .adminTable {
    min-width: 640px;
  }

  .adminTable__head,
  .adminTable__cell {
    padding: 9px;
  }
}
</style>
