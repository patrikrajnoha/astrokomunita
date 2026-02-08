import { computed, ref } from 'vue'

export function useSwipeCard(options = {}) {
  const {
    onLeft = async () => {},
    onRight = async () => {},
    onUp = async () => {},
    threshold = 110,
    velocityThreshold = 0.5,
    maxRotation = 12,
  } = options

  const dragging = ref(false)
  const animating = ref(false)
  const dx = ref(0)
  const dy = ref(0)

  let pointerId = null
  let startX = 0
  let startY = 0
  let startTs = 0

  const badge = computed(() => {
    if (!dragging.value) return ''
    if (dx.value >= 56) return 'STAR'
    if (dx.value <= -56) return 'IGNORE'
    if (dy.value <= -56) return 'DETAIL'
    return ''
  })

  const cardStyle = computed(() => {
    const normalized = Math.max(-1, Math.min(1, dx.value / 180))
    const rotate = normalized * maxRotation
    const transition = animating.value
      ? 'transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1)'
      : dragging.value
        ? 'none'
        : 'transform 180ms ease'

    return {
      transform: `translate(${dx.value}px, ${dy.value}px) rotate(${rotate}deg)`,
      transition,
    }
  })

  function reset() {
    dx.value = 0
    dy.value = 0
    dragging.value = false
  }

  function onPointerDown(e) {
    if (animating.value) return
    pointerId = e.pointerId
    startX = e.clientX
    startY = e.clientY
    startTs = performance.now()
    dragging.value = true
    e.currentTarget.setPointerCapture?.(pointerId)
  }

  function onPointerMove(e) {
    if (!dragging.value || animating.value) return
    dx.value = e.clientX - startX
    dy.value = e.clientY - startY
  }

  function animateBack() {
    animating.value = true
    dx.value = 0
    dy.value = 0
    window.setTimeout(() => {
      animating.value = false
    }, 190)
  }

  async function flyOut({ x, y, cb }) {
    animating.value = true
    dx.value = x
    dy.value = y

    await new Promise((resolve) => {
      window.setTimeout(resolve, 230)
    })

    try {
      await cb()
    } finally {
      animating.value = false
      reset()
    }
  }

  async function onPointerUp() {
    if (!dragging.value || animating.value) return

    dragging.value = false
    const dt = Math.max(1, performance.now() - startTs)
    const vx = dx.value / dt
    const vy = dy.value / dt

    const right = dx.value >= threshold || vx >= velocityThreshold
    const left = dx.value <= -threshold || vx <= -velocityThreshold
    const up = dy.value <= -threshold || vy <= -velocityThreshold

    if (right) {
      await flyOut({ x: 640, y: dy.value, cb: onRight })
      return
    }

    if (left) {
      await flyOut({ x: -640, y: dy.value, cb: onLeft })
      return
    }

    if (up) {
      await flyOut({ x: dx.value * 0.15, y: -420, cb: onUp })
      return
    }

    animateBack()
  }

  return {
    dragging,
    animating,
    badge,
    cardStyle,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    reset,
  }
}

