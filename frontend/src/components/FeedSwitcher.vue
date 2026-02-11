<template>
  <div class="switcherRoot">
    <button
      v-if="showLeftArrow"
      type="button"
      class="stripArrow stripArrowLeft"
      data-testid="strip-arrow-left"
      aria-label="Posunut taby dolava"
      @click="scrollStrip(-1)"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
      </svg>
    </button>

    <div ref="stripRef" class="tabStrip" data-testid="tab-strip" @scroll.passive="handleStripScroll">
      <div ref="tabListRef" class="tabList" role="tablist" aria-label="Feed sekcie">
        <button
          v-for="(tab, index) in tabs"
          :id="tab.tabId"
          :key="tab.id"
          :ref="(el) => setTabRef(el, index)"
          role="tab"
          type="button"
          class="tabButton"
          :class="{ tabButtonActive: modelValue === tab.id }"
          :tabindex="modelValue === tab.id ? 0 : -1"
          :aria-controls="tab.panelId"
          :aria-selected="modelValue === tab.id ? 'true' : 'false'"
          @click="activateTab(index)"
          @focus="focusedIndex = index"
          @keydown="onTabKeydown($event, index)"
        >
          {{ tab.label }}
        </button>

        <span
          class="tabIndicator"
          :style="indicatorStyle"
          aria-hidden="true"
        ></span>
      </div>
    </div>

    <button
      v-if="showRightArrow"
      type="button"
      class="stripArrow stripArrowRight"
      data-testid="strip-arrow-right"
      aria-label="Posunut taby doprava"
      @click="scrollStrip(1)"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
      </svg>
    </button>
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

const stripRef = ref(null)
const tabListRef = ref(null)
const tabButtonRefs = ref([])
const focusedIndex = ref(0)
const showLeftArrow = ref(false)
const showRightArrow = ref(false)
const indicatorLeft = ref(0)
const indicatorWidth = ref(0)

let resizeObserver = null

const activeIndex = computed(() => {
  const index = props.tabs.findIndex((tab) => tab.id === props.modelValue)
  return index >= 0 ? index : 0
})

const indicatorStyle = computed(() => ({
  width: `${indicatorWidth.value}px`,
  transform: `translateX(${indicatorLeft.value}px)`,
  opacity: indicatorWidth.value > 0 ? 1 : 0,
}))

const setTabRef = (element, index) => {
  if (!element) return
  tabButtonRefs.value[index] = element
}

const updateOverflowState = () => {
  const strip = stripRef.value
  if (!strip) return

  const maxScrollLeft = Math.max(0, strip.scrollWidth - strip.clientWidth)
  showLeftArrow.value = strip.scrollLeft > 2
  showRightArrow.value = strip.scrollLeft < maxScrollLeft - 2
}

const updateIndicator = () => {
  const strip = stripRef.value
  const activeButton = tabButtonRefs.value[activeIndex.value]

  if (!strip || !activeButton) {
    indicatorLeft.value = 0
    indicatorWidth.value = 0
    return
  }

  indicatorLeft.value = activeButton.offsetLeft - strip.scrollLeft
  indicatorWidth.value = activeButton.offsetWidth
}

const updateLayout = () => {
  updateOverflowState()
  updateIndicator()
}

const focusTabByIndex = (index) => {
  const normalized = (index + props.tabs.length) % props.tabs.length
  const button = tabButtonRefs.value[normalized]
  if (!button) return

  focusedIndex.value = normalized
  button.focus()

  const strip = stripRef.value
  if (strip) {
    const targetLeft = Math.max(0, button.offsetLeft - strip.clientWidth / 2 + button.offsetWidth / 2)
    strip.scrollTo({ left: targetLeft, behavior: 'smooth' })
  }
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

const handleStripScroll = () => {
  updateOverflowState()
  updateIndicator()
}

const scrollStrip = (direction) => {
  const strip = stripRef.value
  if (!strip) return

  const amount = Math.max(120, Math.round(strip.clientWidth * 0.7))
  strip.scrollBy({
    left: direction * amount,
    behavior: 'smooth',
  })
}

watch(
  () => props.modelValue,
  async () => {
    focusedIndex.value = activeIndex.value
    await nextTick()
    updateIndicator()
  },
  { immediate: true },
)

watch(
  () => props.tabs.length,
  async () => {
    await nextTick()
    focusedIndex.value = activeIndex.value
    updateLayout()
  },
)

onMounted(async () => {
  await nextTick()
  focusedIndex.value = activeIndex.value
  updateLayout()

  if (typeof ResizeObserver !== 'undefined' && stripRef.value) {
    resizeObserver = new ResizeObserver(() => {
      updateLayout()
    })

    resizeObserver.observe(stripRef.value)
    if (tabListRef.value) {
      resizeObserver.observe(tabListRef.value)
    }
  }

  window.addEventListener('resize', updateLayout)
})

onBeforeUnmount(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }

  window.removeEventListener('resize', updateLayout)
})
</script>

<style scoped>
.switcherRoot {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  width: 100%;
}

.tabStrip {
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.tabStrip::-webkit-scrollbar {
  display: none;
}

.tabList {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-width: 100%;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
}

.tabButton {
  position: relative;
  appearance: none;
  border: 0;
  background: transparent;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-size: 0.92rem;
  font-weight: 600;
  white-space: nowrap;
  min-height: 2.85rem;
  padding: 0.75rem 1rem 0.8rem;
  cursor: pointer;
  transition: color 140ms ease;
}

.tabButton:hover {
  color: var(--color-surface);
}

.tabButton:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
}

.tabButtonActive {
  color: var(--color-surface);
}

.tabIndicator {
  position: absolute;
  left: 0;
  bottom: -1px;
  height: 3px;
  border-radius: 99px;
  background: var(--color-primary);
  transition: transform 180ms ease, width 180ms ease, opacity 180ms ease;
  will-change: transform, width;
}

.stripArrow {
  flex-shrink: 0;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
  background: rgb(var(--color-bg-rgb) / 0.82);
  color: var(--color-surface);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 120ms ease, border-color 120ms ease;
}

.stripArrow:hover {
  background: rgb(var(--color-bg-rgb) / 0.95);
  border-color: rgb(var(--color-text-secondary-rgb) / 0.54);
}

.stripArrow:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.stripArrow svg {
  width: 0.95rem;
  height: 0.95rem;
}

@media (max-width: 768px) {
  .stripArrow {
    display: none;
  }

  .tabButton {
    padding-left: 0.9rem;
    padding-right: 0.9rem;
  }
}
</style>
