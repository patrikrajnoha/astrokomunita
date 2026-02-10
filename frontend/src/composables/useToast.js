import { reactive } from 'vue'

const toastState = reactive({
  visible: false,
  message: '',
  type: 'success',
})

let hideTimer = null

export function useToast() {
  const hideToast = () => {
    toastState.visible = false
  }

  const showToast = (message, type = 'success', duration = 2400) => {
    if (!message) return

    toastState.message = String(message)
    toastState.type = type
    toastState.visible = true

    if (hideTimer) {
      clearTimeout(hideTimer)
    }

    hideTimer = setTimeout(() => {
      toastState.visible = false
      hideTimer = null
    }, Math.max(900, Number(duration) || 2400))
  }

  return {
    toast: toastState,
    showToast,
    hideToast,
  }
}
