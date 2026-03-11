import { describe, expect, it } from 'vitest'
import {
  normalizeBotDisplayText,
  resolvedDisplayText,
} from './feedListBotContent.utils'

describe('feedListBotContent whitespace normalization', () => {
  it('normalizes bot text tabs and repeated spaces while keeping paragraph breaks', () => {
    const input = 'Curiosity\t\tje\u00a0v  pohybe  \n  Dalsi\triadok\n\n\nKoniec'
    expect(normalizeBotDisplayText(input)).toBe('Curiosity je v pohybe\nDalsi riadok\n\nKoniec')
  })

  it('normalizes translated bot variant returned by resolvedDisplayText', () => {
    const post = {
      id: 11,
      author_kind: 'bot',
      meta: {
        original_title: 'EN title',
        original_content: 'EN content',
        translated_title: 'CG 4:\t\tGlobule  a  Galaxia',
        translated_content: 'Je\t toto    test.',
        used_translation: true,
      },
    }

    expect(resolvedDisplayText(post)).toBe('CG 4: Globule a Galaxia\n\nJe toto test.')
  })

  it('keeps user post content unchanged', () => {
    const post = {
      id: 12,
      author_kind: 'user',
      content: 'A\t  B',
    }

    expect(resolvedDisplayText(post)).toBe('A\t  B')
  })
})
