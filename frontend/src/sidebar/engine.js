import SearchBar from '@/components/SearchBar.vue'
import RightObservingSidebar from '@/components/RightObservingSidebar.vue'
import LatestArticlesWidget from '@/components/widgets/LatestArticlesWidget.vue'
import NasaApodWidget from '@/components/widgets/NasaApodWidget.vue'
import NextEventWidget from '@/components/widgets/NextEventWidget.vue'

export const sidebarComponentMap = {
  search: SearchBar,
  observing_conditions: RightObservingSidebar,
  nasa_apod: NasaApodWidget,
  next_event: NextEventWidget,
  latest_articles: LatestArticlesWidget,
}

const sidebarIconMap = {
  search: {
    viewBox: '0 0 24 24',
    paths: ['M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z', 'm20 20-3.5-3.5'],
  },
  observing_conditions: {
    viewBox: '0 0 24 24',
    paths: ['M4 19h16', 'M8 19l2.2-8h3.6L16 19', 'M6.5 8.8 12 5l5.5 3.8', 'M12 5v2.3'],
  },
  nasa_apod: {
    viewBox: '0 0 24 24',
    paths: ['M4 5h16v14H4z', 'm4 14 4.5-4.5L12 13l2.5-2.5L20 16', 'M9 9h.01'],
  },
  next_event: {
    viewBox: '0 0 24 24',
    paths: [
      'M7 3v3',
      'M17 3v3',
      'M4 8h16',
      'M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
      'M12 12v4',
      'm12 12 2.5-2.5',
    ],
  },
  latest_articles: {
    viewBox: '0 0 24 24',
    paths: ['M5 5h14v14H5z', 'M8 9h8', 'M8 12h8', 'M8 15h5'],
  },
}

const toSafeNumber = (value, fallback = 0) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

export const normalizeSidebarSections = (items) => {
  if (!Array.isArray(items)) return []

  return items
    .map((item) => ({
      section_key: String(item?.section_key || ''),
      title: String(item?.title || ''),
      order: toSafeNumber(item?.order, 0),
      is_enabled: Boolean(item?.is_enabled),
    }))
    .filter((item) => item.section_key !== '')
    .sort((a, b) => a.order - b.order)
}

export const getEnabledSidebarSections = (items) => {
  return normalizeSidebarSections(items).filter((item) => item.is_enabled)
}

export const resolveSidebarComponent = (sectionKey) => {
  return sidebarComponentMap[sectionKey] || null
}

export const resolveSidebarIcon = (sectionKey) => {
  return (
    sidebarIconMap[sectionKey] || {
      viewBox: '0 0 24 24',
      paths: ['M4 5h16v14H4z'],
    }
  )
}
