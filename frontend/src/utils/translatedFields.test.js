import { describe, expect, it } from 'vitest'
import {
  candidateDisplayShort,
  eventDisplayDescription,
  eventDisplayTitle,
  repairUtf8Mojibake,
} from './translatedFields'

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

  it('repairs mojibake in event titles', () => {
    const event = {
      translated_title: 'AstronomickÃ¡ udalosÅ¥',
      translated_description: 'Pozorovanie Mesiaca počas noci.',
    }

    expect(eventDisplayTitle(event)).toBe('Astronomická udalosť')
    expect(eventDisplayDescription(event)).toBe('Pozorovanie Mesiaca počas noci.')
  })

  it('keeps clean utf-8 text unchanged', () => {
    expect(repairUtf8Mojibake('Planéta Venuša')).toBe('Planéta Venuša')
  })

  it('repairs double-encoded mojibake strings from api payloads', () => {
    expect(repairUtf8Mojibake('MeteorickÃƒÂ½ roj Lyrid')).toBe('Meteorický roj Lyrid')
  })
})
