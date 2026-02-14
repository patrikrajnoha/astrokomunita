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

describe('PollCard', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('does not render hero preview when no option has image', () => {
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
            { id: 11, text: 'A', image_url: null, percent: 50, votes_count: 6, is_winner: false },
            { id: 12, text: 'B', image_url: null, percent: 50, votes_count: 6, is_winner: false },
          ],
        },
      },
    })

    expect(wrapper.find('[data-testid="poll-hero"]').exists()).toBe(false)
  })

  it('renders hero preview and switches on hover before vote', async () => {
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
            { id: 21, text: 'Option 1', image_url: 'https://example.com/one.jpg', percent: 60, votes_count: 6, is_winner: false },
            { id: 22, text: 'Option 2', image_url: 'https://example.com/two.jpg', percent: 40, votes_count: 4, is_winner: false },
          ],
        },
      },
    })

    const hero = () => wrapper.find('[data-testid="poll-hero"] img')
    expect(hero().attributes('src')).toBe('https://example.com/one.jpg')

    await wrapper.findAll('.pollOption')[1].trigger('mouseenter')
    expect(hero().attributes('src')).toBe('https://example.com/two.jpg')
  })

  it('after vote shows chosen hero image and result bars', () => {
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
            { id: 31, text: 'First', image_url: 'https://example.com/first.jpg', percent: 30, votes_count: 3, is_winner: false },
            { id: 32, text: 'Second', image_url: 'https://example.com/second.jpg', percent: 70, votes_count: 7, is_winner: false },
          ],
        },
      },
    })

    expect(wrapper.find('[data-testid="poll-hero"] img').attributes('src')).toBe('https://example.com/second.jpg')
    expect(wrapper.findAll('.pollFill')).toHaveLength(2)
    expect(wrapper.text()).toContain('70%')
    expect(wrapper.text()).toContain('Pocet hlasov: 10')
  })
})
