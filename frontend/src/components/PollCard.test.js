import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PollCard from '@/components/PollCard.vue'

vi.mock('@/services/api', () => ({
  default: {
    vote: vi.fn(),
    fetchPoll: vi.fn(),
  },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    warn: vi.fn(),
    error: vi.fn(),
  }),
}))

describe('PollCard responsive layout', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders mobile card wrapper with full-width option buttons', () => {
    const wrapper = mount(PollCard, {
      props: {
        isAuthed: true,
        poll: {
          id: 1,
          is_closed: false,
          total_votes: 12,
          ends_in_seconds: 3600,
          my_vote_option_id: null,
          options: [
            { id: 11, text: 'A', image_url: 'https://example.com/a.jpg', percent: 50, votes_count: 6, is_winner: false },
            { id: 12, text: 'B', image_url: null, percent: 50, votes_count: 6, is_winner: false },
          ],
        },
      },
    })

    expect(wrapper.find('[data-testid="poll-mobile-card"]').exists()).toBe(true)
    expect(wrapper.findAll('.mPollOption')).toHaveLength(2)
    expect(wrapper.find('[data-testid="poll-mobile-card"] .mPollOption').element.tagName).toBe('BUTTON')
  })

  it('keeps desktop layout structure available', () => {
    const wrapper = mount(PollCard, {
      props: {
        isAuthed: true,
        poll: {
          id: 2,
          is_closed: false,
          total_votes: 10,
          ends_in_seconds: 1000,
          my_vote_option_id: null,
          options: [
            { id: 21, text: 'Option 1', image_url: null, percent: 60, votes_count: 6, is_winner: false },
            { id: 22, text: 'Option 2', image_url: null, percent: 40, votes_count: 4, is_winner: false },
          ],
        },
      },
    })

    expect(wrapper.find('[data-testid="poll-desktop"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid="poll-desktop"] .pollOption')).toHaveLength(2)
    expect(wrapper.text()).toContain('Pocet hlasov: 10')
  })

  it('shows result fill and percents after vote in both layouts', () => {
    const wrapper = mount(PollCard, {
      props: {
        isAuthed: true,
        poll: {
          id: 3,
          is_closed: false,
          total_votes: 10,
          ends_in_seconds: 500,
          my_vote_option_id: 32,
          user_has_voted: true,
          chosen_option_id: 32,
          options: [
            { id: 31, text: 'First', image_url: null, percent: 30, votes_count: 3, is_winner: false },
            { id: 32, text: 'Second', image_url: null, percent: 70, votes_count: 7, is_winner: false },
          ],
        },
      },
    })

    expect(wrapper.findAll('.pollFill')).toHaveLength(2)
    expect(wrapper.findAll('.mPollFill')).toHaveLength(2)
    expect(wrapper.text()).toContain('70%')
  })
})
