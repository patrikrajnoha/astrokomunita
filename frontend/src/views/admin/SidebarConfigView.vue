<template>
  <div class="sidebarAdmin">
    <header class="heroHeader">
      <div>
        <h1>Konfiguracia sidebaru</h1>
        <p>
          Zachovava existujuci builder rozlozenia, custom widget workflow a doplna
          compact gallery pre sidebar widgety.
        </p>
      </div>

      <div class="heroStats">
        <span class="heroPill">Scope: {{ activeTabLabel }}</span>
        <span class="heroPill" :class="{ warning: hasBuilderChanges }">
          {{ hasBuilderChanges ? 'Neulozene zmeny' : 'Rozlozenie synchronizovane' }}
        </span>
        <span class="heroPill">{{ saveStatusLabel }}</span>
      </div>
    </header>

    <div class="modeTabs" role="tablist" aria-label="Rezim spravy sidebaru">
      <button
        type="button"
        class="modeBtn"
        :class="{ active: activeMode === 'layout' }"
        @click="onModeClick('layout')"
      >
        <strong>Editor rozlozenia</strong>
        <small>Polozky, poradie, viditelnost, scope tabs</small>
      </button>

      <button
        type="button"
        class="modeBtn"
        :class="{ active: activeMode === 'custom' }"
        @click="onModeClick('custom')"
      >
        <strong>Vlastne komponenty</strong>
        <small>Tvorba a editacia custom widgetov</small>
      </button>

      <button
        type="button"
        class="modeBtn"
        :class="{ active: activeMode === 'registry' }"
        @click="onModeClick('registry')"
      >
        <strong>Sidebar widgety</strong>
        <small>Preview + editable props iba pre sidebar komponenty</small>
      </button>
    </div>

    <div v-if="stickyErrorBanner" class="alert alertError alertSticky" role="alert">
      <div>{{ stickyErrorBanner }}</div>
      <button class="uiBtn uiBtnGhost" type="button" :disabled="retryLoading" @click="retrySidebarLoad">
        {{ retryLoading ? 'Opakujem...' : 'Skusit znova' }}
      </button>
    </div>

    <section v-if="activeMode === 'layout'" class="workspaceCard">
      <div class="scopeTabs" role="tablist" aria-label="Kontexty sidebaru">
        <button
          v-for="tab in scopeTabs"
          :key="tab.value"
          type="button"
          class="scopeBtn"
          :class="{ active: activeScope === tab.value }"
          :disabled="loading"
          @click="onScopeClick(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="workspaceHeader">
        <div>
          <h2>{{ activeTabLabel }} rozlozenie</h2>
          <p>Drag and drop, keyboard-friendly reorder a prehladne stavy pre kazdu polozku.</p>
        </div>

        <div class="workspaceActions">
          <button class="uiBtn" type="button" @click="openCreateCustomComponent">Novy komponent</button>
          <button class="uiBtn uiBtnGhost" type="button" :disabled="!hasBuilderChanges || loading" @click="resetLayoutChanges">
            Reset zmen
          </button>
          <button class="uiBtn uiBtnPrimary" :disabled="loading || !hasBuilderChanges" @click="saveLayoutChanges">
            <span v-if="loading" class="spinner"></span>
            {{ loading ? 'Ukladam...' : 'Ulozit rozlozenie' }}
          </button>
        </div>
      </div>

      <div v-if="error" class="alert alertError">{{ error }}</div>

      <div class="quickStats">
        <span class="statPill">Polozky: {{ sections.length }}</span>
        <span class="statPill">Aktivne: {{ enabledSectionsCount }} / {{ MAX_ENABLED_SIDEBAR_WIDGETS }}</span>
        <span class="statPill">Custom komponenty: {{ availableCustomComponents.length }}</span>
        <span class="statPill">Match: {{ matchingSectionsCount }} / {{ sections.length }}</span>
      </div>

      <div class="builderGrid">
        <section class="builderPanel">
          <header class="panelHeader">
            <div>
              <h3>Polozky sidebaru</h3>
              <p>Vstavane aj custom polozky v jednom zozname.</p>
            </div>

            <label class="searchField">
              <span>Filter</span>
              <input
                v-model="layoutSearch"
                type="text"
                placeholder="Nazov, slug, custom..."
              />
            </label>
          </header>

          <draggable
            v-model="sections"
            item-key="client_key"
            handle=".dragHandle"
            class="sectionsList"
            @end="dragEnd"
          >
            <template #item="{ element: section }">
              <article
                class="sectionItem"
                :class="{
                  isHidden: !section.is_enabled,
                  isMuted: hasLayoutSearch && !sectionMatchesLayoutSearch(section),
                }"
              >
                <div class="sectionTop">
                  <button type="button" class="dragHandle" aria-label="Presunut sekciu">::</button>

                  <div class="sectionCopy">
                    <div class="sectionTitleRow">
                      <h4>{{ section.title || 'Bez nazvu' }}</h4>
                      <div class="sectionBadges">
                        <span class="kindBadge">{{ section.kind === 'builtin' ? 'Vstavane' : 'Vlastne' }}</span>
                        <span class="visibilityBadge" :class="{ on: section.is_enabled }">
                          {{ section.is_enabled ? 'Viditelne' : 'Skryte' }}
                        </span>
                      </div>
                    </div>

                    <div class="sectionMeta">
                      <code>{{ section.kind === 'builtin' ? section.section_key : `custom:${section.custom_component_id}` }}</code>
                      <span>Poradie: {{ section.order + 1 }}</span>
                    </div>
                  </div>
                </div>

                <div class="sectionBottom">
                  <div class="moveControls">
                    <button
                      type="button"
                      class="iconBtn"
                      :disabled="!canMoveUp(section)"
                      @click="moveSection(section.client_key, -1)"
                    >
                      &uarr;
                    </button>
                    <button
                      type="button"
                      class="iconBtn"
                      :disabled="!canMoveDown(section)"
                      @click="moveSection(section.client_key, 1)"
                    >
                      &darr;
                    </button>
                  </div>

                  <div v-if="section.kind === 'custom_component'" class="inlineActions">
                    <button type="button" class="textBtn" @click="editCustomComponentFromLayout(section)">Upravit</button>
                    <button type="button" class="textBtn danger" @click="removeCustomComponentFromLayout(section)">Odobrat</button>
                  </div>

                  <label class="toggle">
                    <input
                      :checked="section.is_enabled"
                      type="checkbox"
                      @change="toggleSectionEnabled(section, $event?.target?.checked)"
                    />
                    <span class="toggleSlider"></span>
                    <span class="toggleLabel">{{ section.is_enabled ? 'On' : 'Off' }}</span>
                  </label>
                </div>
              </article>
            </template>
          </draggable>
        </section>

        <aside class="catalogPanel">
          <header class="panelHeader">
            <div>
              <h3>Aktivne vlastne komponenty</h3>
              <p>Pridavaj a upravuj custom widgety bez opustenia builderu.</p>
            </div>
            <button class="textBtn" type="button" @click="openCreateCustomComponent">Vytvorit</button>
          </header>

          <label class="searchField">
            <span>Filter</span>
            <input
              v-model="customSearch"
              type="text"
              placeholder="Najst komponent..."
            />
          </label>

          <div v-if="availableCustomComponents.length === 0" class="emptyBox">
            <p>Zatial nemas aktivne custom komponenty.</p>
            <button class="uiBtn uiBtnGhost" type="button" @click="openCreateCustomComponent">Vytvorit prvy komponent</button>
          </div>

          <div v-else-if="filteredAvailableCustomComponents.length === 0" class="emptyBox">
            <p>Ziadny komponent nevyhovuje filtru.</p>
          </div>

          <div v-else class="availableList">
            <article v-for="component in filteredAvailableCustomComponents" :key="component.id" class="availableItem">
              <div class="availableCopy">
                <div class="availableNameRow">
                  <strong>{{ component.name }}</strong>
                  <span class="typeBadge">{{ component.type }}</span>
                </div>
                <div class="availableMeta">
                  <span>ID: {{ component.id }}</span>
                  <span>V rozlozeni: {{ getCustomComponentUsage(component.id) }}x</span>
                </div>
              </div>

              <div class="availableActions">
                <button class="uiBtn uiBtnSmall" type="button" @click="addCustomComponentToLayout(component)">
                  {{ getCustomComponentUsage(component.id) > 0 ? 'Pridat dalsiu' : 'Pridat' }}
                </button>
                <button class="uiBtn uiBtnSmall uiBtnGhost" type="button" @click="openEditCustomComponent(component)">
                  Upravit
                </button>
              </div>
            </article>
          </div>
        </aside>
      </div>
    </section>

    <section v-else-if="activeMode === 'custom'" class="workspaceCard workspaceCard--flat">
      <div class="workspaceHeader workspaceHeaderCompact">
        <div>
          <h2>Vlastne komponenty</h2>
          <p>Povodny workflow ostava zachovany, vratane list + formular + live preview.</p>
        </div>
        <button class="uiBtn uiBtnGhost" type="button" @click="onModeClick('layout')">Spat na rozlozenie</button>
      </div>

      <SidebarCustomComponentsView
        ref="customViewRef"
        @components-changed="onCustomComponentsChanged"
        @dirty-change="onCustomDirtyChange"
      />
    </section>

    <section v-else class="workspaceCard workspaceCard--flat">
      <div class="workspaceHeader workspaceHeaderCompact">
      <div>
        <h2>Sidebar widgety gallery</h2>
        <p>Kompaktny playground iba pre widgety, ktore su realne v sidebare.</p>
      </div>
      </div>

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
import { EXCLUSIVE_SIDEBAR_SECTION_KEYS, MAX_ENABLED_SIDEBAR_WIDGETS } from '@/sidebar/engine'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import SidebarCustomComponentsView from '@/components/admin/sidebar/SidebarCustomComponentsView.vue'
import SidebarComponentRegistryView from '@/components/admin/sidebar/SidebarComponentRegistryView.vue'

const scopeTabs = [
  { value: SIDEBAR_SCOPE.HOME, label: 'Domov' },
  { value: SIDEBAR_SCOPE.EVENTS, label: 'Udalosti + kalendar' },
  { value: SIDEBAR_SCOPE.LEARNING, label: 'Vzdelavanie' },
  { value: SIDEBAR_SCOPE.SEARCH, label: 'Vyhladavanie' },
  { value: SIDEBAR_SCOPE.NOTIFICATIONS, label: 'Notifikacie' },
  { value: SIDEBAR_SCOPE.POST_DETAIL, label: 'Detail prispevku' },
  { value: SIDEBAR_SCOPE.PROFILE, label: 'Profil + verejny profil (/u/:username)' },
  { value: SIDEBAR_SCOPE.SETTINGS, label: 'Nastavenia' },
  { value: SIDEBAR_SCOPE.OBSERVING, label: 'Pozorovanie' },
]

const activeMode = ref('layout')
const activeScope = ref(DEFAULT_SIDEBAR_SCOPE)
const sections = ref([])
const originalSections = ref([])
const loading = ref(false)
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

const matchingSectionsCount = computed(() => {
  if (!hasLayoutSearch.value) return sections.value.length
  return sections.value.filter((section) => sectionMatchesLayoutSearch(section)).length
})

const enabledSectionsCount = computed(() => sections.value.filter((section) => section.is_enabled).length)

const formatSavedAt = (value) => {
  if (!value) return 'Caka na ulozenie'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return 'Caka na ulozenie'

  return new Intl.DateTimeFormat('sk-SK', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed)
}

const saveStatusLabel = computed(() => {
  if (loading.value) return 'Prave ukladam...'
  if (hasBuilderChanges.value) return 'Neulozene lokalne zmeny'
  return `Naposledy: ${formatSavedAt(lastSavedAt.value)}`
})

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

const isExclusiveSection = (section) => {
  return EXCLUSIVE_SIDEBAR_SECTION_KEYS.includes(String(section?.section_key || ''))
}

const applyLayoutRules = (items) => {
  const rows = normalizeLayoutItems(items)
  const enabled = rows.filter((item) => item.is_enabled)
  const exclusiveEnabled = enabled.find((item) => isExclusiveSection(item))

  if (exclusiveEnabled) {
    rows.forEach((item) => {
      item.is_enabled = item.client_key === exclusiveEnabled.client_key
    })
    return rows
  }

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
  sections.value = applyLayoutRules(items)
  applyOrderFromPosition()
  originalSections.value = normalizeLayoutItems(sections.value)
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
  loading.value = true
  error.value = ''

  try {
    const payload = await sidebarConfigAdminApi.get(scope)
    setScopeData(payload?.data || [])
    availableCustomComponents.value = Array.isArray(payload?.available_custom_components)
      ? payload.available_custom_components
      : []
    stickyErrorBanner.value = ''
    lastSavedAt.value = new Date().toISOString()
  } catch (err) {
    const message = handleSidebarLoadError(err, 'Nepodarilo sa nacitat konfiguraciu sidebaru.')
    error.value = message
    setScopeData(sidebarConfigStore.getDefaultForScope())
  } finally {
    loading.value = false
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

const toggleSectionEnabled = (section, checked) => {
  const nextEnabled = Boolean(checked)
  if (!section || typeof section !== 'object') return

  if (!nextEnabled) {
    section.is_enabled = false
    return
  }

  if (isExclusiveSection(section)) {
    sections.value.forEach((item) => {
      item.is_enabled = String(item.client_key) === String(section.client_key)
    })
    showToast('Observing Conditions je exkluzivny widget a moze byt aktivny iba samostatne.', 'warning')
    return
  }

  const hasExclusiveEnabled = sections.value.some(
    (item) => item.is_enabled && String(item.client_key) !== String(section.client_key) && isExclusiveSection(item),
  )
  if (hasExclusiveEnabled) {
    showToast('Najprv vypni Observing Conditions. Tento widget sa neda kombinovat s inymi.', 'warning')
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
  showToast('Neulozene zmeny boli zahodene.', 'success')
}

const saveLayoutChanges = async () => {
  if (!hasBuilderChanges.value) return

  loading.value = true
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
    error.value = message
    notifyErrorOnce(message)
  } finally {
    loading.value = false
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

@media (max-width: 1220px) {
  .builderGrid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 980px) {
  .heroHeader,
  .workspaceHeader {
    flex-direction: column;
  }

  .heroStats {
    justify-content: flex-start;
  }

  .workspaceActions {
    justify-content: flex-start;
  }

  .modeTabs {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 720px) {
  .sidebarAdmin {
    padding: 0.75rem;
  }

  .panelHeader {
    flex-direction: column;
    align-items: stretch;
  }

  .searchField {
    min-width: 0;
  }
}
</style>
