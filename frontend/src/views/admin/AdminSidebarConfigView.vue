<template>
  <div class="max-w-[520px] grid gap-5">

    <!-- Header -->
    <header class="flex items-start justify-between gap-4">
      <div>
        <h1 class="m-0 text-base font-bold">Widgety sidebaru</h1>
        <p class="m-0 mt-1 text-xs text-muted/70 leading-relaxed">Vyber max 3 widgety, ktoré sa zobrazia hosťom a novým používateľom.</p>
      </div>
      <span v-if="saveStateLabel" class="text-xs whitespace-nowrap pt-0.5 shrink-0" :class="saveStateTone">{{ saveStateLabel }}</span>
    </header>

    <!-- Error / Loading -->
    <p v-if="state.error" class="m-0 text-danger text-xs">{{ state.error }}</p>
    <div v-if="state.loading" class="text-muted/60 text-sm">Načítavam konfiguráciu...</div>

    <template v-else>

      <!-- ACTIVE ZONE -->
      <section class="grid gap-2">
        <p class="m-0 flex items-center gap-2 text-[10.5px] font-bold uppercase tracking-[0.07em] text-muted/60">
          Aktívne
          <span
            class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold tabular-nums"
            :class="activeWidgets.length >= MAX_ENABLED ? 'bg-vivid/20 text-vivid' : 'bg-secondary-btn text-muted'"
          >{{ activeWidgets.length }}/{{ MAX_ENABLED }}</span>
        </p>

        <div
          class="dropZone dropZone--active grid gap-1.5 min-h-[52px] rounded-xl p-1.5"
          :class="{ 'dropZone--dropping': dragOverZone === 'active' }"
          @dragover.prevent="dragOverZone = 'active'"
          @dragleave="onActiveLeave"
          @drop.prevent="onDrop('active')"
        >
          <article
            v-for="widget in activeWidgets"
            :key="widget.section_key"
            class="widgetCard flex items-center gap-2.5 px-2.5 py-2 rounded-xl bg-hover cursor-grab select-none"
            :class="{
              'widgetCard--dragging': dragKey === widget.section_key,
              'widgetCard--dragOver': dragOverKey === widget.section_key && dragKey !== widget.section_key,
            }"
            draggable="true"
            @dragstart="onDragStart($event, widget, 'active')"
            @dragend="onDragEnd"
            @dragenter.prevent="dragOverKey = widget.section_key"
          >
            <span class="cardHandle text-muted/35 text-base leading-none shrink-0" aria-hidden="true">⠿</span>
            <span class="flex-1 min-w-0 text-[13.5px] font-medium overflow-hidden text-ellipsis whitespace-nowrap">{{ widget.title }}</span>
            <button
              type="button"
              class="removeBtn border-0 bg-transparent text-muted/40 text-[11px] cursor-pointer p-1 rounded-lg leading-none shrink-0 transition-colors duration-100"
              title="Odstrániť"
              @click="disableWidget(widget)"
            >✕</button>
          </article>

          <div v-if="activeWidgets.length === 0" class="py-3 text-muted/45 text-xs text-center">
            Pretiahnite sem widget
          </div>
        </div>
      </section>

      <!-- AVAILABLE ZONE -->
      <section class="grid gap-2">
        <p class="m-0 text-[10.5px] font-bold uppercase tracking-[0.07em] text-muted/60">Dostupné</p>

        <input
          v-model="search"
          type="text"
          class="w-full px-2.5 py-2 rounded-xl bg-hover border-0 text-[13px] placeholder:text-muted/40 focus:outline-none box-border"
          placeholder="Hľadať widget..."
        />

        <div
          class="dropZone grid gap-1.5 min-h-[52px] rounded-xl p-1.5"
          :class="{ 'dropZone--dropping': dragOverZone === 'available' }"
          @dragover.prevent="dragOverZone = 'available'"
          @dragleave="dragOverZone = null"
          @drop.prevent="onDrop('available')"
        >
          <article
            v-for="widget in filteredAvailable"
            :key="widget.section_key"
            class="widgetCard flex items-center gap-2.5 px-2.5 py-2 rounded-xl bg-hover select-none"
            :class="{
              'widgetCard--dragging': dragKey === widget.section_key,
              'widgetCard--locationLocked': requiresLocation(widget),
              'cursor-grab': !requiresLocation(widget),
            }"
            :draggable="requiresLocation(widget) ? 'false' : 'true'"
            :title="requiresLocation(widget) ? 'Vyžaduje polohu používateľa – nie je možné pridať do predvolených widgetov pre hostí' : undefined"
            @dragstart="onDragStart($event, widget, 'available')"
            @dragend="onDragEnd"
          >
            <span class="cardHandle text-muted/35 text-base leading-none shrink-0" aria-hidden="true">⠿</span>
            <span class="flex-1 min-w-0 text-[13.5px] font-medium overflow-hidden text-ellipsis whitespace-nowrap">{{ widget.title }}</span>
            <span
              v-if="requiresLocation(widget)"
              class="flex items-center shrink-0 text-yellow-400/70"
              aria-label="Vyžaduje polohu"
            >
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
            </span>
          </article>

          <div v-if="filteredAvailable.length === 0" class="py-3 text-muted/45 text-xs text-center">
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
/* ── Drop zone states ─────────────────────────── */
.dropZone {
  border: 1.5px dashed transparent;
  transition: border-color 0.14s, background 0.14s;
}

.dropZone--active {
  border-color: rgba(171, 184, 201, 0.13);
}

.dropZone--dropping {
  border-color: #0F73FF !important;
  background: rgba(15, 115, 255, 0.05) !important;
}

/* ── Card drag states ─────────────────────────── */
.widgetCard {
  transition: opacity 0.14s, box-shadow 0.14s, transform 0.12s;
}

.widgetCard:active {
  cursor: grabbing;
}

.widgetCard--dragging {
  opacity: 0.3;
}

.widgetCard--dragOver {
  box-shadow: 0 0 0 2px #0F73FF;
  transform: translateY(-2px);
}

/* ── Remove button hover ──────────────────────── */
.removeBtn:hover {
  color: #EB2452;
}

/* ── Location locked ──────────────────────────── */
.widgetCard--locationLocked {
  cursor: default;
  opacity: 0.5;
}

.widgetCard--locationLocked .cardHandle {
  opacity: 0.15;
  cursor: default;
}

/* ── Save state indicator ─────────────────────── */
.saveState--saving { color: #0F73FF; }
.saveState--error  { color: #EB2452; }
.saveState--saved  { color: #22C55E; }
</style>
