<template>
  <div class="adminLayout">
    <div class="pageHeader">
      <h1 class="pageTitle">Konfiguracia sidebaru</h1>
      <p class="pageDescription">Builder layoutu a sprava vlastnych widget komponentov.</p>
    </div>

    <div class="modeTabs" role="tablist" aria-label="Rezim spravy sidebaru">
      <button type="button" class="tabBtn" :class="{ active: activeMode === 'layout' }" @click="activeMode = 'layout'">
        Editor rozlozenia
      </button>
      <button type="button" class="tabBtn" :class="{ active: activeMode === 'custom' }" @click="activeMode = 'custom'">
        Vlastne komponenty
      </button>
    </div>

    <div v-if="stickyErrorBanner" class="alert alertError alertSticky" role="alert">
      <div>{{ stickyErrorBanner }}</div>
      <button class="btn" type="button" :disabled="retryLoading" @click="retrySidebarLoad">
        {{ retryLoading ? 'Opakujem...' : 'Skusit znova' }}
      </button>
    </div>

    <div v-if="activeMode === 'layout'" class="card">
      <div class="tabs" role="tablist" aria-label="Kontexty sidebaru">
        <button
          v-for="tab in scopeTabs"
          :key="tab.value"
          type="button"
          class="tabBtn"
          :class="{ active: activeScope === tab.value }"
          :disabled="loading"
          @click="onScopeClick(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="cardHeader">
        <h2>{{ activeTabLabel }} rozlozenie</h2>
        <div class="headerActions">
          <button class="btn" type="button" @click="openCreateCustomComponent">Novy komponent</button>
          <button class="btn btnPrimary" :disabled="loading || !hasBuilderChanges" @click="saveLayoutChanges">
            <span v-if="loading" class="spinner"></span>
            {{ loading ? 'Ukladam...' : 'Ulozit rozlozenie' }}
          </button>
        </div>
      </div>

      <div v-if="error" class="alert alertError">{{ error }}</div>

      <div class="quickStats">
        <span class="statPill">Polozky: {{ sections.length }}</span>
        <span class="statPill">Vlastne: {{ availableCustomComponents.length }}</span>
        <span class="statPill" :class="{ warning: hasBuilderChanges }">
          {{ hasBuilderChanges ? 'Neulozene zmeny' : 'Vsetko ulozene' }}
        </span>
      </div>

      <div class="builderGrid">
        <div>
          <div class="sectionTitle">Polozky sidebaru</div>
          <draggable
            v-model="sections"
            tag="div"
            :component-data="{ class: 'sectionsList' }"
            handle=".dragHandle"
            item-key="client_key"
            @end="dragEnd"
          >
            <template #item="{ element: section }">
              <div class="sectionItem" :class="{ isHidden: !section.is_enabled }">
                <div class="sectionContent">
                  <button type="button" class="dragHandle" aria-label="Presunut sekciu">::</button>

                  <div class="sectionInfo">
                    <div class="sectionRow">
                      <div class="sectionName">{{ section.title }}</div>
                      <span class="kindBadge">{{ section.kind === 'builtin' ? 'Vstavane' : 'Vlastne' }}</span>
                    </div>
                    <div class="sectionKey">
                      {{ section.kind === 'builtin' ? section.section_key : `custom:${section.custom_component_id}` }}
                    </div>
                    <div v-if="section.kind === 'custom_component'" class="sectionActions">
                      <button type="button" class="linkBtn" @click="editCustomComponentFromLayout(section)">Upravit</button>
                      <button type="button" class="linkBtn danger" @click="removeCustomComponentFromLayout(section)">
                        Odobrat
                      </button>
                    </div>
                  </div>

                  <label class="toggle">
                    <input v-model="section.is_enabled" type="checkbox" />
                    <span class="toggleSlider"></span>
                    <span class="toggleLabel">{{ section.is_enabled ? 'Viditelne' : 'Skryte' }}</span>
                  </label>
                </div>
              </div>
            </template>
          </draggable>
        </div>

        <div class="availableBox">
          <div class="sectionTitleRow">
            <div class="sectionTitle">Aktivne vlastne komponenty</div>
            <button class="linkBtn" type="button" @click="openCreateCustomComponent">Vytvorit</button>
          </div>

          <input
            v-model="customSearch"
            class="compactInput"
            type="text"
            placeholder="Najst komponent..."
          />

          <div v-if="availableCustomComponents.length === 0" class="emptyText">
            Zatial nemas aktivne custom komponenty.
          </div>
          <div v-else-if="filteredAvailableCustomComponents.length === 0" class="emptyText">
            Ziadny komponent nevyhovuje filtru.
          </div>
          <div v-else class="availableList">
            <div v-for="component in filteredAvailableCustomComponents" :key="component.id" class="availableItem">
              <div>
                <div class="availableName">{{ component.name }}</div>
                <div class="availableMeta">{{ component.type }}</div>
              </div>
              <div class="availableActions">
                <button class="btn btnSmall" type="button" @click="addCustomComponentToLayout(component)">Pridat</button>
                <button class="btn btnSmall" type="button" @click="openEditCustomComponent(component)">Upravit</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <SidebarCustomComponentsView
      v-else
      ref="customViewRef"
      @components-changed="onCustomComponentsChanged"
      @dirty-change="onCustomDirtyChange"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import draggable from 'vuedraggable'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  sidebarConfigAdminApi,
} from '@/services/api/admin/sidebarConfig'
import { DEFAULT_SIDEBAR_SCOPE, SIDEBAR_SCOPE } from '@/generated/sidebarScopes'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import SidebarCustomComponentsView from '@/components/admin/sidebar/SidebarCustomComponentsView.vue'

const scopeTabs = [
  { value: SIDEBAR_SCOPE.HOME, label: 'Domov' },
  { value: SIDEBAR_SCOPE.EVENTS, label: 'Udalosti + kalendar' },
  { value: SIDEBAR_SCOPE.LEARNING, label: 'Vzdelavanie' },
  { value: SIDEBAR_SCOPE.SEARCH, label: 'Vyhladavanie' },
  { value: SIDEBAR_SCOPE.NOTIFICATIONS, label: 'Notifikacie' },
  { value: SIDEBAR_SCOPE.POST_DETAIL, label: 'Detail prispevku' },
  { value: SIDEBAR_SCOPE.PROFILE, label: 'Profil' },
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
const availableCustomComponents = ref([])
const stickyErrorBanner = ref('')
const shownErrorMessages = ref(new Set())
const customFormDirty = ref(false)
const customViewRef = ref(null)

const { showToast } = useToast()
const { confirm } = useConfirm()
const sidebarConfigStore = useSidebarConfigStore()

const activeTabLabel = computed(() => scopeTabs.find((tab) => tab.value === activeScope.value)?.label || 'Domov')

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
  if (!query) return availableCustomComponents.value

  return availableCustomComponents.value.filter((component) =>
    String(component?.name || '').toLowerCase().includes(query)
      || String(component?.type || '').toLowerCase().includes(query),
  )
})

const applyOrderFromPosition = () => {
  sections.value.forEach((item, index) => {
    item.order = index
  })
}

const setScopeData = (items) => {
  sections.value = normalizeLayoutItems(items)
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

const dragEnd = () => {
  applyOrderFromPosition()
}

const addCustomComponentToLayout = (component) => {
  if (!component || !component.id) return

  sections.value.push({
    client_key: `custom:${component.id}:${Date.now()}`,
    kind: 'custom_component',
    section_key: 'custom_component',
    title: component.name || `Custom #${component.id}`,
    custom_component_id: component.id,
    custom_component: component,
    order: sections.value.length,
    is_enabled: true,
  })
  applyOrderFromPosition()
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
    showToast('Rozlozenie sidebaru bolo ulozene.', 'success')
  } catch (err) {
    const message = err?.response?.data?.message || 'Nepodarilo sa ulozit konfiguraciu sidebaru.'
    error.value = message
    notifyErrorOnce(message)
  } finally {
    loading.value = false
  }
}

const onCustomComponentsChanged = async () => {
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
.adminLayout {
  max-width: 1380px;
  margin: 0 auto;
  padding: 1.25rem 0.9rem;
  display: grid;
  gap: 0.8rem;
}

.pageHeader {
  display: grid;
  gap: 0.2rem;
}

.pageTitle {
  margin: 0;
  font-size: 1.58rem;
  font-weight: 800;
  color: var(--color-surface);
}

.pageDescription {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 0.9rem;
}

.modeTabs,
.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.42rem;
}

.tabBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
  border-radius: 0.72rem;
  background: rgb(var(--color-bg-rgb) / 0.26);
  color: var(--color-text-secondary);
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.44rem 0.66rem;
}

.tabBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.5);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.card {
  background: linear-gradient(160deg, rgb(var(--color-bg-rgb) / 0.46), rgb(var(--color-bg-rgb) / 0.3));
  border-radius: 1rem;
  box-shadow: inset 0 0 0 1px rgb(var(--color-text-secondary-rgb) / 0.14);
  padding: 0.92rem;
  display: grid;
  gap: 0.68rem;
}

.cardHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.65rem;
}

.cardHeader h2 {
  margin: 0;
  font-size: 1rem;
}

.headerActions {
  display: inline-flex;
  gap: 0.42rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0.48rem 0.76rem;
  border-radius: 0.7rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.3);
  font-size: 0.8rem;
}

.btnPrimary {
  background: rgb(var(--color-primary-rgb) / 0.22);
  border-color: rgb(var(--color-primary-rgb) / 0.54);
}

.btnSmall {
  padding: 0.34rem 0.58rem;
  font-size: 0.73rem;
}

.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgb(255 255 255 / 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.quickStats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.statPill {
  font-size: 0.72rem;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  background: rgb(var(--color-bg-rgb) / 0.3);
  color: var(--color-text-secondary);
}

.statPill.warning {
  color: rgb(var(--color-warning-rgb, 255 178 64));
}

.builderGrid {
  display: grid;
  gap: 0.76rem;
  grid-template-columns: 1.6fr 1fr;
}

.sectionTitle {
  margin-bottom: 0.45rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--color-text-secondary);
}

.sectionsList {
  display: grid;
  gap: 0.48rem;
}

.sectionItem {
  border-radius: 0.8rem;
  background: rgb(var(--color-bg-rgb) / 0.22);
  box-shadow: inset 0 0 0 1px rgb(var(--color-text-secondary-rgb) / 0.16);
}

.sectionItem.isHidden {
  opacity: 0.6;
}

.sectionContent {
  display: flex;
  align-items: center;
  gap: 0.62rem;
  padding: 0.62rem;
}

.dragHandle {
  border: 0;
  background: transparent;
  color: var(--color-text-secondary);
  cursor: grab;
  font-weight: 700;
}

.sectionInfo {
  flex: 1;
}

.sectionRow {
  display: flex;
  justify-content: space-between;
  gap: 0.35rem;
  align-items: center;
}

.sectionName {
  font-weight: 600;
  color: var(--color-surface);
  font-size: 0.86rem;
}

.sectionKey {
  font-size: 0.73rem;
  color: var(--color-text-secondary);
  font-family: monospace;
}

.sectionActions {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.22rem;
}

.kindBadge {
  font-size: 0.66rem;
  padding: 0.14rem 0.42rem;
  border-radius: 999px;
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.toggle input[type='checkbox'] {
  display: none;
}

.toggleSlider {
  width: 42px;
  height: 21px;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.33);
  position: relative;
}

.toggleSlider::before {
  content: '';
  position: absolute;
  width: 17px;
  height: 17px;
  border-radius: 50%;
  top: 2px;
  left: 2px;
  background: white;
  transition: transform 0.2s ease;
}

.toggle input[type='checkbox']:checked + .toggleSlider {
  background: rgb(var(--color-primary-rgb) / 0.66);
}

.toggle input[type='checkbox']:checked + .toggleSlider::before {
  transform: translateX(21px);
}

.toggleLabel {
  font-size: 0.74rem;
  color: var(--color-text-secondary);
}

.availableBox {
  border-radius: 0.86rem;
  background: rgb(var(--color-bg-rgb) / 0.2);
  box-shadow: inset 0 0 0 1px rgb(var(--color-text-secondary-rgb) / 0.14);
  padding: 0.66rem;
}

.sectionTitleRow {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.44rem;
}

.availableList {
  display: grid;
  gap: 0.44rem;
  margin-top: 0.46rem;
}

.availableItem {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.42rem;
  padding: 0.44rem;
  border-radius: 0.68rem;
  background: rgb(var(--color-bg-rgb) / 0.26);
}

.availableName {
  font-weight: 600;
  font-size: 0.82rem;
}

.availableMeta {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

.availableActions {
  display: inline-flex;
  gap: 0.22rem;
}

.compactInput {
  width: 100%;
  border-radius: 0.64rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.26);
  background: rgb(var(--color-bg-rgb) / 0.34);
  color: var(--color-surface);
  padding: 0.44rem 0.56rem;
  font-size: 0.8rem;
}

.linkBtn {
  border: 0;
  background: transparent;
  color: var(--color-primary);
  font-size: 0.76rem;
  padding: 0.1rem 0.2rem;
}

.linkBtn.danger {
  color: var(--color-danger);
}

.alert {
  border-radius: 0.7rem;
  padding: 0.56rem;
  font-size: 0.82rem;
}

.alertError {
  background: rgb(var(--color-danger-rgb) / 0.1);
  color: var(--color-danger);
}

.alertSticky {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
}

.emptyText {
  color: var(--color-text-secondary);
  font-size: 0.78rem;
}

@media (max-width: 1080px) {
  .builderGrid {
    grid-template-columns: 1fr;
  }

  .cardHeader {
    flex-direction: column;
    align-items: flex-start;
  }

  .headerActions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
