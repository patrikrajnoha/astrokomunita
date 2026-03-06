import SearchBar from '@/components/SearchBar.vue'
import RightObservingSidebar from '@/components/RightObservingSidebar.vue'
import NasaApodWidget from '@/components/widgets/NasaApodWidget.vue'
import NextEventWidget from '@/components/widgets/NextEventWidget.vue'
import LatestArticlesWidget from '@/components/widgets/LatestArticlesWidget.vue'
import UpcomingEventsWidget from '@/components/widgets/UpcomingEventsWidget.vue'

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
    label: 'Search',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/SearchBar.vue',
    description: 'Realny search widget zo sidebaru.',
    component: SearchBar,
    initialProps: {},
    editableProps: [],
  },
  {
    id: 'sidebar-observing-conditions',
    label: 'Observing Conditions',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/RightObservingSidebar.vue',
    description: 'Sky conditions widget zo sidebaru.',
    component: RightObservingSidebar,
    initialProps: {
      lat: 48.1486,
      lon: 17.1077,
      tz: 'Europe/Bratislava',
      locationName: 'Bratislava',
    },
    editableProps: [
      { key: 'lat', label: 'Latitude', type: 'number', defaultValue: 48.1486, min: -90, max: 90, step: 0.0001 },
      { key: 'lon', label: 'Longitude', type: 'number', defaultValue: 17.1077, min: -180, max: 180, step: 0.0001 },
      { key: 'tz', label: 'Timezone', type: 'text', defaultValue: 'Europe/Bratislava' },
      { key: 'locationName', label: 'Location', type: 'text', defaultValue: 'Bratislava' },
    ],
  },
  {
    id: 'sidebar-nasa-apod',
    label: 'NASA APOD',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/NasaApodWidget.vue',
    description: 'NASA obrazok dna widget zo sidebaru.',
    component: NasaApodWidget,
    initialProps: {
      title: 'NASA - Obrazok dna',
    },
    editableProps: [
      { key: 'title', label: 'Title', type: 'text', defaultValue: 'NASA - Obrazok dna' },
    ],
  },
  {
    id: 'sidebar-next-event',
    label: 'Next Event',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/NextEventWidget.vue',
    description: 'Widget najblizsej udalosti zo sidebaru.',
    component: NextEventWidget,
    initialProps: {
      title: 'Najblizsia udalost',
    },
    editableProps: [
      { key: 'title', label: 'Title', type: 'text', defaultValue: 'Najblizsia udalost' },
    ],
  },
  {
    id: 'sidebar-latest-articles',
    label: 'Latest Articles',
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
      { key: 'mostReadTitle', label: 'Most read title', type: 'text', defaultValue: 'Najcitanejsie clanky' },
      { key: 'latestTitle', label: 'Latest title', type: 'text', defaultValue: 'Najnovsie clanky' },
      { key: 'emptyStateTitle', label: 'Empty state text', type: 'text', defaultValue: 'Zatial ziadne clanky' },
      { key: 'loadErrorTitle', label: 'Error title', type: 'text', defaultValue: 'Nepodarilo sa nacitat' },
      {
        key: 'switchIntervalMs',
        label: 'Switch interval ms',
        type: 'number',
        defaultValue: 60000,
        min: 1000,
        max: 600000,
        step: 500,
        parser: (value) => toInt(value, 60000),
      },
      {
        key: 'refetchIntervalMs',
        label: 'Refetch interval ms',
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
    label: 'Co sa deje (Upcoming Events)',
    category: 'Sidebar widgety',
    sourcePath: 'frontend/src/components/widgets/UpcomingEventsWidget.vue',
    description: 'Widget upcoming events zo sidebaru.',
    component: UpcomingEventsWidget,
    initialProps: {
      title: 'Co sa deje',
      showMoreLabel: 'Show more',
      loadErrorTitle: 'Nepodarilo sa nacitat',
    },
    editableProps: [
      { key: 'title', label: 'Title', type: 'text', defaultValue: 'Co sa deje' },
      { key: 'showMoreLabel', label: 'Show more label', type: 'text', defaultValue: 'Show more' },
      { key: 'loadErrorTitle', label: 'Error title', type: 'text', defaultValue: 'Nepodarilo sa nacitat' },
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
