export const BASE_NOTIFICATION_PREFERENCE_KEYS = [
  'post_like',
  'post_comment',
  'reply',
  'system',
]

export const EVENT_REMINDER_PREFERENCE_ROWS = [
  {
    key: 'event_reminder',
    label: 'Všetky uložené udalosti',
    description: 'Základné pripomienky pre udalosti, ktoré sleduješ alebo si si uložil.',
  },
  {
    key: 'event_reminder_meteors',
    label: 'Meteory a roje',
    description: 'Maximum rojov, aktívne meteoritické noci a podobné úkazy.',
  },
  {
    key: 'event_reminder_eclipses',
    label: 'Zatmenia',
    description: 'Slnečné aj mesačné zatmenia a ich lokálne pripomienky.',
  },
  {
    key: 'event_reminder_planetary',
    label: 'Planety a konjunkcie',
    description: 'Planetárne javy, opozície a tesné stretnutia objektov.',
  },
  {
    key: 'event_reminder_small_bodies',
    label: 'Komety a asteroidy',
    description: 'Prelety komét, asteroidy a iné malé telesá.',
  },
  {
    key: 'event_reminder_aurora',
    label: 'Polárna žiara',
    description: 'Udalosti a aktivity spojené s aurorou.',
  },
  {
    key: 'event_reminder_space',
    label: 'Misie a štarty',
    description: 'Vesmírne misie, štarty a iné kozmické udalosti.',
  },
  {
    key: 'event_reminder_observing',
    label: 'Pozorovacie okná',
    description: 'Špeciálne časové okná vhodné na pozorovanie oblohy.',
  },
]

export const ALL_NOTIFICATION_PREFERENCE_KEYS = [
  ...BASE_NOTIFICATION_PREFERENCE_KEYS,
  ...EVENT_REMINDER_PREFERENCE_ROWS.map((row) => row.key),
]

export function buildNotificationPreferenceMap(defaultValue) {
  return ALL_NOTIFICATION_PREFERENCE_KEYS.reduce((acc, key) => {
    acc[key] = Boolean(defaultValue)
    return acc
  }, {})
}

export function normalizeNotificationPreferenceMap(raw, defaultValue) {
  const fallback = buildNotificationPreferenceMap(defaultValue)
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
    return fallback
  }

  return ALL_NOTIFICATION_PREFERENCE_KEYS.reduce((acc, key) => {
    acc[key] = typeof raw[key] === 'boolean' ? raw[key] : fallback[key]
    return acc
  }, {})
}
