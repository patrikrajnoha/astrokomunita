<script setup>
import { computed } from 'vue'
import { candidateDisplayTitle } from '@/utils/translatedFields'

const props = defineProps({
  loading: { type: Boolean, default: false },
  data: { type: Object, default: null },
  showScoreInList: { type: Boolean, default: false },
  selectedIds: { type: Set, default: () => new Set() },
  formatDate: { type: Function, required: true },
  formatConfidence: { type: Function, required: true },
  sourceLabel: { type: Function, required: true },
  normalizeSources: { type: Function, required: true },
})

const emit = defineEmits([
  'publish-candidate',
  'open-candidate',
  'prev-page',
  'next-page',
  'toggle-select',
  'select-all',
  'deselect-all',
])

const allIds = computed(() => (props.data?.data || []).map((c) => c.id))
const allSelected = computed(() => allIds.value.length > 0 && allIds.value.every((id) => props.selectedIds.has(id)))
const someSelected = computed(() => allIds.value.some((id) => props.selectedIds.has(id)))

function candidateTypeLabel(value) {
  const key = String(value || '').trim().toLowerCase()
  if (key === 'observation_window') return 'Pozorovacie okno'
  if (key === 'meteor_shower') return 'Meteoritický roj'
  if (key === 'eclipse_lunar') return 'Zatmenie Mesiaca'
  if (key === 'eclipse_solar') return 'Zatmenie Slnka'
  if (key === 'planetary_event') return 'Planetárny úkaz'
  if (key === 'aurora') return 'Polárna žiara'
  if (key === 'other') return 'Iná udalosť'
  if (key === '') return '-'
  return key.replaceAll('_', ' ')
}

function matchedSourcesCount(candidate) {
  const primary = String(candidate?.source_name || '').trim().toLowerCase()
  const normalized = props.normalizeSources(candidate?.matched_sources)
  const unique = [...new Set(normalized)]
  return unique.filter((source) => source !== primary).length
}

function resolveRowState(candidate) {
  const status = String(candidate?.status || '').trim().toLowerCase()
  const translation = String(candidate?.translation_status || '').trim().toLowerCase()

  if (status === 'approved') return { label: 'Publikované', tone: 'statusPill--success' }
  if (status === 'rejected') return { label: 'Zamietnuté', tone: 'statusPill--danger' }
  if (status === 'pending') {
    if (translation === 'failed' || translation === 'error') return { label: 'Zlyhalo', tone: 'statusPill--danger' }
    if (['pending', 'queued', 'running', 'processing', 'in_progress'].includes(translation)) {
      return { label: 'Vo fronte', tone: 'statusPill--warning' }
    }
    return { label: 'Pripravené', tone: 'statusPill--ready' }
  }
  return { label: 'Čaká', tone: 'statusPill--neutral' }
}

function scoreTone(value) {
  let score = Number(value)
  if (!Number.isFinite(score)) return 'scoreCell--neutral'
  if (score <= 1) score *= 100
  if (score >= 80) return 'scoreCell--high'
  if (score >= 55) return 'scoreCell--mid'
  return 'scoreCell--low'
}

function translationModeBadge(candidate) {
  const mode = String(candidate?.translation_mode || '').trim().toLowerCase()
  const status = String(candidate?.translation_status || '').trim().toLowerCase()
  if (['pending', 'queued', 'running', 'processing', 'in_progress'].includes(status)) return null
  if (mode === 'template') return { label: 'Šablóna', tone: 'modeBadge--template' }
  if (mode === 'ai_refined') return { label: 'AI: title + popis', tone: 'modeBadge--ai' }
  if (mode === 'ai_title') return { label: 'AI: title', tone: 'modeBadge--ai' }
  if (mode === 'ai_description') return { label: 'AI: popis', tone: 'modeBadge--ai' }
  if (mode === 'manual') return { label: 'Ručne', tone: 'modeBadge--manual' }
  return null
}
</script>

<template>
  <div v-if="data && !loading" class="candidatesResults">
    <div class="candidatesTableWrap">
      <table class="candidatesTable">
        <colgroup>
          <col class="colCheck" />
          <col class="colTitle" />
          <col class="colDate" />
          <col class="colStatus" />
        </colgroup>
        <thead>
          <tr>
            <th>
              <input
                type="checkbox"
                class="rowCheckbox"
                :checked="allSelected"
                :indeterminate="someSelected && !allSelected"
                @change="allSelected ? emit('deselect-all', allIds) : emit('select-all', allIds)"
              />
            </th>
            <th>Kandidát</th>
            <th>Začiatok</th>
            <th>Stav</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="candidate in data.data" :key="candidate.id" class="candidatesRow" :class="{ 'candidatesRow--selected': selectedIds.has(candidate.id) }">
            <td class="cellCheck">
              <input
                type="checkbox"
                class="rowCheckbox"
                :checked="selectedIds.has(candidate.id)"
                @change="emit('toggle-select', candidate.id)"
              />
            </td>
            <td>
              <div class="candidateHead">
                <button
                  type="button"
                  class="candidateTitleButton"
                  title="Zobraziť detail kandidáta"
                  @click="emit('open-candidate', candidate.id)"
                >
                  <span class="candidateTitle">{{ candidateDisplayTitle(candidate) }}</span>
                </button>

                <div class="rowActions">
                  <button
                    v-if="candidate.status === 'pending'"
                    type="button"
                    :disabled="loading"
                    class="rowActionButton rowActionButton--primary"
                    @click="emit('publish-candidate', candidate)"
                  >
                    Publikovať
                  </button>

                  <button type="button" class="rowActionButton rowActionButton--ghost" @click="emit('open-candidate', candidate.id)">Detail</button>
                </div>
              </div>

              <div class="candidateMeta">
                <span>{{ sourceLabel(candidate.source_name) }}</span>
                <span>•</span>
                <span>{{ candidateTypeLabel(candidate.type) }}</span>
                <span v-if="matchedSourcesCount(candidate) > 0">• +{{ matchedSourcesCount(candidate) }} zdroje</span>
                <span
                  v-if="translationModeBadge(candidate)"
                  class="modeBadge"
                  :class="translationModeBadge(candidate).tone"
                >{{ translationModeBadge(candidate).label }}</span>
                <span v-if="showScoreInList" class="scoreInline" :class="scoreTone(candidate.confidence_score)">
                  • Skóre {{ formatConfidence(candidate.confidence_score) }}
                </span>
              </div>
            </td>

            <td class="cellDate">{{ formatDate(candidate.start_at) }}</td>
            <td><span class="statusPill" :class="resolveRowState(candidate).tone">{{ resolveRowState(candidate).label }}</span></td>
          </tr>

          <tr v-if="data.data.length === 0">
            <td colspan="3" class="tableEmpty">Žiadne kandidáty pre aktuálny filter.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="candidatesMobileList">
      <article v-for="candidate in data.data" :key="`mobile-${candidate.id}`" class="candidateMobileCard">
        <button type="button" class="candidateTitleButton" @click="emit('open-candidate', candidate.id)">
          <span class="candidateTitle">{{ candidateDisplayTitle(candidate) }}</span>
        </button>
        <div class="candidateMeta">
          <span>{{ sourceLabel(candidate.source_name) }}</span>
          <span>•</span>
          <span>{{ candidateTypeLabel(candidate.type) }}</span>
        </div>
        <div class="mobileInfo">
          <span class="cellDate">{{ formatDate(candidate.start_at) }}</span>
          <span class="statusPill" :class="resolveRowState(candidate).tone">{{ resolveRowState(candidate).label }}</span>
          <span v-if="showScoreInList" class="scoreInline" :class="scoreTone(candidate.confidence_score)">
            Skóre {{ formatConfidence(candidate.confidence_score) }}
          </span>
        </div>
        <div class="rowActions">
          <button v-if="candidate.status === 'pending'" type="button" class="rowActionButton rowActionButton--primary" @click="emit('publish-candidate', candidate)">Publikovať</button>
          <button type="button" class="rowActionButton rowActionButton--ghost" @click="emit('open-candidate', candidate.id)">Detail</button>
        </div>
      </article>
      <div v-if="data.data.length === 0" class="tableEmpty">Žiadne kandidáty pre aktuálny filter.</div>
    </div>

    <div class="pagerRow">
      <div class="pagerMeta">Strana {{ data.current_page }} / {{ data.last_page }} (spolu {{ data.total }})</div>
      <div class="pagerActions">
        <button type="button" :disabled="loading || data.current_page <= 1" class="toolbarButton toolbarButton--ghost" @click="emit('prev-page')">Predchádzajúca</button>
        <button type="button" :disabled="loading || data.current_page >= data.last_page" class="toolbarButton toolbarButton--ghost" @click="emit('next-page')">Nasledujúca</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.candidatesTableWrap {
  border-radius: 10px;
  overflow: auto;
  background: rgb(var(--color-bg-rgb) / 0.98);
}

.candidatesTable {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.candidatesTable thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  text-align: left;
  padding: 7px 8px;
  font-size: 10px;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  white-space: nowrap;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--color-surface-rgb) / 0.06);
  border-bottom: 1px solid var(--divider-color);
}

.candidatesTable td {
  padding: 8px;
  border-top: 1px solid var(--divider-color);
  vertical-align: top;
  font-size: 13px;
}

.colCheck {
  width: 32px;
}

.colTitle {
  width: 60%;
}

.colDate {
  width: 20%;
}

.colStatus {
  width: 16%;
}

.cellCheck {
  padding: 0 4px;
  text-align: center;
  vertical-align: middle;
}

.rowCheckbox {
  width: 15px;
  height: 15px;
  cursor: pointer;
  accent-color: rgb(var(--color-primary-rgb));
}

.candidatesRow:hover {
  background: rgb(var(--color-surface-rgb) / 0.02);
}

.candidatesRow--selected {
  background: rgb(var(--color-primary-rgb) / 0.06);
}

.candidateHead {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.candidateTitleButton {
  display: block;
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.candidateTitle {
  font-weight: 700;
  line-height: 1.22;
  word-break: break-word;
}

.candidateMeta {
  margin-top: 3px;
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  font-size: 12px;
}

.cellDate {
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
  font-variant-numeric: tabular-nums;
}

.statusPill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 11px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.22);
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.statusPill--ready { border-color: rgb(var(--color-primary-rgb) / 0.42); background: rgb(var(--color-primary-rgb) / 0.18); }
.statusPill--warning { border-color: rgb(245 158 11 / 0.4); background: rgb(245 158 11 / 0.14); }
.statusPill--success { border-color: rgb(var(--color-success-rgb) / 0.4); background: rgb(var(--color-success-rgb) / 0.16); }
.statusPill--danger { border-color: rgb(var(--color-danger-rgb) / 0.4); background: rgb(var(--color-danger-rgb) / 0.14); }

.scoreInline { font-weight: 700; }
.scoreCell--high { color: rgb(var(--color-success-rgb) / 0.95); }
.scoreCell--mid { color: rgb(var(--color-primary-rgb) / 0.95); }
.scoreCell--low { color: rgb(var(--color-danger-rgb) / 0.9); }
.scoreCell--neutral { color: rgb(var(--color-text-secondary-rgb) / 0.88); }

.rowActions {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-shrink: 0;
}

.rowActionButton {
  min-height: 26px;
  padding: 3px 8px;
  border-radius: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: transparent;
  color: inherit;
  font-size: 11px;
  cursor: pointer;
  white-space: nowrap;
}

.rowActionButton--primary { border-color: rgb(var(--color-success-rgb) / 0.44); background: rgb(var(--color-success-rgb) / 0.18); }
.rowActionButton--ghost,
.rowActionButton--menu { background: rgb(var(--color-surface-rgb) / 0.08); }
.rowActionButton--subtle { color: rgb(var(--color-text-secondary-rgb) / 0.92); }

.rowMenu { position: relative; }
.rowMenu > summary { list-style: none; }
.rowMenu > summary::-webkit-details-marker { display: none; }
.rowMenu__panel {
  position: absolute;
  right: 0;
  top: calc(100% + 5px);
  z-index: 10;
  min-width: 110px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: rgb(var(--color-bg-rgb) / 0.98);
  padding: 4px;
  display: grid;
  gap: 4px;
}

.rowMenu__item {
  min-height: 30px;
  border-radius: 6px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  background: rgb(var(--color-surface-rgb) / 0.06);
  color: inherit;
  cursor: pointer;
  font-size: 12px;
}

.tableEmpty { padding: 16px 10px; color: rgb(var(--color-text-secondary-rgb) / 0.82); font-size: 13px; }

.candidatesResults { display: contents; }

.modeBadge {
  display: inline-flex;
  align-items: center;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.02em;
  border: 1px solid transparent;
}
.modeBadge--template {
  background: rgb(var(--color-surface-rgb) / 0.14);
  border-color: rgb(var(--color-surface-rgb) / 0.22);
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
}
.modeBadge--ai {
  background: rgb(var(--color-primary-rgb) / 0.12);
  border-color: rgb(var(--color-primary-rgb) / 0.28);
  color: rgb(var(--color-primary-rgb));
}
.modeBadge--manual {
  background: rgb(245 158 11 / 0.1);
  border-color: rgb(245 158 11 / 0.28);
  color: rgb(161 98 7);
}

.candidatesMobileList { display: none; gap: 10px; }
.candidateMobileCard { border-radius: 10px; background: rgb(var(--color-surface-rgb) / 0.06); padding: 10px; display: grid; gap: 7px; }
.mobileInfo { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.pagerRow { margin-top: 6px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.pagerMeta { color: rgb(var(--color-text-secondary-rgb) / 0.9); font-size: 12px; }
.pagerActions { display: flex; gap: 8px; }

.toolbarButton {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font-size: 12px;
  padding: 7px 10px;
  cursor: pointer;
}
.toolbarButton--ghost { background: rgb(var(--color-surface-rgb) / 0.08); }

@media (max-width: 1200px) {
  .colTitle { width: 58%; }
  .colDate { width: 24%; }
  .colStatus { width: 18%; }
}

@media (max-width: 900px) {
  .candidatesTableWrap { display: none; }
  .candidatesMobileList { display: grid; }
  .pagerMeta,
  .pagerActions { width: 100%; }
  .pagerActions .toolbarButton { flex: 1 1 auto; }
  .rowActions .rowActionButton { flex: 1 1 auto; min-height: 34px; }
}
</style>
