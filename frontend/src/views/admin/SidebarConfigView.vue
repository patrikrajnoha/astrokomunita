<template src="./sidebarConfig/SidebarConfigView.template.html"></template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import draggable from 'vuedraggable'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { sidebarConfigAdminApi, sidebarCustomComponentsAdminApi } from '@/services/api/admin/sidebarConfig'
import { DEFAULT_SIDEBAR_SCOPE, SIDEBAR_SCOPE } from '@/generated/sidebarScopes'
import { MAX_ENABLED_SIDEBAR_WIDGETS } from '@/sidebar/engine'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import SidebarBuilderRow from '@/components/admin/sidebar/SidebarBuilderRow.vue'
import SidebarCustomComponentsView from '@/components/admin/sidebar/SidebarCustomComponentsView.vue'
import SidebarComponentRegistryView from '@/components/admin/sidebar/SidebarComponentRegistryView.vue'
import { createScopeTabs, MODE_TABS } from './sidebarConfig/sidebarConfigView.constants'

const modeTabs = MODE_TABS
const scopeTabs = createScopeTabs(SIDEBAR_SCOPE)
const LAYOUT_AUTOSAVE_DELAY_MS = 700

const activeMode = ref('layout')
const activeScope = ref(DEFAULT_SIDEBAR_SCOPE)
const sections = ref([])
const originalSections = ref([])
const loadingScope = ref(false)
const savingLayout = ref(false)
const error = ref('')
const retryLoading = ref(false)
const customSearch = ref('')
const layoutSearch = ref('')
const availableCustomComponents = ref([])
const stickyErrorBanner = ref('')
const shownErrorMessages = ref(new Set())
const customFormDirty = ref(false)
const customViewRef = ref(null)
const lastSavedAt = ref(null)
const saveError = ref('')
const selectedSectionKey = ref('')
let autoSaveTimerId = null
let autoSaveQueued = false

const { showToast } = useToast()
const { confirm } = useConfirm()
const sidebarConfigStore = useSidebarConfigStore()

const activeTabLabel = computed(() => scopeTabs.find((tab) => tab.value === activeScope.value)?.label || 'Domov')

const hasLayoutSearch = computed(() => String(layoutSearch.value || '').trim().length > 0)

const activeCustomUsageMap = computed(() => {
  return sections.value.reduce((acc, item) => {
    if (item.kind !== 'custom_component') return acc

    const id = Number(item.custom_component_id)
    if (!Number.isFinite(id) || id < 1) return acc

    acc[id] = (acc[id] || 0) + 1
    return acc
  }, {})
})

const sectionMatchesLayoutSearch = (section) => {
  const query = String(layoutSearch.value || '').trim().toLowerCase()
  if (!query) return true

  const haystack = [
    section?.title,
    section?.section_key,
    section?.kind,
    section?.custom_component_id,
  ]
    .map((value) => String(value || '').toLowerCase())
    .join(' ')

  return haystack.includes(query)
}

const filteredSections = computed(() => {
  if (!hasLayoutSearch.value) return sections.value
  return sections.value.filter((section) => sectionMatchesLayoutSearch(section))
})

const enabledSectionsCount = computed(() => sections.value.filter((section) => section.is_enabled).length)

const layoutSummary = computed(() => {
  const total = sections.value.length
  const shown = filteredSections.value.length

  if (hasLayoutSearch.value) {
    return `Zobrazenych ${shown} z ${total} poloziek - ${enabledSectionsCount.value} aktivne`
  }

  return `${total} poloziek - ${enabledSectionsCount.value} aktivne`
})

const selectedSection = computed(() => {
  return sections.value.find((item) => String(item?.client_key || '') === String(selectedSectionKey.value || '')) || null
})

const showRightPanel = computed(() => {
  return Boolean(selectedSection.value) || availableCustomComponents.value.length > 0
})

const formatSavedAt = (value) => {
  if (!value) return null
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return null

  return new Intl.DateTimeFormat('sk-SK', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed)
}

const normalizeLayoutItems = (items) => {
  return [...(Array.isArray(items) ? items : [])]
    .map((item, index) => {
      const kind = item?.kind === 'custom_component' ? 'custom_component' : 'builtin'
      const customId = Number.isFinite(Number(item?.custom_component_id)) ? Number(item.custom_component_id) : null
      const key = kind === 'custom_component' ? `custom:${customId}:${index}` : `builtin:${item.section_key}`

      return {
        client_key: key,
        kind,
        section_key: kind === 'builtin' ? String(item.section_key || '') : 'custom_component',
        title: String(item.title || ''),
        custom_component_id: customId,
        custom_component: item.custom_component || null,
        order: Number.isFinite(item.order) ? Number(item.order) : 0,
        is_enabled: Boolean(item.is_enabled),
      }
    })
    .sort((a, b) => a.order - b.order)
}

const hasBuilderChanges = computed(() => {
  const current = JSON.stringify(
    sections.value.map((item, index) => ({
      kind: item.kind,
      section_key: item.section_key,
      custom_component_id: item.custom_component_id,
      order: index,
      is_enabled: item.is_enabled,
    })),
  )

  const initial = JSON.stringify(
    originalSections.value.map((item, index) => ({
      kind: item.kind,
      section_key: item.section_key,
      custom_component_id: item.custom_component_id,
      order: index,
      is_enabled: item.is_enabled,
    })),
  )

  return current !== initial
})

const layoutAutosaveSignature = computed(() => {
  return JSON.stringify(
    sections.value.map((item, index) => ({
      kind: item.kind,
      section_key: item.section_key,
      custom_component_id: item.custom_component_id,
      order: index,
      is_enabled: item.is_enabled,
    })),
  )
})

const syncStateLabel = computed(() => {
  if (savingLayout.value) return 'Uklada sa'
  if (saveError.value) return 'Chyba pri automatickom ukladani'
  if (hasBuilderChanges.value) return 'Caka na automaticke ulozenie'
  return 'Synchronizovane'
})

const updatedAtLabel = computed(() => {
  const formatted = formatSavedAt(lastSavedAt.value)
  if (!formatted) return 'bez ulozenia'
  return `naposledy ${formatted}`
})

const headerMetaLine = computed(() => {
  return `${activeTabLabel.value} - ${syncStateLabel.value} - ${updatedAtLabel.value}`
})

const saveStateLabel = computed(() => {
  if (savingLayout.value) return 'Ukladam...'
  if (saveError.value) return 'Chyba pri automatickom ukladani'
  if (hasBuilderChanges.value) return 'Caka na ulozenie...'
  if (lastSavedAt.value) return 'Ulozene automaticky'
  return 'Pripravene'
})

const saveStateTone = computed(() => {
  if (savingLayout.value) return 'isSaving'
  if (saveError.value) return 'isError'
  if (hasBuilderChanges.value) return 'isDirty'
  return 'isSaved'
})

const filteredAvailableCustomComponents = computed(() => {
  const query = customSearch.value.trim().toLowerCase()
  const rows = [...availableCustomComponents.value]
    .sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || ''), 'sk'))

  if (!query) return rows

  return rows.filter((component) =>
    String(component?.name || '').toLowerCase().includes(query)
      || String(component?.type || '').toLowerCase().includes(query),
  )
})

const applyOrderFromPosition = () => {
  sections.value.forEach((item, index) => {
    item.order = index
  })
}

const applyLayoutRules = (items) => {
  const rows = normalizeLayoutItems(items)

  let activeCount = 0
  rows.forEach((item) => {
    if (!item.is_enabled) return
    if (activeCount >= MAX_ENABLED_SIDEBAR_WIDGETS) {
      item.is_enabled = false
      return
    }
    activeCount += 1
  })

  return rows
}

const setScopeData = (items) => {
  const previousSelectedKey = selectedSectionKey.value

  sections.value = applyLayoutRules(items)
  applyOrderFromPosition()
  originalSections.value = normalizeLayoutItems(sections.value)

  if (!sections.value.some((item) => String(item?.client_key || '') === String(previousSelectedKey || ''))) {
    selectedSectionKey.value = ''
  }
}

const isMissingCustomComponentsTableError = (err) => {
  const code = err?.response?.data?.error_code
  const message = String(err?.response?.data?.message || '')

  return code === 'missing_sidebar_custom_components_table' || message.includes('sidebar_custom_components')
}

const notifyErrorOnce = (message) => {
  const key = String(message || '').trim()
  if (!key || shownErrorMessages.value.has(key)) return

  shownErrorMessages.value.add(key)
  showToast(key, 'error')
}

const handleSidebarLoadError = (err, fallbackMessage) => {
  const message = err?.response?.data?.message || fallbackMessage

  if (isMissingCustomComponentsTableError(err)) {
    stickyErrorBanner.value = 'Chyba DB tabulky sidebar_custom_components. Spusti: php artisan migrate.'
    error.value = message
    console.error('[Sidebar admin] Missing migration for custom components.', err)
    return message
  }

  notifyErrorOnce(message)
  return message
}

const loadScope = async (scope) => {
  loadingScope.value = true
  error.value = ''

  try {
    const payload = await sidebarConfigAdminApi.get(scope)
    setScopeData(payload?.data || [])
    availableCustomComponents.value = Array.isArray(payload?.available_custom_components)
      ? payload.available_custom_components
      : []
    stickyErrorBanner.value = ''
    saveError.value = ''
    lastSavedAt.value = new Date().toISOString()
  } catch (err) {
    const message = handleSidebarLoadError(err, 'Nepodarilo sa nacitat konfiguraciu sidebaru.')
    error.value = message
    setScopeData(sidebarConfigStore.getDefaultForScope())
    availableCustomComponents.value = []
  } finally {
    loadingScope.value = false
  }
}

const retrySidebarLoad = async () => {
  retryLoading.value = true

  try {
    await loadScope(activeScope.value)
  } finally {
    retryLoading.value = false
  }
}

const clearAutoSaveTimer = () => {
  if (autoSaveTimerId !== null) {
    window.clearTimeout(autoSaveTimerId)
    autoSaveTimerId = null
  }
}

const saveLayoutChanges = async ({ silent = true } = {}) => {
  if (!hasBuilderChanges.value || loadingScope.value) return true

  if (savingLayout.value) {
    autoSaveQueued = true
    return false
  }

  savingLayout.value = true
  saveError.value = ''
  error.value = ''

  try {
    const payloadItems = sections.value.map((item, index) => ({
      kind: item.kind,
      section_key: item.kind === 'builtin' ? item.section_key : 'custom_component',
      custom_component_id: item.kind === 'custom_component' ? item.custom_component_id : null,
      order: index,
      is_enabled: Boolean(item.is_enabled),
    }))

    const response = await sidebarConfigAdminApi.update(activeScope.value, payloadItems)
    const savedItems = normalizeLayoutItems(response?.data || payloadItems)

    setScopeData(savedItems)
    sidebarConfigStore.byScope[activeScope.value] = savedItems
    lastSavedAt.value = new Date().toISOString()
    if (!silent) {
      showToast('Rozlozenie sidebaru bolo ulozene.', 'success')
    }
    return true
  } catch (err) {
    const message = err?.response?.data?.message || 'Nepodarilo sa ulozit konfiguraciu sidebaru.'
    saveError.value = message
    error.value = message
    notifyErrorOnce(message)
    return false
  } finally {
    savingLayout.value = false

    if (autoSaveQueued) {
      autoSaveQueued = false
      if (hasBuilderChanges.value) {
        void scheduleAutoSave({ immediate: true })
      }
    }
  }
}

const scheduleAutoSave = async ({ immediate = false } = {}) => {
  clearAutoSaveTimer()

  if (!hasBuilderChanges.value || loadingScope.value) return
  if (savingLayout.value) {
    autoSaveQueued = true
    return
  }

  saveError.value = ''
  const delay = immediate ? 0 : LAYOUT_AUTOSAVE_DELAY_MS
  autoSaveTimerId = window.setTimeout(() => {
    autoSaveTimerId = null
    void saveLayoutChanges()
  }, delay)
}

const flushAutoSave = async () => {
  clearAutoSaveTimer()
  if (!hasBuilderChanges.value) return true
  return saveLayoutChanges()
}

const onScopeClick = async (nextScope) => {
  if (nextScope === activeScope.value) return

  if (hasBuilderChanges.value || savingLayout.value) {
    const persisted = await flushAutoSave()
    if (persisted && !hasBuilderChanges.value) {
      // Saved successfully, no confirmation needed.
    } else {
    const confirmed = await confirm({
      title: 'Neulozene zmeny',
      message: 'Automaticke ulozenie zlyhalo. Pokracovat bez ulozenia?',
      confirmText: 'Pokracovat bez ulozenia',
      cancelText: 'Zostat tu',
      variant: 'danger',
    })
    if (!confirmed) return
    }
  }

  activeScope.value = nextScope
  selectedSectionKey.value = ''
  await loadScope(nextScope)
}

const onModeClick = async (nextMode) => {
  if (nextMode === activeMode.value) return

  if (activeMode.value === 'custom' && customFormDirty.value) {
    const approved = await confirm({
      title: 'Neulozene zmeny vo formulari',
      message: 'Opustenim karty Vlastne komponenty stratis neulozene zmeny formulara.',
      confirmText: 'Opustit kartu',
      cancelText: 'Zostat',
      variant: 'danger',
    })
    if (!approved) return
  }

  activeMode.value = nextMode
}

const dragEnd = () => {
  applyOrderFromPosition()
}

const findSectionIndex = (clientKey) => {
  return sections.value.findIndex((item) => String(item?.client_key || '') === String(clientKey || ''))
}

const canMoveUp = (section) => findSectionIndex(section?.client_key) > 0
const canMoveDown = (section) => {
  const index = findSectionIndex(section?.client_key)
  return index >= 0 && index < sections.value.length - 1
}

const moveSection = (clientKey, direction) => {
  const index = findSectionIndex(clientKey)
  const nextIndex = index + Number(direction)
  if (index < 0 || nextIndex < 0 || nextIndex >= sections.value.length) return

  const nextSections = [...sections.value]
  const [moved] = nextSections.splice(index, 1)
  nextSections.splice(nextIndex, 0, moved)
  sections.value = nextSections
  applyOrderFromPosition()
}

const selectSection = (sectionOrItem) => {
  const key = typeof sectionOrItem === 'string'
    ? sectionOrItem
    : sectionOrItem?.client_key
  selectedSectionKey.value = String(key || '')
}

const toggleSectionEnabled = (section, checked) => {
  const nextEnabled = Boolean(checked)
  if (!section || typeof section !== 'object') return

  saveError.value = ''

  if (!nextEnabled) {
    section.is_enabled = false
    return
  }

  const currentlyEnabled = sections.value.filter(
    (item) => item.is_enabled && String(item.client_key) !== String(section.client_key),
  ).length
  if (currentlyEnabled >= MAX_ENABLED_SIDEBAR_WIDGETS) {
    showToast(`Na jeden sidebar mozu byt aktivne maximalne ${MAX_ENABLED_SIDEBAR_WIDGETS} widgety.`, 'warning')
    return
  }

  section.is_enabled = true
}

const getCustomComponentUsage = (componentId) => {
  const id = Number(componentId)
  if (!Number.isFinite(id) || id < 1) return 0
  return activeCustomUsageMap.value[id] || 0
}

const addCustomComponentToLayout = (component) => {
  if (!component || !component.id) return

  const nextSection = {
    client_key: `custom:${component.id}:${Date.now()}`,
    kind: 'custom_component',
    section_key: 'custom_component',
    title: component.name || `Custom #${component.id}`,
    custom_component_id: component.id,
    custom_component: component,
    order: sections.value.length,
    is_enabled: false,
  }

  sections.value.push(nextSection)
  applyOrderFromPosition()
  selectSection(nextSection)

  toggleSectionEnabled(nextSection, true)
  showToast('Komponent bol pridany do rozlozenia.', 'success')
}

const openCreateCustomComponent = async () => {
  activeMode.value = 'custom'
  await nextTick()
  customViewRef.value?.startCreate?.()
}

const openEditCustomComponent = async (componentOrId) => {
  const componentId = Number(typeof componentOrId === 'object' ? componentOrId?.id : componentOrId)
  if (!Number.isFinite(componentId) || componentId < 1) return

  activeMode.value = 'custom'
  await nextTick()
  customViewRef.value?.openEditor?.(componentId)
}

const editCustomComponentFromLayout = async (section) => {
  const componentId = Number(section?.custom_component_id)
  if (!Number.isFinite(componentId) || componentId < 1) return

  await openEditCustomComponent(componentId)
}

const removeCustomComponentFromLayout = async (section) => {
  const componentName = section?.title || `Custom #${section?.custom_component_id}`
  const confirmed = await confirm({
    title: 'Odobrat z rozlozenia',
    message: `Odobrat "${componentName}" z tohto rozlozenia sidebaru? Komponent zostane vo Vlastnych komponentoch.`,
    confirmText: 'Odobrat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!confirmed) return

  const currentKey = String(section?.client_key || '')
  sections.value = sections.value.filter((item) => String(item?.client_key || '') !== currentKey)
  applyOrderFromPosition()

  if (String(selectedSectionKey.value) === currentKey) {
    selectedSectionKey.value = ''
  }

  showToast('Komponent bol odobrany z rozlozenia.', 'success')
}

const refreshAvailableCustomComponents = async () => {
  try {
    const payload = await sidebarCustomComponentsAdminApi.list({ activeOnly: true })
    const rows = Array.isArray(payload?.data) ? payload.data : []
    availableCustomComponents.value = rows

    const mapById = rows.reduce((acc, item) => {
      const id = Number(item?.id)
      if (Number.isFinite(id) && id > 0) {
        acc[id] = item
      }
      return acc
    }, {})

    sections.value = sections.value.map((item) => {
      if (item.kind !== 'custom_component') return item

      const id = Number(item.custom_component_id)
      if (!Number.isFinite(id) || id < 1) return item

      const linkedComponent = mapById[id]
      if (!linkedComponent) return item

      return {
        ...item,
        title: String(linkedComponent.name || item.title || ''),
        custom_component: linkedComponent,
      }
    })
  } catch (err) {
    const message = err?.response?.data?.message || 'Nepodarilo sa obnovit zoznam custom komponentov.'
    notifyErrorOnce(message)
  }
}

const onCustomComponentsChanged = async () => {
  if (hasBuilderChanges.value) {
    await refreshAvailableCustomComponents()
    showToast('Custom komponenty sa obnovili, lokalne zmeny rozlozenia ostali zachovane.', 'success')
    return
  }

  await loadScope(activeScope.value)
}

const onCustomDirtyChange = (value) => {
  customFormDirty.value = Boolean(value)
}

const sectionIdentifier = (section) => {
  if (!section || typeof section !== 'object') return ''
  if (section.kind === 'custom_component') {
    return `custom:${section.custom_component_id || 'n/a'}`
  }
  return String(section.section_key || '')
}

const beforeUnloadListener = (event) => {
  if (!hasBuilderChanges.value && !savingLayout.value && !customFormDirty.value) return
  event.preventDefault()
  event.returnValue = ''
}

watch(layoutAutosaveSignature, async () => {
  if (!hasBuilderChanges.value) {
    clearAutoSaveTimer()
    return
  }

  await scheduleAutoSave()
})

onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnloadListener)
  await loadScope(activeScope.value)
})

onBeforeUnmount(() => {
  clearAutoSaveTimer()
  window.removeEventListener('beforeunload', beforeUnloadListener)
})

onBeforeRouteLeave(async () => {
  if (hasBuilderChanges.value || savingLayout.value) {
    const persisted = await flushAutoSave()
    if (!persisted && hasBuilderChanges.value) {
      return confirm({
        title: 'Neulozene zmeny',
        message: 'Automaticke ulozenie zlyhalo. Naozaj chces opustit tuto stranku?',
        confirmText: 'Opustit stranku',
        cancelText: 'Zostat tu',
        variant: 'danger',
      })
    }
  }

  if (!customFormDirty.value) return true

  return confirm({
    title: 'Neulozene zmeny',
    message: 'Mas neulozene zmeny. Naozaj chces opustit tuto stranku?',
    confirmText: 'Opustit stranku',
    cancelText: 'Zostat tu',
    variant: 'danger',
  })
})
</script>

<style scoped src="./sidebarConfig/SidebarConfigView.css"></style>
