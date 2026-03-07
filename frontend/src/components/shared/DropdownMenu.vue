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

    <teleport to="body">
      <div
        v-if="open"
        ref="menuRef"
        class="dropdownMenu"
        role="menu"
        :aria-label="menuLabel"
        :style="menuStyle"
        @click.stop
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
    </teleport>
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
const menuStyle = ref({})
let repositionRaf = null

const focusItemByIndex = (index) => {
  const menu = menuRef.value
  if (!menu) return
  const items = menu.querySelectorAll('[role="menuitem"]')
  const targetIndex = Math.max(0, Math.min(index, items.length - 1))
  items[targetIndex]?.focus()
}

const focusFirstItem = () => focusItemByIndex(0)

const close = (restoreFocus = true) => {
  removePositionListeners()
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
  positionMenu()
  addPositionListeners()
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
  const menu = menuRef.value
  if (!open.value || !root || !(event.target instanceof Node)) return
  if (root.contains(event.target) || menu?.contains(event.target)) return
  close(false)
}

const positionMenu = () => {
  const trigger = triggerRef.value
  const menu = menuRef.value
  if (!trigger || !menu) return

  const triggerRect = trigger.getBoundingClientRect()
  const menuRect = menu.getBoundingClientRect()
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight
  const viewportPadding = 8
  const gap = 8

  let left = triggerRect.right - menuRect.width
  left = Math.max(viewportPadding, Math.min(left, viewportWidth - menuRect.width - viewportPadding))

  let top = triggerRect.bottom + gap
  let originY = 'top'
  const maxTop = viewportHeight - menuRect.height - viewportPadding

  if (top > maxTop) {
    const flippedTop = triggerRect.top - menuRect.height - gap
    if (flippedTop >= viewportPadding) {
      top = flippedTop
      originY = 'bottom'
    } else {
      top = Math.max(viewportPadding, maxTop)
    }
  }

  menuStyle.value = {
    left: `${Math.round(left)}px`,
    top: `${Math.round(top)}px`,
    '--dropdown-origin-y': originY,
  }
}

const schedulePosition = () => {
  if (repositionRaf !== null) {
    window.cancelAnimationFrame(repositionRaf)
  }
  repositionRaf = window.requestAnimationFrame(() => {
    repositionRaf = null
    positionMenu()
  })
}

const handleViewportChange = () => {
  if (!open.value) return
  schedulePosition()
}

const addPositionListeners = () => {
  window.addEventListener('resize', handleViewportChange, { passive: true })
  window.addEventListener('scroll', handleViewportChange, true)
}

const removePositionListeners = () => {
  window.removeEventListener('resize', handleViewportChange)
  window.removeEventListener('scroll', handleViewportChange, true)
  if (repositionRaf !== null) {
    window.cancelAnimationFrame(repositionRaf)
    repositionRaf = null
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  document.addEventListener('touchstart', handleClickOutside, { passive: true })
})

onBeforeUnmount(() => {
  removePositionListeners()
  document.removeEventListener('mousedown', handleClickOutside)
  document.removeEventListener('touchstart', handleClickOutside)
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
  display: inline-flex;
  align-items: center;
}

.dropdownTrigger {
  background: transparent;
  border: 1px solid var(--color-border);
  min-width: var(--control-height-sm);
  min-height: var(--control-height-sm);
  padding: 6px;
  border-radius: var(--radius-pill);
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: background-color var(--motion-fast), color var(--motion-fast), border-color var(--motion-fast);
}

.dropdownTrigger:hover {
  border-color: var(--color-border-strong);
  background: var(--interactive-hover);
  color: var(--color-text-primary);
}

.dropdownTrigger:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.dropdownMenu {
  position: fixed;
  z-index: 1200;
  min-width: 180px;
  max-width: min(280px, calc(100vw - 16px));
  max-height: calc(100vh - 16px);
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: none;
  -ms-overflow-style: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-card);
  padding: 6px;
  display: grid;
  gap: 4px;
  box-shadow: var(--shadow-medium);
  transform-origin: right var(--dropdown-origin-y, top);
  animation: dropdownIn 140ms ease-out;
}

.dropdownMenu::-webkit-scrollbar {
  width: 0;
  height: 0;
}

.dropdownItem {
  border: 1px solid transparent;
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--color-text-primary);
  text-align: left;
  padding: 8px 14px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
}

.dropdownItem:hover {
  border-color: var(--color-border);
  background: var(--interactive-hover);
}

.dropdownItem:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.dropdownItem--danger {
  color: var(--color-danger);
}

.dropdownItem--danger:hover {
  border-color: rgb(var(--color-danger-rgb) / 0.35);
  background: rgb(var(--color-danger-rgb) / 0.14);
}

@keyframes dropdownIn {
  from {
    opacity: 0;
    transform: scale(0.98);
  }

  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
