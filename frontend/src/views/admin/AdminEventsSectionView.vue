<script setup>
import { RouterView } from 'vue-router'
import AdminSectionTabs from '@/components/admin/AdminSectionTabs.vue'
</script>

<template>
  <section class="adminSectionView adminSectionView--eventsMinimal bg-app text-white">
    <AdminSectionTabs section="events" />
    <RouterView />
  </section>
</template>

<style>
@reference "../../assets/main.css";

.adminSectionView {
  display: grid;
  gap: 10px;
  min-width: 0;
}

.adminSectionView--eventsMinimal {
  @apply bg-app text-white;
}

.adminSectionView--eventsMinimal :is(
  .adminPageShell__header,
  .adminSectionTabs,
  .panel,
  .card,
  .translationQueueBar,
  .statusTabs,
  .actionToolbar,
  .filterPanel,
  .toolbarDetails__menu,
  .tableWrap,
  .candidatesTableWrap,
  .manualTableWrap,
  .eventActionsMenu__dropdown,
  .candidateMobileCard,
  .eventMobileCard,
  .sourceMobileCard,
  .runMobileCard,
  .manualMobileCard,
  .manualFormPanel,
  .candidateDetailModalItem,
  .candidateDetailOriginal,
  .candidateManualEdit,
  .timeContextBar,
  .timeContextBar__item,
  .publishProgress,
  .progressPanel,
  .maintenanceBox,
  .aiPreviewCard,
  .filterMore__panel
) {
  @apply !bg-app !border-0 !shadow-none;
}

.adminSectionView--eventsMinimal :is(
  h1,
  h2,
  h3,
  h4,
  h5,
  h6,
  .pageTitle,
  .headerPanel__title,
  .adminPageShell__title,
  .cardHead h2,
  .manualFormTitle,
  .candidateTitle,
  .title,
  .sourceName
) {
  @apply !text-white;
}

.adminSectionView--eventsMinimal :is(
  .muted,
  .adminPageShell__subtitle,
  .meta span,
  .sub,
  .candidateMeta,
  .modeCounts,
  .pagerMeta,
  .translationQueueBar__summary,
  .manualFormHint,
  .cellMuted,
  .inlineLoading,
  .aiHint,
  .aiNotice,
  .progressPanel__label,
  .progressPanel__detail,
  .progressPanel__meta,
  .field > span,
  .fieldLabel span,
  .candidateDetailModalItem span,
  .candidateDetailOriginal__label,
  .tableEmpty
) {
  @apply !text-muted;
}

.adminSectionView--eventsMinimal .adminSectionTabs {
  @apply !rounded-2xl !p-1.5;
}

.adminSectionView--eventsMinimal .adminSectionTabs__tab {
  @apply !border-0 !shadow-none !rounded-full !bg-secondary-btn !text-muted !font-medium;
}

.adminSectionView--eventsMinimal .adminSectionTabs__tab:hover {
  @apply !bg-hover !text-white;
}

.adminSectionView--eventsMinimal .adminSectionTabs__tab.active {
  @apply !bg-vivid !text-white;
}

.adminSectionView--eventsMinimal :is(
  .btn,
  .toolbarButton,
  .primaryBtn,
  .ghostBtn,
  .rowActionButton,
  .quickChip,
  .translationQueueBar__cancelBtn,
  .aiUndoBtn,
  .eventActionsMenu__trigger,
  .eventActionsMenu__item,
  .btn-danger
) {
  @apply !border-0 !shadow-none !rounded-full !font-medium;
}

.adminSectionView--eventsMinimal :is(
  .btn,
  .btn.ghost,
  .btn--ghost,
  .toolbarButton,
  .toolbarButton--ghost,
  .toolbarButton--tab,
  .ghostBtn,
  .rowActionButton,
  .rowActionButton--ghost,
  .quickChip,
  .aiUndoBtn,
  .eventActionsMenu__trigger,
  .eventActionsMenu__item
) {
  @apply !bg-secondary-btn !text-muted;
}

.adminSectionView--eventsMinimal :is(
  .btn,
  .btn.ghost,
  .btn--ghost,
  .toolbarButton,
  .toolbarButton--ghost,
  .toolbarButton--tab,
  .ghostBtn,
  .rowActionButton,
  .rowActionButton--ghost,
  .quickChip,
  .aiUndoBtn,
  .eventActionsMenu__trigger,
  .eventActionsMenu__item
):hover:not(:disabled) {
  @apply !bg-hover !text-white;
}

.adminSectionView--eventsMinimal :is(
  .primaryBtn,
  .btn.primary,
  .btn--primary,
  .toolbarButton--primary,
  .toolbarButton--success,
  .rowActionButton--primary,
  .quickChip--primary
) {
  @apply !bg-vivid !text-white;
}

.adminSectionView--eventsMinimal :is(
  .primaryBtn,
  .btn.primary,
  .btn--primary,
  .toolbarButton--primary,
  .toolbarButton--success,
  .rowActionButton--primary,
  .quickChip--primary
):hover:not(:disabled) {
  background-color: #0d65e6 !important;
}

.adminSectionView--eventsMinimal :is(
  .btn-danger,
  .btn--danger,
  .btn--dangerSoft,
  .translationQueueBar__cancelBtn,
  .eventActionsMenu__item--danger
) {
  @apply !bg-danger !text-white;
}

.adminSectionView--eventsMinimal :is(
  .btn-danger,
  .btn--danger,
  .btn--dangerSoft,
  .translationQueueBar__cancelBtn,
  .eventActionsMenu__item--danger
):hover:not(:disabled) {
  background-color: #d61f48 !important;
}

.adminSectionView--eventsMinimal :is(
  .btn,
  .toolbarButton,
  .primaryBtn,
  .ghostBtn,
  .rowActionButton,
  .quickChip,
  .translationQueueBar__cancelBtn,
  .aiUndoBtn,
  .eventActionsMenu__trigger,
  .eventActionsMenu__item,
  .btn-danger
):disabled {
  @apply opacity-50 cursor-not-allowed;
}

.adminSectionView--eventsMinimal :is(
  input:not([type='checkbox']):not([type='radio']),
  select,
  textarea
) {
  @apply !bg-secondary-btn !text-white !border-0 !rounded-2xl !shadow-none;
}

.adminSectionView--eventsMinimal :is(
  input:not([type='checkbox']):not([type='radio']),
  select,
  textarea
):focus-visible {
  outline: 2px solid rgb(15 115 255 / 0.35);
  outline-offset: 1px;
}

.adminSectionView--eventsMinimal :is(input[type='checkbox'], input[type='radio']) {
  accent-color: #0f73ff;
}

.adminSectionView--eventsMinimal input::placeholder,
.adminSectionView--eventsMinimal textarea::placeholder {
  color: #abb8c9;
  opacity: 0.82;
}

.adminSectionView--eventsMinimal :is(
  .table tbody tr:hover,
  .compactTable tbody tr:hover,
  .candidatesRow:hover,
  .manualTable tbody tr:hover,
  .candidateMobileCard:hover,
  .eventMobileCard:hover,
  .sourceMobileCard:hover,
  .runMobileCard:hover,
  .manualMobileCard:hover
) {
  @apply !bg-hover;
}

.adminSectionView--eventsMinimal :is(
  .selectionBar,
  .runFilterChip,
  .statusTab--active,
  .statusTab__count
) {
  @apply !bg-vivid/15 !text-vivid !border-0;
}

.adminSectionView--eventsMinimal :is(
  .progressBar__fill,
  .publishProgress__bar,
  .translationQueueBar__fill,
  .candidateDetailProgress__bar
) {
  background: #0f73ff !important;
}

.adminSectionView--eventsMinimal :is(
  .translationQueueBar__percent,
  .selectionBar__count,
  .progressPanel--crawl .progressPanel__percent
) {
  color: #0f73ff !important;
}

.adminSectionView--eventsMinimal :is(
  .table th,
  .table td,
  .compactTable th,
  .compactTable td,
  .manualTable th,
  .manualTable td,
  .candidatesTable thead th,
  .candidatesTable td
) {
  border-color: rgb(28 39 54 / 0.9) !important;
}
</style>
