export const ADMIN_SECTION_KEYS = {
  EVENTS: 'events',
  COMMUNITY: 'community',
  CONTENT: 'content',
}

export const ADMIN_SECTION_CONFIG = {
  [ADMIN_SECTION_KEYS.EVENTS]: {
    key: ADMIN_SECTION_KEYS.EVENTS,
    label: 'Event Pipeline',
    tabs: [
      { key: 'crawling', label: 'Crawling', to: { name: 'admin.event-sources' } },
      { key: 'candidates', label: 'Kandidáti', to: { name: 'admin.event-candidates' } },
      { key: 'published', label: 'Publikované', to: { name: 'admin.events' } },
    ],
  },
  [ADMIN_SECTION_KEYS.COMMUNITY]: {
    key: ADMIN_SECTION_KEYS.COMMUNITY,
    label: 'Správa komunity',
    tabs: [
      { key: 'users', label: 'Používatelia', to: { name: 'admin.users' } },
      { key: 'moderation', label: 'Moderácia', to: { name: 'admin.moderation' } },
    ],
  },
  [ADMIN_SECTION_KEYS.CONTENT]: {
    key: ADMIN_SECTION_KEYS.CONTENT,
    label: 'Obsah',
    tabs: [
      { key: 'articles', label: 'Články', to: { name: 'admin.blog' } },
      { key: 'newsletter', label: 'Newsletter', to: { name: 'admin.newsletter' } },
    ],
  },
}

export function isKnownAdminSection(sectionKey) {
  return Boolean(sectionKey && ADMIN_SECTION_CONFIG[sectionKey])
}

export function getAdminSectionConfig(sectionKey) {
  const key = String(sectionKey || '')
  return ADMIN_SECTION_CONFIG[key] || null
}

export function getAdminSectionLabel(sectionKey) {
  return getAdminSectionConfig(sectionKey)?.label || 'Admin'
}

export function getAdminSectionTabs(sectionKey) {
  const tabs = getAdminSectionConfig(sectionKey)?.tabs || []
  return tabs.slice()
}
