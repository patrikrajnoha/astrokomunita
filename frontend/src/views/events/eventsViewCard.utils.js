import { EVENT_TIMEZONE, formatEventDate, resolveEventTimeContext } from '@/utils/eventTime'
import { eventDisplayShort, eventDisplayTitle } from '@/utils/translatedFields'

export function regionLabel(region) {
  const map = { sk: 'Slovensko', eu: 'Europa', global: 'Globalne' }
  return map[region] || region || '-'
}

export function typeLabel(type) {
  const map = {
    meteors: 'Meteory',
    meteor_shower: 'Meteoricky roj',
    eclipse: 'Zatmenie',
    eclipse_lunar: 'Zatmenie (L)',
    eclipse_solar: 'Zatmenie (S)',
    conjunction: 'Konjunkcia',
    planetary_event: 'Planetarny ukaz',
    comet: 'Kometa',
    asteroid: 'Asteroid',
    mission: 'Misia',
    other: 'Ine',
  }

  return map[type] || type
}

export function publicConfidenceBadgeLabel(eventItem) {
  const level = eventItem?.public_confidence?.level
  if (!level || level === 'unknown') return ''
  if (level === 'verified') return 'Overene'
  if (level === 'partial') return 'Ciastocne'
  if (level === 'low') return 'Nizka dovera'
  return ''
}

export function publicConfidenceTooltip(eventItem) {
  const confidence = eventItem?.public_confidence
  if (!confidence) return ''
  if (confidence.level === 'unknown') return 'Nie su dostupne udaje o doveryhodnosti.'

  if (typeof confidence.score === 'number' && typeof confidence.sources_count === 'number') {
    return `${confidence.reason} Skore: ${confidence.score}/100 | Zdrojov: ${confidence.sources_count}`
  }

  return confidence.reason || 'Nie su dostupne udaje o doveryhodnosti.'
}

export function eventCardSummary(eventItem) {
  const summary = eventDisplayShort(eventItem)
  if (!summary || summary === '-') return ''

  const normalizedSummary = normalizeText(summary)
  const normalizedTitle = normalizeText(eventDisplayTitle(eventItem))

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
