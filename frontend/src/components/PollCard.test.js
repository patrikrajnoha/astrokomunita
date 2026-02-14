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

  it('renders before-vote state with thumbnails', () => {
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

    expect(wrapper.findAll('.pollOption')).toHaveLength(2)
    expect(wrapper.text()).toContain('Pocet hlasov: 12')
    expect(wrapper.find('.pollThumb').exists()).toBe(true)
    expect(wrapper.find('.pollPercent').exists()).toBe(false)
  })

  it('after vote shows percent and checkmark', () => {
    const wrapper = mount(PollCard, {
      props: {
        isAuthed: true,
        poll: {
          id: 2,
          is_closed: false,
          total_votes: 10,
          ends_in_seconds: 1000,
          my_vote_option_id: 21,
          options: [
            { id: 21, text: 'Yes', image_url: null, percent: 60, votes_count: 6, is_winner: false },
            { id: 22, text: 'No', image_url: null, percent: 40, votes_count: 4, is_winner: false },
          ],
        },
      },
    })

    expect(wrapper.text()).toContain('60%')
    expect(wrapper.text()).toContain('40%')
    expect(wrapper.find('.pollCheck').exists()).toBe(true)
  })

  it('closed poll shows winner highlight', () => {
    const wrapper = mount(PollCard, {
      props: {
        isAuthed: true,
        poll: {
          id: 3,
          is_closed: true,
          total_votes: 10,
          ends_in_seconds: 0,
          my_vote_option_id: null,
          options: [
            { id: 31, text: 'Winner', image_url: null, percent: 70, votes_count: 7, is_winner: true },
            { id: 32, text: 'Loser', image_url: null, percent: 30, votes_count: 3, is_winner: false },
          ],
        },
      },
    })

    expect(wrapper.text()).toContain('Vitaz')
    expect(wrapper.find('.pollOption--winner').exists()).toBe(true)
  })
})

