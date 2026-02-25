import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EventCard from '@/components/events/EventCard.vue'

describe('EventCard', () => {
  it('renders verified badge and confidence tooltip reason', () => {
    const wrapper = mount(EventCard, {
      props: {
        event: {
          title: 'Lyrids',
          description: 'Meteoricky roj',
          public_confidence: {
            level: 'verified',
            reason: 'Skore 82/100, potvrdene 3 zdrojmi.',
            score: 82,
            sources_count: 3,
          },
        },
      },
    })

    const badge = wrapper.find('.confidence-badge')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toContain('Overene')
    expect(badge.attributes('title')).toContain('Skore 82/100, potvrdene 3 zdrojmi.')
  })
})
