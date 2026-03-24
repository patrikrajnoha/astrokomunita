<template>
  <div class="sidebarEditor">
    <header class="sidebarEditor__header">
      <div>
        <h1 class="sidebarEditor__title">Widgety sidebaru</h1>
        <p class="sidebarEditor__desc">Vyber max 3 widgety, ktoré sa zobrazia hosťom a novým používateľom.</p>
      </div>
      <span v-if="saveStateLabel" class="saveState" :class="saveStateTone">{{ saveStateLabel }}</span>
    </header>

    <p v-if="state.error" class="fieldError">{{ state.error }}</p>
    <div v-if="state.loading" class="stateHint">Načítavam konfiguráciu...</div>

    <template v-else>
      <!-- ACTIVE ZONE -->
      <section class="widgetZone">
        <p class="widgetZone__label">
          Aktívne
          <span
            class="widgetZone__count"
            :class="{ 'widgetZone__count--full': activeWidgets.length >= MAX_ENABLED }"
          >
            {{ activeWidgets.length }}/{{ MAX_ENABLED }}
          </span>
        </p>

        <div
          class="widgetZone__list widgetZone__list--active"
          :class="{ 'widgetZone__list--dropping': dragOverZone === 'active' }"
          @dragover.prevent="dragOverZone = 'active'"
          @dragleave="onActiveLeave"
          @drop.prevent="onDrop('active')"
        >
          <article
            v-for="widget in activeWidgets"
            :key="widget.section_key"
            class="widgetCard widgetCard--active"
            :class="{
              'widgetCard--dragging': dragKey === widget.section_key,
              'widgetCard--dragOver': dragOverKey === widget.section_key && dragKey !== widget.section_key,
            }"
            draggable="true"
            @dragstart="onDragStart($event, widget, 'active')"
            @dragend="onDragEnd"
            @dragenter.prevent="dragOverKey = widget.section_key"
          >
            <span class="widgetCard__handle" aria-hidden="true">⠿</span>
            <span class="widgetCard__name">{{ widget.title }}</span>
            <button
              type="button"
              class="widgetCard__remove"
              title="Odstrániť"
              @click="disableWidget(widget)"
            >✕</button>
          </article>

          <div v-if="activeWidgets.length === 0" class="widgetZone__empty">
            Pretiahnite sem widget
          </div>
        </div>
      </section>

      <!-- AVAILABLE ZONE -->
      <section class="widgetZone">
        <p class="widgetZone__label">Dostupné</p>

        <input
          v-model="search"
          type="text"
          class="widgetSearch"
          placeholder="Hľadať widget..."
        >

        <div
          class="widgetZone__list"
          :class="{ 'widgetZone__list--dropping': dragOverZone === 'available' }"
          @dragover.prevent="dragOverZone = 'available'"
          @dragleave="dragOverZone = null"
          @drop.prevent="onDrop('available')"
        >
          <article
            v-for="widget in filteredAvailable"
            :key="widget.section_key"
            class="widgetCard"
            :class="{
              'widgetCard--dragging': dragKey === widget.section_key,
              'widgetCard--locationLocked': requiresLocation(widget),
            }"
            :draggable="requiresLocation(widget) ? 'false' : 'true'"
            :title="requiresLocation(widget) ? 'Vyžaduje polohu používateľa – nie je možné pridať do predvolených widgetov pre hostí' : undefined"
            @dragstart="onDragStart($event, widget, 'available')"
            @dragend="onDragEnd"
          >
            <span class="widgetCard__handle" aria-hidden="true">⠿</span>
            <span class="widgetCard__name">{{ widget.title }}</span>
            <span v-if="requiresLocation(widget)" class="widgetCard__locationBadge" aria-label="Vyžaduje polohu">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
            </span>
          </article>

          <div v-if="filteredAvailable.length === 0" class="widgetZone__empty">
            {{ search ? 'Žiadne výsledky' : 'Všetky widgety sú aktívne' }}
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import { normalizeSidebarSections, OBSERVING_SECTION_KEYS } from '@/sidebar/engine'
import { useSidebarConfigStore } from '@/stores/sidebarConfig'

const MAX_ENABLED = 3

const sidebarConfigStore = useSidebarConfigStore()
const sections = ref([])
const search = ref('')

// Drag state
const dragKey = ref(null)
const dragFrom = ref(null)
const dragOverKey = ref(null)
const dragOverZone = ref(null)

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
  return ''
})

const saveStateTone = computed(() => {
  if (state.saving) return 'saveState--saving'
  if (state.saveError) return 'saveState--error'
  return 'saveState--saved'
})

const activeWidgets = computed(() =>
  sections.value.filter((s) => s.is_enabled).sort((a, b) => a.order - b.order),
)

const availableWidgets = computed(() =>
  sections.value.filter((s) => !s.is_enabled),
)

const filteredAvailable = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return availableWidgets.value
  return availableWidgets.value.filter((s) => s.title.toLowerCase().includes(q))
})

function requiresLocation(widget) {
  return OBSERVING_SECTION_KEYS.includes(widget.section_key)
}

function enforceMaxEnabled(items) {
  const sorted = [...items].sort((a, b) => a.order - b.order)
  let enabledCount = 0

  return sorted.map((item, index) => {
    const keepEnabled = Boolean(item.is_enabled) && enabledCount < MAX_ENABLED
    if (keepEnabled) {
      enabledCount += 1
    }

    return {
      ...item,
      is_enabled: keepEnabled,
      order: index,
    }
  })
}

// ── Drag & Drop ───────────────────────────────────────────────────────────────

function onDragStart(e, widget, zone) {
  dragKey.value = widget.section_key
  dragFrom.value = zone
  dragOverKey.value = null
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', widget.section_key)
}

function onDragEnd() {
  dragKey.value = null
  dragFrom.value = null
  dragOverKey.value = null
  dragOverZone.value = null
}

function onActiveLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    dragOverZone.value = null
  }
}

function onDrop(zone) {
  const key = dragKey.value
  const from = dragFrom.value
  const overKey = dragOverKey.value

  if (!key) { onDragEnd(); return }

  if (zone === 'active') {
    if (from === 'available') {
      const widget = sections.value.find((s) => s.section_key === key)
      if (widget && requiresLocation(widget)) {
        state.error = 'Widgety vyžadujúce polohu nie je možné pridať – hostia ich neuvidia.'
        onDragEnd()
        return
      }
      if (activeWidgets.value.length >= MAX_ENABLED) {
        state.error = `Môžu byť aktívne najviac ${MAX_ENABLED} widgety.`
        onDragEnd()
        return
      }
      state.error = ''
      if (widget) {
        widget.is_enabled = true
        rebuildOrder()
      }
    } else if (from === 'active' && overKey && overKey !== key) {
      reorderActive(key, overKey)
    }
  } else if (zone === 'available' && from === 'active') {
    state.error = ''
    const widget = sections.value.find((s) => s.section_key === key)
    if (widget) {
      widget.is_enabled = false
      rebuildOrder()
    }
  }

  void persistConfig()
  onDragEnd()
}

function reorderActive(fromKey, toKey) {
  const enabled = sections.value.filter((s) => s.is_enabled).sort((a, b) => a.order - b.order)
  const fromIdx = enabled.findIndex((s) => s.section_key === fromKey)
  const toIdx = enabled.findIndex((s) => s.section_key === toKey)
  if (fromIdx < 0 || toIdx < 0) return

  const reordered = [...enabled]
  const [moved] = reordered.splice(fromIdx, 1)
  reordered.splice(toIdx, 0, moved)

  const disabled = sections.value.filter((s) => !s.is_enabled)
  sections.value = [...reordered, ...disabled].map((s, i) => ({ ...s, order: i }))
}

function rebuildOrder() {
  const enabled = sections.value.filter((s) => s.is_enabled).sort((a, b) => a.order - b.order)
  const disabled = sections.value.filter((s) => !s.is_enabled)
  sections.value = [...enabled, ...disabled].map((s, i) => ({ ...s, order: i }))
}

function disableWidget(widget) {
  state.error = ''
  widget.is_enabled = false
  rebuildOrder()
  void persistConfig()
}

// ── API ───────────────────────────────────────────────────────────────────────

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
    sections.value = enforceMaxEnabled(items)
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
    const sanitizedSections = enforceMaxEnabled(sections.value)
    sections.value = sanitizedSections

    const payload = sanitizedSections.map((s, index) => ({
      section_key: s.section_key,
      is_enabled: s.is_enabled,
      order: index,
    }))
    await api.put('/admin/sidebar-config', { sections: payload })
    state.lastSavedAt = new Date().toISOString()

    // Invalidate client-side cache so DynamicSidebar re-fetches on next navigation
    delete sidebarConfigStore.byScope['home']
  } catch (err) {
    state.saveError = err?.response?.data?.message || 'Uloženie zlyhalo.'
  } finally {
    state.saving = false
    if (saveQueued) {
      saveQueued = false
      await persistConfig()
    }
  }
}

onMounted(() => {
  void fetchConfig()
})
</script>

<style scoped>
.sidebarEditor {
  max-width: 520px;
  display: grid;
  gap: 1.5rem;
}

.sidebarEditor__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.sidebarEditor__title {
  margin: 0 0 0.22rem;
  font-size: 1.05rem;
  font-weight: 700;
}

.sidebarEditor__desc {
  margin: 0;
  font-size: 0.8rem;
  color: var(--text-secondary);
  line-height: 1.4;
}

.saveState {
  font-size: 0.75rem;
  white-space: nowrap;
  padding-top: 0.15rem;
}

.saveState--saving { color: var(--color-primary); }
.saveState--error  { color: var(--color-danger, #e53e3e); }
.saveState--saved  { color: var(--color-success, #22c55e); }

.fieldError {
  margin: 0;
  color: var(--color-danger, #e53e3e);
  font-size: 0.82rem;
}

.stateHint {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

/* ── Zones ── */
.widgetZone {
  display: grid;
  gap: 0.6rem;
}

.widgetZone__label {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.widgetZone__count {
  font-size: 0.7rem;
  padding: 0.08rem 0.42rem;
  border-radius: 999px;
  background: rgb(var(--color-text-secondary-rgb, 148 163 184) / 0.12);
  color: var(--text-secondary);
  font-weight: 600;
}

.widgetZone__count--full {
  background: rgb(var(--color-primary-rgb, 59 130 246) / 0.16);
  color: var(--color-primary);
}

.widgetZone__list {
  display: grid;
  gap: 0.36rem;
  min-height: 3.2rem;
  border-radius: 0.65rem;
  padding: 0.35rem;
  border: 1.5px dashed transparent;
  transition: border-color 0.14s, background 0.14s;
}

.widgetZone__list--active {
  border-color: rgb(var(--color-text-secondary-rgb, 148 163 184) / 0.16);
  background: rgb(var(--color-bg-rgb, 15 23 42) / 0.25);
}

.widgetZone__list--dropping {
  border-color: var(--color-primary) !important;
  background: rgb(var(--color-primary-rgb, 59 130 246) / 0.06) !important;
}

.widgetZone__empty {
  padding: 0.85rem 0.75rem;
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-align: center;
  opacity: 0.7;
}

/* ── Cards ── */
.widgetCard {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.55rem 0.68rem;
  border-radius: 0.5rem;
  border: 1px solid var(--divider-color);
  background: var(--color-card, rgb(var(--color-bg-rgb, 15 23 42) / 0.55));
  cursor: grab;
  user-select: none;
  transition: box-shadow 0.14s, opacity 0.14s, transform 0.12s;
}

.widgetCard:active {
  cursor: grabbing;
}

.widgetCard--dragging {
  opacity: 0.35;
}

.widgetCard--dragOver {
  box-shadow: 0 0 0 2px var(--color-primary);
  transform: translateY(-2px);
}

.widgetCard__handle {
  color: var(--text-secondary);
  opacity: 0.45;
  font-size: 1rem;
  line-height: 1;
  flex-shrink: 0;
}

.widgetCard__name {
  flex: 1;
  min-width: 0;
  font-size: 0.86rem;
  font-weight: 500;
  color: var(--color-surface);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.widgetCard__remove {
  border: none;
  background: none;
  color: var(--text-secondary);
  opacity: 0.45;
  font-size: 0.68rem;
  cursor: pointer;
  padding: 0.18rem 0.3rem;
  border-radius: 0.28rem;
  line-height: 1;
  flex-shrink: 0;
  transition: opacity 0.12s, color 0.12s;
}

.widgetCard__remove:hover {
  opacity: 1;
  color: var(--color-danger, #e53e3e);
}

/* ── Search ── */
.widgetSearch {
  width: 100%;
  padding: 0.5rem 0.7rem;
  border: 1px solid var(--divider-color);
  border-radius: var(--radius-md, 0.5rem);
  background: rgb(var(--color-bg-rgb, 15 23 42) / 0.4);
  color: var(--color-surface);
  font-size: 0.85rem;
  box-sizing: border-box;
}

.widgetSearch::placeholder {
  color: var(--text-secondary);
  opacity: 0.65;
}

.widgetSearch:focus {
  outline: none;
  border-color: var(--color-primary);
}

/* ── Location-locked cards ── */
.widgetCard--locationLocked {
  cursor: default;
  opacity: 0.6;
}

.widgetCard--locationLocked .widgetCard__handle {
  opacity: 0.15;
  cursor: default;
}

.widgetCard__locationBadge {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  color: var(--color-warning, #f59e0b);
}

.widgetCard__locationBadge svg {
  width: 0.82rem;
  height: 0.82rem;
}
</style>
