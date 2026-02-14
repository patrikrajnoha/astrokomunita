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
  background: transparent;
  border: none;
  padding: 6px;
  border-radius: 999px;
  color: #71767b;
  cursor: pointer;
  transition: background-color 150ms ease, color 150ms ease;
}

.dropdownTrigger:hover {
  background: rgb(29 155 240 / 0.12);
  color: #1d9bf0;
}

.dropdownTrigger:focus-visible {
  outline: 2px solid #1d9bf0;
  outline-offset: 2px;
}

.dropdownMenu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 80;
  min-width: 170px;
  border: 1px solid #2f3336;
  border-radius: 12px;
  background: #16181c;
  padding: 4px;
  display: grid;
  gap: 2px;
  animation: dropdownIn 140ms ease-out;
}

.dropdownItem {
  border: none;
  border-radius: 10px;
  background: transparent;
  color: #e7e9ea;
  text-align: left;
  padding: 0.45rem 0.6rem;
  cursor: pointer;
  font-size: 13px;
}

.dropdownItem:hover {
  background: #1a1f24;
}

.dropdownItem:focus-visible {
  outline: 2px solid #1d9bf0;
  outline-offset: 1px;
}

.dropdownItem--danger {
  color: #f4212e;
}

.dropdownItem--danger:hover {
  background: rgb(244 33 46 / 0.15);
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
