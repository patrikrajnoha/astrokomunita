<template>
  <div class="sidebarAdmin">
    <header class="pageHeader">
      <h1>Konfiguracia sidebaru</h1>
      <p class="metaLine">{{ headerMetaLine }}</p>
    </header>

    <div class="modeTabs" role="tablist" aria-label="Rezim spravy sidebaru">
      <button
        v-for="mode in modeTabs"
        :key="mode.value"
        type="button"
        class="modeTab"
        :class="{ active: activeMode === mode.value }"
        @click="onModeClick(mode.value)"
      >
        {{ mode.label }}
      </button>
    </div>

    <div v-if="stickyErrorBanner" class="alert alertError alertSticky" role="alert">
      <div>{{ stickyErrorBanner }}</div>
      <button class="btn btnGhost" type="button" :disabled="retryLoading" @click="retrySidebarLoad">
        {{ retryLoading ? 'Opakujem...' : 'Skusit znova' }}
      </button>
    </div>

    <section v-if="activeMode === 'layout'" class="modePanel">
      <div class="scopeRow">
        <span class="scopeLabel">Kontext</span>
        <div class="scopeTabs" role="tablist" aria-label="Kontexty sidebaru">
          <button
            v-for="tab in scopeTabs"
            :key="tab.value"
            type="button"
            class="scopeTab"
            :class="{ active: activeScope === tab.value }"
            :disabled="loadingScope || savingLayout"
            @click="onScopeClick(tab.value)"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div class="toolbar">
        <div class="searchWrap">
          <input
            v-model="layoutSearch"
            type="text"
            class="searchInput"
            placeholder="Hladat polozky sidebaru"
          />
          <p class="summaryLine">{{ layoutSummary }}</p>
        </div>

        <div class="toolbarActions">
          <span class="saveState" :class="saveStateTone">{{ saveStateLabel }}</span>
          <button
            type="button"
            class="btn btnGhost"
            :disabled="!hasBuilderChanges || loadingScope || savingLayout"
            @click="resetLayoutChanges"
          >
            Reset zmien
          </button>
          <button
            type="button"
            class="btn btnPrimary"
            :disabled="loadingScope || savingLayout || !hasBuilderChanges"
            @click="saveLayoutChanges"
          >
            <span v-if="savingLayout" class="spinner"></span>
            {{ savingLayout ? 'Ukladam...' : 'Ulozit rozlozenie' }}
          </button>
        </div>
      </div>

      <div v-if="error" class="alert alertError">{{ error }}</div>

      <div class="builderGrid" :class="{ singleColumn: !showRightPanel }">
        <section class="listPanel">
          <header class="listHeader">
            <h2>Polozky sidebaru</h2>
          </header>

          <div v-if="filteredSections.length === 0" class="emptyState">
            <h3>Ziadne polozky nevyhovuju filtru</h3>
            <p>Skus iny vyraz alebo vycisti filter.</p>
            <button v-if="hasLayoutSearch" type="button" class="btn btnGhost" @click="layoutSearch = ''">Vymazat filter</button>
          </div>

          <draggable
            v-else-if="!hasLayoutSearch"
            v-model="sections"
            item-key="client_key"
            handle=".dragHandle"
            class="rowsList"
            @end="dragEnd"
          >
            <template #item="{ element: section }">
              <SidebarBuilderRow
                :section="section"
                :selected="String(selectedSectionKey) === String(section.client_key)"
                :draggable-enabled="true"
                @select="selectSection"
                @toggle="toggleSectionEnabled"
              />
            </template>
          </draggable>

          <div v-else class="rowsList">
            <SidebarBuilderRow
              v-for="section in filteredSections"
              :key="section.client_key"
              :section="section"
              :selected="String(selectedSectionKey) === String(section.client_key)"
              :draggable-enabled="false"
              @select="selectSection"
              @toggle="toggleSectionEnabled"
            />
          </div>
        </section>

        <aside v-if="showRightPanel" class="sidePanel">
          <section v-if="selectedSection" class="panelSection">
            <header class="panelHeader">
              <h3>Detail polozky</h3>
            </header>

            <dl class="detailList">
              <div>
                <dt>Nazov</dt>
                <dd>{{ selectedSection.title || 'Bez nazvu' }}</dd>
              </div>
              <div>
                <dt>Identifikator</dt>
                <dd>{{ sectionIdentifier(selectedSection) }}</dd>
              </div>
              <div>
                <dt>Stav</dt>
                <dd>{{ selectedSection.is_enabled ? 'Viditelna' : 'Skryta' }}</dd>
              </div>
            </dl>

            <div class="panelActions">
              <button
                type="button"
                class="btn btnGhost"
                :disabled="!canMoveUp(selectedSection)"
                @click="moveSection(selectedSection.client_key, -1)"
              >
                Posunut vyssie
              </button>
              <button
                type="button"
                class="btn btnGhost"
                :disabled="!canMoveDown(selectedSection)"
                @click="moveSection(selectedSection.client_key, 1)"
              >
                Posunut nizsie
              </button>
              <button
                v-if="selectedSection.kind === 'custom_component'"
                type="button"
                class="btn btnGhost"
                @click="editCustomComponentFromLayout(selectedSection)"
              >
                Upravit komponent
              </button>
              <button
                v-if="selectedSection.kind === 'custom_component'"
                type="button"
                class="btn btnDanger"
                @click="removeCustomComponentFromLayout(selectedSection)"
              >
                Odobrat z rozlozenia
              </button>
            </div>
          </section>

          <section v-if="availableCustomComponents.length > 0" class="panelSection">
            <header class="panelHeader panelHeaderTight">
              <h3>Vlastne komponenty</h3>
              <button type="button" class="btn btnGhost" @click="openCreateCustomComponent">Novy komponent</button>
            </header>

            <input
              v-model="customSearch"
              type="text"
              class="searchInput"
              placeholder="Hladat komponent"
            />

            <div v-if="filteredAvailableCustomComponents.length === 0" class="emptyInline">
              Ziadny komponent nevyhovuje filtru.
            </div>

            <ul v-else class="customList">
              <li v-for="component in filteredAvailableCustomComponents" :key="component.id" class="customItem">
                <div class="customCopy">
                  <strong>{{ component.name }}</strong>
                  <span>{{ component.type }} - pouzitie {{ getCustomComponentUsage(component.id) }}x</span>
                </div>

                <div class="customActions">
                  <button type="button" class="btn btnSmall" @click="addCustomComponentToLayout(component)">Pridat</button>
                  <button type="button" class="btn btnGhost btnSmall" @click="openEditCustomComponent(component)">Upravit</button>
                </div>
              </li>
            </ul>
          </section>
        </aside>
      </div>
    </section>

    <section v-else-if="activeMode === 'custom'" class="modePanel modePanelFlat">
      <div class="modeToolbar">
        <p>Sprava vlastnych komponentov</p>
        <button type="button" class="btn" @click="openCreateCustomComponent">Novy komponent</button>
      </div>

      <SidebarCustomComponentsView
        ref="customViewRef"
        @components-changed="onCustomComponentsChanged"
        @dirty-change="onCustomDirtyChange"
      />
    </section>

    <section v-else class="modePanel modePanelFlat">
      <SidebarComponentRegistryView />
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
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

const modeTabs = [
  { value: 'layout', label: 'Rozlozenie' },
  { value: 'custom', label: 'Vlastne komponenty' },
  { value: 'widgets', label: 'Widgety' },
]

const scopeTabs = [
  { value: SIDEBAR_SCOPE.HOME, label: 'Domov' },
  { value: SIDEBAR_SCOPE.EVENTS, label: 'Udalosti + kalendar' },
  { value: SIDEBAR_SCOPE.LEARNING, label: 'Vzdelavanie' },
  { value: SIDEBAR_SCOPE.SEARCH, label: 'Vyhladavanie' },
  { value: SIDEBAR_SCOPE.NOTIFICATIONS, label: 'Notifikacie' },
  { value: SIDEBAR_SCOPE.POST_DETAIL, label: 'Detail prispevku' },
  { value: SIDEBAR_SCOPE.ARTICLE_DETAIL, label: 'Detail clanku' },
  { value: SIDEBAR_SCOPE.PROFILE, label: 'Profil' },
  { value: SIDEBAR_SCOPE.SETTINGS, label: 'Nastavenia' },
  { value: SIDEBAR_SCOPE.OBSERVING, label: 'Pozorovanie' },
]

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

const syncStateLabel = computed(() => {
  if (savingLayout.value) return 'Uklada sa'
  if (saveError.value) return 'Chyba pri ukladani'
  if (hasBuilderChanges.value) return 'Neulozene zmeny'
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
  if (saveError.value) return 'Chyba pri ukladani'
  if (hasBuilderChanges.value) return 'Neulozene zmeny'
  if (lastSavedAt.value) return 'Ulozene prave teraz'
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

const onScopeClick = async (nextScope) => {
  if (nextScope === activeScope.value) return

  if (hasBuilderChanges.value) {
    const confirmed = await confirm({
      title: 'Neulozene zmeny',
      message: 'Mas neulozene zmeny rozlozenia. Pokracovat a zahodit ich?',
      confirmText: 'Zahodit zmeny',
      cancelText: 'Zostat tu',
      variant: 'danger',
    })
    if (!confirmed) return
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
  showToast('Komponent bol pridany do rozlozenia. Nezabudni ulozit.', 'success')
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

  showToast('Komponent bol odobrany z rozlozenia. Uloz rozlozenie pre potvrdenie zmien.', 'success')
}

const resetLayoutChanges = async () => {
  if (!hasBuilderChanges.value) return

  const confirmed = await confirm({
    title: 'Reset neulozenych zmien',
    message: 'Naozaj chces vratit rozlozenie do posledneho ulozeneho stavu?',
    confirmText: 'Resetovat',
    cancelText: 'Zrusit',
    variant: 'danger',
  })
  if (!confirmed) return

  setScopeData(originalSections.value)
  saveError.value = ''
  showToast('Neulozene zmeny boli zahodene.', 'success')
}

const saveLayoutChanges = async () => {
  if (!hasBuilderChanges.value) return

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
    showToast('Rozlozenie sidebaru bolo ulozene.', 'success')
  } catch (err) {
    const message = err?.response?.data?.message || 'Nepodarilo sa ulozit konfiguraciu sidebaru.'
    saveError.value = message
    error.value = message
    notifyErrorOnce(message)
  } finally {
    savingLayout.value = false
  }
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
    showToast('Custom komponenty sa obnovili, neulozene zmeny rozlozenia ostali zachovane.', 'success')
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
  if (!hasBuilderChanges.value && !customFormDirty.value) return
  event.preventDefault()
  event.returnValue = ''
}

onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnloadListener)
  await loadScope(activeScope.value)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnloadListener)
})

onBeforeRouteLeave(async () => {
  if (!hasBuilderChanges.value && !customFormDirty.value) return true

  return confirm({
    title: 'Neulozene zmeny',
    message: 'Mas neulozene zmeny. Naozaj chces opustit tuto stranku?',
    confirmText: 'Opustit stranku',
    cancelText: 'Zostat tu',
    variant: 'danger',
  })
})
</script>

<style scoped>
.sidebarAdmin {
  max-width: 1420px;
  margin: 0 auto;
  padding: 1rem;
  display: grid;
  gap: 0.85rem;
}

.heroHeader {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.8rem;
}

.heroHeader h1 {
  margin: 0;
  font-size: 1.52rem;
  font-weight: 800;
  color: var(--color-surface);
}

.heroHeader p {
  margin: 0.28rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.87rem;
  max-width: 52rem;
}

.heroStats {
  display: inline-flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.34rem;
}

.heroPill {
  font-size: 0.7rem;
  padding: 0.2rem 0.56rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.26);
  background: rgb(var(--color-bg-rgb) / 0.3);
  color: var(--color-text-secondary);
}

.heroPill.warning {
  border-color: rgb(var(--color-warning-rgb, 255 178 64) / 0.42);
  color: rgb(var(--color-warning-rgb, 255 178 64));
}

.modeTabs {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.modeBtn {
  display: grid;
  gap: 0.16rem;
  text-align: left;
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.24);
  padding: 0.66rem 0.76rem;
  color: var(--color-text-secondary);
}

.modeBtn strong {
  font-size: 0.84rem;
}

.modeBtn small {
  font-size: 0.72rem;
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.modeBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.55);
  background: rgb(var(--color-primary-rgb) / 0.18);
  color: var(--color-surface);
}

.workspaceCard {
  border-radius: 1rem;
  background: linear-gradient(160deg, rgb(var(--color-bg-rgb) / 0.45), rgb(var(--color-bg-rgb) / 0.28));
  box-shadow: inset 0 0 0 1px rgb(var(--color-text-secondary-rgb) / 0.14);
  padding: 0.88rem;
  display: grid;
  gap: 0.72rem;
}

.workspaceCard--flat {
  background: rgb(var(--color-bg-rgb) / 0.24);
}

.scopeTabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.scopeBtn {
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.26);
  color: var(--color-text-secondary);
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.35rem 0.62rem;
}

.scopeBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.52);
  background: rgb(var(--color-primary-rgb) / 0.2);
  color: var(--color-surface);
}

.workspaceHeader {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.7rem;
}

.workspaceHeader h2 {
  margin: 0;
  font-size: 1.02rem;
}

.workspaceHeader p {
  margin: 0.24rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.8rem;
}

.workspaceHeaderCompact {
  align-items: center;
}

.workspaceActions {
  display: inline-flex;
  align-items: center;
  gap: 0.34rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.uiBtn {
  min-height: 2.05rem;
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.3);
  color: var(--color-surface);
  font-size: 0.76rem;
  font-weight: 700;
  padding: 0.44rem 0.72rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
}

.uiBtn:disabled {
  opacity: 0.56;
}

.uiBtnPrimary {
  border-color: rgb(var(--color-primary-rgb) / 0.6);
  background: rgb(var(--color-primary-rgb) / 0.25);
}

.uiBtnGhost {
  background: transparent;
}

.uiBtnSmall {
  min-height: 1.84rem;
  padding: 0.3rem 0.56rem;
  font-size: 0.72rem;
}

.quickStats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.statPill {
  font-size: 0.71rem;
  padding: 0.18rem 0.52rem;
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.28);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  color: var(--color-text-secondary);
}

.builderGrid {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  gap: 0.72rem;
}

.builderPanel,
.catalogPanel {
  border-radius: 0.9rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  background: rgb(var(--color-bg-rgb) / 0.2);
  padding: 0.72rem;
  display: grid;
  gap: 0.56rem;
}

.panelHeader {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.54rem;
}

.panelHeader h3 {
  margin: 0;
  font-size: 0.9rem;
}

.panelHeader p {
  margin: 0.2rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.76rem;
}

.searchField {
  display: grid;
  gap: 0.2rem;
  min-width: 12rem;
}

.searchField span {
  font-size: 0.69rem;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.searchField input {
  min-height: 2rem;
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.35);
  color: var(--color-surface);
  font-size: 0.77rem;
  padding: 0.36rem 0.52rem;
}

.sectionsList {
  display: grid;
  gap: 0.46rem;
}

.sectionItem {
  border-radius: 0.84rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.17);
  background: rgb(var(--color-bg-rgb) / 0.25);
  padding: 0.55rem;
  display: grid;
  gap: 0.48rem;
}

.sectionItem.isHidden {
  opacity: 0.66;
}

.sectionItem.isMuted {
  opacity: 0.38;
}

.sectionTop {
  display: flex;
  gap: 0.46rem;
  align-items: flex-start;
}

.dragHandle {
  border: 0;
  background: transparent;
  color: var(--color-text-secondary);
  cursor: grab;
  font-weight: 700;
  font-size: 0.92rem;
  line-height: 1;
  padding: 0.2rem;
}

.sectionCopy {
  min-width: 0;
  flex: 1;
}

.sectionTitleRow {
  display: flex;
  gap: 0.48rem;
  justify-content: space-between;
  align-items: flex-start;
}

.sectionTitleRow h4 {
  margin: 0;
  font-size: 0.86rem;
  color: var(--color-surface);
}

.sectionBadges {
  display: inline-flex;
  gap: 0.25rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.kindBadge,
.visibilityBadge {
  border-radius: 999px;
  padding: 0.11rem 0.42rem;
  font-size: 0.63rem;
  font-weight: 700;
}

.kindBadge {
  background: rgb(var(--color-primary-rgb) / 0.15);
  color: var(--color-surface);
}

.visibilityBadge {
  background: rgb(var(--color-text-secondary-rgb) / 0.18);
  color: var(--color-text-secondary);
}

.visibilityBadge.on {
  background: rgb(var(--color-success-rgb) / 0.2);
  color: var(--color-success);
}

.sectionMeta {
  margin-top: 0.14rem;
  display: flex;
  gap: 0.42rem;
  flex-wrap: wrap;
  align-items: center;
}

.sectionMeta code {
  font-size: 0.7rem;
  color: var(--color-surface);
}

.sectionMeta span {
  font-size: 0.68rem;
  color: var(--color-text-secondary);
}

.sectionBottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.moveControls {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
}

.iconBtn {
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 0.54rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.3);
  color: var(--color-surface);
  font-size: 0.66rem;
  font-weight: 700;
}

.inlineActions {
  display: inline-flex;
  align-items: center;
  gap: 0.32rem;
}

.textBtn {
  border: 0;
  background: transparent;
  color: var(--color-primary);
  font-size: 0.73rem;
  font-weight: 600;
  padding: 0.1rem 0.18rem;
}

.textBtn.danger {
  color: var(--color-danger);
}

.toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.44rem;
}

.toggle input[type='checkbox'] {
  display: none;
}

.toggleSlider {
  width: 39px;
  height: 20px;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.35);
  position: relative;
}

.toggleSlider::before {
  content: '';
  position: absolute;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  top: 2px;
  left: 2px;
  background: white;
  transition: transform 0.2s ease;
}

.toggle input[type='checkbox']:checked + .toggleSlider {
  background: rgb(var(--color-primary-rgb) / 0.68);
}

.toggle input[type='checkbox']:checked + .toggleSlider::before {
  transform: translateX(19px);
}

.toggleLabel {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

.availableList {
  display: grid;
  gap: 0.4rem;
}

.availableItem {
  border-radius: 0.72rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(var(--color-bg-rgb) / 0.22);
  padding: 0.45rem;
  display: flex;
  gap: 0.52rem;
  justify-content: space-between;
  align-items: center;
}

.availableCopy {
  min-width: 0;
}

.availableNameRow {
  display: flex;
  gap: 0.34rem;
  align-items: center;
  flex-wrap: wrap;
}

.availableNameRow strong {
  font-size: 0.81rem;
}

.typeBadge {
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.18);
  color: var(--color-text-secondary);
  padding: 0.09rem 0.4rem;
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
}

.availableMeta {
  margin-top: 0.16rem;
  display: inline-flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  font-size: 0.67rem;
  color: var(--color-text-secondary);
}

.availableActions {
  display: inline-flex;
  gap: 0.22rem;
}

.emptyBox {
  border-radius: 0.72rem;
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.2);
  padding: 0.8rem;
  display: grid;
  gap: 0.42rem;
  justify-items: start;
}

.emptyBox p {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

.alert {
  border-radius: 0.72rem;
  padding: 0.56rem;
  font-size: 0.8rem;
}

.alertError {
  background: rgb(var(--color-danger-rgb) / 0.1);
  color: var(--color-danger);
}

.alertSticky {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.8rem;
}

.spinner {
  width: 13px;
  height: 13px;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.sidebarAdmin {
  max-width: 1380px;
  padding: 1rem;
  gap: 0.9rem;
}

.pageHeader h1 {
  margin: 0;
  font-size: 1.42rem;
  color: var(--color-surface);
}

.metaLine {
  margin: 0.25rem 0 0;
  color: var(--color-text-secondary);
  font-size: 0.8rem;
}

.modeTabs {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 0.28rem;
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.2);
  padding-bottom: 0.4rem;
}

.modeTab {
  border: 0;
  border-radius: 0.56rem;
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.4rem 0.64rem;
}

.modeTab.active {
  background: rgb(var(--color-primary-rgb) / 0.17);
  color: var(--color-surface);
}

.modePanel {
  border-radius: 0.9rem;
  background: rgb(var(--color-bg-rgb) / 0.2);
  box-shadow: inset 0 0 0 1px rgb(var(--color-text-secondary-rgb) / 0.14);
  padding: 0.78rem;
  display: grid;
  gap: 0.7rem;
}

.modePanelFlat {
  background: transparent;
  box-shadow: none;
  border: 0;
  padding: 0;
}

.scopeRow {
  display: grid;
  gap: 0.34rem;
}

.scopeLabel {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

.scopeTabs {
  display: flex;
  gap: 0.3rem;
  overflow-x: auto;
  padding-bottom: 0.2rem;
}

.scopeTab {
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  background: rgb(var(--color-bg-rgb) / 0.25);
  color: var(--color-text-secondary);
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
  padding: 0.28rem 0.58rem;
}

.scopeTab.active {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  background: rgb(var(--color-primary-rgb) / 0.19);
  color: var(--color-surface);
}

.toolbar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.7rem;
}

.searchWrap {
  min-width: 0;
  flex: 1;
}

.searchInput {
  width: 100%;
  min-height: 2rem;
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  background: rgb(var(--color-bg-rgb) / 0.34);
  color: var(--color-surface);
  font-size: 0.78rem;
  padding: 0.36rem 0.5rem;
}

.summaryLine {
  margin: 0.25rem 0 0;
  font-size: 0.74rem;
  color: var(--color-text-secondary);
}

.toolbarActions {
  display: inline-flex;
  align-items: center;
  gap: 0.34rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.saveState {
  font-size: 0.73rem;
  color: var(--color-text-secondary);
}

.saveState.isDirty {
  color: rgb(var(--color-warning-rgb, 255 178 64));
}

.saveState.isError {
  color: var(--color-danger);
}

.saveState.isSaving {
  color: var(--color-surface);
}

.btn {
  min-height: 1.94rem;
  border-radius: 0.66rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.32);
  color: var(--color-surface);
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.38rem 0.62rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.3rem;
}

.btn:disabled {
  opacity: 0.56;
}

.btnPrimary {
  border-color: rgb(var(--color-primary-rgb) / 0.58);
  background: rgb(var(--color-primary-rgb) / 0.24);
}

.btnGhost {
  background: transparent;
}

.btnDanger {
  border-color: rgb(var(--color-danger-rgb) / 0.44);
  color: var(--color-danger);
  background: rgb(var(--color-danger-rgb) / 0.08);
}

.btnSmall {
  min-height: 1.74rem;
  padding: 0.28rem 0.5rem;
  font-size: 0.72rem;
}

.builderGrid {
  grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.95fr);
}

.builderGrid.singleColumn {
  grid-template-columns: 1fr;
}

.listPanel,
.sidePanel {
  min-width: 0;
  border-radius: 0.82rem;
  background: rgb(var(--color-bg-rgb) / 0.14);
  box-shadow: inset 0 0 0 1px rgb(var(--color-text-secondary-rgb) / 0.14);
  padding: 0.62rem;
}

.listHeader {
  margin-bottom: 0.45rem;
}

.listHeader h2 {
  margin: 0;
  font-size: 0.92rem;
}

.rowsList {
  display: grid;
  gap: 0.38rem;
}

.sidePanel {
  display: grid;
  gap: 0.52rem;
  align-content: start;
}

.panelSection {
  display: grid;
  gap: 0.45rem;
}

.panelHeaderTight {
  align-items: flex-start;
}

.detailList {
  margin: 0;
  display: grid;
  gap: 0.3rem;
}

.detailList div {
  display: grid;
  gap: 0.06rem;
}

.detailList dt {
  font-size: 0.68rem;
  color: var(--color-text-secondary);
}

.detailList dd {
  margin: 0;
  font-size: 0.77rem;
  color: var(--color-surface);
}

.panelActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.28rem;
}

.customList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.35rem;
}

.customItem {
  border-radius: 0.7rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  background: rgb(var(--color-bg-rgb) / 0.2);
  padding: 0.42rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.42rem;
}

.customCopy strong {
  display: block;
  font-size: 0.79rem;
}

.customCopy span {
  display: block;
  margin-top: 0.1rem;
  font-size: 0.7rem;
  color: var(--color-text-secondary);
}

.customActions {
  display: inline-flex;
  gap: 0.24rem;
}

.emptyInline {
  border-radius: 0.7rem;
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.3);
  padding: 0.62rem;
  font-size: 0.75rem;
  color: var(--color-text-secondary);
}

.emptyState {
  border-radius: 0.76rem;
  border: 1px dashed rgb(var(--color-text-secondary-rgb) / 0.3);
  background: rgb(var(--color-bg-rgb) / 0.18);
  padding: 0.75rem;
  display: grid;
  gap: 0.32rem;
  justify-items: start;
}

.emptyState h3 {
  margin: 0;
  font-size: 0.84rem;
}

.emptyState p {
  margin: 0;
  font-size: 0.76rem;
  color: var(--color-text-secondary);
}

.modeToolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  padding: 0.1rem 0.1rem 0.35rem;
}

.modeToolbar p {
  margin: 0;
  font-size: 0.78rem;
  color: var(--color-text-secondary);
}

@media (max-width: 1220px) {
  .builderGrid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 980px) {
  .toolbar,
  .modeToolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .toolbarActions {
    justify-content: flex-start;
  }
}

@media (max-width: 720px) {
  .sidebarAdmin {
    padding: 0.75rem;
  }
}
</style>
