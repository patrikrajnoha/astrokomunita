// Sidebar domain model:
// - layout builder stores ordered references to widgets per scope,
// - custom component is a reusable widget definition with type + config.

export const SIDEBAR_WIDGET_TYPES = {
  CTA: 'cta',
  INFO_CARD: 'info_card',
  LINK_LIST: 'link_list',
  HTML: 'html',
} as const

export const LEGACY_WIDGET_TYPE_SPECIAL_EVENT = 'special_event' as const

export type SidebarWidgetType = (typeof SIDEBAR_WIDGET_TYPES)[keyof typeof SIDEBAR_WIDGET_TYPES]

export type SidebarWidgetTypeInput = SidebarWidgetType | typeof LEGACY_WIDGET_TYPE_SPECIAL_EVENT | string | null | undefined

export interface SidebarWidgetLink {
  label: string
  href: string
}

export interface SidebarWidgetCtaConfig {
  headline: string
  body: string
  buttonText: string
  buttonHref: string
  imageUrl: string
  icon: string
}

export interface SidebarWidgetInfoCardConfig {
  title: string
  content: string
  icon: string
}

export interface SidebarWidgetLinkListConfig {
  title: string
  links: SidebarWidgetLink[]
}

export interface SidebarWidgetHtmlConfig {
  html: string
}

export type SidebarWidgetConfig =
  | SidebarWidgetCtaConfig
  | SidebarWidgetInfoCardConfig
  | SidebarWidgetLinkListConfig
  | SidebarWidgetHtmlConfig

export interface SidebarCustomComponentPayload {
  id: number | null
  name: string
  type: SidebarWidgetType
  is_active: boolean
  config_json: SidebarWidgetConfig
  updated_at: string | null
  created_at: string | null
}

export interface SidebarCustomComponentFormState {
  id: number | null
  name: string
  type: SidebarWidgetType
  is_active: boolean
  config_json: SidebarWidgetConfig
}

export const SIDEBAR_WIDGET_TYPE_OPTIONS: Array<{ value: SidebarWidgetType; label: string }> = [
  { value: SIDEBAR_WIDGET_TYPES.CTA, label: 'CTA' },
  { value: SIDEBAR_WIDGET_TYPES.INFO_CARD, label: 'Info karta' },
  { value: SIDEBAR_WIDGET_TYPES.LINK_LIST, label: 'Zoznam odkazov' },
  { value: SIDEBAR_WIDGET_TYPES.HTML, label: 'HTML blok' },
]

const typeLabelMap: Record<SidebarWidgetType, string> = {
  [SIDEBAR_WIDGET_TYPES.CTA]: 'CTA',
  [SIDEBAR_WIDGET_TYPES.INFO_CARD]: 'Info karta',
  [SIDEBAR_WIDGET_TYPES.LINK_LIST]: 'Zoznam odkazov',
  [SIDEBAR_WIDGET_TYPES.HTML]: 'HTML blok',
}

const trimString = (value: unknown): string => {
  return typeof value === 'string' ? value.trim() : ''
}

const normalizeLinks = (value: unknown): SidebarWidgetLink[] => {
  if (!Array.isArray(value)) return []

  return value.map((item) => ({
    label: trimString((item as Record<string, unknown>)?.label),
    href: trimString((item as Record<string, unknown>)?.href),
  }))
}

const normalizeCtaConfig = (input: unknown): SidebarWidgetCtaConfig => {
  const source = input && typeof input === 'object' ? (input as Record<string, unknown>) : {}
  const legacyEventId = Number((source.eventId as number | string | undefined) ?? Number.NaN)
  const legacyFallbackHref = Number.isFinite(legacyEventId) && legacyEventId > 0 ? `/events/${legacyEventId}` : ''

  const buttonHref = trimString(source.buttonHref) || trimString(source.buttonTarget) || legacyFallbackHref

  return {
    headline: trimString(source.headline) || trimString(source.title),
    body: trimString(source.body) || trimString(source.description),
    buttonText: trimString(source.buttonText) || trimString(source.buttonLabel),
    buttonHref,
    imageUrl: trimString(source.imageUrl),
    icon: trimString(source.icon),
  }
}

const normalizeInfoCardConfig = (input: unknown): SidebarWidgetInfoCardConfig => {
  const source = input && typeof input === 'object' ? (input as Record<string, unknown>) : {}

  return {
    title: trimString(source.title),
    content: trimString(source.content),
    icon: trimString(source.icon),
  }
}

const normalizeLinkListConfig = (input: unknown): SidebarWidgetLinkListConfig => {
  const source = input && typeof input === 'object' ? (input as Record<string, unknown>) : {}

  return {
    title: trimString(source.title),
    links: normalizeLinks(source.links),
  }
}

const normalizeHtmlConfig = (input: unknown): SidebarWidgetHtmlConfig => {
  const source = input && typeof input === 'object' ? (input as Record<string, unknown>) : {}

  return {
    html: trimString(source.html),
  }
}

export const normalizeWidgetType = (type: SidebarWidgetTypeInput): SidebarWidgetType => {
  const raw = String(type || '').trim().toLowerCase()

  if (raw === LEGACY_WIDGET_TYPE_SPECIAL_EVENT) {
    return SIDEBAR_WIDGET_TYPES.CTA
  }

  if (raw === SIDEBAR_WIDGET_TYPES.INFO_CARD) return SIDEBAR_WIDGET_TYPES.INFO_CARD
  if (raw === SIDEBAR_WIDGET_TYPES.LINK_LIST) return SIDEBAR_WIDGET_TYPES.LINK_LIST
  if (raw === SIDEBAR_WIDGET_TYPES.HTML) return SIDEBAR_WIDGET_TYPES.HTML

  return SIDEBAR_WIDGET_TYPES.CTA
}

export const normalizeWidgetConfig = (
  typeInput: SidebarWidgetTypeInput,
  configInput: unknown,
): SidebarWidgetConfig => {
  const type = normalizeWidgetType(typeInput)

  if (type === SIDEBAR_WIDGET_TYPES.INFO_CARD) {
    return normalizeInfoCardConfig(configInput)
  }

  if (type === SIDEBAR_WIDGET_TYPES.LINK_LIST) {
    return normalizeLinkListConfig(configInput)
  }

  if (type === SIDEBAR_WIDGET_TYPES.HTML) {
    return normalizeHtmlConfig(configInput)
  }

  return normalizeCtaConfig(configInput)
}

export const createEmptyWidgetFormState = (
  type: SidebarWidgetType = SIDEBAR_WIDGET_TYPES.CTA,
): SidebarCustomComponentFormState => ({
  id: null,
  name: '',
  type,
  is_active: true,
  config_json: normalizeWidgetConfig(type, {}),
})

export const normalizeSidebarCustomComponent = (payload: unknown): SidebarCustomComponentPayload => {
  const source = payload && typeof payload === 'object' ? (payload as Record<string, unknown>) : {}
  const type = normalizeWidgetType(source.type as SidebarWidgetTypeInput)
  const idValue = Number(source.id)

  return {
    id: Number.isFinite(idValue) && idValue > 0 ? idValue : null,
    name: trimString(source.name),
    type,
    is_active: Boolean(source.is_active ?? true),
    config_json: normalizeWidgetConfig(type, source.config_json ?? source.config),
    updated_at: trimString(source.updated_at) || null,
    created_at: trimString(source.created_at) || null,
  }
}

export const getWidgetTypeLabel = (typeInput: SidebarWidgetTypeInput): string => {
  const type = normalizeWidgetType(typeInput)
  return typeLabelMap[type]
}

export const cloneWidgetFormState = (
  state: SidebarCustomComponentFormState,
): SidebarCustomComponentFormState => {
  const type = normalizeWidgetType(state.type)

  return {
    id: Number.isFinite(Number(state.id)) ? Number(state.id) : null,
    name: trimString(state.name),
    type,
    is_active: Boolean(state.is_active),
    config_json: normalizeWidgetConfig(type, state.config_json),
  }
}
