export function useEventDetailSwipeNavigation({
  adjacentEventIds,
  canGoNext,
  canGoPrev,
  canSwipe,
  eventId,
  router,
  swipeDx,
  swipeNavigating,
  swipeReleaseAnimating,
  swipeTouchActive,
}) {
  let touchStartX = null
  let touchStartY = null
  let touchStartAt = 0

  function onCardTouchStart(eventValue) {
    if (!canSwipe.value || swipeNavigating.value || isInteractiveTarget(eventValue.target)) return
    const touch = eventValue.touches?.[0]
    if (!touch) return
    swipeTouchActive.value = true
    swipeReleaseAnimating.value = false
    swipeDx.value = 0
    touchStartX = touch.clientX
    touchStartY = touch.clientY
    touchStartAt = Date.now()
  }

  function onCardTouchMove(eventValue) {
    if (!canSwipe.value || !swipeTouchActive.value || touchStartX === null || touchStartY === null) return
    const touch = eventValue.touches?.[0]
    if (!touch) return

    const dx = touch.clientX - touchStartX
    const dy = touch.clientY - touchStartY

    if (Math.abs(dy) > Math.abs(dx) * 1.1) {
      swipeDx.value = Math.max(-40, Math.min(40, dx * 0.15))
      return
    }

    swipeDx.value = Math.max(-240, Math.min(240, dx))
  }

  function onCardTouchEnd(eventValue) {
    if (!canSwipe.value || touchStartX === null || touchStartY === null) {
      resetSwipeGesture()
      return
    }

    const touch = eventValue.changedTouches?.[0]
    if (!touch) {
      animateSwipeBack()
      return
    }

    const dx = touch.clientX - touchStartX
    const dy = touch.clientY - touchStartY
    const elapsed = Date.now() - touchStartAt
    swipeTouchActive.value = false
    touchStartX = null
    touchStartY = null
    touchStartAt = 0

    if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.2) {
      animateSwipeBack()
      return
    }
    if (elapsed > 700) {
      animateSwipeBack()
      return
    }

    if (dx < 0) {
      if (!canGoNext.value) {
        animateSwipeBack()
        return
      }
      void goToAdjacentEvent('next', { animate: true })
      return
    }

    if (!canGoPrev.value) {
      animateSwipeBack()
      return
    }
    void goToAdjacentEvent('prev', { animate: true })
  }

  function onCardTouchCancel() {
    animateSwipeBack()
  }

  function isInteractiveTarget(target) {
    if (!(target instanceof Element)) return false
    return Boolean(target.closest('button, a, input, textarea, select, label, [role="button"]'))
  }

  async function goToAdjacentEvent(direction, options = {}) {
    const targetId = direction === 'prev' ? adjacentEventIds.value.prev : adjacentEventIds.value.next
    if (!Number.isInteger(targetId) || swipeNavigating.value || Number(targetId) === eventId.value) return

    const animate = options?.animate === true
    swipeNavigating.value = true

    if (animate) {
      swipeTouchActive.value = false
      swipeReleaseAnimating.value = true
      swipeDx.value = direction === 'prev' ? 220 : -220
      await waitForMs(90)
    }

    try {
      await router.push({ name: 'event-detail', params: { id: Number(targetId) } })
    } finally {
      swipeNavigating.value = false
      resetSwipeGesture()
    }
  }

  function animateSwipeBack() {
    swipeTouchActive.value = false
    swipeReleaseAnimating.value = true
    swipeDx.value = 0
    window.setTimeout(() => {
      swipeReleaseAnimating.value = false
    }, 220)
  }

  function resetSwipeGesture() {
    swipeTouchActive.value = false
    swipeReleaseAnimating.value = false
    swipeDx.value = 0
    touchStartX = null
    touchStartY = null
    touchStartAt = 0
  }

  function waitForMs(delayMs) {
    return new Promise((resolve) => {
      window.setTimeout(resolve, delayMs)
    })
  }

  function handleWindowKeydown(eventValue) {
    if (eventValue.defaultPrevented) return
    if (isInteractiveTarget(eventValue.target)) return

    if (eventValue.key === 'ArrowLeft') {
      eventValue.preventDefault()
      void goToAdjacentEvent('prev')
      return
    }

    if (eventValue.key === 'ArrowRight') {
      eventValue.preventDefault()
      void goToAdjacentEvent('next')
    }
  }

  return {
    handleWindowKeydown,
    onCardTouchCancel,
    onCardTouchEnd,
    onCardTouchMove,
    onCardTouchStart,
    resetSwipeGesture,
  }
}
