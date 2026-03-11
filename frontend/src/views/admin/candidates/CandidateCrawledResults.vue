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
  translationStatusStyle: {
    type: Function,
    required: true,
  },
  normalizeTranslationStatus: {
    type: Function,
    required: true,
  },
  sourceBadgeStyle: {
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
  statusBadgeStyle: {
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
</script>

<template>
  <template v-if="data && !loading">
    <div class="candidatesTableWrap">
      <table class="candidatesTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nazov</th>
            <th>Zdroj / typ</th>
            <th>Zaciatok / stav</th>
            <th v-if="showConfidenceColumn">Skore zdrojov</th>
            <th>Preklad</th>
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
                <span :style="sourceBadgeStyle(candidate.source_name)">{{ sourceLabel(candidate.source_name) }}</span>
                <span class="typeTag">{{ candidate.type || '-' }}</span>
              </div>
              <div class="matchedSources">
                <span
                  v-for="src in normalizeSources(candidate.matched_sources)"
                  :key="`matched-${candidate.id}-${src}`"
                  class="matchedSourceTag"
                >
                  {{ sourceLabel(src) }}
                </span>
                <span v-if="normalizeSources(candidate.matched_sources).length === 0" class="cellMuted">-</span>
              </div>
            </td>
            <td>
              <div class="cellStack">
                <span>{{ formatDate(candidate.start_at) }}</span>
                <span :style="statusBadgeStyle(candidate.status)">{{ candidate.status }}</span>
              </div>
            </td>
            <td v-if="showConfidenceColumn" class="cellMono">{{ formatConfidence(candidate.confidence_score) }}</td>
            <td>
              <span :style="translationStatusStyle(candidate.translation_status)">
                {{ normalizeTranslationStatus(candidate.translation_status) }}
              </span>
            </td>
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
            <td :colspan="showConfidenceColumn ? 7 : 6" class="tableEmpty">
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
          <span :style="translationStatusStyle(candidate.translation_status)">
            {{ normalizeTranslationStatus(candidate.translation_status) }}
          </span>
        </div>
        <div class="candidateMobileCard__title">{{ candidateDisplayTitle(candidate) }}</div>
        <div
          v-if="candidatePreviewShort(candidate) && candidatePreviewShort(candidate) !== '-'"
          class="candidateShort"
        >
          {{ candidatePreviewShort(candidate) }}
        </div>
        <div class="candidateMobileCard__meta">
          <span :style="sourceBadgeStyle(candidate.source_name)">{{ sourceLabel(candidate.source_name) }}</span>
          <span class="typeTag">{{ candidate.type || '-' }}</span>
          <span class="cellMuted">{{ formatDate(candidate.start_at) }}</span>
          <span v-if="showConfidenceColumn" class="cellMono">Skore {{ formatConfidence(candidate.confidence_score) }}</span>
        </div>
        <div class="matchedSources">
          <span
            v-for="src in normalizeSources(candidate.matched_sources)"
            :key="`matched-mobile-${candidate.id}-${src}`"
            class="matchedSourceTag"
          >
            {{ sourceLabel(src) }}
          </span>
          <span v-if="normalizeSources(candidate.matched_sources).length === 0" class="cellMuted">-</span>
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
  margin-top: 12px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 12px;
  overflow: auto;
  background: rgb(var(--color-bg-rgb) / 0.98);
}

.candidatesTable {
  width: 100%;
  min-width: 860px;
  border-collapse: collapse;
}

.candidatesTable thead {
  background: rgb(var(--color-surface-rgb) / 0.05);
}

.candidatesTable th {
  text-align: left;
  padding: 7px 6px;
  font-size: 12px;
  opacity: 0.85;
  background: rgb(var(--color-surface-rgb) / 0.04);
}

.candidatesTable td {
  padding: 7px 6px;
  border-top: 1px solid rgb(var(--color-surface-rgb) / 0.06);
  vertical-align: top;
}

.candidatesRow:hover {
  background: rgb(var(--color-surface-rgb) / 0.02);
}

.candidatesMobileList {
  margin-top: 10px;
  display: none;
  gap: 8px;
}

.candidateMobileCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.1);
  border-radius: 10px;
  background: rgb(var(--color-bg-rgb) / 0.96);
  padding: 9px;
  display: grid;
  gap: 7px;
}

.candidateMobileCard__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.candidateMobileCard__id {
  font-size: 12px;
  opacity: 0.8;
}

.candidateMobileCard__title {
  font-weight: 700;
  line-height: 1.25;
}

.candidateMobileCard__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.candidateTitle {
  font-weight: 600;
}

.candidateShort {
  opacity: 0.75;
  font-size: 12px;
  margin-top: 4px;
  line-height: 1.3;
}

.cellMono {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.cellStack {
  display: grid;
  gap: 4px;
}

.typeTag {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  background: rgb(var(--color-surface-rgb) / 0.08);
  font-size: 11px;
  width: fit-content;
}

.matchedSources {
  margin-top: 6px;
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.matchedSourceTag {
  display: inline-flex;
  align-items: center;
  padding: 1px 7px;
  border-radius: 999px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.14);
  background: transparent;
  font-size: 11px;
}

.cellMuted {
  opacity: 0.7;
}

.rowActions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.rowActionButton {
  padding: 5px 8px;
  border-radius: 8px;
  border: 1px solid rgb(var(--color-surface-rgb) / 0.16);
  background: transparent;
  color: inherit;
  font-size: 12px;
  cursor: pointer;
}

.rowActionButton--success {
  border-color: rgb(var(--color-success-rgb) / 0.24);
  background: rgb(var(--color-success-rgb) / 0.05);
}

.rowActionButton--primary {
  border-color: rgb(var(--color-primary-rgb) / 0.24);
  background: rgb(var(--color-primary-rgb) / 0.06);
}

.rowActionButton--ghost {
  background: rgb(var(--color-surface-rgb) / 0.08);
}

.tableEmpty {
  padding: 20px 10px;
  opacity: 0.75;
}

.pagerRow {
  margin-top: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.pagerMeta {
  opacity: 0.85;
  font-size: 14px;
}

.pagerActions {
  display: flex;
  gap: 10px;
}

.toolbarButton {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.2);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font-size: 12px;
  padding: 8px 10px;
  cursor: pointer;
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

  .pagerRow {
    gap: 8px;
  }

  .pagerMeta {
    width: 100%;
    font-size: 13px;
  }

  .pagerActions {
    width: 100%;
  }

  .pagerActions .toolbarButton {
    flex: 1 1 auto;
  }
}
</style>
