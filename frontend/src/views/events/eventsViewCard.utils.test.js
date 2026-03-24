import { describe, expect, it } from 'vitest'
import {
  eventCardSummary,
  eventTypeIcon,
  eventTypeIconLabel,
  formatEventCardTitle,
} from './eventsViewCard.utils'

describe('eventsViewCard utils', () => {
  it('uses custom icon when icon_emoji is provided', () => {
    const eventItem = {
      title: 'Manual icon event',
      type: 'other',
      icon_emoji: '\u{1F319}',
    }

    expect(eventTypeIcon(eventItem)).toBe('\u{1F319}')
    expect(eventTypeIconLabel(eventItem)).toBe('Vlastná ikona udalosti')
  })

  it('falls back to type-based icon when custom icon is missing', () => {
    const eventItem = {
      title: 'Meteor event',
      type: 'meteor_shower',
    }

    expect(eventTypeIcon(eventItem)).toBeTruthy()
    expect(eventTypeIconLabel(eventItem)).toBe('Meteoricky roj')
  })

  it('normalizes Spica to Spika in event title and summary', () => {
    const eventItem = {
      title: 'Spica 1.8°N of Moon',
      short: 'Spica bude blizko Mesiaca',
      type: 'other',
    }

    expect(formatEventCardTitle(eventItem)).toBe('Hviezda Spika 1.8°N of Moon')
    expect(eventCardSummary(eventItem)).toBe('Spika bude blizko Mesiaca')
  })
})
