import { EVENT_TIMEZONE, formatEventDate, resolveEventTimeContext } from '@/utils/eventTime'
import { eventDisplayShort, eventDisplayTitle } from '@/utils/translatedFields'

const STAR_NAMES = new Set([
  'Regulus', 'Pollux', 'Castor', 'Spica', 'Spika', 'Antares', 'Aldebaran',
  'Arcturus', 'Sirius', 'Vega', 'Deneb', 'Altair', 'Betelgeuse',
  'Rigel', 'Capella', 'Procyon', 'Canopus', 'Fomalhaut', 'Achernar',
  'Algol', 'Mira', 'Elnath', 'Mimosa', 'Hadar', 'Acrux', 'Gacrux',
  'Alnilam', 'Alnitak', 'Mintaka', 'Bellatrix', 'Saiph', 'Nunki',
  'Zubenelgenubi', 'Zubeneschamali', 'Alpheratz', 'Mirfak', 'Algenib',
  'Menkar', 'Menkib', 'Sheratan', 'Hamal', 'Almach', 'Mirach',
])

function normalizeStarNameForDisplay(value) {
  if (typeof value !== 'string' || value === '') return value
  return value
    .replace(/\bSpica\b/g, 'Spika')
    .replace(/\bspica\b/g, 'spika')
}

export function prependStarLabel(title) {
  const normalizedTitle = normalizeStarNameForDisplay(title)
  if (!normalizedTitle || normalizedTitle === '-') return normalizedTitle
  const firstWord = normalizedTitle.split(/[\s,°]/)[0]
  if (STAR_NAMES.has(firstWord)) return `Hviezda ${normalizedTitle}`
  return normalizedTitle
}

export function formatEventCardTitle(eventItem) {
  return prependStarLabel(eventDisplayTitle(eventItem))
}

export function regionLabel(region) {
  const map = { sk: 'Slovensko', eu: 'Europa', global: 'Globalne' }
  return map[region] || region || '-'
}

export function typeLabel(type) {
  const map = {
    meteors: 'Meteory',
    meteor_shower: 'Meteorický roj',
    eclipse: 'Zatmenie',
    eclipse_lunar: 'Zatmenie (L)',
    eclipse_solar: 'Zatmenie (S)',
    conjunction: 'Konjunkcia',
    planetary_event: 'Planetárny úkaz',
    aurora: 'Polárna žiara',
    comet: 'Kométa',
    asteroid: 'Asteroid',
    mission: 'Misia',
    other: 'Iné',
  }

  return map[type] || type
}

export function eventTypeIcon(eventItem) {
  return resolveEventTypePresentation(eventItem).icon
}

export function eventTypeIconLabel(eventItem) {
  return resolveEventTypePresentation(eventItem).label
}

export function publicConfidenceBadgeLabel(eventItem) {
  const level = eventItem?.public_confidence?.level
  if (!level || level === 'unknown') return ''
  if (level === 'verified') return 'Overené'
  if (level === 'partial') return 'Čiastočné'
  if (level === 'low') return 'Nizka dovera'
  return ''
}

export function publicConfidenceTooltip(eventItem) {
  const confidence = eventItem?.public_confidence
  if (!confidence) return ''
  if (confidence.level === 'unknown') return 'Nie sú dostupné údaje o dôveryhodnosti.'

  if (typeof confidence.score === 'number' && typeof confidence.sources_count === 'number') {
    return `${confidence.reason} Skore: ${confidence.score}/100 | Zdrojov: ${confidence.sources_count}`
  }

  return confidence.reason || 'Nie sú dostupné údaje o dôveryhodnosti.'
}

export function eventCardSummary(eventItem) {
  const summary = normalizeStarNameForDisplay(eventDisplayShort(eventItem))
  if (!summary || summary === '-') return ''

  const normalizedSummary = normalizeText(summary)
  const normalizedTitle = normalizeText(normalizeStarNameForDisplay(eventDisplayTitle(eventItem)))

  if (!normalizedSummary || normalizedSummary === normalizedTitle) return ''
  if (isRedundantEventSummary(summary)) return ''

  return summary
}

export function formatCardDate(value) {
  return formatEventDate(value, EVENT_TIMEZONE, {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
  })
}

export function eventCardTimeContext(eventItem) {
  return resolveEventTimeContext(eventItem, EVENT_TIMEZONE)
}

export function eventCardTimeMessage(eventItem) {
  return eventCardTimeContext(eventItem).message
}

export function eventCardTimeTimezoneLabel(eventItem) {
  const context = eventCardTimeContext(eventItem)
  return context.showTimezoneLabel ? context.timezoneLabelShort : ''
}

export function eventCardTimeAriaLabel(eventItem) {
  const context = eventCardTimeContext(eventItem)
  if (!context.showTimezoneLabel) {
    return context.message
  }

  return `${context.message} (${context.timezoneLabelShort}), cas v ${context.timezoneLabelLong}`
}

export function shouldShowRegion(region) {
  return Boolean(region && region !== 'global')
}

function resolveEventTypePresentation(eventItem) {
  const customIcon = resolveCustomEventIcon(eventItem)
  if (customIcon) {
    return {
      icon: customIcon,
      label: 'Vlastná ikona udalosti',
    }
  }

  const type = normalizeText(eventItem?.type)
  const title = normalizeText(eventDisplayTitle(eventItem))

  if (looksLikeMoonEvent(type, title)) {
    return {
      icon: '🌙',
      label: 'Udalosť súvisiaca s Mesiacom',
    }
  }

  if (type === 'comet' || title.includes('komet')) {
    return {
      icon: '☄️',
      label: 'Kometa',
    }
  }

  if (type === 'meteors' || type === 'meteor_shower' || title.includes('meteor')) {
    return {
      icon: '☄️',
      label: 'Meteoricky roj',
    }
  }

  if (
    type === 'eclipse'
    || type === 'eclipse_lunar'
    || type === 'eclipse_solar'
    || title.includes('zatmen')
  ) {
    return {
      icon: '🌘',
      label: 'Zatmenie',
    }
  }

  if (type === 'asteroid' || title.includes('asteroid')) {
    return {
      icon: '🛰️',
      label: 'Asteroid',
    }
  }

  if (type === 'aurora' || title.includes('aurora') || title.includes('polarna ziara')) {
    return {
      icon: '🌌',
      label: 'Polarna ziara',
    }
  }

  if (type === 'mission' || title.includes('misia') || title.includes('launch')) {
    return {
      icon: '🚀',
      label: 'Misia',
    }
  }

  if (looksLikePlanetEvent(type, title)) {
    return {
      icon: '🪐',
      label: 'Planetarny ukaz',
    }
  }

  return {
    icon: '✨',
    label: 'Astronomicka udalost',
  }
}

function resolveCustomEventIcon(eventItem) {
  const candidates = [
    eventItem?.icon_emoji,
    eventItem?.icon,
  ]

  for (const candidate of candidates) {
    if (typeof candidate !== 'string') continue
    const trimmed = candidate.trim()
    if (!trimmed) continue
    return Array.from(trimmed).slice(0, 4).join('')
  }

  return ''
}

function normalizeText(value) {
  if (typeof value !== 'string') return ''
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim()
}

function isRedundantEventSummary(summary) {
  const normalized = normalizeText(summary)
  if (!normalized) return true

  return /^(priblizne|orientacne|okolo|cca)?\s*\d{1,2}\.\s*\d{1,2}\.\s*\d{2,4}(\s+\d{1,2}:\d{2})?$/.test(
    normalized,
  )
}

function looksLikeMoonEvent(type, title) {
  if (type === 'eclipse_lunar') return true
  if (title.includes('mesiac')) return true
  if (title.includes('moon')) return true
  if (title.includes('nov') || title.includes('spln') || title.includes('stvrt')) return true
  return false
}

function looksLikePlanetEvent(type, title) {
  if (type === 'conjunction' || type === 'planetary_event') return true

  const planetKeywords = [
    'mars',
    'jupiter',
    'saturn',
    'venus',
    'venusa',
    'venera',
    'merkur',
    'uran',
    'neptun',
    'pluto',
  ]

  return planetKeywords.some((keyword) => title.includes(keyword))
}
