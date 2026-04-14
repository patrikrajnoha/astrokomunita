const MOJIBAKE_MARKERS = /[\u00C2\u00C3\u00C4\u00C5]/
const SLOVAK_DIACRITICS = /[\u00E1\u00E4\u010D\u010F\u00E9\u00ED\u013A\u013E\u0148\u00F3\u00F4\u0155\u0161\u0165\u00FA\u00FD\u017E\u00C1\u00C4\u010C\u010E\u00C9\u00CD\u0139\u013D\u0147\u00D3\u00D4\u0154\u0160\u0164\u00DA\u00DD\u017D]/

function countSlovakDiacritics(value) {
  return Array.from(String(value || '')).filter((char) => SLOVAK_DIACRITICS.test(char)).length
}

function repairCp1250Mojibake(value) {
  return String(value || '')
    .replaceAll('\u00C4\u015A', '\u010C') // ÄŚ -> Č
    .replaceAll('\u00C4\u0164', '\u010D') // ÄŤ -> č
    .replaceAll('\u00C4\u017D', '\u010E') // ÄŽ -> Ď
    .replaceAll('\u00C4\u0179', '\u010F') // ÄŹ -> ď
    .replaceAll('\u00C4\u013E', '\u013E') // Äľ -> ľ
    .replaceAll('\u00C5\u02C7', '\u0161') // Åˇ -> š
    .replaceAll('\u00C5\u00A5', '\u0165') // Å¥ -> ť
    .replaceAll('\u00C5\u013E', '\u017E') // Åľ -> ž
}

function collapseDoubleEncodedUtf8Mojibake(value) {
  let repaired = String(value || '')
  repaired = repaired
    .replaceAll('\u00C3\u0192\u00C2', '\u00C3') // ÃƒÂx -> Ãx
    .replaceAll('\u00C3\u0192\u00C4', '\u00C4') // ÃƒÄx -> Äx
    .replaceAll('\u00C3\u0192\u00C5', '\u00C5') // ÃƒÅx -> Åx

  return repaired
}

function repairLatin1Mojibake(value) {
  const input = collapseDoubleEncodedUtf8Mojibake(value)
  const hasNonLatin1Char = Array.from(input).some((char) => char.charCodeAt(0) > 0xff)
  if (hasNonLatin1Char || !MOJIBAKE_MARKERS.test(input)) return input

  try {
    const bytes = Uint8Array.from(input, (char) => char.charCodeAt(0) & 0xff)
    const decoder = new TextDecoder('utf-8', { fatal: true })
    const decoded = decoder.decode(bytes)
    if (!decoded || MOJIBAKE_MARKERS.test(decoded)) return input
    return decoded
  } catch {
    return input
  }
}

export function repairUtf8Mojibake(value) {
  if (typeof value !== 'string') return ''

  const input = value.trim()
  if (input === '') return input

  let repaired = repairLatin1Mojibake(input)
  repaired = repairCp1250Mojibake(repaired)

  const inputDiacritics = countSlovakDiacritics(input)
  const repairedDiacritics = countSlovakDiacritics(repaired)
  return repairedDiacritics >= inputDiacritics ? repaired : input
}

export function pickFirstNonEmpty(valueCandidates = []) {
  for (const value of valueCandidates) {
    if (typeof value === 'string' && value.trim() !== '') {
      return repairUtf8Mojibake(value)
    }
  }

  return ''
}

export function candidateDisplayTitle(candidate) {
  return pickFirstNonEmpty([
    candidate?.translated_title,
    candidate?.title_translated,
    candidate?.title_sk,
    candidate?.title,
  ]) || '-'
}

export function candidateDisplayDescription(candidate) {
  return pickFirstNonEmpty([
    candidate?.translated_description,
    candidate?.description_translated,
    candidate?.description_sk,
    candidate?.description,
  ]) || '-'
}

export function candidateDisplayShort(candidate) {
  return pickFirstNonEmpty([
    candidate?.short_translated,
    candidate?.translated_short,
    candidate?.short_sk,
    candidate?.translated_description,
    candidate?.description_translated,
    candidate?.description_sk,
    candidate?.short,
    candidateDisplayDescription(candidate),
  ]) || '-'
}

export function eventDisplayTitle(event) {
  return pickFirstNonEmpty([
    event?.translated_title,
    event?.title_translated,
    event?.title_sk,
    event?.title,
  ]) || '-'
}

export function eventDisplayShort(event) {
  return pickFirstNonEmpty([
    event?.translated_short,
    event?.short_translated,
    event?.short_sk,
    event?.short,
    eventDisplayDescription(event),
  ]) || '-'
}

export function eventDisplayDescription(event) {
  return pickFirstNonEmpty([
    event?.translated_description,
    event?.description_translated,
    event?.description_sk,
    event?.description,
  ]) || '-'
}
