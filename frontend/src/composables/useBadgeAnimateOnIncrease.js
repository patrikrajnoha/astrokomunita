import { nextTick, onBeforeUnmount, ref, unref, watch } from 'vue'

export const BADGE_ANIMATION_MS = 680

export function shouldAnimateOnIncrease(previousCount, currentCount) {
  if (previousCount === undefined || previousCount === null) return false

  const prev = Number(previousCount)
  const curr = Number(currentCount)
  if (!Number.isFinite(prev) || !Number.isFinite(curr)) return false

  return curr > prev
}

export function useBadgeAnimateOnIncrease(countRef, options = {}) {
  const durationMs = Number.isFinite(options.durationMs) ? options.durationMs : BADGE_ANIMATION_MS
  const shouldAnimate = ref(false)
  const readyRef = options.readyRef

  let timerId = null
  let baselineReady = false
  let previousCount = null

  const isReady = () => {
    if (readyRef === undefined) return true
    return Boolean(unref(readyRef))
  }

  const readCount = () => {
    const value = Number(unref(countRef))
    return Number.isFinite(value) ? value : 0
  }

  const clearTimer = () => {
    if (timerId !== null) {
      clearTimeout(timerId)
      timerId = null
    }
  }

  const stopAnimation = () => {
    clearTimer()
    shouldAnimate.value = false
  }

  const triggerAnimation = async () => {
    stopAnimation()
    // Remove and re-add class in the next DOM tick to restart keyframes on fast consecutive updates.
    await nextTick()
    shouldAnimate.value = true
    timerId = setTimeout(() => {
      shouldAnimate.value = false
      timerId = null
    }, durationMs)
  }

  watch(
    () => isReady(),
    (ready) => {
      if (!ready) {
        baselineReady = false
        previousCount = null
        stopAnimation()
        return
      }

      if (!baselineReady) {
        previousCount = readCount()
        baselineReady = true
      }
    },
    { immediate: true },
  )

  watch(
    () => readCount(),
    async (currentCount) => {
      if (!isReady()) return

      if (!baselineReady) {
        previousCount = currentCount
        baselineReady = true
        return
      }

      if (shouldAnimateOnIncrease(previousCount, currentCount)) {
        await triggerAnimation()
      }

      previousCount = currentCount
    },
  )

  onBeforeUnmount(() => {
    stopAnimation()
  })

  return {
    shouldAnimate,
    triggerAnimation,
  }
}
