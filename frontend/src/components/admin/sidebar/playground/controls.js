const CONTROL_TYPES = {
  TEXT: 'text',
  TEXTAREA: 'textarea',
  BOOLEAN: 'boolean',
  SELECT: 'select',
  NUMBER: 'number',
}

const isObject = (value) => {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

export const deepClone = (value) => {
  if (Array.isArray(value)) {
    return value.map((item) => deepClone(item))
  }

  if (isObject(value)) {
    return Object.fromEntries(
      Object.entries(value).map(([key, item]) => [key, deepClone(item)]),
    )
  }

  return value
}

const splitPath = (path) => {
  return String(path || '')
    .split('.')
    .map((segment) => segment.trim())
    .filter(Boolean)
}

const getContainerByPath = (source, pathSegments, { create = false } = {}) => {
  if (!isObject(source)) return null

  let cursor = source
  for (let i = 0; i < pathSegments.length; i += 1) {
    const key = pathSegments[i]
    const isLast = i === pathSegments.length - 1
    const currentValue = cursor[key]

    if (isLast) {
      return { container: cursor, key }
    }

    if (!isObject(currentValue)) {
      if (!create) {
        return null
      }
      cursor[key] = {}
    }

    cursor = cursor[key]
  }

  return null
}

export const getByPath = (source, path, fallback = undefined) => {
  const segments = splitPath(path)
  if (!segments.length) {
    return source === undefined ? fallback : source
  }

  let cursor = source
  for (const segment of segments) {
    if (!isObject(cursor) || !(segment in cursor)) {
      return fallback
    }
    cursor = cursor[segment]
  }

  return cursor
}

export const setByPath = (source, path, value) => {
  const segments = splitPath(path)
  if (!segments.length) {
    return deepClone(value)
  }

  const target = isObject(source) ? deepClone(source) : {}
  const containerRef = getContainerByPath(target, segments, { create: true })
  if (!containerRef) return target

  containerRef.container[containerRef.key] = deepClone(value)
  return target
}

const toNumber = (rawValue) => {
  const parsed = Number(rawValue)
  return Number.isFinite(parsed) ? parsed : null
}

const normalizeNumber = (control, rawValue) => {
  const parsed = toNumber(rawValue)
  if (parsed === null) {
    const fallback = toNumber(control.defaultValue)
    return fallback === null ? 0 : fallback
  }

  let value = parsed
  if (Number.isFinite(Number(control.min))) {
    value = Math.max(Number(control.min), value)
  }
  if (Number.isFinite(Number(control.max))) {
    value = Math.min(Number(control.max), value)
  }

  return value
}

const fallbackForControl = (control) => {
  if (!control || typeof control !== 'object') return null
  if (Object.prototype.hasOwnProperty.call(control, 'defaultValue')) {
    return deepClone(control.defaultValue)
  }

  if (control.type === CONTROL_TYPES.BOOLEAN) return false
  if (control.type === CONTROL_TYPES.NUMBER) return 0
  return ''
}

export const normalizeControlValue = (control, rawValue) => {
  if (!control || typeof control !== 'object') {
    return rawValue
  }

  let nextValue
  if (control.type === CONTROL_TYPES.BOOLEAN) {
    nextValue = Boolean(rawValue)
  } else if (control.type === CONTROL_TYPES.NUMBER) {
    nextValue = normalizeNumber(control, rawValue)
  } else if (control.type === CONTROL_TYPES.SELECT) {
    nextValue = String(rawValue ?? '')
  } else {
    nextValue = String(rawValue ?? '')
  }

  if (typeof control.parser === 'function') {
    nextValue = control.parser(nextValue, control)
  }

  if (typeof control.guard === 'function') {
    const valid = control.guard(nextValue, control)
    if (!valid) {
      return fallbackForControl(control)
    }
  }

  return nextValue
}

const deepMerge = (target, source) => {
  if (!isObject(source)) {
    return deepClone(source)
  }

  const base = isObject(target) ? deepClone(target) : {}
  for (const [key, value] of Object.entries(source)) {
    const existing = base[key]
    if (isObject(value)) {
      base[key] = deepMerge(existing, value)
    } else {
      base[key] = deepClone(value)
    }
  }

  return base
}

const applyControlDefaults = (props, controls) => {
  let nextProps = isObject(props) ? deepClone(props) : {}

  for (const control of Array.isArray(controls) ? controls : []) {
    if (!control || !control.key) continue

    const currentValue = getByPath(nextProps, control.key)
    if (currentValue !== undefined) continue

    nextProps = setByPath(nextProps, control.key, fallbackForControl(control))
  }

  return nextProps
}

export const createPropsState = (entry, variantId = null) => {
  const base = isObject(entry?.initialProps) ? deepClone(entry.initialProps) : {}
  const controls = Array.isArray(entry?.editableProps) ? entry.editableProps : []
  const variants = Array.isArray(entry?.variants) ? entry.variants : []

  let nextProps = applyControlDefaults(base, controls)

  if (variantId) {
    const variant = variants.find((item) => String(item?.id) === String(variantId))
    if (variant && isObject(variant.props)) {
      nextProps = deepMerge(nextProps, variant.props)
      nextProps = applyControlDefaults(nextProps, controls)
    }
  }

  return nextProps
}

export const describeControlDefault = (control) => {
  const value = fallbackForControl(control)
  if (typeof value === 'string') return value
  if (typeof value === 'number') return String(value)
  if (typeof value === 'boolean') return value ? 'true' : 'false'
  return JSON.stringify(value)
}

export const PLAYGROUND_CONTROL_TYPES = CONTROL_TYPES
