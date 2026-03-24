import SearchBar from '@/components/SearchBar.vue'
import RightObservingSidebar from '@/components/RightObservingSidebar.vue'
import ObservingWeatherWidget from '@/components/sky/ObservingWeatherWidget.vue'
import NightSkyWidget from '@/components/sky/NightSkyWidget.vue'
import IssPassWidget from '@/components/sky/IssPassWidget.vue'
import NasaHighlightsWidget from '@/components/widgets/NasaHighlightsWidget.vue'
import NextEventWidget from '@/components/widgets/NextEventWidget.vue'
import ConstellationsNowWidget from '@/components/widgets/ConstellationsNowWidget.vue'
import LatestArticlesWidget from '@/components/widgets/LatestArticlesWidget.vue'
import UpcomingEventsWidget from '@/components/widgets/UpcomingEventsWidget.vue'
import MoonPhasesWidget from '@/components/widgets/MoonPhasesWidget.vue'
import MoonEventsWidget from '@/components/widgets/MoonEventsWidget.vue'

const CATEGORY_ORDER = [
  'Sidebar widgety',
]

const toInt = (value, fallback = 0) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? Math.round(parsed) : fallback
}

export const sidebarComponentPlaygroundRegistry = [
  {
    id: 'sidebar-search',
    label: 'Hľadaj',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/SearchBar.vue',
    description: 'Reálny search widget zo sidebaru.',
    component: SearchBar,
    initialProps: {},
    editableProps: [],
  },
  {
    id: 'sidebar-observing-conditions',
    label: 'Pozorovanie dnes',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/RightObservingSidebar.vue',
    description: 'Hlavný summary widget pre rýchly stav oblohy.',
    component: RightObservingSidebar,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      locationName: 'Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Zemepisná šírka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisná dĺžka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Časové pásmo', type: 'text', defaultValue: 'Europe/Bratislava' },
      { key: 'locationName', label: 'Lokalita', type: 'text', defaultValue: 'Bratislava' },
    ],
  },
  {
    id: 'sidebar-observing-weather',
    label: 'Počasie na pozorovanie',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/sky/ObservingWeatherWidget.vue',
    description: 'Kompaktné metriky počasia pre pozorovanie.',
    component: ObservingWeatherWidget,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Zemepisná šírka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisná dĺžka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Časové pásmo', type: 'text', defaultValue: 'Europe/Bratislava' },
    ],
  },
  {
    id: 'sidebar-night-sky',
    label: 'Nočná obloha',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/sky/NightSkyWidget.vue',
    description: 'Mesiac, Bortle a viditeľné planety bez balastu.',
    component: NightSkyWidget,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Zemepisná šírka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisná dĺžka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Časové pásmo', type: 'text', defaultValue: 'Europe/Bratislava' },
    ],
  },
  {
    id: 'sidebar-moon-phases',
    label: 'Fázy Mesiaca',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/MoonPhasesWidget.vue',
    description: 'Všetky fázy mesiaca so start/end intervalom a aktuálnym highlightom.',
    component: MoonPhasesWidget,
    initialProps: {
      title: 'Fázy Mesiaca',
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Fázy Mesiaca' },
      { key: 'lat', label: 'Zemepisná šírka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisná dĺžka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Časové pásmo', type: 'text', defaultValue: 'Europe/Bratislava' },
    ],
  },
  {
    id: 'sidebar-moon-events',
    label: 'Lunárny kalendár',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/MoonEventsWidget.vue',
    description: 'Špeciálne lunárne udalosti pre aktuálny rok a lokalitu.',
    component: MoonEventsWidget,
    initialProps: {
      title: 'Lunárny kalendár',
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      date: '',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Lunárny kalendár' },
      { key: 'lat', label: 'Zemepisná šírka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisná dĺžka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Časové pásmo', type: 'text', defaultValue: 'Europe/Bratislava' },
      { key: 'date', label: 'Dátum (YYYY-MM-DD)', type: 'text', defaultValue: '' },
    ],
  },
  {
    id: 'sidebar-iss-pass',
    label: 'ISS nad tebou',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/sky/IssPassWidget.vue',
    description: 'ISS tracker a najbližší viditeľný prelet pre tvoju polohu.',
    component: IssPassWidget,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Zemepisná šírka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisná dĺžka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Časové pásmo', type: 'text', defaultValue: 'Europe/Bratislava' },
    ],
  },
  {
    id: 'sidebar-nasa-apod',
    label: 'Astrofoto dňa',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/NasaHighlightsWidget.vue',
    description: 'NASA novinky widget zo sidebaru.',
    component: NasaHighlightsWidget,
    initialProps: {
      title: 'Astrofoto dňa',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Astrofoto dňa' },
    ],
  },
  {
    id: 'sidebar-next-event',
    label: 'Najbližšia udalosť',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/NextEventWidget.vue',
    description: 'Widget najbližšej udalosti zo sidebaru.',
    component: NextEventWidget,
    initialProps: {
      title: 'Najbližšia udalosť',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Najbližšia udalosť' },
    ],
  },
  {
    id: 'sidebar-constellations-now',
    label: 'Viditeľné súhvezdia',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/ConstellationsNowWidget.vue',
    description: 'Rýchly výber súhvezdí, ktoré sa dnes oplatí sledovať.',
    component: ConstellationsNowWidget,
    initialProps: {
      title: 'Viditeľné súhvezdia',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Viditeľné súhvezdia' },
    ],
  },
  {
    id: 'sidebar-latest-articles',
    label: 'Astro čítanie',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/LatestArticlesWidget.vue',
    description: 'Widget pre najnovšie a najčítanejšie články zo sidebaru.',
    component: LatestArticlesWidget,
    initialProps: {
      mostReadTitle: 'Najčítanejšie články',
      latestTitle: 'Astro čítanie',
      emptyStateTitle: 'Zatiaľ žiadne články',
      loadErrorTitle: 'Nepodarilo sa načítať',
      switchIntervalMs: 60000,
      refetchIntervalMs: 180000,
    },
    editableProps: [
      { key: 'mostReadTitle', label: 'Nadpis najčítanejších', type: 'text', defaultValue: 'Najčítanejšie články' },
      { key: 'latestTitle', label: 'Nadpis najnovších', type: 'text', defaultValue: 'Astro čítanie' },
      { key: 'emptyStateTitle', label: 'Text prázdneho stavu', type: 'text', defaultValue: 'Zatiaľ žiadne články' },
      { key: 'loadErrorTitle', label: 'Nadpis chyby', type: 'text', defaultValue: 'Nepodarilo sa načítať' },
      {
        key: 'switchIntervalMs',
        label: 'Interval prepinania (ms)',
        type: 'number',
        defaultValue: 60000,
        min: 1000,
        max: 600000,
        step: 500,
        parser: (value) => toInt(value, 60000),
      },
      {
        key: 'refetchIntervalMs',
        label: 'Interval obnovenia (ms)',
        type: 'number',
        defaultValue: 180000,
        min: 5000,
        max: 1200000,
        step: 1000,
        parser: (value) => toInt(value, 180000),
      },
    ],
  },
  {
    id: 'sidebar-upcoming-events',
    label: 'Udalosti v kalendári',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/UpcomingEventsWidget.vue',
    description: 'Widget upcoming events zo sidebaru.',
    component: UpcomingEventsWidget,
    initialProps: {
      title: 'Udalosti v kalendári',
      showMoreLabel: 'Zobraziť viac',
      loadErrorTitle: 'Nepodarilo sa načítať',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Udalosti v kalendári' },
      { key: 'showMoreLabel', label: 'Text tlačidla viac', type: 'text', defaultValue: 'Zobraziť viac' },
      { key: 'loadErrorTitle', label: 'Nadpis chyby', type: 'text', defaultValue: 'Nepodarilo sa načítať' },
    ],
  },
]

export const componentRegistryCategories = CATEGORY_ORDER

export const componentRegistryByCategory = sidebarComponentPlaygroundRegistry.reduce((acc, entry) => {
  const category = String(entry?.category || 'Sidebar widgety')
  if (!acc[category]) {
    acc[category] = []
  }
  acc[category].push(entry)
  return acc
}, {})

export const getRegistryCategoryCount = (category) => {
  return (componentRegistryByCategory[category] || []).length
}
