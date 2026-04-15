import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SharedPostPreview from '@/components/SharedPostPreview.vue'

describe('SharedPostPreview', () => {
  it('renders a nested shared post poll and updates it in place', async () => {
    const post = {
      id: 10,
      content: 'Repost',
      shared_post: {
        id: 11,
        content: 'Original poll post',
        user: { username: 'astrofan' },
        poll: {
          id: 22,
          options: [
            { id: 1, text: 'Mars' },
            { id: 2, text: 'Saturn' },
          ],
        },
      },
    }

    const wrapper = mount(SharedPostPreview, {
      props: {
        post,
        isAuthed: true,
      },
      global: {
        stubs: {
          HashtagText: {
            props: ['content'],
            template: '<div class="shared-text">{{ content }}</div>',
          },
          PollCard: {
            props: ['poll'],
            emits: ['updated'],
            template: '<button class="poll-stub" @click="$emit(\'updated\', { ...poll, updated: true })">{{ poll.id }}</button>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Zdielany prispevok')
    expect(wrapper.text()).toContain('@astrofan')
    expect(wrapper.text()).toContain('Original poll post')
    expect(wrapper.find('.poll-stub').exists()).toBe(true)

    await wrapper.find('.poll-stub').trigger('click')

    expect(post.shared_post.poll.updated).toBe(true)
  })
})
