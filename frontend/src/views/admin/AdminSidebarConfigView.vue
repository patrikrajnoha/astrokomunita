<template>
  <div class="adminSidebarConfig">
    <div class="adminSidebarConfig__toolbar">
      <input
        v-model="search"
        type="text"
        class="field-input adminSidebarConfig__search"
        placeholder="Hľadať widget..."
        :disabled="state.loading"
      >
      <span class="adminSidebarConfig__saveState" :class="saveStateTone">{{ saveStateLabel }}</span>
    </div>

    <p v-if="state.error" class="field-error">{{ state.error }}</p>

    <div v-if="state.loading" class="adminSidebarConfig__loading">Načítavam konfiguráciu...</div>

    <div v-else-if="filteredSections.length === 0" class="adminSidebarConfig__empty">
      Žiadne položky nevyhovujú filtru.
    </div>

    <div v-else class="adminSidebarConfig__rows">
      <article
        v-for="section in filteredSections"
        :key="section.section_key"
        class="adminSidebarConfig__row"
        :class="{ disabled: !section.is_enabled }"
      >
        <div class="adminSidebarConfig__rowCopy">
          <p class="adminSidebarConfig__rowTitle">{{ section.title || 'Bez názvu' }}</p>
          <p class="adminSidebarConfig__rowMeta">{{ section.section_key }}</p>
        </div>

        <div class="adminSidebarConfig__rowActions">
          <div class="adminSidebarConfig__orderControls">
            <button
              type="button"
              class="adminSidebarConfig__orderBtn"
              :disabled="isMoveDisabled(section, 'up')"
              @click="moveSection(section, 'up')"
            >▲</button>
            <button
              type="button"
              class="adminSidebarConfig__orderBtn"
              :disabled="isMoveDisabled(section, 'down')"
              @click="moveSection(section, 'down')"
            >▼</button>
          </div>

          <label class="adminSidebarConfig__toggle" :for="`admin-widget-${section.section_key}`">
            <input
              :id="`admin-widget-${section.section_key}`"
              :checked="section.is_enabled"
              type="checkbox"
              :disabled="isToggleDisabled(section)"
              @change="toggleSection(section, $event.target.checked)"
            >
            <span class="adminSidebarConfig__slider"></span>
            <span class="adminSidebarConfig__toggleLabel">
              {{ section.is_enabled ? 'Zapnuté' : 'Vypnuté' }}
            </span>
          </label>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { normalizeSidebarSections } from '@/sidebar/engine'

const MAX_ENABLED = 3

const sections = ref([])
const search = ref('')

const state = reactive({
  loading: false,
  saving: false,
  error: '',
  saveError: '',
  lastSavedAt: null,
})

let saveQueued = false

const saveStateLabel = computed(() => {
  if (state.saving) return 'Ukladám...'
  if (state.saveError) return 'Chyba pri ukladaní'
  if (state.lastSavedAt) return 'Uložené'
  return 'Pripravené'
})

const saveStateTone = computed(() => {
  if (state.saving) return 'is-saving'
  if (state.saveError) return 'is-error'
  return 'is-saved'
})

const filteredSections = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return sections.value
  return sections.value.filter((s) =>
    `${s.title} ${s.section_key}`.toLowerCase().includes(q),
  )
})

const enabledCount = computed(() => sections.value.filter((s) => s.is_enabled).length)

const enabledKeys = computed(() =>
  sections.value.filter((s) => s.is_enabled).map((s) => s.section_key),
)

const fetchConfig = async () => {
  state.loading = true
  state.error = ''

  try {
    const response = await api.get('/admin/sidebar-config')
    const items = normalizeSidebarSections(response?.data?.data || [])
      .filter((item) => item.kind === 'builtin')
      .map((item) => ({
        section_key: item.section_key,
        title: item.title,
        order: item.order,
        is_enabled: item.is_enabled,
      }))
      .sort((a, b) => a.order - b.order)
    sections.value = items
  } catch (err) {
    state.error = err?.response?.data?.message || 'Nepodarilo sa načítať konfiguráciu sidebaru.'
  } finally {
    state.loading = false
  }
}

const persistConfig = async () => {
  if (state.saving) {
    saveQueued = true
    return
  }

  state.saving = true
  state.saveError = ''

  try {
    const payload = sections.value.map((s, index) => ({
      section_key: s.section_key,
      is_enabled: s.is_enabled,
      order: index,
    }))
    await api.put('/admin/sidebar-config', { sections: payload })
    state.lastSavedAt = new Date().toISOString()
  } catch (err) {
    state.saveError = err?.response?.data?.message || 'Uloženie konfigurácie zlyhalo.'
  } finally {
    state.saving = false

    if (saveQueued) {
      saveQueued = false
      await persistConfig()
    }
  }
}

const toggleSection = (section, checked) => {
  if (checked && enabledCount.value >= MAX_ENABLED) {
    state.error = `Môžu byť aktívne najviac ${MAX_ENABLED} widgety.`
    return
  }
  state.error = ''
  section.is_enabled = checked
  void persistConfig()
}

const canMove = (section, direction) => {
  if (!section.is_enabled) return false
  const keys = enabledKeys.value
  const idx = keys.indexOf(section.section_key)
  if (idx < 0) return false
  return direction === 'up' ? idx > 0 : idx < keys.length - 1
}

const isMoveDisabled = (section, direction) => {
  if (state.loading || state.saving) return true
  return !canMove(section, direction)
}

const isToggleDisabled = (section) => {
  if (state.loading || state.saving) return true
  if (section.is_enabled) return false
  return enabledCount.value >= MAX_ENABLED
}

const moveSection = (section, direction) => {
  if (isMoveDisabled(section, direction)) return

  const keys = [...enabledKeys.value]
  const idx = keys.indexOf(section.section_key)
  if (idx < 0) return

  const swapIdx = direction === 'up' ? idx - 1 : idx + 1
  ;[keys[idx], keys[swapIdx]] = [keys[swapIdx], keys[idx]]

  const keyedMap = new Map(sections.value.map((s) => [s.section_key, s]))
  const enabledInOrder = keys.map((k) => keyedMap.get(k)).filter(Boolean)
  const disabled = sections.value.filter((s) => !s.is_enabled)
  sections.value = [...enabledInOrder, ...disabled].map((s, i) => ({ ...s, order: i }))

  void persistConfig()
}

onMounted(() => {
  void fetchConfig()
})
</script>

<style scoped>
.adminSidebarConfig {
  max-width: 560px;
}

.adminSidebarConfig__toolbar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.adminSidebarConfig__search {
  flex: 1;
}

.adminSidebarConfig__saveState {
  font-size: 0.78rem;
  white-space: nowrap;
}

.adminSidebarConfig__saveState.is-saving {
  color: var(--color-primary);
}

.adminSidebarConfig__saveState.is-error {
  color: var(--color-danger, #e53e3e);
}

.adminSidebarConfig__saveState.is-saved {
  color: var(--text-secondary);
}

.adminSidebarConfig__loading,
.adminSidebarConfig__empty {
  padding: 0.75rem 0;
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.adminSidebarConfig__rows {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.adminSidebarConfig__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.45rem 0;
  border-bottom: 1px solid var(--divider-color);
}

.adminSidebarConfig__row.disabled {
  opacity: 0.55;
}

.adminSidebarConfig__rowCopy {
  flex: 1;
  min-width: 0;
}

.adminSidebarConfig__rowTitle {
  font-size: 0.85rem;
  font-weight: 500;
  margin: 0;
  color: var(--color-surface);
}

.adminSidebarConfig__rowMeta {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin: 0;
}

.adminSidebarConfig__rowActions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.adminSidebarConfig__orderControls {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.adminSidebarConfig__orderBtn {
  background: none;
  border: 1px solid var(--divider-color);
  border-radius: 4px;
  padding: 0 0.3rem;
  font-size: 0.6rem;
  cursor: pointer;
  color: var(--text-secondary);
  line-height: 1.4;
}

.adminSidebarConfig__orderBtn:disabled {
  opacity: 0.3;
  cursor: default;
}

.adminSidebarConfig__toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  user-select: none;
}

.adminSidebarConfig__toggle input {
  display: none;
}

.adminSidebarConfig__slider {
  position: relative;
  display: inline-block;
  width: 2.2rem;
  height: 1.2rem;
  background: var(--divider-color);
  border-radius: 1rem;
  transition: background 0.2s;
  flex-shrink: 0;
}

.adminSidebarConfig__toggle input:checked ~ .adminSidebarConfig__slider {
  background: var(--color-primary);
}

.adminSidebarConfig__slider::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: calc(1.2rem - 4px);
  height: calc(1.2rem - 4px);
  background: #fff;
  border-radius: 50%;
  transition: transform 0.2s;
}

.adminSidebarConfig__toggle input:checked ~ .adminSidebarConfig__slider::after {
  transform: translateX(1rem);
}

.adminSidebarConfig__toggleLabel {
  font-size: 0.8rem;
  color: var(--text-secondary);
  min-width: 3.5rem;
}
</style>
