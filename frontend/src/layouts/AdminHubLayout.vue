<template>
  <section class="adminHub">
    <div class="adminHub__bg" aria-hidden="true"></div>

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
    radial-gradient(110% 80% at 0% 0%, rgb(var(--color-primary-rgb) / 0.12), transparent 58%),
    radial-gradient(90% 70% at 100% 10%, rgb(var(--color-surface-rgb) / 0.08), transparent 64%);
  pointer-events: none;
}

.adminHub__center {
  min-width: 0;
}

.adminHub__contentCard {
  border: 1px solid rgb(var(--color-surface-rgb) / 0.12);
  border-radius: 14px;
  background: rgb(var(--color-bg-rgb) / 0.72);
  box-shadow: 0 20px 38px rgb(0 0 0 / 0.2);
  backdrop-filter: blur(6px);
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
    grid-template-columns: minmax(0, 1fr) 320px;
    align-items: start;
  }

  .adminHub__subNav--mobile {
    display: none;
  }

  .adminHub__subNav--desktop {
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
</style>
