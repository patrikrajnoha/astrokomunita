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

  it('applies deterministic kozmobot preset when bot has no uploaded avatar', () => {
    const state = resolveAvatarState({
      id: 42,
      username: 'kozmobot',
      role: 'bot',
      is_bot: true,
      avatar_mode: 'image',
      avatar_url: null,
      avatar_path: null,
    })

    expect(state.usesImage).toBe(false)
    expect(state.mode).toBe('generated')
    expect(state.colorIndex).toBe(3)
    expect(state.iconIndex).toBe(0)
  })

  it('applies deterministic stellarbot preset when bot has no uploaded avatar', () => {
    const state = resolveAvatarState({
      id: 43,
      username: 'stellarbot',
      role: 'bot',
      is_bot: true,
      avatar_mode: 'image',
      avatar_url: null,
      avatar_path: null,
    })

    expect(state.usesImage).toBe(false)
    expect(state.mode).toBe('generated')
    expect(state.colorIndex).toBe(4)
    expect(state.iconIndex).toBe(2)
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
})
