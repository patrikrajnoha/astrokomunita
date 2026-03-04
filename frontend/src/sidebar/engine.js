import SearchBar from '@/components/SearchBar.vue'
import RightObservingSidebar from '@/components/RightObservingSidebar.vue'
import LatestArticlesWidget from '@/components/widgets/LatestArticlesWidget.vue'
import NasaApodWidget from '@/components/widgets/NasaApodWidget.vue'
import NextEventWidget from '@/components/widgets/NextEventWidget.vue'
import UpcomingEventsWidget from '@/components/widgets/UpcomingEventsWidget.vue'
import SidebarWidgetRenderer from '@/components/widgets/SidebarWidgetRenderer.vue'
import {
  LEGACY_WIDGET_TYPE_SPECIAL_EVENT,
  SIDEBAR_WIDGET_TYPES,
  normalizeWidgetConfig,
  normalizeWidgetType,
} from '@/sidebar/customWidgets/types'

export const sidebarComponentMap = {
  search: SearchBar,
  observing_conditions: RightObservingSidebar,
  nasa_apod: NasaApodWidget,
  next_event: NextEventWidget,
  latest_articles: LatestArticlesWidget,
  upcoming_events: UpcomingEventsWidget,
}

export const customSidebarComponentMap = {
  [SIDEBAR_WIDGET_TYPES.CTA]: SidebarWidgetRenderer,
  [SIDEBAR_WIDGET_TYPES.INFO_CARD]: SidebarWidgetRenderer,
  [SIDEBAR_WIDGET_TYPES.LINK_LIST]: SidebarWidgetRenderer,
  [SIDEBAR_WIDGET_TYPES.HTML]: SidebarWidgetRenderer,
  [LEGACY_WIDGET_TYPE_SPECIAL_EVENT]: SidebarWidgetRenderer,
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
  upcoming_events: {
    viewBox: '0 0 24 24',
    paths: [
      'M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
      'M8 9h8',
      'M8 13h5',
      'M8 17h6',
    ],
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

export const normalizeSidebarSections = (items) => {
  if (!Array.isArray(items)) return []

  return items
    .map((item) => ({
      kind: item?.kind === 'custom_component' ? 'custom_component' : 'builtin',
      section_key: String(item?.section_key || ''),
      title: String(item?.title || ''),
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
    }))
    .filter((item) => {
      if (item.kind === 'custom_component') {
        return Number.isFinite(item.custom_component_id)
      }
      return item.section_key !== ''
    })
    .sort((a, b) => a.order - b.order)
}

export const getEnabledSidebarSections = (items) => {
  return normalizeSidebarSections(items).filter((item) => item.is_enabled)
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
