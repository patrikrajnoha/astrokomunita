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
  matchValue: '',
  matchValueTrim: true,
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

function normalizeOptions(baseOptions, input = {}) {
  const options = {
    ...baseOptions,
    ...(input || {}),
    variant: normalizeVariant(input?.variant),
  }

  if (options.variant === 'danger' && !String(options.message || '').trim()) {
    options.message = 'Tato akcia sa neda vratit.'
  }

  return options
}

function normalizedPromptValue(value, trim) {
  const resolved = String(value || '')
  if (trim === false) return resolved
  return resolved.trim()
}

export function isPromptInputValid(value, options = {}) {
  const source = String(value || '')

  if (options.required && source.trim() === '') {
    return false
  }

  const expected = String(options.matchValue || '')
  if (expected === '') {
    return true
  }

  const trim = options.matchValueTrim !== false
  return normalizedPromptValue(source, trim) === normalizedPromptValue(expected, trim)
}

function confirm(input = {}) {
  if (confirmState.open) {
    closeDialog(false)
  }

  confirmState.mode = 'confirm'
  confirmState.options = normalizeOptions(defaultConfirmOptions, input)
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
  confirmState.options = normalizeOptions(defaultPromptOptions, input)
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
    if (!isPromptInputValid(value, confirmState.options)) return
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
