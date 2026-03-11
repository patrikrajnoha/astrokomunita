export const MODE_TABS = [
  { value: 'layout', label: 'Rozlozenie' },
  { value: 'custom', label: 'Vlastne komponenty' },
  { value: 'widgets', label: 'Widgety' },
]

export function createScopeTabs(SIDEBAR_SCOPE) {
  return [
    { value: SIDEBAR_SCOPE.HOME, label: 'Domov' },
    { value: SIDEBAR_SCOPE.EVENTS, label: 'Udalosti + kalendar' },
    { value: SIDEBAR_SCOPE.LEARNING, label: 'Vzdelavanie' },
    { value: SIDEBAR_SCOPE.SEARCH, label: 'Vyhladavanie' },
    { value: SIDEBAR_SCOPE.NOTIFICATIONS, label: 'Notifikacie' },
    { value: SIDEBAR_SCOPE.POST_DETAIL, label: 'Detail prispevku' },
    { value: SIDEBAR_SCOPE.ARTICLE_DETAIL, label: 'Detail clanku' },
    { value: SIDEBAR_SCOPE.PROFILE, label: 'Profil' },
    { value: SIDEBAR_SCOPE.SETTINGS, label: 'Nastavenia' },
    { value: SIDEBAR_SCOPE.OBSERVING, label: 'Pozorovanie' },
  ]
}
