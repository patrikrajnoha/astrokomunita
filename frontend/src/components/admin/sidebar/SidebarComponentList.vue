<template>
  <section class="panel">
    <div class="panelHead">
      <h3>Komponenty</h3>
      <span class="count">{{ items.length }}</span>
    </div>

    <input
      :value="modelValue"
      class="searchInput"
      type="text"
      placeholder="Hladaj podla nazvu"
      @input="$emit('update:modelValue', $event.target.value)"
    />

    <p v-if="loading" class="stateText">Nacitavam komponenty...</p>
    <p v-else-if="errorMessage" class="stateText error">{{ errorMessage }}</p>
    <p v-else-if="items.length === 0" class="stateText empty">
      Zatial nemas ziadne vlastne komponenty. Vytvor prvy widget.
    </p>

    <div v-else class="tableWrap">
      <table class="listTable">
        <thead>
          <tr>
            <th>Nazov</th>
            <th>Typ</th>
            <th>Aktivny</th>
            <th>Aktualizovane</th>
            <th class="actions">Akcie</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in items"
            :key="item.id"
            :class="{ selected: Number(selectedId) === Number(item.id) }"
            @click="$emit('select', item)"
          >
            <td>{{ item.name }}</td>
            <td>{{ getWidgetTypeLabel(item.type) }}</td>
            <td>{{ item.is_active ? 'Ano' : 'Nie' }}</td>
            <td>{{ formatDate(item.updated_at) }}</td>
            <td class="actions">
              <button type="button" class="actionBtn" :disabled="busy" @click.stop="$emit('select', item)">
                Upravit
              </button>
              <button type="button" class="actionBtn" :disabled="busy" @click.stop="$emit('toggle-active', item)">
                {{ item.is_active ? 'Vypnut' : 'Zapnut' }}
              </button>
              <button
                type="button"
                class="actionBtn danger"
                :disabled="busy"
                @click.stop="$emit('delete-item', item)"
              >
                Zmazat
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { getWidgetTypeLabel } from '@/sidebar/customWidgets/types'

defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  items: {
    type: Array,
    default: () => [],
  },
  selectedId: {
    type: [Number, String, null],
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errorMessage: {
    type: String,
    default: '',
  },
  busy: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue', 'select', 'toggle-active', 'delete-item'])

const formatDate = (value) => {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return new Intl.DateTimeFormat('sk-SK', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed)
}
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.65rem;
  min-width: 0;
}

.panelHead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.panelHead h3 {
  margin: 0;
  font-size: 0.95rem;
}

.count {
  font-size: 0.74rem;
  color: var(--color-text-secondary);
}

.searchInput {
  width: 100%;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 0.7rem;
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  padding: 0.45rem 0.56rem;
  font-size: 0.8rem;
}

.stateText {
  margin: 0;
  font-size: 0.8rem;
  color: var(--color-text-secondary);
}

.stateText.error {
  color: var(--color-danger);
}

.tableWrap {
  overflow: auto;
  max-height: 62vh;
}

.listTable {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 0.28rem;
  font-size: 0.78rem;
}

.listTable th {
  color: var(--color-text-secondary);
  font-weight: 600;
  text-align: left;
  padding: 0 0.35rem 0.3rem;
}

.listTable td {
  padding: 0.5rem 0.35rem;
  background: rgb(var(--color-bg-rgb) / 0.22);
}

.listTable tr td:first-child {
  border-top-left-radius: 0.68rem;
  border-bottom-left-radius: 0.68rem;
}

.listTable tr td:last-child {
  border-top-right-radius: 0.68rem;
  border-bottom-right-radius: 0.68rem;
}

.listTable tr {
  cursor: pointer;
}

.listTable tr.selected td {
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.actions {
  white-space: nowrap;
}

.actionBtn {
  border: 0;
  background: transparent;
  color: var(--color-surface);
  font-size: 0.74rem;
  padding: 0.1rem 0.26rem;
}

.actionBtn.danger {
  color: var(--color-danger);
}

.actionBtn:disabled {
  opacity: 0.5;
}
</style>
