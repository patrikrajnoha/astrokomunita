import { describe, it, expect } from 'vitest'
import { formatPollRemainingSk } from '@/utils/pollTime'

describe('formatPollRemainingSk', () => {
  it('formats under hour in minutes', () => {
    expect(formatPollRemainingSk(12 * 60)).toBe('12 min.')
  })

  it('formats hour and minutes', () => {
    expect(formatPollRemainingSk((19 * 60 + 47) * 60)).toBe('19 hod. 47 min.')
  })

  it('formats singular day', () => {
    expect(formatPollRemainingSk(24 * 60 * 60)).toBe('1 den')
  })

  it('formats days and hours', () => {
    expect(formatPollRemainingSk((2 * 24 * 60 + 3 * 60) * 60)).toBe('2 dni 3 hod.')
  })
})
