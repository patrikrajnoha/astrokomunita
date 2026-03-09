<template>
  <section class="panel" :class="{ ultraCompact: props.ultraCompact }">
    <div class="panelHead">
      <h3>Komponenty</h3>
      <span class="count">{{ items.length }}</span>
    </div>

    <input
      :value="modelValue"
      class="searchInput"
      type="text"
      placeholder="Hladaj podla nazvu"
      @input="emit('update:modelValue', $event.target.value)"
    />

    <p v-if="loading" class="stateText">Nacitavam komponenty...</p>
    <p v-else-if="errorMessage" class="stateText error">{{ errorMessage }}</p>
    <div v-else-if="items.length === 0" class="emptyState">
      <p>Zatial nemas ziadne vlastne komponenty.</p>
      <button type="button" class="createBtn" :disabled="busy" @click="$emit('create-item')">Vytvorit prvy komponent</button>
    </div>

    <div v-else class="tableWrap">
      <table class="listTable">
        <thead>
          <tr>
            <th>Nazov</th>
            <th>Typ</th>
            <th>Aktivny</th>
            <th class="actions">Akcie</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in items"
            :key="item.id"
            :class="{ selected: Number(selectedId) === Number(item.id) }"
            @click="selectItem(item)"
          >
            <td class="nameCell">{{ item.name }}</td>
            <td class="typeCell">{{ getWidgetTypeLabel(item.type) }}</td>
            <td>{{ item.is_active ? 'Ano' : 'Nie' }}</td>
            <td class="actions" @click.stop>
              <div class="menuWrap">
                <button
                  type="button"
                  class="menuTrigger"
                  :disabled="busy"
                  :aria-expanded="isMenuOpen(item) ? 'true' : 'false'"
                  @click.stop="toggleMenu(item.id)"
                >
                  ...
                </button>

                <div v-if="isMenuOpen(item)" class="menuDropdown">
                  <button type="button" class="menuItem" :disabled="busy" @click.stop="runAction('select', item)">
                    Upravit
                  </button>
                  <button type="button" class="menuItem" :disabled="busy" @click.stop="runAction('toggle-active', item)">
                    {{ item.is_active ? 'Vypnut' : 'Zapnut' }}
                  </button>
                  <button type="button" class="menuItem danger" :disabled="busy" @click.stop="runAction('delete-item', item)">
                    Zmazat
                  </button>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getWidgetTypeLabel } from '@/sidebar/customWidgets/types'

const props = defineProps({
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
  ultraCompact: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'select', 'toggle-active', 'delete-item', 'create-item'])

const openMenuId = ref('')

const keyFor = (id) => String(id ?? '')

const closeMenu = () => {
  openMenuId.value = ''
}

const isMenuOpen = (item) => openMenuId.value === keyFor(item?.id)

const toggleMenu = (id) => {
  if (props.busy) return
  const next = keyFor(id)
  openMenuId.value = openMenuId.value === next ? '' : next
}

const runAction = (action, item) => {
  emit(action, item)
  closeMenu()
}

const selectItem = (item) => {
  closeMenu()
  emit('select', item)
}

const onDocumentClick = () => {
  closeMenu()
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})

watch(
  () => props.busy,
  (busy) => {
    if (busy) closeMenu()
  },
)
</script>

<style scoped>
.panel {
  display: grid;
  gap: 0.65rem;
  min-width: 0;
}

.panel.ultraCompact {
  gap: 0.5rem;
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

.emptyState {
  border-radius: 0.72rem;
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.2);
  padding: 0.7rem;
  display: grid;
  gap: 0.35rem;
  justify-items: start;
}

.emptyState p {
  margin: 0;
  font-size: 0.78rem;
  color: var(--color-text-secondary);
}

.createBtn {
  min-height: 1.9rem;
  border-radius: 0.64rem;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
  font-size: 0.74rem;
  font-weight: 700;
  padding: 0.34rem 0.6rem;
}

.tableWrap {
  overflow: auto;
  max-height: 56vh;
}

.listTable {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 0.28rem;
  font-size: 0.78rem;
  table-layout: fixed;
}

.listTable th {
  color: var(--color-text-secondary);
  font-weight: 600;
  text-align: left;
  padding: 0 0.35rem 0.3rem;
}

.listTable th.actions {
  text-align: right;
  width: 3.3rem;
}

.listTable td {
  padding: 0.5rem 0.35rem;
  background: rgb(var(--color-bg-rgb) / 0.22);
  vertical-align: middle;
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

.nameCell,
.typeCell {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.actions {
  text-align: right;
  white-space: nowrap;
}

.menuWrap {
  position: relative;
  display: inline-block;
}

.menuTrigger {
  width: 2rem;
  min-height: 1.7rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
  border-radius: 0.56rem;
  background: rgb(var(--color-bg-rgb) / 0.28);
  color: var(--color-surface);
  font-size: 0.82rem;
  line-height: 1;
  cursor: pointer;
}

.menuTrigger:disabled {
  opacity: 0.5;
}

.menuDropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 0.3rem);
  width: 8.4rem;
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.96);
  box-shadow: 0 8px 22px rgb(0 0 0 / 0.28);
  padding: 0.2rem;
  display: grid;
  z-index: 20;
}

.menuItem {
  border: 0;
  background: transparent;
  text-align: left;
  padding: 0.34rem 0.42rem;
  font-size: 0.74rem;
  border-radius: 0.5rem;
  color: var(--color-surface);
}

.menuItem:hover {
  background: rgb(var(--color-primary-rgb) / 0.2);
}

.menuItem.danger {
  color: var(--color-danger);
}

.panel.ultraCompact .searchInput {
  padding: 0.4rem 0.5rem;
  font-size: 0.76rem;
}

.panel.ultraCompact .listTable {
  font-size: 0.74rem;
}

.panel.ultraCompact .listTable td {
  padding: 0.42rem 0.3rem;
}

.panel.ultraCompact .menuTrigger {
  min-height: 1.56rem;
  width: 1.86rem;
}
</style>
