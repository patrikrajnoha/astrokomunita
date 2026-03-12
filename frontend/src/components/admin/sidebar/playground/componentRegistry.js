import SearchBar from '@/components/SearchBar.vue'
import RightObservingSidebar from '@/components/RightObservingSidebar.vue'
import ObservingWeatherWidget from '@/components/sky/ObservingWeatherWidget.vue'
import NightSkyWidget from '@/components/sky/NightSkyWidget.vue'
import IssPassWidget from '@/components/sky/IssPassWidget.vue'
import NasaHighlightsWidget from '@/components/widgets/NasaHighlightsWidget.vue'
import NextEventWidget from '@/components/widgets/NextEventWidget.vue'
import LatestArticlesWidget from '@/components/widgets/LatestArticlesWidget.vue'
import UpcomingEventsWidget from '@/components/widgets/UpcomingEventsWidget.vue'
import MoonPhasesWidget from '@/components/widgets/MoonPhasesWidget.vue'

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
    label: 'Hladat',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/SearchBar.vue',
    description: 'Realny search widget zo sidebaru.',
    component: SearchBar,
    initialProps: {},
    editableProps: [],
  },
  {
    id: 'sidebar-observing-conditions',
    label: 'Astronomicke podmienky',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/RightObservingSidebar.vue',
    description: 'Hlavny summary widget pre rychly stav oblohy.',
    component: RightObservingSidebar,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      locationName: 'Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Zemepisna sirka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisna dlzka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Casove pasmo', type: 'text', defaultValue: 'Europe/Bratislava' },
      { key: 'locationName', label: 'Lokalita', type: 'text', defaultValue: 'Bratislava' },
    ],
  },
  {
    id: 'sidebar-observing-weather',
    label: 'Pocasie pre pozorovanie',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/sky/ObservingWeatherWidget.vue',
    description: 'Kompaktne metriky pocasia pre pozorovanie.',
    component: ObservingWeatherWidget,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Zemepisna sirka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisna dlzka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Casove pasmo', type: 'text', defaultValue: 'Europe/Bratislava' },
    ],
  },
  {
    id: 'sidebar-night-sky',
    label: 'Nocna obloha',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/sky/NightSkyWidget.vue',
    description: 'Mesiac, Bortle a viditelne planety bez balastu.',
    component: NightSkyWidget,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Zemepisna sirka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisna dlzka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Casove pasmo', type: 'text', defaultValue: 'Europe/Bratislava' },
    ],
  },
  {
    id: 'sidebar-moon-phases',
    label: 'Fazy mesiaca',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/MoonPhasesWidget.vue',
    description: 'Vsetky fazy mesiaca so start/end intervalom a aktualnym highlightom.',
    component: MoonPhasesWidget,
    initialProps: {
      title: 'Fazy mesiaca',
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Fazy mesiaca' },
      { key: 'lat', label: 'Zemepisna sirka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisna dlzka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Casove pasmo', type: 'text', defaultValue: 'Europe/Bratislava' },
    ],
  },
  {
    id: 'sidebar-iss-pass',
    label: 'ISS prelet',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/sky/IssPassWidget.vue',
    description: 'Zobrazi sa iba pri viditelnom ISS prelete.',
    component: IssPassWidget,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Zemepisna sirka', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Zemepisna dlzka', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Casove pasmo', type: 'text', defaultValue: 'Europe/Bratislava' },
    ],
  },
  {
    id: 'sidebar-nasa-apod',
    label: 'NASA Novinky',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/NasaHighlightsWidget.vue',
    description: 'NASA novinky widget zo sidebaru.',
    component: NasaHighlightsWidget,
    initialProps: {
      title: 'NASA Novinky',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'NASA Novinky' },
    ],
  },
  {
    id: 'sidebar-next-event',
    label: 'Najblizsia udalost',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/NextEventWidget.vue',
    description: 'Widget najblizsej udalosti zo sidebaru.',
    component: NextEventWidget,
    initialProps: {
      title: 'Najblizsia udalost',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Najblizsia udalost' },
    ],
  },
  {
    id: 'sidebar-latest-articles',
    label: 'Najnovsie clanky',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/LatestArticlesWidget.vue',
    description: 'Widget pre najnovsie a najcitanejsie clanky zo sidebaru.',
    component: LatestArticlesWidget,
    initialProps: {
      mostReadTitle: 'Najcitanejsie clanky',
      latestTitle: 'Najnovsie clanky',
      emptyStateTitle: 'Zatial ziadne clanky',
      loadErrorTitle: 'Nepodarilo sa nacitat',
      switchIntervalMs: 60000,
      refetchIntervalMs: 180000,
    },
    editableProps: [
      { key: 'mostReadTitle', label: 'Nadpis najcitanejsich', type: 'text', defaultValue: 'Najcitanejsie clanky' },
      { key: 'latestTitle', label: 'Nadpis najnovsich', type: 'text', defaultValue: 'Najnovsie clanky' },
      { key: 'emptyStateTitle', label: 'Text prazdneho stavu', type: 'text', defaultValue: 'Zatial ziadne clanky' },
      { key: 'loadErrorTitle', label: 'Nadpis chyby', type: 'text', defaultValue: 'Nepodarilo sa nacitat' },
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
    label: 'Co sa deje (Nadchadzajuce udalosti)',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/UpcomingEventsWidget.vue',
    description: 'Widget upcoming events zo sidebaru.',
    component: UpcomingEventsWidget,
    initialProps: {
      title: 'Co sa deje',
      showMoreLabel: 'Zobrazit viac',
      loadErrorTitle: 'Nepodarilo sa nacitat',
    },
    editableProps: [
      { key: 'title', label: 'Nadpis', type: 'text', defaultValue: 'Co sa deje' },
      { key: 'showMoreLabel', label: 'Text tlacidla viac', type: 'text', defaultValue: 'Zobrazit viac' },
      { key: 'loadErrorTitle', label: 'Nadpis chyby', type: 'text', defaultValue: 'Nepodarilo sa nacitat' },
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
