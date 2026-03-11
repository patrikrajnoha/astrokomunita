import { computed, ref } from 'vue'
import {
  buildWidgetProps,
  createSheetTouchHandlers,
  normalizeComposerAction,
  normalizeComposerAttachmentFile,
} from './appLayout.utils'

export const APP_LAYOUT_COMPOSER_OPEN_EVENT = 'post:composer:open'

const LAST_WIDGET_STORAGE_KEY = 'mobile_sidebar_last_widget'
const DEFAULT_WIDGET_TITLE = 'Widget'

export function useAppLayoutWidgets({
  auth,
  enabledMobileSections,
  isMobileViewport,
  observingContext,
  warmSidebarConfig,
}) {
  const isDrawerOpen = ref(false)
  const isComposerOpen = ref(false)
  const composerInitialAction = ref('post')
  const composerInitialAttachmentFile = ref(null)
  const isWidgetMenuOpen = ref(false)
  const isWidgetSheetOpen = ref(false)
  const showAllWidgets = ref(false)
  const activeWidgetKey = ref('')
  const activeWidgetTitle = ref(DEFAULT_WIDGET_TITLE)
  const widgetSheetOffsetY = ref(0)
  const widgetMenuOffsetY = ref(0)
  const touchStartY = ref(0)
  const touchMode = ref('')
  const lastWidgetKey = ref('')

  const lastOpenedWidget = computed(() => {
    if (!lastWidgetKey.value) return null
    return (
      enabledMobileSections.value.find((section) => section.section_key === lastWidgetKey.value) ||
      null
    )
  })

  const closeComposerModal = () => {
    isComposerOpen.value = false
    composerInitialAction.value = 'post'
    composerInitialAttachmentFile.value = null
  }

  const closeDrawer = () => {
    isDrawerOpen.value = false
  }

  const openDrawer = () => {
    closeComposerModal()
    isDrawerOpen.value = true
  }

  const closeWidgetMenu = () => {
    isWidgetMenuOpen.value = false
    widgetMenuOffsetY.value = 0
  }

  const closeWidgetSheet = () => {
    isWidgetSheetOpen.value = false
    showAllWidgets.value = false
    activeWidgetKey.value = ''
    activeWidgetTitle.value = DEFAULT_WIDGET_TITLE
    widgetSheetOffsetY.value = 0
  }

  const closeWidgetLayers = () => {
    closeWidgetMenu()
    closeWidgetSheet()
  }

  const openComposerModal = (action = 'post', { attachmentFile = null } = {}) => {
    if (!auth.isAuthed) return
    closeWidgetLayers()
    closeDrawer()
    composerInitialAction.value = normalizeComposerAction(action)
    composerInitialAttachmentFile.value = normalizeComposerAttachmentFile(attachmentFile)
    isComposerOpen.value = true
  }

  const handleComposerOpenEvent = (event) => {
    const action = normalizeComposerAction(event?.detail?.action)
    const attachmentFile = normalizeComposerAttachmentFile(event?.detail?.attachmentFile)
    openComposerModal(action, { attachmentFile })
  }

  const openWidgetsMenu = async () => {
    if (!isMobileViewport.value) return
    closeComposerModal()
    closeDrawer()
    await warmSidebarConfig()
    isWidgetMenuOpen.value = true
  }

  const openWidgetSheet = (section) => {
    if (!section) return
    activeWidgetKey.value = section.section_key
    activeWidgetTitle.value = section.title || DEFAULT_WIDGET_TITLE
    showAllWidgets.value = false
    closeWidgetMenu()
    isWidgetSheetOpen.value = true
    lastWidgetKey.value = section.section_key

    if (typeof window !== 'undefined') {
      window.localStorage.setItem(LAST_WIDGET_STORAGE_KEY, section.section_key)
    }
  }

  const openAllWidgetsSheet = () => {
    showAllWidgets.value = true
    activeWidgetKey.value = ''
    activeWidgetTitle.value = 'Vsetky widgety'
    closeWidgetMenu()
    isWidgetSheetOpen.value = true
  }

  const openComposerFromWidgets = () => {
    closeWidgetMenu()
    openComposerModal('post')
  }

  const propsForWidget = (sectionKey, title) => {
    return buildWidgetProps(sectionKey, title, observingContext.value || {})
  }

  const hydrateLastWidgetFromStorage = () => {
    if (typeof window === 'undefined') return
    const persisted = window.localStorage.getItem(LAST_WIDGET_STORAGE_KEY)
    if (persisted) {
      lastWidgetKey.value = persisted
    }
  }

  const {
    onSheetTouchEnd,
    onSheetTouchMove,
    onSheetTouchStart,
  } = createSheetTouchHandlers({
    closeWidgetMenu,
    closeWidgetSheet,
    touchMode,
    touchStartY,
    widgetMenuOffsetY,
    widgetSheetOffsetY,
  })

  return {
    activeWidgetKey,
    activeWidgetTitle,
    closeComposerModal,
    closeDrawer,
    closeWidgetLayers,
    closeWidgetMenu,
    closeWidgetSheet,
    composerInitialAction,
    composerInitialAttachmentFile,
    handleComposerOpenEvent,
    hydrateLastWidgetFromStorage,
    isComposerOpen,
    isDrawerOpen,
    isWidgetMenuOpen,
    isWidgetSheetOpen,
    lastOpenedWidget,
    onSheetTouchEnd,
    onSheetTouchMove,
    onSheetTouchStart,
    openAllWidgetsSheet,
    openComposerFromWidgets,
    openComposerModal,
    openDrawer,
    openWidgetSheet,
    openWidgetsMenu,
    propsForWidget,
    showAllWidgets,
    widgetMenuOffsetY,
    widgetSheetOffsetY,
  }
}
