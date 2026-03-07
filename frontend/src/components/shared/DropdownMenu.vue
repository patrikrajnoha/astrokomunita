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
        v-for="item in items"
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
  label: { type: String, default: 'Dalsie akcie' },
  menuLabel: { type: String, default: 'Akcie prispevku' },
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
  background: transparent;
  border: 1px solid transparent;
  min-width: var(--control-height-sm);
  min-height: var(--control-height-sm);
  padding: 6px;
  border-radius: var(--radius-pill);
  color: var(--text-muted);
  cursor: pointer;
  transition: background-color var(--motion-fast), color var(--motion-fast), border-color var(--motion-fast);
}

.dropdownTrigger:hover {
  border-color: var(--border-subtle);
  background: var(--interactive-hover);
  color: var(--text-primary);
}

.dropdownTrigger:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.dropdownMenu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 80;
  min-width: 180px;
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  background: rgb(var(--bg-surface-rgb) / 0.98);
  padding: 6px;
  display: grid;
  gap: 4px;
  box-shadow: var(--elevation-2);
  animation: dropdownIn 140ms ease-out;
}

.dropdownItem {
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--text-primary);
  text-align: left;
  padding: 0.48rem 0.62rem;
  cursor: pointer;
  font-size: var(--font-size-sm);
}

.dropdownItem:hover {
  border-color: var(--border-subtle);
  background: var(--interactive-hover);
}

.dropdownItem:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.dropdownItem--danger {
  color: var(--danger);
}

.dropdownItem--danger:hover {
  border-color: rgb(var(--danger-rgb) / 0.35);
  background: rgb(var(--danger-rgb) / 0.14);
}

@keyframes dropdownIn {
  from {
    opacity: 0;
    transform: translateY(-4px) scale(0.98);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>
