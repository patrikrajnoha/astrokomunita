import { describe, expect, it, vi } from 'vitest'
import { resolveAvatarState } from './avatar'

vi.mock('@/services/api', () => ({
  default: {
    defaults: {
      baseURL: '/api',
    },
  },
}))

describe('resolveAvatarState', () => {
  it('uses uploaded image in image mode', () => {
    const state = resolveAvatarState({
      id: 19,
      avatar_mode: 'image',
      avatar_url: '/api/media/file/avatars/19/test.jpg',
    })

    expect(state.usesImage).toBe(true)
    expect(state.imageUrl).toContain('/api/media/file/avatars/19/test.jpg')
  })

  it('respects generated mode and does not render uploaded image', () => {
    const state = resolveAvatarState({
      id: 19,
      avatar_mode: 'generated',
      avatar_url: '/api/media/file/avatars/19/test.jpg',
    })

    expect(state.usesImage).toBe(false)
  })

  it('uses avatar_path fallback when avatar_url is missing', () => {
    const state = resolveAvatarState({
      id: 19,
      avatar_mode: 'image',
      avatar_path: 'avatars/19/test.jpg',
    })

    expect(state.usesImage).toBe(true)
    expect(state.imageUrl).toContain('/api/media/file/avatars/19/test.jpg')
  })

  it('still uses image when preview explicitly sets image mode', () => {
    const state = resolveAvatarState({
      id: 19,
      avatar_mode: 'generated',
      avatar_path: 'avatars/19/test.jpg',
    }, {
      mode: 'image',
    })

    expect(state.usesImage).toBe(true)
  })

  it('uses bot asset avatar when bot has no uploaded avatar', () => {
    const botState = resolveAvatarState({
      id: 42,
      username: 'kozmobot',
      role: 'bot',
      is_bot: true,
      avatar_mode: 'image',
      avatar_url: null,
      avatar_path: null,
    })

    expect(botState.usesImage).toBe(true)
    expect(botState.mode).toBe('image')
    expect(botState.imageUrl).toContain('/api/bot-avatars/kozmobot/kb_blue.png')
  })

  it('uses uploaded avatar for bot when image exists', () => {
    const state = resolveAvatarState({
      id: 77,
      username: 'kozmobot',
      role: 'bot',
      is_bot: true,
      avatar_mode: 'image',
      avatar_url: '/api/media/file/avatars/77/bot.png',
    })

    expect(state.usesImage).toBe(true)
    expect(state.imageUrl).toContain('/api/media/file/avatars/77/bot.png')
  })

  it('prefers explicit preview avatarUrl for bot even when persisted avatar_path differs', () => {
    const state = resolveAvatarState({
      id: 42,
      username: 'kozmobot',
      role: 'bot',
      is_bot: true,
      avatar_mode: 'image',
      avatar_path: 'bots/kozmobot/kb_blue.png',
      avatar_url: '/api/bot-avatars/kozmobot/kb_blue.png',
    }, {
      avatarUrl: '/api/bot-avatars/kozmobot/kb_red.png',
    })

    expect(state.usesImage).toBe(true)
    expect(state.imageUrl).toContain('/api/bot-avatars/kozmobot/kb_red.png')
  })

  it('keeps temporary blob preview for bot before save', () => {
    const state = resolveAvatarState({
      id: 42,
      username: 'kozmobot',
      role: 'bot',
      is_bot: true,
      avatar_mode: 'image',
      avatar_path: 'bots/kozmobot/kb_blue.png',
      avatar_url: '/api/bot-avatars/kozmobot/kb_blue.png',
    }, {
      avatarUrl: 'blob:bot-avatar-preview',
    })

    expect(state.usesImage).toBe(true)
    expect(state.imageUrl).toContain('blob:bot-avatar-preview')
  })
})
