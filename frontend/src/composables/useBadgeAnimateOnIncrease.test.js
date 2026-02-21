import { describe, expect, it } from 'vitest'
import { shouldAnimateOnIncrease } from './useBadgeAnimateOnIncrease'

describe('shouldAnimateOnIncrease', () => {
  it('animates only when unread count increases', () => {
    expect(shouldAnimateOnIncrease(0, 1)).toBe(true)
    expect(shouldAnimateOnIncrease(1, 2)).toBe(true)
    expect(shouldAnimateOnIncrease(2, 1)).toBe(false)
    expect(shouldAnimateOnIncrease(undefined, 2)).toBe(false)
  })
})
