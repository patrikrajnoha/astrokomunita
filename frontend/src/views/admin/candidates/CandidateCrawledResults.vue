<script setup>
import { candidateDisplayTitle } from '@/utils/translatedFields'

const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  data: {
    type: Object,
    default: null,
  },
  showConfidenceColumn: {
    type: Boolean,
    default: false,
  },
  candidatePreviewShort: {
    type: Function,
    required: true,
  },
  formatDate: {
    type: Function,
    required: true,
  },
  formatConfidence: {
    type: Function,
    required: true,
  },
  normalizeTranslationStatus: {
    type: Function,
    required: true,
  },
  sourceLabel: {
    type: Function,
    required: true,
  },
  normalizeSources: {
    type: Function,
    required: true,
  },
})

const emit = defineEmits([
  'publish-candidate',
  'retranslate-candidate',
  'open-candidate',
  'prev-page',
  'next-page',
])

function sourceToneClass(source) {
  const key = String(source || '').trim().toLowerCase()
  if (key === 'astropixels') return 'chip--source-astropixels'
  if (key === 'imo') return 'chip--source-imo'
  if (key === 'nasa' || key === 'nasa_wts' || key === 'nasa_watch_the_skies') return 'chip--source-nasa'
  return 'chip--source-default'
}

function statusToneClass(value) {
  const status = String(value || '').trim().toLowerCase()
  if (status === 'approved') return 'statusBadge--approved'
  if (status === 'rejected') return 'statusBadge--rejected'
  if (status === 'pending') return 'statusBadge--pending'
  return 'statusBadge--neutral'
}

function translationToneClass(value) {
  const label = String(props.normalizeTranslationStatus(value) || '').trim().toLowerCase()
  if (label === 'prelozene') return 'translationBadge--success'
  if (label === 'zlyhalo') return 'translationBadge--error'
  return 'translationBadge--pending'
}

function matchedSources(candidate) {
  return props.normalizeSources(candidate?.matched_sources)
}

function candidateTypeLabel(value) {
  const key = String(value || '').trim().toLowerCase()
  if (key === 'observation_window') return 'Pozorovacie okno'
  if (key === 'meteor_shower') return 'Meteorický roj'
  if (key === 'eclipse_lunar') return 'Zatmenie Mesiaca'
  if (key === 'eclipse_solar') return 'Zatmenie Slnka'
  if (key === 'planetary_event') return 'Planetárny úkaz'
  if (key === 'other') return 'Iná udalosť'
  if (key === '') return '-'
  return key.replaceAll('_', ' ')
}

function matchedSourcesWithoutPrimary(candidate) {
  const primary = String(candidate?.source_name || '').trim().toLowerCase()
  const unique = [...new Set(matchedSources(candidate))]
  return unique.filter((source) => source !== primary)
}

function visibleMatchedSources(candidate, max = 2) {
  return matchedSourcesWithoutPrimary(candidate).slice(0, max)
}

function hiddenMatchedSourcesCount(candidate, max = 2) {
  return Math.max(0, matchedSourcesWithoutPrimary(candidate).length - max)
}
</script>

<template>
  <template v-if="data && !loading">
    <div class="candidatesTableWrap">
      <table class="candidatesTable">
        <colgroup>
          <col class="colId" />
          <col class="colTitle" />
          <col class="colSourceType" />
          <col class="colState" />
          <col v-if="showConfidenceColumn" class="colScore" />
          <col class="colActions" />
        </colgroup>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nazov</th>
            <th>Zdroj / typ</th>
            <th>Zaciatok / stav</th>
            <th v-if="showConfidenceColumn">Skore zdrojov</th>
            <th>Akcie</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="candidate in data.data" :key="candidate.id" class="candidatesRow">
            <td class="cellMono">{{ candidate.id }}</td>
            <td>
              <div class="candidateTitle">{{ candidateDisplayTitle(candidate) }}</div>
              <div
                v-if="candidatePreviewShort(candidate) && candidatePreviewShort(candidate) !== '-'"
                class="candidateShort"
              >
                {{ candidatePreviewShort(candidate) }}
              </div>
            </td>
            <td>
              <div class="cellStack">
                <span class="chip" :class="sourceToneClass(candidate.source_name)" :title="sourceLabel(candidate.source_name)">
                  {{ sourceLabel(candidate.source_name) }}
                </span>
                <span class="typeTag" :title="`Typ: ${candidateTypeLabel(candidate.type)}`">{{ candidateTypeLabel(candidate.type) }}</span>
              </div>
              <div v-if="matchedSourcesWithoutPrimary(candidate).length > 0" class="matchedSources">
                <span
                  v-for="src in visibleMatchedSources(candidate)"
                  :key="`matched-${candidate.id}-${src}`"
                  class="matchedSourceTag"
                  :class="sourceToneClass(src)"
                >
                  {{ sourceLabel(src) }}
                </span>
                <span v-if="hiddenMatchedSourcesCount(candidate) > 0" class="cellMuted">
                  +{{ hiddenMatchedSourcesCount(candidate) }}
                </span>
              </div>
            </td>
            <td>
              <div class="cellStack">
                <span class="cellDate">{{ formatDate(candidate.start_at) }}</span>
                <div class="cellInlineBadges">
                  <span class="statusBadge" :class="statusToneClass(candidate.status)">{{ candidate.status || '-' }}</span>
                  <span class="translationBadge" :class="translationToneClass(candidate.translation_status)">
                    {{ normalizeTranslationStatus(candidate.translation_status) }}
                  </span>
                </div>
              </div>
            </td>
            <td v-if="showConfidenceColumn" class="cellMono">{{ formatConfidence(candidate.confidence_score) }}</td>
            <td>
              <div class="rowActions">
                <button
                  v-if="candidate.status === 'pending'"
                  type="button"
                  :disabled="loading"
                  class="rowActionButton rowActionButton--success"
                  @click="emit('publish-candidate', candidate)"
                >
                  Publikovat
                </button>
                <button
                  type="button"
                  :disabled="loading"
                  class="rowActionButton rowActionButton--primary"
                  title="Retranslate"
                  @click="emit('retranslate-candidate', candidate)"
                >
                  Retr.
                </button>
                <button
                  type="button"
                  class="rowActionButton rowActionButton--ghost"
                  @click="emit('open-candidate', candidate.id)"
                >
                  Detail
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="data.data.length === 0">
            <td :colspan="showConfidenceColumn ? 6 : 5" class="tableEmpty">
              Ziadne kandidaty pre aktualny filter.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="candidatesMobileList">
      <article v-for="candidate in data.data" :key="`mobile-${candidate.id}`" class="candidateMobileCard">
        <div class="candidateMobileCard__head">
          <span class="candidateMobileCard__id">#{{ candidate.id }}</span>
          <div class="candidateMobileCard__headBadges">
            <span class="statusBadge" :class="statusToneClass(candidate.status)">{{ candidate.status || '-' }}</span>
            <span class="translationBadge" :class="translationToneClass(candidate.translation_status)">
              {{ normalizeTranslationStatus(candidate.translation_status) }}
            </span>
          </div>
        </div>
        <div class="candidateMobileCard__title">{{ candidateDisplayTitle(candidate) }}</div>
        <div
          v-if="candidatePreviewShort(candidate) && candidatePreviewShort(candidate) !== '-'"
          class="candidateShort"
        >
          {{ candidatePreviewShort(candidate) }}
        </div>
        <div class="candidateMobileCard__meta">
          <span class="chip" :class="sourceToneClass(candidate.source_name)">{{ sourceLabel(candidate.source_name) }}</span>
          <span class="typeTag">{{ candidateTypeLabel(candidate.type) }}</span>
          <span class="cellMuted">{{ formatDate(candidate.start_at) }}</span>
          <span v-if="showConfidenceColumn" class="cellMono">Skore {{ formatConfidence(candidate.confidence_score) }}</span>
        </div>
        <div v-if="matchedSourcesWithoutPrimary(candidate).length > 0" class="matchedSources">
          <span
            v-for="src in visibleMatchedSources(candidate)"
            :key="`matched-mobile-${candidate.id}-${src}`"
            class="matchedSourceTag"
            :class="sourceToneClass(src)"
          >
            {{ sourceLabel(src) }}
          </span>
          <span v-if="hiddenMatchedSourcesCount(candidate) > 0" class="cellMuted">
            +{{ hiddenMatchedSourcesCount(candidate) }}
          </span>
        </div>
        <div class="rowActions">
          <button
            v-if="candidate.status === 'pending'"
            type="button"
            :disabled="loading"
            class="rowActionButton rowActionButton--success"
            @click="emit('publish-candidate', candidate)"
          >
            Publikovat
          </button>
          <button
            type="button"
            :disabled="loading"
            class="rowActionButton rowActionButton--primary"
            @click="emit('retranslate-candidate', candidate)"
          >
            Retr.
          </button>
          <button
            type="button"
            class="rowActionButton rowActionButton--ghost"
            @click="emit('open-candidate', candidate.id)"
          >
            Detail
          </button>
        </div>
      </article>
      <div v-if="data.data.length === 0" class="tableEmpty">
        Ziadne kandidaty pre aktualny filter.
      </div>
    </div>

    <div class="pagerRow">
      <div class="pagerMeta">
        Strana {{ data.current_page }} / {{ data.last_page }} (spolu {{ data.total }})
      </div>

      <div class="pagerActions">
        <button
          type="button"
          :disabled="loading || data.current_page <= 1"
          class="toolbarButton toolbarButton--ghost"
          @click="emit('prev-page')"
        >
          Pred
        </button>
        <button
          type="button"
          :disabled="loading || data.current_page >= data.last_page"
          class="toolbarButton toolbarButton--ghost"
          @click="emit('next-page')"
        >
          Dalsia
        </button>
      </div>
    </div>
  </template>
</template>

<style scoped>
.candidatesTableWrap {
  margin-top: 10px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 11px;
  overflow: auto;
  background: rgb(var(--color-bg-rgb) / 0.98);
}

.candidatesTable {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
  table-layout: fixed;
}

.candidatesTable thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  text-align: left;
  padding: 7px 5px;
  font-size: 11px;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  background: rgb(var(--color-surface-rgb) / 0.06);
  border-bottom: 1px solid var(--divider-color);
}

.candidatesTable td {
  padding: 5px;
  border-top: 1px solid var(--divider-color);
  vertical-align: top;
  font-size: 12px;
}

.colId {
  width: 48px;
}

.colTitle {
  width: 36%;
}

.colSourceType {
  width: 23%;
}

.colState {
  width: 20%;
}

.colScore {
  width: 10%;
}

.colActions {
  width: 125px;
}

.candidatesRow:hover {
  background: rgb(var(--color-surface-rgb) / 0.025);
}

.candidatesMobileList {
  margin-top: 10px;
  display: none;
  gap: 8px;
}

.candidateMobileCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 8px;
  display: grid;
  gap: 6px;
}

.candidateMobileCard__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.candidateMobileCard__headBadges {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.candidateMobileCard__id {
  font-size: 12px;
  color: rgb(var(--color-text-secondary-rgb) / 0.86);
}

.candidateMobileCard__title,
.candidateTitle {
  font-weight: 700;
  line-height: 1.25;
  word-break: break-word;
}

.candidateTitle {
  max-width: min(450px, 100%);
}

.candidateShort {
  color: rgb(var(--color-text-secondary-rgb) / 0.85);
  font-size: 11px;
  margin-top: 3px;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cellInlineBadges {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.candidateMobileCard__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.cellMono {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.cellStack {
  display: grid;
  gap: 4px;
}

.cellDate {
  color: rgb(var(--color-text-secondary-rgb) / 0.92);
}

.chip,
.typeTag,
.matchedSourceTag,
.statusBadge,
.translationBadge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  font-size: 9px;
  line-height: 1.2;
}

.chip,
.typeTag {
  padding: 1px 6px;
}

.matchedSourceTag {
  padding: 1px 5px;
}

.chip {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.chip--source-astropixels {
  border-color: rgb(30 64 175 / 0.3);
  background: rgb(30 64 175 / 0.1);
}

.chip--source-imo {
  border-color: rgb(6 95 70 / 0.3);
  background: rgb(6 95 70 / 0.1);
}

.chip--source-nasa {
  border-color: rgb(107 33 168 / 0.3);
  background: rgb(107 33 168 / 0.1);
}

.chip--source-default {
  border-color: rgb(var(--color-surface-rgb) / 0.2);
}

.typeTag,
.matchedSourceTag {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  background: transparent;
}

.statusBadge,
.translationBadge {
  padding: 1px 7px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.statusBadge--pending {
  border-color: rgb(245 158 11 / 0.4);
  background: rgb(245 158 11 / 0.12);
}

.statusBadge--approved {
  border-color: rgb(22 163 74 / 0.36);
  background: rgb(22 163 74 / 0.12);
}

.statusBadge--rejected {
  border-color: rgb(239 68 68 / 0.36);
  background: rgb(239 68 68 / 0.12);
}

.statusBadge--neutral {
  border-color: rgb(var(--color-surface-rgb) / 0.2);
}

.translationBadge--success {
  border-color: rgb(22 163 74 / 0.36);
  background: rgb(22 163 74 / 0.12);
}

.translationBadge--error {
  border-color: rgb(239 68 68 / 0.36);
  background: rgb(239 68 68 / 0.12);
}

.translationBadge--pending {
  border-color: rgb(245 158 11 / 0.4);
  background: rgb(245 158 11 / 0.12);
}

.matchedSources {
  margin-top: 4px;
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.cellMuted {
  color: rgb(var(--color-text-secondary-rgb) / 0.8);
}

.rowActions {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-start;
}

.rowActionButton {
  min-height: 24px;
  padding: 3px 7px;
  border-radius: 7px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.18);
  background: transparent;
  color: inherit;
  font-size: 10px;
  cursor: pointer;
  transition: border-color var(--motion-fast), background-color var(--motion-fast), transform var(--motion-fast);
}

.rowActionButton:hover:not(:disabled) {
  transform: translateY(-1px);
}

.rowActionButton:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.rowActionButton--success {
  border-color: rgb(var(--color-success-rgb) / 0.35);
  background: rgb(var(--color-success-rgb) / 0.1);
}

.rowActionButton--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.35);
  background: rgb(var(--color-primary-rgb) / 0.12);
}

.rowActionButton--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.tableEmpty {
  padding: 18px 10px;
  color: rgb(var(--color-text-secondary-rgb) / 0.82);
  font-size: 13px;
}

.pagerRow {
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.pagerMeta {
  color: rgb(var(--color-text-secondary-rgb) / 0.9);
  font-size: 12px;
}

.pagerActions {
  display: flex;
  gap: 8px;
}

.toolbarButton {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font-size: 12px;
  padding: 7px 10px;
  cursor: pointer;
}

.toolbarButton:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.toolbarButton--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

@media (max-width: 900px) {
  .candidatesTableWrap {
    display: none;
  }

  .candidatesMobileList {
    display: grid;
  }

  .pagerMeta {
    width: 100%;
  }

  .pagerActions {
    width: 100%;
  }

  .pagerActions .toolbarButton {
    flex: 1 1 auto;
  }
}
</style>
