<template>
  <div
    ref="rootRef"
    class="feedSwitcher"
    :class="{ 'is-scrolled': isScrolled }"
    :style="{ '--feed-tabs-offset': 'var(--app-header-h, 56px)' }"
    data-testid="feed-tabs-sticky"
  >
    <div
      ref="tabListRef"
      class="feedSwitcher__list"
      role="tablist"
      aria-label="Feed sekcie"
      data-testid="feed-tabs-list"
    >
      <button
        v-for="(tab, index) in tabs"
        :id="tab.tabId"
        :key="tab.id"
        :ref="(el) => setTabRef(el, index)"
        role="tab"
        type="button"
        class="feedSwitcher__tab"
        :class="{ active: modelValue === tab.id }"
        :tabindex="modelValue === tab.id ? 0 : -1"
        :aria-controls="tab.panelId"
        :aria-selected="modelValue === tab.id ? 'true' : 'false'"
        @click="activateTab(index)"
        @focus="focusedIndex = index"
        @keydown="onTabKeydown($event, index)"
      >
        <span :ref="(el) => setLabelRef(el, index)">
          {{ tab.label }}
        </span>
      </button>

      <div
        class="feedSwitcher__ink"
        :style="inkBarStyle"
        data-testid="feed-tabs-ink-bar"
        aria-hidden="true"
      ></div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    required: true,
  },
  tabs: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const rootRef = ref(null)
const tabListRef = ref(null)
const tabButtonRefs = ref([])
const tabLabelRefs = ref([])
const focusedIndex = ref(0)
const inkBarX = ref(0)
const inkBarWidth = ref(0)
const isScrolled = ref(false)

let resizeObserver = null
let scrollTarget = null

const activeIndex = computed(() => {
  const index = props.tabs.findIndex((tab) => tab.id === props.modelValue)
  return index >= 0 ? index : 0
})

const inkBarStyle = computed(() => ({
  width: `${inkBarWidth.value}px`,
  transform: `translateX(${inkBarX.value}px)`,
  opacity: inkBarWidth.value > 0 ? 1 : 0,
}))

const setTabRef = (element, index) => {
  if (!element) return
  tabButtonRefs.value[index] = element
}

const setLabelRef = (element, index) => {
  if (!element) return
  tabLabelRefs.value[index] = element
}

const clamp = (value, min, max) => Math.min(max, Math.max(min, value))

const updateInkBar = () => {
  const activeButton = tabButtonRefs.value[activeIndex.value]
  if (!activeButton) {
    inkBarX.value = 0
    inkBarWidth.value = 0
    return
  }

  const tabWidth = activeButton.offsetWidth
  const tabLeft = activeButton.offsetLeft
  const labelWidth = tabLabelRefs.value[activeIndex.value]?.offsetWidth || tabWidth * 0.5

  if (!tabWidth) {
    inkBarX.value = 0
    inkBarWidth.value = 0
    return
  }

  const minWidth = tabWidth * 0.46
  const maxWidth = tabWidth * 0.62
  const nextWidth = clamp(labelWidth + 24, minWidth, maxWidth)

  inkBarWidth.value = nextWidth
  inkBarX.value = tabLeft + (tabWidth - nextWidth) / 2
}

const findScrollTarget = () => {
  if (typeof window === 'undefined') return null

  let node = rootRef.value?.parentElement || null
  while (node && node !== document.body) {
    const style = window.getComputedStyle(node)
    const isScrollable = /(auto|scroll|overlay)/.test(style.overflowY)
    if (isScrollable && node.scrollHeight > node.clientHeight + 1) {
      return node
    }
    node = node.parentElement
  }

  return window
}

const resolveScrollTop = () => {
  if (!scrollTarget || scrollTarget === window) {
    return window.scrollY || document.documentElement.scrollTop || 0
  }

  return scrollTarget.scrollTop || 0
}

const updateScrolledState = () => {
  isScrolled.value = resolveScrollTop() > 10
}

const onScroll = () => {
  updateScrolledState()
}

const bindScrollListener = () => {
  const nextTarget = findScrollTarget()
  if (!nextTarget || nextTarget === scrollTarget) {
    updateScrolledState()
    return
  }

  if (scrollTarget) {
    scrollTarget.removeEventListener('scroll', onScroll)
  }

  scrollTarget = nextTarget
  scrollTarget.addEventListener('scroll', onScroll, { passive: true })
  updateScrolledState()
}

const focusTabByIndex = (index) => {
  if (!props.tabs.length) return

  const normalized = (index + props.tabs.length) % props.tabs.length
  const button = tabButtonRefs.value[normalized]
  if (!button) return

  focusedIndex.value = normalized
  button.focus()
}

const activateTab = (index) => {
  const selected = props.tabs[index]
  if (!selected) return
  emit('update:modelValue', selected.id)
}

const onTabKeydown = (event, index) => {
  if (event.key === 'ArrowRight') {
    event.preventDefault()
    focusTabByIndex(index + 1)
    return
  }

  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    focusTabByIndex(index - 1)
    return
  }

  if (event.key === 'Home') {
    event.preventDefault()
    focusTabByIndex(0)
    return
  }

  if (event.key === 'End') {
    event.preventDefault()
    focusTabByIndex(props.tabs.length - 1)
    return
  }

  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    activateTab(index)
  }
}

const updateLayout = () => {
  bindScrollListener()
  updateInkBar()
}

watch(
  () => props.modelValue,
  async () => {
    focusedIndex.value = activeIndex.value
    await nextTick()
    updateInkBar()
  },
  { immediate: true },
)

watch(
  () => props.tabs.length,
  async () => {
    tabButtonRefs.value = tabButtonRefs.value.slice(0, props.tabs.length)
    tabLabelRefs.value = tabLabelRefs.value.slice(0, props.tabs.length)
    focusedIndex.value = activeIndex.value
    await nextTick()
    updateLayout()
  },
)

const handleWindowResize = () => {
  updateLayout()
}

onMounted(async () => {
  await nextTick()
  focusedIndex.value = activeIndex.value
  updateLayout()

  if (typeof ResizeObserver !== 'undefined' && tabListRef.value) {
    resizeObserver = new ResizeObserver(() => {
      updateInkBar()
    })

    resizeObserver.observe(tabListRef.value)
    tabButtonRefs.value.forEach((button) => {
      if (button) {
        resizeObserver.observe(button)
      }
    })
  }

  window.addEventListener('resize', handleWindowResize)
})

onBeforeUnmount(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }

  if (scrollTarget) {
    scrollTarget.removeEventListener('scroll', onScroll)
    scrollTarget = null
  }

  window.removeEventListener('resize', handleWindowResize)
})
</script>

<style scoped>
.feedSwitcher {
  position: sticky;
  top: var(--feed-tabs-offset);
  z-index: 20;
  background: rgb(var(--bg-app-rgb) / 0.9);
  border-bottom: 1px solid var(--divider-color);
  backdrop-filter: blur(8px);
  transition: background-color var(--motion-base), border-color var(--motion-base);
}

.feedSwitcher.is-scrolled {
  background: rgb(var(--bg-app-rgb) / 0.96);
  border-bottom-color: var(--divider-strong);
}

.feedSwitcher__list {
  position: relative;
  display: flex;
  width: 100%;
}

.feedSwitcher__tab {
  position: relative;
  display: flex;
  min-height: var(--control-height-lg);
  flex: 1;
  align-items: center;
  justify-content: center;
  padding: 0 var(--space-3);
  border: 0;
  background: transparent;
  color: rgb(var(--text-secondary-rgb) / 0.86);
  font-size: var(--font-size-md);
  font-weight: 600;
  cursor: pointer;
  transition: color var(--motion-fast), background-color var(--motion-fast);
}

.feedSwitcher__tab:hover {
  background: var(--interactive-hover);
  color: var(--text-primary);
}

.feedSwitcher__tab.active {
  color: var(--text-primary);
  font-weight: 700;
}

.feedSwitcher__tab:focus-visible {
  outline: none;
  box-shadow: inset 0 0 0 2px rgb(var(--primary-rgb) / 0.38);
}

.feedSwitcher__ink {
  position: absolute;
  bottom: 0;
  height: 2px;
  border-radius: var(--radius-pill);
  background: var(--accent-primary);
  transition: transform var(--motion-base), width var(--motion-base), opacity var(--motion-base);
}

@media (min-width: 768px) {
  .feedSwitcher {
    top: 0;
  }
}
</style>
