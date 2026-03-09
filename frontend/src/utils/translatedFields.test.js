import { describe, expect, it } from 'vitest'
import { candidateDisplayShort } from './translatedFields'

describe('translatedFields', () => {
  it('prefers translated description over raw short for candidates', () => {
    const value = candidateDisplayShort({
      short: 'The Leonids are best known for producing meteor storms.',
      translated_description: 'Leonidy su meteoricky roj.',
    })

    expect(value).toBe('Leonidy su meteoricky roj.')
  })

  it('falls back to short when translated text is missing', () => {
    const value = candidateDisplayShort({
      short: 'Kratky popis',
    })

    expect(value).toBe('Kratky popis')
  })
})
