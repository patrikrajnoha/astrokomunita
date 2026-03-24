import { describe, expect, it } from 'vitest'
import { canDeletePost, canReportPost } from './postPermissions'

describe('postPermissions', () => {
  it('allows owner or admin to delete post', () => {
    expect(canDeletePost({ user_id: 9 }, { id: 9, role: 'user' })).toBe(true)
    expect(canDeletePost({ user_id: 9 }, { id: 1, role: 'admin' })).toBe(true)
    expect(canDeletePost({ user_id: 9 }, { id: 1, is_admin: true })).toBe(true)
    expect(canDeletePost({ user_id: 9 }, { id: 1, role: 'user' })).toBe(false)
  })

  it('blocks reporting bot-authored posts', () => {
    expect(canReportPost({ user: { is_bot: true } }, { id: 1 })).toBe(false)
    expect(canReportPost({ user: { role: 'bot' } }, { id: 1 })).toBe(false)
    expect(canReportPost({ author_kind: 'bot' }, { id: 1 })).toBe(false)
    expect(canReportPost({ source_name: 'nasa_rss' }, { id: 1 })).toBe(false)
    expect(canReportPost({ bot_identity: 'kozmo' }, { id: 1 })).toBe(false)
  })

  it('allows reporting regular post for guest or non-owner', () => {
    const regularPost = {
      user_id: 9,
      user: { is_bot: false, role: 'user' },
      source_name: 'manual',
    }

    expect(canReportPost(regularPost, null)).toBe(true)
    expect(canReportPost(regularPost, { id: 1 })).toBe(true)
    expect(canReportPost(regularPost, { id: 9 })).toBe(false)
  })
})

