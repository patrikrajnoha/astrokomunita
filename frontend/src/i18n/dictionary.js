function normalizeLocale(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace('_', '-')
}

function pickLocale(messages, requestedLocale) {
  const availableLocales = Object.keys(messages || {})
  if (availableLocales.length === 0) return 'sk'

  const normalizedRequested = normalizeLocale(requestedLocale)
  if (normalizedRequested && messages[normalizedRequested]) return normalizedRequested

  const baseLocale = normalizedRequested.split('-')[0]
  if (baseLocale && messages[baseLocale]) return baseLocale

  if (messages.sk) return 'sk'
  return availableLocales[0]
}

function readPath(object, path) {
  return String(path || '')
    .split('.')
    .reduce((cursor, segment) => {
      if (!cursor || typeof cursor !== 'object') return undefined
      return cursor[segment]
    }, object)
}

function interpolate(template, params) {
  if (typeof template !== 'string') return template

  return template.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, key) => {
    if (!params || !(key in params)) return ''
    return String(params[key] ?? '')
  })
}

export function createDictionaryTranslator(messages = {}, preferredLocale = '') {
  const browserLocale =
    preferredLocale ||
    (typeof document !== 'undefined' ? document.documentElement?.lang : '') ||
    (typeof navigator !== 'undefined' ? navigator.language : '')

  const locale = pickLocale(messages, browserLocale)
  const fallbackLocale = messages.sk ? 'sk' : locale

  function t(path, params = {}) {
    const localizedValue = readPath(messages[locale], path)
    if (localizedValue !== undefined) return interpolate(localizedValue, params)

    const fallbackValue = readPath(messages[fallbackLocale], path)
    if (fallbackValue !== undefined) return interpolate(fallbackValue, params)

    return String(path || '')
  }

  return {
    locale,
    t,
  }
}
