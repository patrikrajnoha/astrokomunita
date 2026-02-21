<template>
  <div class="tourRoot" aria-hidden="false">
    <div class="tourOverlay" @click.stop @mousedown.stop @touchstart.stop></div>

    <section
      ref="tooltipRef"
      class="tourTooltip"
      :style="computedTooltipStyle"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      tabindex="-1"
      @click.stop
    >
      <p class="tourProgress">Krok {{ currentStepIndex + 1 }} z {{ steps.length }}</p>
      <h2 :id="titleId" class="tourTitle">{{ currentStep.title }}</h2>
      <p class="tourBody">{{ currentStep.body }}</p>

      <div class="tourActions">
        <button type="button" class="tourBtn ghost" @click="skipTour">Preskocit</button>

        <div class="tourActionsRight">
          <button
            v-if="currentStepIndex > 0"
            type="button"
            class="tourBtn ghost"
            @click="goPrev"
          >
            Spat
          </button>
          <button
            v-if="!isLastStep"
            type="button"
            class="tourBtn primary"
            @click="goNext"
          >
            Dalej
          </button>
          <button
            v-else
            type="button"
            class="tourBtn primary"
            @click="finishTour"
          >
            Hotovo
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOnboardingTourStore } from '@/stores/onboardingTour'

const tourStore = useOnboardingTourStore()
const router = useRouter()
const route = useRoute()

// Add a new step by defining a unique selector + route and adding data-tour="<id>" to target DOM.
const steps = [
  {
    id: 'feed',
    selector: '[data-tour="feed"]',
    title: 'Toto je feed',
    body: 'Tu vidis najnovsie prispevky a udalosti.',
    route: { name: 'home' },
  },
  {
    id: 'calendar',
    selector: '[data-tour="calendar"]',
    title: 'Toto je kalendar',
    body: 'Tu najdes udalosti v kalendarovom zobrazeni.',
    route: { name: 'calendar' },
  },
  {
    id: 'conditions',
    selector: '[data-tour="conditions"]',
    title: 'Toto su astronomicke podmienky',
    body: 'Tu vidis oblacnost, seeing a dalsie podmienky.',
    route: { name: 'home' },
  },
]

const TOOLTIP_MARGIN = 12
const VIEWPORT_MARGIN = 12
const MAX_TOOLTIP_WIDTH = 360
const RESOLVE_ATTEMPTS = 10
const RESOLVE_DELAY_MS = 150
const HIGHLIGHT_CLASS = 'onboarding-tour-target'
const FALLBACK_TOOLTIP_HEIGHT = 180

const currentStepIndex = ref(0)
const tooltipRef = ref(null)
const targetElement = ref(null)
const targetRect = ref(null)
const tooltipStyle = ref({
  top: `${VIEWPORT_MARGIN}px`,
  left: `${VIEWPORT_MARGIN}px`,
  maxWidth: `${MAX_TOOLTIP_WIDTH}px`,
  transform: 'none',
})

const titleId = 'onboarding-tour-title'
let resolveSequence = 0
let rafHandle = 0

const currentStep = computed(() => steps[currentStepIndex.value] || steps[0])
const isLastStep = computed(() => currentStepIndex.value >= steps.length - 1)
const computedTooltipStyle = computed(() => {
  if (targetRect.value) {
    return tooltipStyle.value
  }

  return {
    top: '50%',
    left: '50%',
    maxWidth: `min(${MAX_TOOLTIP_WIDTH}px, calc(100vw - ${VIEWPORT_MARGIN * 2}px))`,
    transform: 'translate(-50%, -50%)',
  }
})

const clamp = (value, min, max) => {
  if (max < min) return min
  return Math.min(max, Math.max(min, value))
}

const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms))

const isElementVisible = (element) => {
  if (!(element instanceof HTMLElement)) return false
  const rect = element.getBoundingClientRect()
  if (rect.width <= 0 || rect.height <= 0) return false

  const style = window.getComputedStyle(element)
  return style.display !== 'none' && style.visibility !== 'hidden'
}

const findVisibleElement = (selector) => {
  const matches = Array.from(document.querySelectorAll(selector))
  return matches.find((element) => isElementVisible(element)) || null
}

const clearTargetHighlight = () => {
  if (!targetElement.value) return
  targetElement.value.classList.remove(HIGHLIGHT_CLASS)
  targetElement.value = null
}

const updateTooltipPosition = () => {
  if (!targetRect.value) return

  const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0
  const availableWidth = Math.max(120, viewportWidth - VIEWPORT_MARGIN * 2)
  const maxWidth = Math.min(MAX_TOOLTIP_WIDTH, availableWidth)
  const tooltipWidth = tooltipRef.value?.offsetWidth
    ? Math.min(tooltipRef.value.offsetWidth, maxWidth)
    : maxWidth
  const tooltipHeight = tooltipRef.value?.offsetHeight || FALLBACK_TOOLTIP_HEIGHT

  let top = targetRect.value.bottom + TOOLTIP_MARGIN
  if (top + tooltipHeight > viewportHeight - VIEWPORT_MARGIN) {
    top = targetRect.value.top - tooltipHeight - TOOLTIP_MARGIN
  }
  top = clamp(top, VIEWPORT_MARGIN, viewportHeight - tooltipHeight - VIEWPORT_MARGIN)

  let left = targetRect.value.left + targetRect.value.width / 2 - tooltipWidth / 2
  left = clamp(left, VIEWPORT_MARGIN, viewportWidth - tooltipWidth - VIEWPORT_MARGIN)

  tooltipStyle.value = {
    top: `${Math.round(top)}px`,
    left: `${Math.round(left)}px`,
    maxWidth: `${Math.round(maxWidth)}px`,
    transform: 'none',
  }
}

const updateTargetRect = () => {
  if (!targetElement.value || !isElementVisible(targetElement.value)) {
    targetRect.value = null
    return
  }

  targetRect.value = targetElement.value.getBoundingClientRect()
  updateTooltipPosition()
}

const scheduleRectUpdate = () => {
  if (rafHandle) return
  rafHandle = window.requestAnimationFrame(() => {
    rafHandle = 0
    updateTargetRect()
  })
}

const waitForElement = async (selector) => {
  for (let attempt = 0; attempt < RESOLVE_ATTEMPTS; attempt += 1) {
    await nextTick()

    const element = findVisibleElement(selector)
    if (element) return element

    await wait(RESOLVE_DELAY_MS)
  }

  return null
}

const isRouteSatisfied = (step) => {
  if (!step?.route) return true

  if (step.route.name === 'calendar') {
    return route.name === 'events' && route.query?.view === 'calendar'
  }

  return route.name === step.route.name
}

const ensureOnRoute = async (step) => {
  if (!step?.route || isRouteSatisfied(step)) return

  try {
    await router.push(step.route)
  } catch (error) {
    console.warn('[OnboardingTour] navigation failed', step.route, error)
  }
}

const focusTooltip = async () => {
  await nextTick()
  tooltipRef.value?.focus()
}

const resolveTarget = async () => {
  const currentSequence = ++resolveSequence
  const step = currentStep.value
  if (!step) {
    finishTour()
    return
  }

  clearTargetHighlight()
  targetRect.value = null

  await ensureOnRoute(step)
  if (currentSequence !== resolveSequence || !tourStore.isOpen) return

  const element = await waitForElement(step.selector)
  if (currentSequence !== resolveSequence || !tourStore.isOpen) return

  if (!element) {
    console.warn(`[OnboardingTour] selector not found: ${step.selector}`)
    if (isLastStep.value) {
      finishTour()
      return
    }
    currentStepIndex.value += 1
    return
  }

  targetElement.value = element
  targetElement.value.classList.add(HIGHLIGHT_CLASS)
  updateTargetRect()
  await focusTooltip()
}

const goNext = () => {
  if (isLastStep.value) {
    finishTour()
    return
  }

  currentStepIndex.value += 1
}

const goPrev = () => {
  if (currentStepIndex.value <= 0) return
  currentStepIndex.value -= 1
}

const finishTour = () => {
  clearTargetHighlight()
  tourStore.markDone()
}

const skipTour = () => {
  clearTargetHighlight()
  tourStore.markDone()
}

const handleKeydown = (event) => {
  if (event.key !== 'Escape') return
  event.preventDefault()
  skipTour()
}

watch(
  () => currentStepIndex.value,
  async () => {
    await resolveTarget()
  },
)

watch(
  () => route.fullPath,
  async () => {
    if (!tourStore.isOpen) return
    await resolveTarget()
  },
)

onMounted(async () => {
  const stepFromStore = Number(tourStore.startStep || 0)
  currentStepIndex.value = clamp(Math.floor(stepFromStore), 0, steps.length - 1)

  window.addEventListener('resize', scheduleRectUpdate)
  window.addEventListener('scroll', scheduleRectUpdate, true)
  window.addEventListener('keydown', handleKeydown)

  await resolveTarget()
})

onBeforeUnmount(() => {
  resolveSequence += 1
  clearTargetHighlight()
  if (rafHandle) {
    window.cancelAnimationFrame(rafHandle)
    rafHandle = 0
  }

  window.removeEventListener('resize', scheduleRectUpdate)
  window.removeEventListener('scroll', scheduleRectUpdate, true)
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.tourRoot {
  position: fixed;
  inset: 0;
  z-index: 2100;
}

.tourOverlay {
  position: absolute;
  inset: 0;
  background: rgb(3 7 18 / 0.62);
  pointer-events: auto;
}

.tourTooltip {
  position: fixed;
  z-index: 2102;
  width: min(360px, calc(100vw - 24px));
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  color: var(--color-surface);
  box-shadow: 0 18px 42px rgb(2 6 23 / 0.45);
  padding: 0.85rem;
  display: grid;
  gap: 0.65rem;
}

.tourProgress {
  margin: 0;
  font-size: 0.72rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-weight: 700;
}

.tourTitle {
  margin: 0;
  font-size: 1rem;
  line-height: 1.2;
}

.tourBody {
  margin: 0;
  font-size: 0.86rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.98);
}

.tourActions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
}

.tourActionsRight {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.tourBtn {
  border-radius: 9px;
  border: 1px solid transparent;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.42rem 0.68rem;
  cursor: pointer;
}

.tourBtn.ghost {
  border-color: rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.64);
  color: var(--color-surface);
}

.tourBtn.primary {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.25);
  color: var(--color-surface);
}

.tourBtn:focus-visible {
  outline: 2px solid rgb(var(--color-primary-rgb) / 0.9);
  outline-offset: 2px;
}

@media (max-width: 640px) {
  .tourTooltip {
    padding: 0.75rem;
  }
}

:global(.onboarding-tour-target) {
  position: relative;
  z-index: 2101;
  box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.95);
  border-radius: 12px;
}
</style>
