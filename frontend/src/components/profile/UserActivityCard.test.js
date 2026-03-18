import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UserActivityCard from './UserActivityCard.vue'

describe('UserActivityCard', () => {
  it('renders activity values', () => {
    const wrapper = mount(UserActivityCard, {
      props: {
        loading: false,
        activity: {
          last_login_at: '2026-02-21T17:00:00Z',
          posts_count: 123,
          event_participations_count: 7,
        },
      },
    })

    expect(wrapper.get('[data-testid="posts-count"]').text()).toContain('123')
    expect(wrapper.get('[data-testid="participations-count"]').text()).toContain('7')
    expect(wrapper.get('[data-testid="last-login"]').text()).not.toContain('Zatiaľ nezaznamenané')
  })

  it('renders fallback when last login is null', () => {
    const wrapper = mount(UserActivityCard, {
      props: {
        loading: false,
        activity: {
          last_login_at: null,
          posts_count: 4,
          event_participations_count: 2,
        },
      },
    })

    expect(wrapper.get('[data-testid="last-login"]').text()).toBe('Zatiaľ nezaznamenané')
  })

  it('renders loading skeleton', () => {
    const wrapper = mount(UserActivityCard, {
      props: {
        loading: true,
      },
    })

    expect(wrapper.find('[data-testid="activity-loading"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="activity-values"]').exists()).toBe(false)
  })
})
