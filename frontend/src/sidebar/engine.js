import { defineAsyncComponent } from 'vue'
import {
  LEGACY_WIDGET_TYPE_SPECIAL_EVENT,
  SIDEBAR_WIDGET_TYPES,
  normalizeWidgetConfig,
  normalizeWidgetType,
} from '@/sidebar/customWidgets/types'

export const MAX_ENABLED_SIDEBAR_WIDGETS = 3
export const EXCLUSIVE_SIDEBAR_SECTION_KEYS = Object.freeze([])
export const OBSERVING_SECTION_KEYS = Object.freeze([
  'observing_conditions',
  'observing_weather',
  'space_weather',
  'aurora_watch',
  'night_sky',
  'iss_pass',
  'moon_phases',
  'moon_overview',
  'moon_events',
])
export const GUEST_OBSERVING_PROMPT_SECTION_KEY = 'guest_observing_prompt'

const SearchBar = defineAsyncComponent(() => import('@/components/SearchBar.vue'))
const RightObservingSidebar = defineAsyncComponent(() => import('@/components/RightObservingSidebar.vue'))
const ObservingWeatherWidget = defineAsyncComponent(() => import('@/components/sky/ObservingWeatherWidget.vue'))
const SpaceWeatherWidget = defineAsyncComponent(() => import('@/components/sky/SpaceWeatherWidget.vue'))
const AuroraWatchWidget = defineAsyncComponent(() => import('@/components/sky/AuroraWatchWidget.vue'))
const NightSkyWidget = defineAsyncComponent(() => import('@/components/sky/NightSkyWidget.vue'))
const IssPassWidget = defineAsyncComponent(() => import('@/components/sky/IssPassWidget.vue'))
const GuestObservingPromptWidget = defineAsyncComponent(() => import('@/components/sky/GuestObservingPromptWidget.vue'))
const LatestArticlesWidget = defineAsyncComponent(() => import('@/components/widgets/LatestArticlesWidget.vue'))
const NasaHighlightsWidget = defineAsyncComponent(() => import('@/components/widgets/NasaHighlightsWidget.vue'))
const NextEventWidget = defineAsyncComponent(() => import('@/components/widgets/NextEventWidget.vue'))
const NextEclipseWidget = defineAsyncComponent(() => import('@/components/widgets/NextEclipseWidget.vue'))
const NextMeteorWidget = defineAsyncComponent(() => import('@/components/widgets/NextMeteorWidget.vue'))
const NeoWatchlistWidget = defineAsyncComponent(() => import('@/components/widgets/NeoWatchlistWidget.vue'))
const UpcomingLaunchesWidget = defineAsyncComponent(() => import('@/components/widgets/UpcomingLaunchesWidget.vue'))
const UpcomingEventsWidget = defineAsyncComponent(() => import('@/components/widgets/UpcomingEventsWidget.vue'))
const MoonPhasesWidget = defineAsyncComponent(() => import('@/components/widgets/MoonPhasesWidget.vue'))
const MoonOverviewWidget = defineAsyncComponent(() => import('@/components/widgets/MoonOverviewWidget.vue'))
const MoonEventsWidget = defineAsyncComponent(() => import('@/components/widgets/MoonEventsWidget.vue'))
const SidebarWidgetRenderer = defineAsyncComponent(() => import('@/components/widgets/SidebarWidgetRenderer.vue'))

export const sidebarComponentMap = {
  search: SearchBar,
  observing_conditions: RightObservingSidebar,
  observing_weather: ObservingWeatherWidget,
  space_weather: SpaceWeatherWidget,
  aurora_watch: AuroraWatchWidget,
  night_sky: NightSkyWidget,
  iss_pass: IssPassWidget,
  [GUEST_OBSERVING_PROMPT_SECTION_KEY]: GuestObservingPromptWidget,
  nasa_apod: NasaHighlightsWidget,
  next_event: NextEventWidget,
  next_eclipse: NextEclipseWidget,
  next_meteor_shower: NextMeteorWidget,
  neo_watchlist: NeoWatchlistWidget,
  upcoming_launches: UpcomingLaunchesWidget,
  latest_articles: LatestArticlesWidget,
  upcoming_events: UpcomingEventsWidget,
  moon_phases: MoonPhasesWidget,
  moon_overview: MoonOverviewWidget,
  moon_events: MoonEventsWidget,
}

export const customSidebarComponentMap = {
  [SIDEBAR_WIDGET_TYPES.CTA]: SidebarWidgetRenderer,
  [SIDEBAR_WIDGET_TYPES.INFO_CARD]: SidebarWidgetRenderer,
  [SIDEBAR_WIDGET_TYPES.LINK_LIST]: SidebarWidgetRenderer,
  [SIDEBAR_WIDGET_TYPES.HTML]: SidebarWidgetRenderer,
  [SIDEBAR_WIDGET_TYPES.CONTEST]: SidebarWidgetRenderer,
  [LEGACY_WIDGET_TYPE_SPECIAL_EVENT]: SidebarWidgetRenderer,
}

const sidebarIconMap = {
  search: {
    viewBox: '0 0 24 24',
    paths: ['M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z', 'm20 20-3.5-3.5'],
  },
  observing_conditions: {
    viewBox: '0 0 24 24',
    paths: ['M4 19h16', 'M7 14h10', 'M9.5 9.5h5', 'M12 5v2.2', 'M8 7.8 12 5l4 2.8'],
  },
  observing_weather: {
    viewBox: '0 0 24 24',
    paths: ['M6 15h12', 'M8 18h8', 'M8 12a4 4 0 1 1 7.7-1.6A3.2 3.2 0 1 1 17 15H8'],
  },
  space_weather: {
    viewBox: '0 0 24 24',
    paths: ['M12 2v5', 'M12 17v5', 'M4.9 4.9l3.5 3.5', 'm15.6 15.6 3.5 3.5', 'M2 12h5', 'M17 12h5', 'm4.9 19.1 3.5-3.5', 'm15.6-10.7 3.5-3.5', 'M12 8a4 4 0 1 0 0 8'],
  },
  aurora_watch: {
    viewBox: '0 0 24 24',
    paths: [
      'M3 16c2.2-3.6 4.7-5.4 7.5-5.4 2.6 0 4.5 1.2 5.9 2.2 1.3.9 2.3 1.6 3.6 1.6',
      'M4 20c2-2.4 4.2-3.6 6.6-3.6 2.3 0 4.2.8 5.8 1.5 1.3.6 2.5 1.1 3.6 1.1',
      'M7 6h.01',
      'M12 4h.01',
      'M17 7h.01',
    ],
  },
  night_sky: {
    viewBox: '0 0 24 24',
    paths: ['M17.2 4.8a7.5 7.5 0 1 0 2 10.4 6.2 6.2 0 0 1-2-10.4Z', 'M5 4h.01', 'M8 2h.01', 'M12 6h.01'],
  },
  iss_pass: {
    viewBox: '0 0 24 24',
    paths: ['M12 3v4', 'm9 9-4-1-2 2-2-2-1-4-3 3 1 2-2 2-2-2-4 1 4 1 2-2 2 2 1 4 3-3-1-2 2-2 2 2 4-1Z'],
  },
  [GUEST_OBSERVING_PROMPT_SECTION_KEY]: {
    viewBox: '0 0 24 24',
    paths: [
      'M7 11h10v8H7z',
      'M9 11V8.8A3 3 0 0 1 12 5.8a3 3 0 0 1 3 3V11',
      'M12 14.2v2.4',
    ],
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
  next_eclipse: {
    viewBox: '0 0 24 24',
    paths: [
      'M12 3a9 9 0 1 0 0 18',
      'M12 3a9 9 0 0 1 0 18',
      'M16 8.5a6 6 0 0 0 0 7',
    ],
  },
  next_meteor_shower: {
    viewBox: '0 0 24 24',
    paths: [
      'M4 18 13 9',
      'M13 9h6',
      'M13 9V3',
      'm8 11 2 2',
      'm5 8 1.5 1.5',
      'm11 15 1.5 1.5',
    ],
  },
  neo_watchlist: {
    viewBox: '0 0 24 24',
    paths: [
      'M6.2 12.1a5.8 4.2 0 1 0 11.6 0 5.8 4.2 0 1 0-11.6 0Z',
      'M3 12h2.4',
      'M18.6 12H21',
      'm8 8.5 1.8-1.8',
      'm14.2 17.3 1.8 1.8',
    ],
  },
  upcoming_launches: {
    viewBox: '0 0 24 24',
    paths: [
      'M5 19h14',
      'm12 4 3.5 7.5-2 1.2 1.2 3.8L12 15l-2.7 1.5 1.2-3.8-2-1.2Z',
      'M12 15v4',
    ],
  },
  latest_articles: {
    viewBox: '0 0 24 24',
    paths: ['M5 5h14v14H5z', 'M8 9h8', 'M8 12h8', 'M8 15h5'],
  },
  upcoming_events: {
    viewBox: '0 0 24 24',
    paths: [
      'M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
      'M8 9h8',
      'M8 13h5',
      'M8 17h6',
    ],
  },
  moon_phases: {
    viewBox: '0 0 24 24',
    paths: ['M12 2a10 10 0 1 0 8.6 15.1A8.3 8.3 0 0 1 12 2Z'],
  },
  moon_overview: {
    viewBox: '0 0 24 24',
    paths: ['M12 3a9 9 0 1 0 7.8 13.6A7.4 7.4 0 0 1 12 3Z', 'M7 20h10', 'M7 17h7'],
  },
  moon_events: {
    viewBox: '0 0 24 24',
    paths: ['M5 4h14v16H5z', 'M8 8h8', 'M8 12h6', 'M8 16h4', 'M16 4v3', 'M8 4v3'],
  },
  custom_component: {
    viewBox: '0 0 24 24',
    paths: ['M6 5h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z', 'M8 10h8', 'M8 14h5'],
  },
}

const toSafeNumber = (value, fallback = 0) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : fallback
}

const normalizePreferredSectionKeys = (value) => {
  if (!Array.isArray(value)) return []

  return Array.from(new Set(
    value
      .map((entry) => String(entry || '').trim())
      .filter(Boolean),
  ))
}

const observingSectionKeySet = new Set(OBSERVING_SECTION_KEYS)

const toGuestObservingPromptSection = (section) => {
  return {
    kind: 'builtin',
    section_key: GUEST_OBSERVING_PROMPT_SECTION_KEY,
    title: 'Astronomicke podmienky',
    custom_component_id: null,
    custom_component: null,
    order: toSafeNumber(section?.order, 0),
    is_enabled: true,
  }
}

const collapseObservingSectionsForGuest = (sections, options = {}) => {
  if (!options?.isGuest) {
    return sections
  }

  const source = Array.isArray(sections) ? sections : []
  let guestPromptInserted = false

  return source.reduce((acc, section) => {
    const sectionKey = String(section?.section_key || '')
    if (!observingSectionKeySet.has(sectionKey)) {
      acc.push(section)
      return acc
    }

    if (!guestPromptInserted) {
      acc.push(toGuestObservingPromptSection(section))
      guestPromptInserted = true
    }

    return acc
  }, [])
}

const collapseObservingSectionsForMissingLocation = (sections, options = {}) => {
  if (!options?.collapseObservingForMissingLocation || options?.isGuest) {
    return sections
  }

  const source = Array.isArray(sections) ? sections : []
  let inserted = false

  return source.reduce((acc, section) => {
    const sectionKey = String(section?.section_key || '')
    if (!observingSectionKeySet.has(sectionKey)) {
      acc.push(section)
      return acc
    }

    if (!inserted) {
      acc.push(toGuestObservingPromptSection(section))
      inserted = true
    }

    return acc
  }, [])
}

const resolveBuiltinSectionTitle = (sectionKey, title) => {
  if (sectionKey === 'nasa_apod') {
    return 'NASA Novinky'
  }

  return title
}

export const normalizeSidebarSections = (items) => {
  if (!Array.isArray(items)) return []

  return items
    .map((item) => {
      const kind = item?.kind === 'custom_component' ? 'custom_component' : 'builtin'
      const sectionKey = String(item?.section_key || '')
      const originalTitle = String(item?.title || '')

      return {
        kind,
        section_key: sectionKey,
        title: kind === 'builtin'
          ? resolveBuiltinSectionTitle(sectionKey, originalTitle)
          : originalTitle,
        custom_component_id: Number.isFinite(Number(item?.custom_component_id))
          ? Number(item.custom_component_id)
          : null,
        custom_component: item?.custom_component && typeof item.custom_component === 'object'
          ? {
              id: Number.isFinite(Number(item.custom_component.id)) ? Number(item.custom_component.id) : null,
              name: String(item.custom_component.name || ''),
              type: normalizeWidgetType(String(item.custom_component.type || '')),
              is_active: Boolean(item.custom_component.is_active),
              config_json: normalizeWidgetConfig(
                item.custom_component.type,
                item.custom_component.config_json || item.custom_component.config || {},
              ),
            }
          : null,
        order: toSafeNumber(item?.order, 0),
        is_enabled: Boolean(item?.is_enabled),
      }
    })
    .filter((item) => {
      if (item.kind === 'custom_component') {
        return Number.isFinite(item.custom_component_id)
      }
      return item.section_key !== ''
    })
    .sort((a, b) => a.order - b.order)
}

export const getEnabledSidebarSections = (items, options = {}) => {
  const normalized = normalizeSidebarSections(items)
  const hasPreferredSectionKeys = Array.isArray(options?.preferredSectionKeys)
  const preferredSectionKeys = normalizePreferredSectionKeys(options?.preferredSectionKeys)
  const enabledSource = normalized.filter((item) => item.is_enabled)
  const sectionByKey = new Map(normalized.map((item) => [String(item?.section_key || ''), item]))
  const enabledItems = hasPreferredSectionKeys
    ? preferredSectionKeys
      .map((key) => sectionByKey.get(key))
      .filter(Boolean)
    : enabledSource
  const guestCollapsed = collapseObservingSectionsForGuest(
    enabledItems,
    { isGuest: Boolean(options?.isGuest) },
  )
  const enabled = collapseObservingSectionsForMissingLocation(
    guestCollapsed,
    {
      isGuest: Boolean(options?.isGuest),
      collapseObservingForMissingLocation: Boolean(options?.collapseObservingForMissingLocation),
    },
  )
  const exclusiveKeySet = new Set(EXCLUSIVE_SIDEBAR_SECTION_KEYS)
  const exclusiveSection = enabled.find((item) => exclusiveKeySet.has(item.section_key))

  if (exclusiveSection) {
    return [exclusiveSection]
  }

  return enabled.slice(0, MAX_ENABLED_SIDEBAR_WIDGETS)
}

export const resolveSidebarComponent = (section) => {
  if (typeof section === 'string') {
    return sidebarComponentMap[section] || null
  }

  if (!section || typeof section !== 'object') {
    return null
  }

  if (section.kind === 'custom_component') {
    const type = String(normalizeWidgetType(section?.custom_component?.type || ''))
    return customSidebarComponentMap[type] || null
  }

  return sidebarComponentMap[String(section.section_key || '')] || null
}

export const resolveSidebarIcon = (section) => {
  const sectionKey = typeof section === 'string'
    ? section
    : section?.kind === 'custom_component'
      ? 'custom_component'
      : String(section?.section_key || '')

  return (
    sidebarIconMap[sectionKey] || {
      viewBox: '0 0 24 24',
      paths: ['M4 5h16v14H4z'],
    }
  )
}
