<template>
  <div class="tourRoot" aria-hidden="false">
    <div class="tourOverlay" aria-hidden="true"></div>
    <div
      v-if="targetRect"
      class="tourSpotlight"
      :style="spotlightStyle"
      aria-hidden="true"
    ></div>

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
      <button
        type="button"
        class="tourClose"
        aria-label="Zavriet onboarding tour"
        @click="skipTour"
      >
        x
      </button>

      <p class="tourProgress">Krok {{ currentStepIndex + 1 }} z {{ steps.length }}</p>
      <div class="tourProgressBar" role="progressbar" :aria-valuenow="progressPercent" aria-valuemin="0" aria-valuemax="100">
        <span class="tourProgressBarFill" :style="{ width: `${progressPercent}%` }"></span>
      </div>

      <h2 :id="titleId" class="tourTitle">{{ currentStep.title }}</h2>
      <p class="tourBody">{{ currentStep.body }}</p>
      <p class="tourTip">{{ currentStep.tip }}</p>
      <OnboardingWidgetPreview v-if="showWidgetPreview" size="compact" />
      <p v-if="!isTargetAvailable" class="tourMissing">{{ currentStep.missingHint }}</p>

      <div class="tourStepDots" role="tablist" aria-label="Kroky tour">
        <button
          v-for="(step, index) in steps"
          :key="step.id"
          type="button"
          class="tourStepDot"
          :class="{ active: index === currentStepIndex }"
          :aria-label="`Prejst na krok ${index + 1}: ${step.title}`"
          :aria-current="index === currentStepIndex ? 'step' : undefined"
          @click="jumpToStep(index)"
        ></button>
      </div>

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
            {{ nextButtonLabel }}
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
import OnboardingWidgetPreview from '@/components/onboarding/OnboardingWidgetPreview.vue'

const tourStore = useOnboardingTourStore()
const router = useRouter()
const route = useRoute()

// Add a new step by defining a unique selector + route and adding data-tour="<id>" to target DOM.
const steps = [
  {
    id: 'feed',
    selector: '[data-tour="feed"]',
    title: 'Komunitny feed',
    body: 'Tu najdes nove prispevky, diskusie a pozorovania od komunity.',
    tip: 'Skus prepnut kartu alebo otvorit detail prispevku.',
    missingHint: 'Feed sa teraz nenasiel. Skus prejst na domovsku stranku a pokracovat.',
    nextLabel: 'Na kalendar',
    route: { name: 'home' },
  },
  {
    id: 'calendar',
    selector: '[data-tour="calendar"]',
    title: 'Kalendar udalosti',
    body: 'V kalendari vidis astronomicke ukazy podla datumu a vyhladavania.',
    tip: 'Otvor detail udalosti a pridaj ju do svojho kalendara.',
    missingHint: 'Kalendar sa teraz nenasiel. Skontroluj, ci je zapnute zobrazenie Kalendar.',
    nextLabel: 'Na podmienky',
    route: { name: 'calendar' },
  },
  {
    id: 'conditions',
    selector: '[data-tour="conditions"]',
    title: 'Pozorovacie podmienky',
    body: 'Na jednom mieste mas pocasie, seeing a dalsie uzitocne widgety.',
    tip: 'Na mobile otvoris widgety tlacidlom vpravo dole, na desktope ich najdes v pravom paneli. Vzhlad a poradie widgetov si vies upravit v Nastaveniach > Sidebar widgety.',
    missingHint: 'Panel podmienok sa teraz nenasiel. Pokracuj na dalsi krok alebo skus obnovit stranku.',
    route: { name: 'home' },
  },
]

const TOOLTIP_MARGIN = 12
const VIEWPORT_MARGIN = 12
const MAX_TOOLTIP_WIDTH = 360
const MAX_TARGET_GUIDE_HEIGHT_PX = 220
const MAX_TARGET_GUIDE_HEIGHT_RATIO = 0.42
const SPOTLIGHT_PADDING = 6
const RESOLVE_ATTEMPTS = 10
const RESOLVE_DELAY_MS = 150
const HIGHLIGHT_CLASS = 'onboarding-tour-target'
const FALLBACK_TOOLTIP_HEIGHT = 180

const currentStepIndex = ref(0)
const tooltipRef = ref(null)
const targetElement = ref(null)
const targetRect = ref(null)
const isTargetAvailable = ref(true)
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
const showWidgetPreview = computed(() => currentStep.value?.id === 'conditions')
const progressPercent = computed(() => {
  if (steps.length === 0) return 0
  return Math.round(((currentStepIndex.value + 1) / steps.length) * 100)
})
const nextButtonLabel = computed(() => currentStep.value?.nextLabel || 'Dalej')
const spotlightStyle = computed(() => {
  if (!targetRect.value) return {}

  const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0
  const baseLeft = targetRect.value.left - SPOTLIGHT_PADDING
  const baseTop = targetRect.value.top - SPOTLIGHT_PADDING
  const baseWidth = targetRect.value.width + SPOTLIGHT_PADDING * 2
  const baseHeight = targetRect.value.height + SPOTLIGHT_PADDING * 2

  const left = clamp(baseLeft, VIEWPORT_MARGIN / 2, viewportWidth - VIEWPORT_MARGIN / 2)
  const top = clamp(baseTop, VIEWPORT_MARGIN / 2, viewportHeight - VIEWPORT_MARGIN / 2)
  const width = Math.max(24, Math.min(baseWidth, viewportWidth - left - VIEWPORT_MARGIN / 2))
  const height = Math.max(24, Math.min(baseHeight, viewportHeight - top - VIEWPORT_MARGIN / 2))

  return {
    left: `${Math.round(left)}px`,
    top: `${Math.round(top)}px`,
    width: `${Math.round(width)}px`,
    height: `${Math.round(height)}px`,
  }
})
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

const normalizeTargetRect = (rect) => {
  const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0
  const maxGuideHeight = Math.min(
    MAX_TARGET_GUIDE_HEIGHT_PX,
    Math.max(120, Math.round(viewportHeight * MAX_TARGET_GUIDE_HEIGHT_RATIO)),
  )
  const maxGuideWidth = Math.max(120, viewportWidth - VIEWPORT_MARGIN * 2)
  const width = Math.min(rect.width, maxGuideWidth)
  const height = Math.min(rect.height, maxGuideHeight)
  const left = clamp(rect.left, VIEWPORT_MARGIN, viewportWidth - width - VIEWPORT_MARGIN)
  const top = clamp(rect.top, VIEWPORT_MARGIN, viewportHeight - height - VIEWPORT_MARGIN)

  return {
    top,
    left,
    width,
    height,
    right: left + width,
    bottom: top + height,
  }
}

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

  targetRect.value = normalizeTargetRect(targetElement.value.getBoundingClientRect())
  updateTooltipPosition()
}

const scheduleRectUpdate = () => {
  if (rafHandle) return
  rafHandle = window.requestAnimationFrame(() => {
    rafHandle = 0
    updateTargetRect()
  })
}

const ensureElementInViewport = async (element) => {
  if (!isElementVisible(element)) return

  const rect = element.getBoundingClientRect()
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0
  const isOutsideViewport = rect.top < VIEWPORT_MARGIN || rect.bottom > viewportHeight - VIEWPORT_MARGIN

  if (!isOutsideViewport) return

  try {
    element.scrollIntoView({
      behavior: 'smooth',
      block: 'center',
      inline: 'nearest',
    })
  } catch {
    element.scrollIntoView()
  }

  await wait(220)
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
  isTargetAvailable.value = true

  await ensureOnRoute(step)
  if (currentSequence !== resolveSequence || !tourStore.isOpen) return

  const element = await waitForElement(step.selector)
  if (currentSequence !== resolveSequence || !tourStore.isOpen) return

  if (!element) {
    console.warn(`[OnboardingTour] selector not found: ${step.selector}`)
    isTargetAvailable.value = false
    targetRect.value = null
    await focusTooltip()
    return
  }

  isTargetAvailable.value = true
  targetElement.value = element
  await ensureElementInViewport(targetElement.value)
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

const jumpToStep = (index) => {
  const nextIndex = clamp(Math.floor(Number(index) || 0), 0, steps.length - 1)
  if (nextIndex === currentStepIndex.value) return
  currentStepIndex.value = nextIndex
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
  if (event.key === 'Escape') {
    event.preventDefault()
    skipTour()
    return
  }

  if (event.key === 'ArrowRight') {
    event.preventDefault()
    goNext()
    return
  }

  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    goPrev()
  }
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
  pointer-events: none;
}

.tourOverlay {
  position: absolute;
  inset: 0;
  background: rgb(3 7 18 / 0.62);
  pointer-events: none;
}

.tourSpotlight {
  position: fixed;
  z-index: 2101;
  border: 2px solid rgb(var(--color-primary-rgb) / 0.95);
  border-radius: 12px;
  box-shadow: 0 0 0 1px rgb(var(--color-bg-rgb) / 0.6);
  pointer-events: none;
}

.tourTooltip {
  position: fixed;
  z-index: 2102;
  pointer-events: auto;
  width: min(360px, calc(100vw - 24px));
  max-height: min(78vh, 520px);
  overflow: auto;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.4);
  border-radius: 12px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  color: var(--color-surface);
  box-shadow: 0 18px 42px rgb(2 6 23 / 0.45);
  padding: 0.85rem;
  display: grid;
  gap: 0.65rem;
}

.tourClose {
  position: absolute;
  top: 0.45rem;
  right: 0.45rem;
  border: 0;
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.18);
  color: var(--color-surface);
  font-size: 0.85rem;
  cursor: pointer;
}

.tourClose:hover {
  background: rgb(var(--color-text-secondary-rgb) / 0.28);
}

.tourProgress {
  margin: 0;
  font-size: 0.72rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.95);
  font-weight: 700;
}

.tourProgressBar {
  width: 100%;
  height: 0.4rem;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.2);
  overflow: hidden;
}

.tourProgressBarFill {
  display: block;
  height: 100%;
  background: rgb(var(--color-primary-rgb) / 0.9);
  transition: width 180ms ease;
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

.tourTip {
  margin: 0;
  font-size: 0.79rem;
  color: rgb(var(--color-primary-rgb) / 0.9);
  border-left: 2px solid rgb(var(--color-primary-rgb) / 0.58);
  padding-left: 0.48rem;
}

.tourMissing {
  margin: 0;
  font-size: 0.78rem;
  color: rgb(var(--color-warning-rgb) / 0.95);
  background: rgb(var(--color-warning-rgb) / 0.12);
  border: 1px solid rgb(var(--color-warning-rgb) / 0.4);
  border-radius: 0.56rem;
  padding: 0.42rem 0.5rem;
}

.tourStepDots {
  display: flex;
  align-items: center;
  gap: 0.36rem;
}

.tourStepDot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 999px;
  border: 0;
  background: rgb(var(--color-text-secondary-rgb) / 0.35);
  padding: 0;
  cursor: pointer;
}

.tourStepDot.active {
  background: rgb(var(--color-primary-rgb) / 0.95);
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
    width: calc(100vw - 16px);
    padding: 0.75rem;
  }

  .tourActions {
    flex-wrap: wrap;
  }

  .tourActionsRight {
    width: 100%;
    justify-content: flex-end;
  }
}

:global(.onboarding-tour-target) {
  position: relative;
  z-index: auto;
}
</style>
