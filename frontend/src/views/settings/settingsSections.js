export const legacySettingsSectionToRouteName = {
  account: 'settings.email',
  activity: 'settings.activity',
  data: 'settings.data-export',
  deactivate: 'settings.deactivate',
  email: 'settings.email',
  export: 'settings.data-export',
  newsletter: 'settings.newsletter',
  onboarding: 'settings.onboarding',
  password: 'settings.password',
  security: 'settings.password',
  sidebar: 'settings.sidebar-widgets',
  widgets: 'settings.sidebar-widgets',
}

export const settingsGroups = [
  {
    label: 'UCET',
    items: [
      {
        key: 'email',
        routeName: 'settings.email',
        title: 'E-mail',
        description: 'Stav overenia, overovaci kod a bezpecna zmena emailu.',
        iconPaths: [
          'M4.5 6.25h15a1 1 0 0 1 1 1v9.5a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1v-9.5a1 1 0 0 1 1-1Z',
          'm4.25 7.75 7.75 5.5 7.75-5.5',
        ],
      },
      {
        key: 'newsletter',
        routeName: 'settings.newsletter',
        title: 'Tyzdenny newsletter',
        description: 'Dostavajte tyzdenny vyber udalosti, clankov a jeden astronomicky tip.',
        iconPaths: [
          'M4.5 5.5h15a1 1 0 0 1 1 1V17.5a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V6.5a1 1 0 0 1 1-1Z',
          'M8 10h8',
          'M8 13.5h5',
        ],
      },
      {
        key: 'onboarding',
        routeName: 'settings.onboarding',
        title: 'Onboarding sprievodca',
        description: 'Spustite interaktivny tour feedu, kalendara a panelu pozorovacich podmienok.',
        iconPaths: [
          'M12 3.5v4',
          'M12 16.5v4',
          'M4.5 12h4',
          'M15.5 12h4',
          'm6.7 6.7 2.8 2.8',
          'm14.5 14.5 2.8 2.8',
          'm17.3 6.7-2.8 2.8',
          'm9.5 14.5-2.8 2.8',
        ],
      },
      {
        key: 'sidebar-widgets',
        routeName: 'settings.sidebar-widgets',
        title: 'Sidebar widgety',
        description: 'Vyberte si vlastne widgety pre pravy panel a mobilne menu widgetov.',
        iconPaths: [
          'M4.5 6.5h7v5h-7z',
          'M12.5 6.5h7v8h-7z',
          'M4.5 12.5h7v7h-7z',
          'M12.5 15.5h7v4h-7z',
        ],
      },
    ],
  },
  {
    label: 'DATA',
    items: [
      {
        key: 'data-export',
        routeName: 'settings.data-export',
        title: 'Export dat',
        description: 'Stiahnite profilove data vo formate JSON pre zalohu alebo GDPR poziadavky.',
        iconPaths: [
          'M12 4.5v10.25',
          'm8.25 10.75-8.25 8.25-8.25-8.25',
          'M4.5 19.5h15',
        ],
      },
      {
        key: 'activity',
        routeName: 'settings.activity',
        title: 'Aktivita pouzivatela',
        description: 'Skontrolujte detaily nedavnej aktivity uctu iba ked je to potrebne.',
        iconPaths: [
          'M5 18.5h14',
          'M7.5 16V9.5',
          'M12 16V6.5',
          'M16.5 16v-4.5',
        ],
      },
    ],
  },
  {
    label: 'BEZPECNOST',
    items: [
      {
        key: 'password',
        routeName: 'settings.password',
        title: 'Zmena hesla',
        description: 'Nastavte nove heslo pre svoj ucet.',
        iconPaths: [
          'M7.5 10.5V8a4.5 4.5 0 1 1 9 0v2.5',
          'M6 10.5h12a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1Z',
          'M12 13.25v2.5',
        ],
      },
      {
        key: 'deactivate',
        routeName: 'settings.deactivate',
        title: 'Deaktivacia uctu',
        description: 'Natrvalo odstrante ucet a odhlaste sa.',
        iconPaths: [
          'M12 5.25 20.25 19H3.75L12 5.25Z',
          'M12 10.5v4.25',
          'M12 17h.01',
        ],
      },
    ],
  },
]
