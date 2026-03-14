<template>
  <SettingsDetailShell
    title="Konfiguracia sidebaru"
    subtitle="Nastavte si vlastne widgety pre jednotlive kontexty aplikacie. Aktivne mozu byt najviac 3."
  >
    <section class="settings-sidebar-builder">
      <header class="settings-sidebar-builder__header">
        <h3 class="settings-sidebar-builder__title">Rozlozenie</h3>
        <p class="settings-sidebar-builder__meta">{{ headerMetaLine }}</p>
      </header>

      <div class="settings-sidebar-builder__toolbar">
        <div class="settings-sidebar-builder__search-wrap">
          <input
            v-model="layoutSearch"
            type="text"
            class="field-input settings-sidebar-builder__search"
            placeholder="Hladat polozky sidebaru"
            :disabled="state.loadingScope"
          >
          <p class="settings-sidebar-builder__summary">{{ layoutSummary }}</p>
        </div>
        <span class="settings-sidebar-builder__save-state" :class="saveStateTone">
          {{ saveStateLabel }}
        </span>
      </div>

      <p v-if="visibleError" class="field-error">{{ visibleError }}</p>

      <div v-if="state.loadingScope" class="settings-sidebar-builder__loading">
        Nacitavam konfiguraciu...
      </div>

      <div v-else-if="filteredSections.length === 0" class="settings-sidebar-builder__empty">
        Ziadne polozky nevyhovuju filtru.
      </div>

      <div v-else class="settings-sidebar-builder__rows">
        <article
          v-for="section in filteredSections"
          :key="section.section_key"
          class="settings-sidebar-builder__row"
          :class="{ disabled: !section.is_enabled }"
        >
          <div class="settings-sidebar-builder__row-copy">
            <p class="settings-sidebar-builder__row-title">{{ section.title || 'Bez nazvu' }}</p>
            <p class="settings-sidebar-builder__row-meta">{{ section.section_key }}</p>
          </div>

          <div class="settings-sidebar-builder__row-actions">
            <div class="settings-sidebar-builder__order-controls">
              <button
                :id="`settings-widget-move-up-${section.section_key}`"
                type="button"
                class="settings-sidebar-builder__order-btn"
                :disabled="isMoveDisabled(section, 'up')"
                @click="moveSection(section, 'up')"
              >
                ▲
              </button>
              <button
                :id="`settings-widget-move-down-${section.section_key}`"
                type="button"
                class="settings-sidebar-builder__order-btn"
                :disabled="isMoveDisabled(section, 'down')"
                @click="moveSection(section, 'down')"
              >
                ▼
              </button>
            </div>

            <label class="settings-sidebar-builder__toggle" :for="`settings-widget-${section.section_key}`">
              <input
                :id="`settings-widget-${section.section_key}`"
                :checked="section.is_enabled"
                type="checkbox"
                :disabled="isToggleDisabled(section)"
                @change="toggleSectionEnabled(section, $event.target.checked)"
              >
              <span class="settings-sidebar-builder__slider"></span>
              <span class="settings-sidebar-builder__toggle-label">
                {{ section.is_enabled ? 'Zapnute' : 'Vypnute' }}
              </span>
            </label>
          </div>
        </article>
      </div>
    </section>
  </SettingsDetailShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import SettingsDetailShell from '@/components/settings/SettingsDetailShell.vue'
import { useEventPreferencesStore } from '@/stores/eventPreferences'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'
import {
  DEFAULT_SIDEBAR_SCOPE,
  SIDEBAR_SCOPE,
} from '@/generated/sidebarScopes'
import { MAX_ENABLED_SIDEBAR_WIDGETS, normalizeSidebarSections } from '@/sidebar/engine'
import { createScopeTabs } from '@/views/admin/sidebarConfig/sidebarConfigView.constants'

const scopeTabs = createScopeTabs(SIDEBAR_SCOPE)
const preferences = useEventPreferencesStore()
const sidebarConfigStore = useSidebarConfigStore()

const activeScope = ref(DEFAULT_SIDEBAR_SCOPE)
const sections = ref([])
const layoutSearch = ref('')
const localOverrides = ref({})

const state = reactive({
  loadingScope: false,
  saving: false,
  scopeError: '',
  preferencesError: '',
  saveError: '',
  lastSavedAt: null,
})

let saveQueued = false

const normalizeWidgetKeys = (value) => {
  if (!Array.isArray(value)) return []

  return Array.from(new Set(
    value
      .map((item) => String(item || '').trim())
      .filter(Boolean),
  )).slice(0, MAX_ENABLED_SIDEBAR_WIDGETS)
}

const normalizeOverrides = (value) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {}

  const normalized = {}
  Object.entries(value).forEach(([scope, keys]) => {
    const normalizedScope = String(scope || '').trim()
    if (!normalizedScope) return

    normalized[normalizedScope] = normalizeWidgetKeys(keys)
  })

  return normalized
}

const hasScopeOverride = (scope) => {
  return Object.prototype.hasOwnProperty.call(localOverrides.value, scope)
}

const readStoreOverrides = () => {
  const normalized = normalizeOverrides(preferences.sidebarWidgetOverrides)
  if (!Object.prototype.hasOwnProperty.call(normalized, 'home') && Array.isArray(preferences.sidebarWidgetKeys)) {
    if (preferences.sidebarWidgetKeys.length > 0) {
      normalized.home = normalizeWidgetKeys(preferences.sidebarWidgetKeys)
    }
  }
  return normalized
}

const saveStateLabel = computed(() => {
  if (state.saving) return 'Ukladam...'
  if (state.saveError) return 'Chyba pri automatickom ukladani'
  if (state.lastSavedAt) return 'Ulozene automaticky'
  return 'Pripravene'
})

const saveStateTone = computed(() => {
  if (state.saving) return 'is-saving'
  if (state.saveError) return 'is-error'
  return 'is-saved'
})

const headerMetaLine = computed(() => {
  const tab = scopeTabs.find((item) => item.value === activeScope.value)
  const label = tab?.label || 'Domov'
  return `${label} - ${saveStateLabel.value}`
})

const filteredSections = computed(() => {
  const query = String(layoutSearch.value || '').trim().toLowerCase()
  if (!query) return sections.value

  return sections.value.filter((item) => {
    const haystack = `${String(item.title || '')} ${String(item.section_key || '')}`.toLowerCase()
    return haystack.includes(query)
  })
})

const visibleScopeTabs = computed(() => {
  const supportedScopes = Array.isArray(preferences.supportedSidebarScopes)
    ? new Set(preferences.supportedSidebarScopes)
    : null

  if (!supportedScopes || supportedScopes.size === 0) return scopeTabs
  return scopeTabs.filter((tab) => supportedScopes.has(tab.value))
})

const visibleError = computed(() => {
  if (state.saveError) return state.saveError
  if (state.scopeError) return state.scopeError
  return state.preferencesError
})

const enabledSectionsCount = computed(() => sections.value.filter((item) => item.is_enabled).length)

const layoutSummary = computed(() => {
  const total = sections.value.length
  const shown = filteredSections.value.length
  return `${shown} z ${total} poloziek - ${enabledSectionsCount.value} aktivne`
})
const enabledSectionKeys = computed(() => (
  sections.value
    .filter((item) => item.is_enabled)
    .map((item) => item.section_key)
))

const applyScopeSelection = (items, selectedKeys) => {
  const normalizedItems = normalizeSidebarSections(items)
    .filter((item) => item.kind === 'builtin')
    .map((item) => ({
      section_key: String(item.section_key || ''),
      title: String(item.title || ''),
      order: Number.isFinite(Number(item.order)) ? Number(item.order) : 0,
      is_enabled: Boolean(item.is_enabled),
    }))
    .sort((left, right) => left.order - right.order)

  const hasExplicitSelection = Array.isArray(selectedKeys)
  const normalizedSelectedKeys = normalizeWidgetKeys(selectedKeys)
  const selectedKeySet = new Set(normalizedSelectedKeys)

  if (hasExplicitSelection) {
    const toggledItems = normalizedItems.map((item) => ({
      ...item,
      is_enabled: selectedKeySet.has(item.section_key),
    }))

    const keyedItems = new Map(toggledItems.map((item) => [item.section_key, item]))
    const enabledBySelectionOrder = normalizedSelectedKeys
      .map((key) => keyedItems.get(key))
      .filter(Boolean)
    const disabledInDefaultOrder = toggledItems.filter((item) => !selectedKeySet.has(item.section_key))

    return [...enabledBySelectionOrder, ...disabledInDefaultOrder]
  }

  let enabledCount = 0
  return normalizedItems.map((item) => {
    if (!item.is_enabled) {
      return item
    }

    if (enabledCount >= MAX_ENABLED_SIDEBAR_WIDGETS) {
      return { ...item, is_enabled: false }
    }

    enabledCount += 1
    return item
  })
}

const loadScope = async (scope) => {
  state.loadingScope = true
  state.scopeError = ''

  try {
    const items = await sidebarConfigStore.fetchScope(scope, { force: true })
    const selectedKeys = hasScopeOverride(scope) ? localOverrides.value[scope] : null
    sections.value = applyScopeSelection(items, selectedKeys)
  } catch (error) {
    state.scopeError = error?.response?.data?.message || 'Nepodarilo sa nacitat konfiguraciu sidebaru.'
    sections.value = []
  } finally {
    state.loadingScope = false
  }
}

const persistOverrides = async () => {
  if (state.saving) {
    saveQueued = true
    return
  }

  state.saving = true
  state.saveError = ''

  try {
    const payloadOverrides = normalizeOverrides(localOverrides.value)
    await preferences.savePreferences({
      sidebar_widget_overrides: payloadOverrides,
      sidebar_widget_keys: payloadOverrides.home || [],
    })

    localOverrides.value = readStoreOverrides()
    state.lastSavedAt = new Date().toISOString()
  } catch (error) {
    const message = error?.response?.data?.message || preferences.error || 'Ulozenie konfiguracie zlyhalo.'
    state.saveError = message
  } finally {
    state.saving = false

    if (saveQueued) {
      saveQueued = false
      await persistOverrides()
    }
  }
}

const updateLocalOverrideFromSections = () => {
  const selectedKeys = sections.value
    .filter((item) => item.is_enabled)
    .map((item) => item.section_key)
    .slice(0, MAX_ENABLED_SIDEBAR_WIDGETS)

  localOverrides.value = {
    ...localOverrides.value,
    [activeScope.value]: selectedKeys,
  }

  sections.value = applyScopeSelection(sections.value, selectedKeys)
  return selectedKeys
}

const toggleSectionEnabled = (section, checked) => {
  const nextEnabled = Boolean(checked)

    if (nextEnabled) {
    const currentlyEnabled = sections.value.filter(
      (item) => item.is_enabled && item.section_key !== section.section_key,
    ).length
    if (currentlyEnabled >= MAX_ENABLED_SIDEBAR_WIDGETS) {
      state.scopeError = `Na jeden sidebar mozu byt aktivne maximalne ${MAX_ENABLED_SIDEBAR_WIDGETS} widgety.`
      return
    }
  }

  state.scopeError = ''
  section.is_enabled = nextEnabled
  updateLocalOverrideFromSections()
  void persistOverrides()
}

const canMoveSection = (section, direction) => {
  if (!section?.is_enabled) return false
  const keys = enabledSectionKeys.value
  const index = keys.indexOf(section.section_key)
  if (index < 0) return false
  if (direction === 'up') return index > 0
  if (direction === 'down') return index < keys.length - 1
  return false
}

const isMoveDisabled = (section, direction) => {
  if (state.loadingScope || state.saving) return true
  return !canMoveSection(section, direction)
}

const moveSection = (section, direction) => {
  if (isMoveDisabled(section, direction)) return

  const keys = [...enabledSectionKeys.value]
  const index = keys.indexOf(section.section_key)
  if (index < 0) return

  const swapIndex = direction === 'up' ? index - 1 : index + 1
  if (swapIndex < 0 || swapIndex >= keys.length) return

  const current = keys[index]
  keys[index] = keys[swapIndex]
  keys[swapIndex] = current

  state.scopeError = ''
  localOverrides.value = {
    ...localOverrides.value,
    [activeScope.value]: keys,
  }
  sections.value = applyScopeSelection(sections.value, keys)
  void persistOverrides()
}

const isToggleDisabled = (section) => {
  if (state.loadingScope || state.saving) return true
  if (section.is_enabled) return false
  return enabledSectionsCount.value >= MAX_ENABLED_SIDEBAR_WIDGETS
}

const switchScope = async (scope) => {
  if (scope === activeScope.value || state.loadingScope) return
  activeScope.value = scope
  await loadScope(scope)
}

onMounted(async () => {
  if (!preferences.loaded) {
    try {
      await preferences.fetchPreferences(true)
      state.preferencesError = ''
    } catch (error) {
      state.preferencesError = error?.userMessage
        || error?.response?.data?.message
        || preferences.error
        || 'Nepodarilo sa nacitat preferencie.'
    }
  }

  if (!visibleScopeTabs.value.some((tab) => tab.value === activeScope.value) && visibleScopeTabs.value.length > 0) {
    activeScope.value = visibleScopeTabs.value[0].value
  }

  localOverrides.value = readStoreOverrides()
  await loadScope(activeScope.value)
})
</script>
