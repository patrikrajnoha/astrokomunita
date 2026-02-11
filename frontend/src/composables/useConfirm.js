import { reactive } from 'vue'

const defaultConfirmOptions = {
  title: 'Potvrdit akciu',
  message: '',
  confirmText: 'Potvrdit',
  cancelText: 'Zrusit',
  variant: 'default',
  closeOnBackdrop: true,
  closeOnEsc: true,
}

const defaultPromptOptions = {
  title: 'Zadaj hodnotu',
  message: '',
  confirmText: 'Potvrdit',
  cancelText: 'Zrusit',
  placeholder: '',
  initialValue: '',
  required: false,
  multiline: false,
  variant: 'default',
  closeOnBackdrop: true,
  closeOnEsc: true,
}

const confirmState = reactive({
  open: false,
  mode: 'confirm',
  options: { ...defaultConfirmOptions },
  value: '',
})

let resolver = null

function settle(payload) {
  if (typeof resolver !== 'function') return
  const done = resolver
  resolver = null
  done(payload)
}

function closeDialog(payload) {
  confirmState.open = false
  settle(payload)
}

function normalizeVariant(value) {
  return value === 'danger' ? 'danger' : 'default'
}

function confirm(input = {}) {
  if (confirmState.open) {
    closeDialog(false)
  }

  confirmState.mode = 'confirm'
  confirmState.options = {
    ...defaultConfirmOptions,
    ...(input || {}),
    variant: normalizeVariant(input?.variant),
  }
  confirmState.value = ''
  confirmState.open = true

  return new Promise((resolve) => {
    resolver = resolve
  })
}

function prompt(input = {}) {
  if (confirmState.open) {
    closeDialog(null)
  }

  confirmState.mode = 'prompt'
  confirmState.options = {
    ...defaultPromptOptions,
    ...(input || {}),
    variant: normalizeVariant(input?.variant),
  }
  confirmState.value = String(confirmState.options.initialValue || '')
  confirmState.open = true

  return new Promise((resolve) => {
    resolver = resolve
  })
}

function confirmProceed() {
  if (!confirmState.open) return

  if (confirmState.mode === 'prompt') {
    const value = String(confirmState.value || '')
    if (confirmState.options.required && !value.trim()) return
    closeDialog(value)
    return
  }

  closeDialog(true)
}

function cancel() {
  if (!confirmState.open) return
  closeDialog(confirmState.mode === 'prompt' ? null : false)
}

export function useConfirm() {
  return {
    state: confirmState,
    confirm,
    prompt,
    confirmProceed,
    cancel,
  }
}
