export function pickFirstNonEmpty(valueCandidates = []) {
  for (const value of valueCandidates) {
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim()
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
