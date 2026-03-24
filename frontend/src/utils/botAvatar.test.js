import { describe, expect, it, vi } from 'vitest'
import { getBotAvatar, mapColorToFile } from './botAvatar'

vi.mock('@/services/api', () => ({
  default: {
    defaults: {
      baseURL: '/api',
    },
  },
}))

describe('botAvatar helper', () => {
  it('maps color to stellarbot filename', () => {
    expect(mapColorToFile('blue', 'stellarbot')).toBe('sb_blue.png')
    expect(mapColorToFile('red', 'stellarbot')).toBe('sb_red.png')
  })

  it('maps color index to kozmobot filename', () => {
    expect(mapColorToFile(3, 'kozmobot')).toBe('kb_blue.png')
    expect(mapColorToFile(5, 'kozmobot')).toBe('kb_red.png')
  })

  it('returns fallback bot avatar path for bot account', () => {
    const botAvatar = getBotAvatar({
      username: 'kozmobot',
      role: 'bot',
      is_bot: true,
      avatar_path: null,
      avatar_url: null,
    })

    expect(botAvatar?.path).toBe('bots/kozmobot/kb_blue.png')
    expect(botAvatar?.url).toContain('/api/bot-avatars/kozmobot/kb_blue.png')
  })

  it('keeps deterministic default blue file when bot has no avatar_path', () => {
    const botAvatar = getBotAvatar({
      username: 'stellarbot',
      role: 'bot',
      is_bot: true,
      avatar_path: null,
      avatar_color: 5,
    })

    expect(botAvatar?.file).toBe('sb_blue.png')
    expect(botAvatar?.path).toBe('bots/stellarbot/sb_blue.png')
    expect(botAvatar?.url).toContain('/api/bot-avatars/stellarbot/sb_blue.png')
  })

  it('keeps uploaded custom avatar for bot account', () => {
    const botAvatar = getBotAvatar({
      username: 'kozmobot',
      role: 'bot',
      is_bot: true,
      avatar_path: 'avatars/42/custom.png',
      avatar_url: '/api/media/file/avatars/42/custom.png',
    })

    expect(botAvatar?.isCustomUpload).toBe(true)
    expect(botAvatar?.path).toBe('avatars/42/custom.png')
    expect(botAvatar?.url).toContain('/api/media/file/avatars/42/custom.png')
  })
})
