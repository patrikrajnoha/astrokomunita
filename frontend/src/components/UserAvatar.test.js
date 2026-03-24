import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import UserAvatar from './UserAvatar.vue'

function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

describe('UserAvatar', () => {
  it('falls back to default bot asset when bot image fails', async () => {
    const wrapper = mount(UserAvatar, {
      props: {
        user: {
          id: 6,
          username: 'stellarbot',
          role: 'bot',
          is_bot: true,
          avatar_mode: 'image',
          avatar_path: 'bots/stellarbot/sb_blue.png',
        },
        avatarUrl: '/api/bot-avatars/stellarbot/missing.png',
      },
    })

    const image = wrapper.get('img.user-avatar-media')
    expect(image.attributes('src')).toContain('/api/bot-avatars/stellarbot/missing.png')

    await image.trigger('error')
    await flush()

    const retried = wrapper.get('img.user-avatar-media')
    expect(retried.attributes('src')).toContain('/api/bot-avatars/stellarbot/sb_blue.png')
  })

  it('keeps generated fallback for non-bot when image fails', async () => {
    const wrapper = mount(UserAvatar, {
      props: {
        user: {
          id: 11,
          username: 'regular-user',
          role: 'user',
          is_bot: false,
          avatar_mode: 'image',
        },
        avatarUrl: '/api/bot-avatars/non-bot/missing.png',
      },
    })

    const image = wrapper.get('img.user-avatar-media')
    await image.trigger('error')
    await flush()

    expect(wrapper.find('img.user-avatar-media').exists()).toBe(false)
    expect(wrapper.find('.default-avatar').exists()).toBe(true)
  })
})
