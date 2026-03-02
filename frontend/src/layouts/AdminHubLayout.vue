<template>
  <section class="adminHub">
    <div class="adminHub__bg" aria-hidden="true"></div>

    <div class="adminHub__mainNav adminHub__mainNav--desktop">
      <div class="adminHub__sticky">
        <MainNavbar />
      </div>
    </div>

    <div class="adminHub__subNav adminHub__subNav--mobile">
      <AdminSubNav />
    </div>

    <div class="adminHub__center">
      <div class="adminHub__contentCard">
        <RouterView />
      </div>
    </div>

    <div class="adminHub__subNav adminHub__subNav--desktop">
      <div class="adminHub__sticky">
        <AdminSubNav />
      </div>
    </div>
  </section>
</template>

<script setup>
import { RouterView } from 'vue-router'
import AdminSubNav from '@/components/admin/AdminSubNav.vue'
import MainNavbar from '@/components/MainNavbar.vue'
</script>

<style scoped>
.adminHub {
  position: relative;
  display: grid;
  gap: 18px;
  isolation: isolate;
}

.adminHub__bg {
  position: absolute;
  inset: -10px -12px;
  z-index: -1;
  border-radius: 18px;
  background:
    radial-gradient(110% 80% at 0% 0%, rgb(var(--primary-rgb) / 0.12), transparent 58%),
    radial-gradient(90% 70% at 100% 10%, rgb(var(--text-secondary-rgb) / 0.08), transparent 64%);
  pointer-events: none;
}

.adminHub__center {
  min-width: 0;
}

.adminHub__mainNav {
  min-width: 0;
}

.adminHub__mainNav--desktop {
  display: none;
}

.adminHub__contentCard {
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--bg-surface);
  box-shadow: 0 20px 38px rgb(var(--bg-app-rgb) / 0.22);
  backdrop-filter: blur(6px);
  min-width: 0;
  overflow: hidden;
}

.adminHub__subNav {
  min-width: 0;
}

.adminHub__subNav--desktop {
  display: none;
}

.adminHub__sticky {
  position: sticky;
  top: 92px;
}

@media (min-width: 901px) {
  .adminHub {
    grid-template-columns: 250px minmax(0, 1fr) 320px;
    align-items: start;
  }

  .adminHub__subNav--mobile {
    display: none;
  }

  .adminHub__subNav--desktop {
    display: block;
  }

  .adminHub__mainNav--desktop {
    display: block;
  }
}

@media (max-width: 900px) {
  .adminHub {
    gap: 14px;
  }

  .adminHub__contentCard {
    border-radius: 12px;
  }
}

@media (max-width: 767px) {
  .adminHub {
    gap: 10px;
  }

  .adminHub__bg {
    inset: -4px -6px;
    border-radius: 14px;
  }

  .adminHub__subNav--mobile {
    position: sticky;
    top: 58px;
    z-index: 8;
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
