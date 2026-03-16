export const BASE_NOTIFICATION_PREFERENCE_KEYS = [
  'post_like',
  'post_comment',
  'reply',
  'system',
]

export const EVENT_REMINDER_PREFERENCE_ROWS = [
  {
    key: 'event_reminder',
    label: 'Vsetky ulozene udalosti',
    description: 'Zakladne pripomienky pre eventy, ktore sledujes alebo si si ulozil.',
  },
  {
    key: 'event_reminder_meteors',
    label: 'Meteory a roje',
    description: 'Maximum rojov, aktivne meteoricke noci a podobne ukazy.',
  },
  {
    key: 'event_reminder_eclipses',
    label: 'Zatmenia',
    description: 'Slnecne aj mesacne zatmenia a ich lokalne pripomienky.',
  },
  {
    key: 'event_reminder_planetary',
    label: 'Planety a konjunkcie',
    description: 'Planetarne javy, opozicie a tesne stretnutia objektov.',
  },
  {
    key: 'event_reminder_small_bodies',
    label: 'Komety a asteroidy',
    description: 'Prelety komet, asteroidy a ine male telesa.',
  },
  {
    key: 'event_reminder_aurora',
    label: 'Polarna ziara',
    description: 'Eventy a highlights spojene s aurorou.',
  },
  {
    key: 'event_reminder_space',
    label: 'Misie a starty',
    description: 'Vesmirne misie, starty a ine space eventy.',
  },
  {
    key: 'event_reminder_observing',
    label: 'Pozorovacie okna',
    description: 'Specialne casove okna vhodne na pozorovanie oblohy.',
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
