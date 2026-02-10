<script setup>
import LoadingIndicator from '@/components/shared/LoadingIndicator.vue'

defineProps({
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
    default: 'No results',
  },
  emptyDescription: {
    type: String,
    default: 'There are no rows to display.',
  },
  canClearFilters: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['clear-filters'])

function textValue(value) {
  if (value === null || value === undefined || value === '') return '-'
  return value
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
            <LoadingIndicator :loading="true" text="Loading..." align="center" />
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
              Clear filters
            </button>
          </td>
        </tr>

        <tr v-for="row in rows" v-else :key="row[rowKey]" class="adminTable__row">
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
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 12px;
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
  font-size: 12px;
  opacity: 0.85;
  background: rgb(var(--color-surface-rgb) / 0.05);
}

.adminTable__row {
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.08);
}

.adminTable__cell {
  padding: 12px;
  vertical-align: middle;
}

.adminTable__state {
  padding: 18px;
  text-align: center;
}

.adminTable__emptyTitle {
  font-weight: 600;
}

.adminTable__emptyText {
  margin-top: 6px;
  opacity: 0.8;
}

.adminTable__clearBtn {
  margin-top: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  background: transparent;
  color: inherit;
  border-radius: 10px;
  padding: 6px 10px;
  cursor: pointer;
}

.adminTable__clearBtn:hover {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.is-right {
  text-align: right;
}
</style>
