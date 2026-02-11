import { reactive, computed } from 'vue'

const MAX_VISIBLE = 3
const DEFAULT_DURATION = 3600
const ERROR_DURATION = 7000

const toastState = reactive({
  visible: [],
  queue: [],
})

const timers = new Map()
let nextToastId = 1

function normalizeType(type) {
  if (type === 'success' || type === 'error' || type === 'warn' || type === 'info') {
    return type
  }
  return 'info'
}

function buildToast(payload = {}, fallbackType = 'info', fallbackDuration) {
  const type = normalizeType(payload.type || fallbackType)
  const message = String(payload.message || '').trim()
  const duration =
    Number.isFinite(Number(payload.duration))
      ? Math.max(1200, Number(payload.duration))
      : type === 'error'
        ? ERROR_DURATION
        : Number.isFinite(Number(fallbackDuration))
          ? Math.max(1200, Number(fallbackDuration))
          : DEFAULT_DURATION

  return {
    id: nextToastId++,
    type,
    message,
    duration,
    title: payload.title ? String(payload.title) : '',
    dismissible: payload.dismissible !== false,
    action: payload.action && payload.action.label && typeof payload.action.onClick === 'function'
      ? {
          label: String(payload.action.label),
          onClick: payload.action.onClick,
        }
      : null,
  }
}

function clearTimer(id) {
  const timer = timers.get(id)
  if (!timer) return
  clearTimeout(timer)
  timers.delete(id)
}

function scheduleDismiss(item) {
  clearTimer(item.id)
  if (!(item.duration > 0)) return

  const timer = setTimeout(() => {
    dismiss(item.id)
  }, item.duration)

  timers.set(item.id, timer)
}

function fillVisibleSlots() {
  while (toastState.visible.length < MAX_VISIBLE && toastState.queue.length > 0) {
    const next = toastState.queue.shift()
    toastState.visible.push(next)
    scheduleDismiss(next)
  }
}

function show(input, type = 'info', duration) {
  const payload = typeof input === 'string' ? { message: input } : { ...(input || {}) }
  const item = buildToast(payload, type, duration)
  if (!item.message) return null

  if (toastState.visible.length < MAX_VISIBLE) {
    toastState.visible.push(item)
    scheduleDismiss(item)
  } else {
    toastState.queue.push(item)
  }

  return item.id
}

function dismiss(id) {
  if (id == null) return

  const visibleIndex = toastState.visible.findIndex((item) => item.id === id)
  if (visibleIndex !== -1) {
    clearTimer(id)
    toastState.visible.splice(visibleIndex, 1)
    fillVisibleSlots()
    return
  }

  const queueIndex = toastState.queue.findIndex((item) => item.id === id)
  if (queueIndex !== -1) {
    toastState.queue.splice(queueIndex, 1)
  }
}

function clearAll() {
  for (const id of timers.keys()) {
    clearTimer(id)
  }
  toastState.visible.splice(0)
  toastState.queue.splice(0)
}

async function triggerAction(id) {
  const item = toastState.visible.find((toast) => toast.id === id)
  if (!item?.action) return

  try {
    await item.action.onClick()
  } finally {
    dismiss(id)
  }
}

const legacyToast = computed(() => {
  const current = toastState.visible[0]
  return {
    visible: Boolean(current),
    message: current?.message || '',
    type: current?.type || 'info',
  }
})

export function useToast() {
  return {
    toasts: toastState,
    toast: legacyToast,
    show,
    showToast: show,
    success: (message, options = {}) => show({ ...options, message, type: 'success' }),
    error: (message, options = {}) => show({ ...options, message, type: 'error' }),
    info: (message, options = {}) => show({ ...options, message, type: 'info' }),
    warn: (message, options = {}) => show({ ...options, message, type: 'warn' }),
    dismiss,
    hideToast: dismiss,
    clearAll,
    triggerAction,
  }
}
