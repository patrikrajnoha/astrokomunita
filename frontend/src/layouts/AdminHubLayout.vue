<template>
  <section class="adminHub">
    <div class="adminHub__subNav adminHub__subNav--mobile">
      <AdminSubNav />
    </div>

    <RouterView />
  </section>
</template>

<script setup>
import { RouterView } from 'vue-router'
import AdminSubNav from '@/components/admin/AdminSubNav.vue'
</script>

<style scoped>
.adminHub {
  display: grid;
  gap: 10px;
  min-width: 0;
  container-type: inline-size;
}

.adminHub__subNav--mobile {
  display: none;
}





:deep(.adminPageShell :is(input:not([type='checkbox']):not([type='radio']), select, textarea)) {
  min-height: 34px;
  border-radius: var(--radius-sm);
  padding: 6px 10px;
  font-size: 13px;
}

:deep(.adminPageShell textarea) {
  min-height: 80px;
  line-height: 1.35;
}

:deep(.adminPageShell button) {
  min-height: 34px;
  font-size: 12px;
}

:deep(.adminPageShell .card),
:deep(.adminPageShell .panel) {
  border-radius: var(--radius-md);
}

:deep(.adminPageShell),
:deep(.adminPageShell__content),
:deep(.adminPageShell__content > *) {
  min-width: 0;
  max-width: 100%;
}

:deep(.tableWrap),
:deep(.tableContainer),
:deep(.adminTableWrap) {
  max-width: 100%;
  overflow-x: auto;
}

:deep(.table),
:deep(.activityTable),
:deep(.adminTable) {
  width: 100%;
}

:deep(.table th),
:deep(.table td),
:deep(.activityTable th),
:deep(.activityTable td),
:deep(.adminTable th),
:deep(.adminTable td) {
  overflow: hidden;
  text-overflow: ellipsis;
}

.adminHub > * {
  min-width: 0;
}

@container (max-width: 760px) {
  :deep(.filterRow),
  :deep(.formGrid),
  :deep(.advancedFiltersBody),
  :deep(.filtersAdvancedBody),
  :deep(.retentionMainRow),
  :deep(.toolbar) {
    grid-template-columns: 1fr !important;
    align-items: stretch;
  }

  :deep(.filterActions),
  :deep(.formActions),
  :deep(.createActions),
  :deep(.advancedActions),
  :deep(.paginationActions) {
    width: 100%;
    justify-content: stretch;
  }

  :deep(.filterActions .actionBtn),
  :deep(.filterActions .ghostBtn),
  :deep(.formActions .actionBtn),
  :deep(.formActions .ghostBtn),
  :deep(.createActions .actionBtn),
  :deep(.advancedActions .actionBtn),
  :deep(.advancedActions .runBtn),
  :deep(.advancedActions .ghostBtn),
  :deep(.advancedActions .dangerBtn),
  :deep(.paginationActions .ghostBtn) {
    flex: 1 1 auto;
    width: 100%;
    text-align: center;
  }

  :deep(.table) {
    min-width: 620px;
  }

  :deep(.activityTable) {
    min-width: 680px;
  }
}

@media (max-width: 900px) {
  .adminHub {
    gap: 10px;
  }
}

@media (min-width: 768px) and (max-width: 1279px) {
  .adminHub__subNav--mobile {
    display: block;
    position: sticky;
    top: calc(var(--navbar-height) + 2px);
    z-index: 8;
  }

  :deep(.adminSubNav) {
    padding: 10px;
    border-radius: var(--radius-md);
  }

  :deep(.adminSubNav__head) {
    display: none;
  }

  :deep(.adminSubNav__groups) {
    margin-top: 0;
    gap: 8px;
  }

  :deep(.adminSubNav__groupTitle) {
    display: none;
  }

  :deep(.adminSubNav__list) {
    gap: 6px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  :deep(.adminSubNav__item) {
    min-height: 36px;
    padding: 7px 9px;
    font-size: 13px;
  }

  :deep(.adminSubNav__itemRow) {
    gap: 6px;
  }

  :deep(.adminSubNav__collapseToggle) {
    width: 30px;
  }

  :deep(.adminSubNav__item--child) {
    margin-left: 0;
    font-size: 12px;
  }
}

@media (max-width: 767px) {
  .adminHub__subNav--mobile {
    display: none !important;
  }

  :deep(.adminPageShell) {
    padding: 14px 10px;
  }

  :deep(.adminPageShell__title) {
    font-size: 1.35rem;
  }

  :deep(.adminPageShell__subtitle) {
    font-size: 13px;
  }

  :deep(.adminToolbar) {
    padding: 10px;
    gap: 10px;
  }

  :deep(.adminToolbar__slot) {
    min-width: 0;
    width: 100%;
  }

  :deep(.adminToolbar input),
  :deep(.adminToolbar select),
  :deep(.adminToolbar button) {
    min-height: 40px;
  }

  :deep(.adminPagination) {
    display: grid;
    gap: 8px;
  }

  :deep(.adminPagination__controls) {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  :deep(.adminPagination__btn) {
    min-height: 40px;
  }

  :deep(.adminTableWrap) {
    border-radius: 10px;
  }

  :deep(.adminTable) {
    min-width: 640px;
  }

  :deep(.adminTable__head),
  :deep(.adminTable__cell) {
    padding: 10px;
  }
}
</style>


