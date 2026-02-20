<template>
  <div
    ref="rootRef"
    class="sticky top-[var(--feed-tabs-offset)] z-30 border-b border-white/10 bg-slate-950/70 backdrop-blur-md transition-shadow duration-200 md:top-0"
    :class="isScrolled ? 'shadow-[0_10px_20px_rgba(2,6,23,0.32)]' : 'shadow-none'"
    :style="{ '--feed-tabs-offset': 'var(--app-header-h, 56px)' }"
    data-testid="feed-tabs-sticky"
  >
    <div
      ref="tabListRef"
      class="relative flex w-full"
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
        class="relative flex h-12 flex-1 select-none items-center justify-center text-sm transition-colors hover:bg-white/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40 focus-visible:ring-inset"
        :class="modelValue === tab.id ? 'font-semibold text-white' : 'text-white/60'"
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
        class="absolute bottom-0 h-[3px] rounded-full bg-sky-500 shadow-[0_0_12px_rgba(56,189,248,0.25)] transition-[transform,width,opacity] duration-300 ease-out"
        :style="inkBarStyle"
        data-testid="feed-tabs-ink-bar"
        aria-hidden="true"
      ></div>
    </div>

    <div
      class="pointer-events-none absolute -bottom-4 left-0 right-0 h-4 bg-gradient-to-b from-slate-950/0 to-slate-950/40 transition-opacity duration-200"
      :class="isScrolled ? 'opacity-100' : 'opacity-0'"
      data-testid="feed-tabs-fade"
      aria-hidden="true"
    ></div>
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
