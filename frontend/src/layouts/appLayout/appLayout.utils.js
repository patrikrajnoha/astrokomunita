export function parseStringValue(value) {
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed !== '' ? trimmed : null
}

export function parseNumericValue(value) {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value !== 'string') return null
  if (value.trim() === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

export function parseDateQuery(value) {
  const source = parseStringValue(Array.isArray(value) ? value[0] : value)
  if (!source) return null
  return /^\d{4}-\d{2}-\d{2}$/.test(source) ? source : null
}

export function localIsoDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function normalizeComposerAction(action) {
  const normalized = String(action || 'post').toLowerCase()
  return ['post', 'poll', 'event', 'observation'].includes(normalized) ? normalized : 'post'
}

export function normalizeComposerAttachmentFile(value) {
  if (typeof File === 'undefined') return null
  return value instanceof File ? value : null
}

const toSectionKeySet = (value) => {
  if (value instanceof Set) return value
  if (Array.isArray(value)) return new Set(value.map((item) => String(item || '')))
  return new Set()
}

export function buildWidgetProps(sectionKey, title, observingContext, options = {}) {
  if (
    sectionKey === 'observing_conditions' ||
    sectionKey === 'observing_weather' ||
    sectionKey === 'space_weather' ||
    sectionKey === 'aurora_watch' ||
    sectionKey === 'night_sky' ||
    sectionKey === 'iss_pass' ||
    sectionKey === 'moon_overview' ||
    sectionKey === 'moon_events'
  ) {
    return {
      lat: observingContext.lat,
      lon: observingContext.lon,
      date: observingContext.date,
      tz: observingContext.tz,
      locationName: observingContext.locationName,
    }
  }

  if (sectionKey === 'moon_phases') {
    const enabledSectionKeys = toSectionKeySet(options.enabledSectionKeys)
    return {
      lat: observingContext.lat,
      lon: observingContext.lon,
      date: observingContext.date,
      tz: observingContext.tz,
      locationName: observingContext.locationName,
      showOverview: !enabledSectionKeys.has('moon_overview'),
      showSpecialEvents: !enabledSectionKeys.has('moon_events'),
    }
  }

  if (
    sectionKey === 'nasa_apod' ||
    sectionKey === 'next_event' ||
    sectionKey === 'next_eclipse' ||
    sectionKey === 'next_meteor_shower' ||
    sectionKey === 'neo_watchlist' ||
    sectionKey === 'latest_articles' ||
    sectionKey === 'upcoming_events'
  ) {
    return title ? { title } : {}
  }

  return {}
}

export function dispatchPostCreated(createdPost) {
  if (typeof window === 'undefined' || !createdPost?.id) return
  window.dispatchEvent(new CustomEvent('post:created', { detail: createdPost }))
}

export function createSheetTouchHandlers({
  closeWidgetMenu,
  closeWidgetSheet,
  touchMode,
  touchStartY,
  widgetMenuOffsetY,
  widgetSheetOffsetY,
}) {
  const onSheetTouchStart = (event, mode) => {
    const point = event?.touches?.[0]
    if (!point) return
    touchStartY.value = point.clientY
    touchMode.value = mode
  }

  const onSheetTouchMove = (event, mode) => {
    if (touchMode.value !== mode) return
    const point = event?.touches?.[0]
    if (!point) return

    const delta = Math.max(0, point.clientY - touchStartY.value)
    if (mode === 'content') {
      widgetSheetOffsetY.value = Math.min(180, delta)
    } else if (mode === 'menu') {
      widgetMenuOffsetY.value = Math.min(180, delta)
    }
  }

  const onSheetTouchEnd = (mode) => {
    if (touchMode.value !== mode) return

    if (mode === 'content') {
      if (widgetSheetOffsetY.value > 80) {
        closeWidgetSheet()
      }
      widgetSheetOffsetY.value = 0
    } else if (mode === 'menu') {
      if (widgetMenuOffsetY.value > 80) {
        closeWidgetMenu()
      }
      widgetMenuOffsetY.value = 0
    }

    touchMode.value = ''
  }

  return {
    onSheetTouchEnd,
    onSheetTouchMove,
    onSheetTouchStart,
  }
}
