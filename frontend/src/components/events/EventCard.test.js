import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EventCard from '@/components/events/EventCard.vue'

describe('EventCard', () => {
  it('renders verified badge with diacritics and normalized tooltip', () => {
    const wrapper = mount(EventCard, {
      props: {
        event: {
          title: 'Lyrids',
          description: 'Meteoricky roj',
          public_confidence: {
            level: 'verified',
            reason: 'Potvrdené viacerými zdrojmi.',
            score: 82,
            sources_count: 3,
          },
        },
      },
    })

    const badge = wrapper.find('.confidence-badge')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toContain('Overené')
    expect(badge.attributes('title')).toContain('Skóre: 82/100')
    expect(badge.attributes('title')).toContain('Zdrojov: 3')
  })

  it('does not render badge for unknown confidence level', () => {
    const wrapper = mount(EventCard, {
      props: {
        event: {
          title: 'Lyrids',
          description: 'Meteoricky roj',
          public_confidence: {
            level: 'unknown',
            reason: 'Nie sú dostupné údaje o dôveryhodnosti.',
            score: null,
            sources_count: null,
          },
        },
      },
    })

    expect(wrapper.find('.confidence-badge').exists()).toBe(false)
  })
})
