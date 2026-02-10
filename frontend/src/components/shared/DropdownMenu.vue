<template>
  <div ref="rootRef" class="dropdownRoot">
    <button
      ref="triggerRef"
      type="button"
      class="dropdownTrigger"
      :aria-label="label"
      aria-haspopup="menu"
      :aria-expanded="open ? 'true' : 'false'"
      @click.stop="toggle"
      @keydown="onTriggerKeydown"
    >
      <slot name="trigger">
        <span aria-hidden="true">...</span>
      </slot>
    </button>

    <div
      v-if="open"
      ref="menuRef"
      class="dropdownMenu"
      role="menu"
      :aria-label="menuLabel"
      @keydown="onMenuKeydown"
    >
      <button
        v-for="(item, index) in items"
        :key="item.key"
        type="button"
        role="menuitem"
        class="dropdownItem"
        :class="{ 'dropdownItem--danger': item.danger }"
        @click.stop="onSelect(item)"
      >
        {{ item.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  label: { type: String, default: 'More actions' },
  menuLabel: { type: String, default: 'Post actions' },
})

const emit = defineEmits(['select', 'open', 'close'])

const open = ref(false)
const rootRef = ref(null)
const triggerRef = ref(null)
const menuRef = ref(null)

const focusItemByIndex = (index) => {
  const menu = menuRef.value
  if (!menu) return
  const items = menu.querySelectorAll('[role="menuitem"]')
  const targetIndex = Math.max(0, Math.min(index, items.length - 1))
  items[targetIndex]?.focus()
}

const focusFirstItem = () => focusItemByIndex(0)

const close = (restoreFocus = true) => {
  open.value = false
  emit('close')
  if (restoreFocus) {
    triggerRef.value?.focus()
  }
}

const openMenu = async () => {
  if (!props.items?.length) return
  open.value = true
  emit('open')
  await nextTick()
  focusFirstItem()
}

const toggle = async () => {
  if (open.value) {
    close(false)
    return
  }
  await openMenu()
}

const onSelect = (item) => {
  emit('select', item)
  close(false)
}

const onTriggerKeydown = async (event) => {
  if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    await openMenu()
  }
}

const onMenuKeydown = (event) => {
  const menu = menuRef.value
  if (!menu) return

  const items = [...menu.querySelectorAll('[role="menuitem"]')]
  const currentIndex = items.indexOf(document.activeElement)

  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }

  if (event.key === 'Home') {
    event.preventDefault()
    focusItemByIndex(0)
    return
  }

  if (event.key === 'End') {
    event.preventDefault()
    focusItemByIndex(items.length - 1)
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    focusItemByIndex(currentIndex + 1)
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    focusItemByIndex(currentIndex <= 0 ? items.length - 1 : currentIndex - 1)
  }
}

const handleClickOutside = (event) => {
  const root = rootRef.value
  if (!open.value || !root || !(event.target instanceof Node)) return
  if (!root.contains(event.target)) {
    close(false)
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
})

watch(
  () => props.items,
  (items) => {
    if (!items?.length && open.value) {
      close(false)
    }
  }
)

defineExpose({ close })
</script>

<style scoped>
.dropdownRoot {
  position: relative;
}

.dropdownTrigger {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 8px;
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: background-color 150ms ease, color 150ms ease;
}

.dropdownTrigger:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.1);
  color: var(--color-surface);
}

.dropdownTrigger:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.dropdownMenu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 80;
  min-width: 180px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.97);
  backdrop-filter: blur(8px);
  padding: 6px;
  display: grid;
  gap: 4px;
  box-shadow: 0 18px 32px rgb(0 0 0 / 0.28);
}

.dropdownItem {
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-surface);
  text-align: left;
  padding: 0.5rem 0.65rem;
  cursor: pointer;
}

.dropdownItem:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.12);
}

.dropdownItem:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.dropdownItem--danger {
  color: var(--color-danger);
}

.dropdownItem--danger:hover {
  background: rgb(var(--color-danger-rgb) / 0.12);
}
</style>
