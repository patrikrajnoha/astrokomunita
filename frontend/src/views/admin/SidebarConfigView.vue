<template>
  <div class="adminLayout">
    <div class="pageHeader">
      <h1 class="pageTitle">Konfigurácia sidebaru</h1>
      <p class="pageDescription">Builder layoutu a správa vlastných sidebar komponentov.</p>
    </div>

    <div class="modeTabs" role="tablist" aria-label="Režim správy sidebaru">
      <button type="button" class="tabBtn" :class="{ active: activeMode === 'layout' }" @click="activeMode = 'layout'">
        Editor rozloženia
      </button>
      <button
        type="button"
        class="tabBtn"
        :class="{ active: activeMode === 'custom' }"
        @click="activeMode = 'custom'"
      >
        Vlastné komponenty
      </button>
    </div>

    <div v-if="stickyErrorBanner" class="alert alertError alertSticky" role="alert">
      <div>{{ stickyErrorBanner }}</div>
      <button class="btn btnSmall" type="button" :disabled="retryLoading" @click="retrySidebarLoad">
        {{ retryLoading ? 'Opakujem...' : 'Skúsiť znova' }}
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
        <h2>{{ activeTabLabel }} rozloženie</h2>
        <div class="headerActions">
          <button class="btn" type="button" @click="openCreateCustomComponent">Nový komponent</button>
          <button class="btn btnPrimary" :disabled="loading || !hasBuilderChanges" @click="saveLayoutChanges">
            <span v-if="loading" class="spinner"></span>
            {{ loading ? 'Ukladám...' : 'Uložiť rozloženie' }}
          </button>
        </div>
      </div>

      <div v-if="error" class="alert alertError">{{ error }}</div>

      <div class="quickStats">
        <span class="statPill">Položky: {{ sections.length }}</span>
        <span class="statPill">Vlastné: {{ availableCustomComponents.length }}</span>
        <span class="statPill" :class="{ warning: hasBuilderChanges }">
          {{ hasBuilderChanges ? 'Neuložené zmeny' : 'Všetko uložené' }}
        </span>
      </div>

      <div class="builderGrid">
        <div>
          <div class="sectionTitle">Položky sidebaru</div>
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
                  <button type="button" class="dragHandle" aria-label="Presunúť sekciu">::</button>

                  <div class="sectionInfo">
                    <div class="sectionRow">
                      <div class="sectionName">{{ section.title }}</div>
                      <span class="kindBadge">{{ section.kind === 'builtin' ? 'Vstavané' : 'Vlastné' }}</span>
                    </div>
                    <div class="sectionKey">
                      {{ section.kind === 'builtin' ? section.section_key : `custom:${section.custom_component_id}` }}
                    </div>
                    <div v-if="section.kind === 'custom_component'" class="sectionActions">
                      <button type="button" class="linkBtn" @click="editCustomComponentFromLayout(section)">Upraviť</button>
                      <button type="button" class="linkBtn danger" @click="removeCustomComponentFromLayout(section)">
                        Odobrať
                      </button>
                    </div>
                  </div>

                  <label class="toggle">
                    <input v-model="section.is_enabled" type="checkbox" />
                    <span class="toggleSlider"></span>
                    <span class="toggleLabel">{{ section.is_enabled ? 'Viditeľné' : 'Skryté' }}</span>
                  </label>
                </div>
              </div>
            </template>
          </draggable>
        </div>

        <div class="availableBox">
          <div class="sectionTitleRow">
            <div class="sectionTitle">Vlastné komponenty</div>
            <button class="linkBtn" type="button" @click="openCreateCustomComponent">Vytvoriť</button>
          </div>
          <input
            v-model="customSearch"
            class="compactInput"
            type="text"
            placeholder="Nájsť komponent..."
          />
          <div v-if="availableCustomComponents.length === 0" class="emptyText">
            Žiadne aktívne custom komponenty.
          </div>
          <div v-else-if="filteredAvailableCustomComponents.length === 0" class="emptyText">
            Žiadny komponent nevyhovuje filtru.
          </div>
          <div v-else class="availableList">
            <div v-for="component in filteredAvailableCustomComponents" :key="component.id" class="availableItem">
              <div>
                <div class="availableName">{{ component.name }}</div>
                <div class="availableMeta">{{ component.type }}</div>
              </div>
              <div class="availableActions">
                <button class="btn btnSmall" type="button" @click="addCustomComponentToLayout(component)">Pridať</button>
                <button class="btn btnSmall" type="button" @click="openEditCustomComponent(component)">Upraviť</button>
                <button class="btn btnSmall btnDanger" type="button" @click="removeComponent(component)">Zmazať</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card customCard">
      <div class="customHeader">
        <h2>Vlastné komponenty</h2>
        <button class="btn" type="button" @click="startCreate">Nový komponent</button>
      </div>

      <div class="customGrid">
        <div class="listPanel">
          <input
            v-model="customListSearch"
            class="compactInput"
            type="text"
            placeholder="Filtrovať podľa názvu..."
          />
          <table class="listTable">
            <thead>
              <tr>
                <th>Názov</th>
                <th>Typ</th>
                <th>Aktívny</th>
                <th>Aktualizované</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in filteredCustomComponents" :key="item.id" :class="{ selected: form.id === item.id }">
                <td>{{ item.name }}</td>
                <td>{{ item.type }}</td>
                <td>{{ item.is_active ? 'Áno' : 'Nie' }}</td>
                <td>{{ formatDate(item.updated_at) }}</td>
                <td class="actionsCol">
                  <button class="linkBtn" @click="editComponent(item)">Upraviť</button>
                  <button class="linkBtn danger" @click="removeComponent(item)">Zmazať</button>
                </td>
              </tr>
              <tr v-if="filteredCustomComponents.length === 0">
                <td colspan="5" class="emptyCell">Nenašli sa žiadne komponenty.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="formPanel">
          <h3>{{ form.id ? 'Upraviť komponent' : 'Vytvoriť komponent' }}</h3>

          <label class="field">
            <span>Názov</span>
            <input v-model="form.name" type="text" />
          </label>

          <label class="field">
            <span>Typ</span>
            <select v-model="form.type">
              <option value="special_event">Špeciálna udalosť</option>
            </select>
          </label>

          <label class="field">
            <span>Nadpis</span>
            <input v-model="form.config_json.title" type="text" />
          </label>

          <label class="field">
            <span>Krátky text</span>
            <textarea v-model="form.config_json.description" rows="3"></textarea>
          </label>

          <div class="field">
            <span>ID udalosti</span>
            <div class="row">
              <input v-model.number="form.config_json.eventId" type="number" min="1" placeholder="Napr. 42" />
              <button class="btn btnSmall" type="button" @click="loadEventSummary">Načítať</button>
            </div>
            <div v-if="eventSummary" class="hint">
              {{ eventSummary.title }} | {{ formatDate(eventSummary.start_at || eventSummary.max_at) }}
            </div>
            <div v-if="eventSummaryError" class="hint errorText">{{ eventSummaryError }}</div>
          </div>

          <label class="field">
            <span>Text tlačidla</span>
            <input v-model="form.config_json.buttonLabel" type="text" />
          </label>

          <label class="field">
            <span>Cieľ tlačidla</span>
            <input v-model="form.config_json.buttonTarget" type="text" placeholder="/events/{id}" />
          </label>

          <label class="field">
            <span>URL obrázka (voliteľné)</span>
            <input v-model="form.config_json.imageUrl" type="text" />
          </label>

          <label class="field">
            <span>Ikona (voliteľné)</span>
            <input v-model="form.config_json.icon" type="text" />
          </label>

          <label class="field fieldInline">
            <input v-model="form.is_active" type="checkbox" />
            <span>Aktívny</span>
          </label>

          <div class="formActions">
            <button class="btn btnPrimary" :disabled="customSaving" @click="saveComponent">
              {{ customSaving ? 'Ukladám...' : form.id ? 'Uložiť zmeny' : 'Vytvoriť komponent' }}
            </button>
            <button v-if="form.id" class="btn" type="button" @click="startCreate">Resetovať</button>
          </div>
        </div>

        <div class="previewPanel">
          <h3>Živý náhľad</h3>
          <SidebarSpecialEventCard
            preview
            :preview-config="previewConfig"
            :preview-event="eventSummary"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import draggable from 'vuedraggable'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  sidebarConfigAdminApi,
  sidebarCustomComponentsAdminApi,
} from '@/services/api/admin/sidebarConfig'
import { DEFAULT_SIDEBAR_SCOPE, SIDEBAR_SCOPE } from '@/generated/sidebarScopes'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import SidebarSpecialEventCard from '@/components/widgets/SidebarSpecialEventCard.vue'

const scopeTabs = [
  { value: SIDEBAR_SCOPE.HOME, label: 'Domov' },
  { value: SIDEBAR_SCOPE.EVENTS, label: 'Udalosti + kalendár' },
  { value: SIDEBAR_SCOPE.LEARNING, label: 'Vzdelávanie' },
  { value: SIDEBAR_SCOPE.SEARCH, label: 'Vyhľadávanie' },
  { value: SIDEBAR_SCOPE.NOTIFICATIONS, label: 'Notifikácie' },
  { value: SIDEBAR_SCOPE.POST_DETAIL, label: 'Detail príspevku' },
  { value: SIDEBAR_SCOPE.PROFILE, label: 'Profil' },
  { value: SIDEBAR_SCOPE.SETTINGS, label: 'Nastavenia' },
  { value: SIDEBAR_SCOPE.SKY, label: 'Obloha' },
  { value: SIDEBAR_SCOPE.OBSERVING, label: 'Pozorovanie' },
]

const activeMode = ref('layout')
const activeScope = ref(DEFAULT_SIDEBAR_SCOPE)
const sections = ref([])
const originalSections = ref([])
const loading = ref(false)
const error = ref('')
const customComponents = ref([])
const availableCustomComponents = ref([])
const customSaving = ref(false)
const retryLoading = ref(false)
const customSearch = ref('')
const customListSearch = ref('')
const eventSummary = ref(null)
const eventSummaryError = ref('')
const form = ref(defaultForm())
const originalFormSnapshot = ref(JSON.stringify(defaultForm()))
const previewConfig = ref({ ...defaultForm().config_json })
const stickyErrorBanner = ref('')
const shownErrorMessages = ref(new Set())
let previewTimer = null

const { showToast } = useToast()
const { confirm } = useConfirm()
const sidebarConfigStore = useSidebarConfigStore()

const activeTabLabel = computed(() => scopeTabs.find((tab) => tab.value === activeScope.value)?.label || 'Domov')

function defaultForm() {
  return {
    id: null,
    name: '',
    type: 'special_event',
    is_active: true,
    config_json: {
      title: '',
      description: '',
      eventId: null,
      buttonLabel: '',
      buttonTarget: '',
      imageUrl: '',
      icon: '',
    },
  }
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

const hasFormChanges = computed(() => {
  return JSON.stringify(form.value) !== originalFormSnapshot.value
})

const filteredAvailableCustomComponents = computed(() => {
  const query = customSearch.value.trim().toLowerCase()
  if (!query) return availableCustomComponents.value

  return availableCustomComponents.value.filter((component) =>
    String(component?.name || '').toLowerCase().includes(query)
      || String(component?.type || '').toLowerCase().includes(query),
  )
})

const filteredCustomComponents = computed(() => {
  const query = customListSearch.value.trim().toLowerCase()
  if (!query) return customComponents.value

  return customComponents.value.filter((component) =>
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
    stickyErrorBanner.value = 'Chyba DB tabuľky sidebar_custom_components. Spusti: php artisan migrate.'
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
    const message = handleSidebarLoadError(err, 'Nepodarilo sa načítať konfiguráciu sidebaru.')
    error.value = message
    setScopeData(sidebarConfigStore.getDefaultForScope())
  } finally {
    loading.value = false
  }
}

const loadCustomComponents = async () => {
  try {
    const payload = await sidebarCustomComponentsAdminApi.list()
    customComponents.value = Array.isArray(payload?.data) ? payload.data : []
    stickyErrorBanner.value = ''
  } catch (err) {
    customComponents.value = []
    handleSidebarLoadError(err, 'Nepodarilo sa načítať vlastné komponenty.')
  }
}

const retrySidebarLoad = async () => {
  retryLoading.value = true

  try {
    await Promise.all([loadScope(activeScope.value), loadCustomComponents()])
  } finally {
    retryLoading.value = false
  }
}

const onScopeClick = async (nextScope) => {
  if (nextScope === activeScope.value) return

  if (hasBuilderChanges.value) {
    const confirmed = await confirm({
      title: 'Neuložené zmeny',
      message: 'Máš neuložené zmeny rozloženia. Pokračovať a zahodiť ich?',
      confirmText: 'Zahodiť zmeny',
      cancelText: 'Zostať tu',
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

const openCreateCustomComponent = () => {
  activeMode.value = 'custom'
  startCreate()
}

const openEditCustomComponent = async (component) => {
  const componentId = Number(component?.id)
  if (!Number.isFinite(componentId) || componentId < 1) return

  try {
    const payload = await sidebarCustomComponentsAdminApi.get(componentId)
    activeMode.value = 'custom'
    editComponent(payload?.data || component)
  } catch (err) {
    notifyErrorOnce(err?.response?.data?.message || 'Nepodarilo sa načítať komponent.')
  }
}

const editCustomComponentFromLayout = async (section) => {
  const componentId = Number(section?.custom_component_id)
  if (!Number.isFinite(componentId) || componentId < 1) return

  const selected = customComponents.value.find((item) => Number(item?.id) === componentId) || null
  if (selected) {
    await openEditCustomComponent(selected)
    return
  }

  await openEditCustomComponent({ id: componentId })
}

const removeCustomComponentFromLayout = async (section) => {
  const componentId = Number(section?.custom_component_id)
  const componentName = section?.title || `Custom #${componentId}`
  const confirmed = await confirm({
    title: 'Odobrať z rozloženia',
    message: `Odobrať "${componentName}" z tohto rozloženia sidebaru? Komponent zostane vo Vlastných komponentoch.`,
    confirmText: 'Odobrať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })
  if (!confirmed) return

  const currentKey = String(section?.client_key || '')
  sections.value = sections.value.filter((item) => String(item?.client_key || '') !== currentKey)
  applyOrderFromPosition()
  showToast('Komponent bol odobratý z rozloženia. Ulož rozloženie pre potvrdenie zmien.', 'success')
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
    showToast('Rozloženie sidebaru bolo uložené.', 'success')
  } catch (err) {
    const message = err?.response?.data?.message || 'Nepodarilo sa uložiť konfiguráciu sidebaru.'
    error.value = message
    notifyErrorOnce(message)
  } finally {
    loading.value = false
  }
}

const setForm = (item) => {
  form.value = {
    id: item?.id || null,
    name: String(item?.name || ''),
    type: String(item?.type || 'special_event'),
    is_active: Boolean(item?.is_active ?? true),
    config_json: {
      title: String(item?.config_json?.title || ''),
      description: String(item?.config_json?.description || ''),
      eventId: Number.isFinite(Number(item?.config_json?.eventId)) ? Number(item.config_json.eventId) : null,
      buttonLabel: String(item?.config_json?.buttonLabel || ''),
      buttonTarget: String(item?.config_json?.buttonTarget || ''),
      imageUrl: String(item?.config_json?.imageUrl || ''),
      icon: String(item?.config_json?.icon || ''),
    },
  }
  originalFormSnapshot.value = JSON.stringify(form.value)
  previewConfig.value = { ...form.value.config_json }
}

const startCreate = () => {
  setForm(defaultForm())
  eventSummary.value = null
  eventSummaryError.value = ''
}

const editComponent = (item) => {
  setForm(item)
  eventSummary.value = item?.event_summary || null
  eventSummaryError.value = ''
}

const loadEventSummary = async () => {
  eventSummary.value = null
  eventSummaryError.value = ''
  const eventId = Number(form.value.config_json.eventId)
  if (!Number.isFinite(eventId) || eventId < 1) return

  try {
    const response = await api.get(`/events/${eventId}`)
    eventSummary.value = response?.data?.data || response?.data || null
  } catch {
    eventSummaryError.value = 'Udalosť nie je dostupná.'
  }
}

const saveComponent = async () => {
  customSaving.value = true

  const payload = {
    name: form.value.name,
    type: form.value.type,
    is_active: form.value.is_active,
    config_json: {
      title: form.value.config_json.title,
      description: form.value.config_json.description,
      eventId: form.value.config_json.eventId || null,
      buttonLabel: form.value.config_json.buttonLabel,
      buttonTarget: form.value.config_json.buttonTarget,
      imageUrl: form.value.config_json.imageUrl,
      icon: form.value.config_json.icon,
    },
  }

  try {
    const response = form.value.id
      ? await sidebarCustomComponentsAdminApi.update(form.value.id, payload)
      : await sidebarCustomComponentsAdminApi.create(payload)

    const data = response?.data
    showToast(form.value.id ? 'Komponent bol upravený.' : 'Komponent bol vytvorený.', 'success')
    await loadCustomComponents()
    await loadScope(activeScope.value)
    if (data) {
      editComponent(data)
    }
  } catch (err) {
    const message = err?.response?.data?.message || 'Nepodarilo sa uložiť komponent.'
    notifyErrorOnce(message)
  } finally {
    customSaving.value = false
  }
}

const removeComponent = async (item) => {
  const confirmed = await confirm({
    title: 'Zmazať komponent',
    message: `Zmazať "${item.name}"? Rozloženia ostanú stabilné, ale komponent už nebude možné upravovať.`,
    confirmText: 'Zmazať',
    cancelText: 'Zrušiť',
    variant: 'danger',
  })
  if (!confirmed) return

  try {
    await sidebarCustomComponentsAdminApi.remove(item.id)
    showToast('Komponent bol zmazaný.', 'success')
    await loadCustomComponents()
    await loadScope(activeScope.value)
    if (form.value.id === item.id) {
      startCreate()
    }
  } catch (err) {
    notifyErrorOnce(err?.response?.data?.message || 'Nepodarilo sa zmazať komponent.')
  }
}

const formatDate = (value) => {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return new Intl.DateTimeFormat('sk-SK', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed)
}

const beforeUnloadListener = (event) => {
  if (!hasBuilderChanges.value && !hasFormChanges.value) return
  event.preventDefault()
  event.returnValue = ''
}

watch(
  () => form.value.config_json,
  (next) => {
    if (previewTimer) {
      window.clearTimeout(previewTimer)
    }
    previewTimer = window.setTimeout(() => {
      previewConfig.value = { ...next }
    }, 220)
  },
  { deep: true, immediate: true },
)

onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnloadListener)
  await Promise.all([loadScope(activeScope.value), loadCustomComponents()])
  startCreate()
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnloadListener)
  if (previewTimer) {
    window.clearTimeout(previewTimer)
  }
})

onBeforeRouteLeave(async () => {
  if (!hasBuilderChanges.value && !hasFormChanges.value) return true

  return confirm({
    title: 'Neuložené zmeny',
    message: 'Máš neuložené zmeny. Naozaj chceš opustiť túto stránku?',
    confirmText: 'Opustiť stránku',
    cancelText: 'Zostať tu',
    variant: 'danger',
  })
})
</script>

<style scoped>
.adminLayout {
  max-width: 1380px;
  margin: 0 auto;
  padding: 1.25rem 0.9rem;
}

.pageHeader {
  margin-bottom: 1rem;
}

.pageTitle {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--color-surface);
  margin-bottom: 0.25rem;
}

.pageDescription {
  color: var(--color-text-secondary);
  font-size: 0.92rem;
}

.modeTabs,
.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.75rem;
}

.tabBtn {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.32);
  border-radius: 0.55rem;
  background: rgb(var(--color-bg-rgb) / 0.4);
  color: var(--color-text-secondary);
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.42rem 0.6rem;
}

.tabBtn.active {
  border-color: rgb(var(--color-primary-rgb) / 0.7);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.16);
}

.card {
  background: rgb(var(--color-bg-rgb) / 0.55);
  border: 1px solid var(--color-text-secondary);
  border-radius: 1rem;
  padding: 1rem;
}

.cardHeader,
.customHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.7rem;
}

.headerActions {
  display: inline-flex;
  gap: 0.4rem;
}

.btn {
  padding: 0.5rem 0.8rem;
  border-radius: 0.6rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  color: var(--color-surface);
  background: rgb(var(--color-bg-rgb) / 0.3);
  font-size: 0.82rem;
}

.btnPrimary {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.btnSmall {
  padding: 0.32rem 0.58rem;
  font-size: 0.74rem;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgb(255 255 255 / 0.35);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.builderGrid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: 1.65fr 1fr;
}

.quickStats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-bottom: 0.7rem;
}

.statPill {
  font-size: 0.72rem;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  color: var(--color-text-secondary);
}

.statPill.warning {
  border-color: rgb(var(--color-warning-rgb, 255 178 64) / 0.5);
  color: rgb(var(--color-warning-rgb, 255 178 64));
}

.sectionTitle {
  margin-bottom: 0.45rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--color-text-secondary);
  letter-spacing: 0.01em;
}

.sectionsList {
  display: grid;
  gap: 0.5rem;
}

.sectionItem {
  background: rgb(var(--color-bg-rgb) / 0.3);
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 0.75rem;
}

.sectionItem.isHidden {
  opacity: 0.62;
}

.sectionContent {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem;
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
  gap: 0.4rem;
  align-items: center;
}

.sectionName {
  font-weight: 600;
  color: var(--color-surface);
  font-size: 0.88rem;
}

.sectionKey {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  font-family: monospace;
}

.sectionActions {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin-top: 0.25rem;
}

.kindBadge {
  font-size: 0.66rem;
  padding: 0.14rem 0.42rem;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-primary-rgb) / 0.38);
  color: var(--color-surface);
  background: rgb(var(--color-primary-rgb) / 0.14);
}

.toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
}

.toggle input[type='checkbox'] {
  display: none;
}

.toggleSlider {
  width: 46px;
  height: 22px;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb) / 0.35);
  position: relative;
}

.toggleSlider::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  top: 2px;
  left: 2px;
  background: white;
  transition: transform 0.2s ease;
}

.toggle input[type='checkbox']:checked + .toggleSlider {
  background: var(--color-primary);
}

.toggle input[type='checkbox']:checked + .toggleSlider::before {
  transform: translateX(24px);
}

.toggleLabel {
  font-size: 0.76rem;
  color: var(--color-text-secondary);
}

.availableBox {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.28);
  border-radius: 0.75rem;
  padding: 0.65rem;
}

.sectionTitleRow {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.4rem;
}

.availableList {
  display: grid;
  gap: 0.45rem;
  margin-top: 0.45rem;
}

.availableItem {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.45rem;
  padding: 0.45rem;
  border-radius: 0.6rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.24);
}

.availableName {
  font-weight: 600;
  font-size: 0.84rem;
}

.availableMeta {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

.availableActions {
  display: inline-flex;
  gap: 0.25rem;
}

.compactInput {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.42);
  color: var(--color-surface);
  padding: 0.42rem 0.55rem;
  font-size: 0.8rem;
}

.customGrid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: 1.12fr 0.95fr 0.9fr;
}

.listPanel,
.formPanel,
.previewPanel {
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.26);
  border-radius: 0.75rem;
  padding: 0.65rem;
  min-width: 0;
}

.listPanel {
  overflow: hidden;
  max-height: 62vh;
  overflow: auto;
}

.listPanel .compactInput {
  margin-bottom: 0.45rem;
}

.listTable {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.78rem;
}

.listTable th,
.listTable td {
  border-bottom: 1px solid rgb(var(--color-text-secondary-rgb) / 0.18);
  text-align: left;
  padding: 0.38rem 0.3rem;
  white-space: nowrap;
}

.listTable tr.selected {
  background: rgb(var(--color-primary-rgb) / 0.1);
}

.emptyCell {
  text-align: center;
  color: var(--color-text-secondary);
  padding: 0.7rem 0.35rem;
}

.actionsCol {
  display: flex;
  gap: 0.2rem;
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

.btnDanger {
  border-color: rgb(var(--color-danger-rgb) / 0.35);
  color: var(--color-danger);
}

.field {
  display: grid;
  gap: 0.35rem;
  margin-bottom: 0.55rem;
}

.field span {
  font-size: 0.73rem;
  color: var(--color-text-secondary);
}

.field input,
.field textarea,
.field select {
  border-radius: 0.5rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.35);
  background: rgb(var(--color-bg-rgb) / 0.42);
  color: var(--color-surface);
  padding: 0.45rem 0.55rem;
  font-size: 0.82rem;
}

.fieldInline {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.row {
  display: flex;
  gap: 0.45rem;
}

.row input {
  flex: 1;
}

.hint {
  font-size: 0.7rem;
  color: var(--color-text-secondary);
}

.hint.errorText {
  color: var(--color-danger);
}

.formActions {
  display: flex;
  gap: 0.4rem;
  margin-top: 0.2rem;
}

.alertError {
  margin-bottom: 0.75rem;
  background: rgb(var(--color-danger-rgb) / 0.1);
  border: 1px solid rgb(var(--color-danger-rgb) / 0.3);
  color: var(--color-danger);
  border-radius: 0.55rem;
  padding: 0.55rem;
  font-size: 0.82rem;
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

@media (max-width: 1320px) {
  .customGrid {
    grid-template-columns: 1fr 1fr;
  }

  .previewPanel {
    grid-column: 1 / -1;
  }
}

@media (max-width: 1080px) {
  .builderGrid,
  .customGrid {
    grid-template-columns: 1fr;
  }

  .listTable {
    max-height: none;
  }

  .headerActions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
