import { describe, it, expect } from 'vitest'
import { BOT_FAILURE_REASON_MESSAGES, BOT_FAILURE_REASONS_BACKEND } from '@/constants/botFailureReasons'

describe('botFailureReasons', () => {
  it('has user-facing message for every backend failure reason', () => {
    for (const reason of BOT_FAILURE_REASONS_BACKEND) {
      expect(typeof BOT_FAILURE_REASON_MESSAGES[reason]).toBe('string')
      expect(BOT_FAILURE_REASON_MESSAGES[reason].length).toBeGreaterThan(0)
    }
  })
})
