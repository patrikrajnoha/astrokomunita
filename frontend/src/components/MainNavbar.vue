<template>
  <nav
    class="mainNavbar flex h-full min-h-0 flex-col px-3 py-3.5"
    aria-label="Hlavná navigácia"
  >
    <RouterLink
      v-if="showBrandLogo"
      to="/"
      class="inline-flex items-center rounded-xl px-1.5 py-1.5 text-sm font-semibold text-[var(--color-surface)] transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
      title="Domov"
      aria-label="Domov"
    >
      <img src="/logo.png" alt="Astrokomunita" class="h-9 w-auto max-w-[12.5rem] object-contain" />
    </RouterLink>

    <!-- Main Navigation -->
    <div class="navScroll mt-3 flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto overflow-x-hidden">
      <RouterLink
        v-for="item in primaryLinks"
        :key="item.key || item.to"
        :to="item.to"
        custom
        v-slot="{ href, navigate, isActive, isExactActive }"
      >
        <div v-if="item.isMore" ref="moreWrapperRef" class="relative mt-3 border-t border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pt-3">
          <button
            type="button"
            class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[0.8125rem] font-semibold !text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] transition-all duration-200 ease-out hover:!text-[var(--color-surface)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.55)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
            :class="isMoreActive
              ? `bg-[color:rgb(var(--color-bg-rgb)/0.65)] !text-[var(--color-surface)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.3)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
              : ''"
            :aria-expanded="isMoreOpen ? 'true' : 'false'"
            aria-controls="more-menu"
            aria-label="Ďalšie"
            title="Ďalšie"
            @click="toggleMore"
          >
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
              aria-hidden="true"
            >
              {{ item.icon }}
            </span>
            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
            <span class="shrink-0 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">?</span>
          </button>

          <div
            v-if="isMoreOpen"
            id="more-menu"
            class="absolute left-0 top-full z-50 mt-2 w-60 max-h-[60vh] overflow-x-hidden overflow-y-auto rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.92)] p-2 backdrop-blur-md ring-1 ring-[color:rgb(var(--color-text-secondary-rgb)/0.18)] shadow-[0_18px_55px_rgb(0_0_0/0.55)]"
            role="menu"
            aria-label="Ďalšie možnosti"
          >
            <RouterLink
              v-if="auth.isAuthed"
              to="/settings"
              custom
              v-slot="{ href: moreHref, navigate: moreNavigate, isActive: isMoreItemActive }"
            >
              <a
                :href="moreHref"
                @click="() => { closeMore(); moreNavigate(); }"
                class="group relative flex w-full min-w-0 items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                :class="isMoreItemActive
                  ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                  : ''"
                role="menuitem"
                aria-label="Nastavenia"
              >
                <span
                  class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                  aria-hidden="true"
                >
                  S
                </span>
                <span class="min-w-0 flex-1 truncate">Nastavenia</span>
              </a>
            </RouterLink>

            <RouterLink
              v-if="isWipEnabled"
              to="/creator-studio"
              custom
              v-slot="{ href: moreHref, navigate: moreNavigate, isActive: isMoreItemActive }"
            >
              <a
                :href="moreHref"
                @click="() => { closeMore(); moreNavigate(); }"
                class="group relative mt-1 flex w-full min-w-0 items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                :class="isMoreItemActive
                  ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                  : ''"
                role="menuitem"
                aria-label="Štúdio tvorcu"
              >
                <span
                  class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                  aria-hidden="true"
                >
                  C
                </span>
                <span class="min-w-0 flex-1 truncate">Štúdio tvorcu</span>
              </a>
            </RouterLink>
          </div>
        </div>
        <div v-else-if="item.isAdmin" ref="adminWrapperRef" class="relative mt-3 border-t border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pt-3">
          <button
            type="button"
            class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[0.8125rem] font-semibold !text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] transition-all duration-200 ease-out hover:!text-[var(--color-surface)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.55)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
            :class="isAdminActive
              ? `bg-[color:rgb(var(--color-bg-rgb)/0.65)] !text-[var(--color-surface)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.3)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
              : ''"
            :aria-expanded="isAdminOpen ? 'true' : 'false'"
            aria-controls="admin-menu"
            aria-label="Admin"
            title="Admin"
            @click="toggleAdmin"
          >
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
              aria-hidden="true"
            >
              {{ item.icon }}
            </span>
            <span class="flex-1">{{ item.label }}</span>
            <span class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">?</span>
          </button>

          <div
            v-if="isAdminOpen"
            id="admin-menu"
            class="absolute left-0 top-full z-50 mt-2 w-60 rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.92)] p-2 backdrop-blur-md ring-1 ring-[color:rgb(var(--color-text-secondary-rgb)/0.18)] shadow-[0_18px_55px_rgb(0_0_0/0.55)]"
            role="menu"
            aria-label="Možnosti administrácie"
          >
            <!-- Admin Group 1: Správa -->
            <div class="mb-2 border-b border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pb-2">
              <div class="px-3 py-1 text-xs font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.7)]">
                Správa
              </div>
              <RouterLink
                :to="{ name: 'admin.dashboard' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Prehľad"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    D
                  </span>
                  <span class="flex-1">Prehľad</span>
                </a>
              </RouterLink>

              <RouterLink
                :to="{ name: 'admin.users' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Používatelia"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    U
                  </span>
                  <span class="flex-1">Používatelia</span>
                </a>
              </RouterLink>

              <RouterLink
                :to="{ name: 'admin.event-candidates' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Kandidáti"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    C
                  </span>
                  <span class="flex-1">Kandidáti</span>
                </a>
              </RouterLink>

              <RouterLink
                :to="{ name: 'admin.moderation' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Moderácia"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    M
                  </span>
                  <span class="flex-1">Moderácia</span>
                </a>
              </RouterLink>

              <RouterLink
                v-if="isWipEnabled"
                :to="{ name: 'admin.banned-words' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Zakázané slová"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    W
                  </span>
                  <span class="flex-1">Zakázané slová</span>
                </a>
              </RouterLink>
            </div>

            <!-- Admin Group 2: Obsah a konfigurácia -->
            <div>
              <div class="px-3 py-1 text-xs font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.7)]">
                Obsah a konfigurácia
              </div>
              <RouterLink
                :to="{ name: 'admin.events' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Udalosti"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    E
                  </span>
                  <span class="flex-1">Udalosti</span>
                </a>
              </RouterLink>

              <RouterLink
                :to="{ name: 'admin.blog' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Články"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    B
                  </span>
                  <span class="flex-1">Články</span>
                </a>
              </RouterLink>

              <RouterLink
                :to="{ name: 'admin.sidebar' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Bočný panel"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    S
                  </span>
                  <span class="flex-1">Bočný panel</span>
                </a>
              </RouterLink>

              <RouterLink
                :to="{ name: 'admin.bots' }"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
                    : ''"
                  role="menuitem"
                  aria-label="Správa botov"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    B
                  </span>
                  <span class="flex-1">Správa botov</span>
                </a>
              </RouterLink>

            </div>
          </div>
        </div>
        <a
          v-else
          :href="href"
          @click="navigate"
          :title="item.title || item.label"
          :aria-label="item.label"
          :data-testid="item.key === 'notifications' ? 'notifications-trigger' : null"
          :data-active="isPrimaryLinkActive(item, isActive, isExactActive) ? 'true' : 'false'"
          :class="[
            'navItem group focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
            isPrimaryLinkActive(item, isActive, isExactActive)
              ? `active before:content-[''] before:absolute before:-left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-primary)]`
              : 'text-[var(--color-surface)]',
          ]"
        >
          <span class="navIconChip" aria-hidden="true">
            <component
              v-if="item.iconOutline && item.iconFilled"
              :is="isPrimaryLinkActive(item, isActive, isExactActive) ? item.iconFilled : item.iconOutline"
            />
            <span v-else>{{ item.icon }}</span>
          </span>
          <span class="navLabel flex-1">{{ item.label }}</span>
          <span
            v-if="item.badge"
            class="notificationBadge rounded-full px-2 py-0.5 text-[0.65rem] font-semibold"
            :class="{ 'notificationBadge--ping': item.key === 'notifications' && shouldAnimateUnreadBadge }"
          >
            {{ item.badge }}
          </span>
        </a>
      </RouterLink>

      <div v-if="!auth.isAuthed" class="guestAuthActions mt-2 shrink-0" data-testid="guest-auth-actions">
        <RouterLink
          to="/register"
          class="ui-pill ui-pill--primary guestAuthActions__btn guestAuthActions__btn--register"
          aria-label="Vytvorit ucet"
        >
          Vytvorit ucet
        </RouterLink>
        <RouterLink
          to="/login"
          class="ui-pill ui-pill--secondary guestAuthActions__btn"
          aria-label="Prihlasit sa"
        >
          Prihlasit sa
        </RouterLink>
      </div>
    </div>
    <div v-if="auth.isAuthed" ref="createPickerWrapperRef" class="relative mt-4 shrink-0">
      <button
        type="button"
        class="createTrigger"
        aria-label="Nový obsah"
        aria-haspopup="menu"
        :aria-expanded="isCreatePickerOpen ? 'true' : 'false'"
        aria-controls="create-content-menu"
        data-testid="create-content-trigger"
        @click="toggleCreatePicker"
      >
        <svg
          class="h-5 w-5"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.8"
            d="M16.862 4.487a2.1 2.1 0 1 1 2.971 2.971L8.25 19.04 4 20l.959-4.25 11.903-11.263zM19.5 14.25v4.125A1.625 1.625 0 0 1 17.875 20H5.625A1.625 1.625 0 0 1 4 18.375V6.125A1.625 1.625 0 0 1 5.625 4.5H9.75"
          />
        </svg>
        <span class="navLabel">Nový obsah</span>
      </button>

      <div
        v-if="isCreatePickerOpen"
        id="create-content-menu"
        class="createPickerMenu"
        role="menu"
        aria-label="Nový obsah"
      >
        <button
          type="button"
          class="createPickerItem"
          role="menuitem"
          data-create-type="post"
          @click="selectCreateType('post')"
        >
          <span class="createPickerIcon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
          </span>
          <span>Príspevok</span>
        </button>

        <button
          type="button"
          class="createPickerItem"
          role="menuitem"
          data-create-type="observation"
          @click="selectCreateType('observation')"
        >
          <span class="createPickerIcon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M7.5 4.75h5l4 4v10.5a1 1 0 0 1-1 1h-8a1 1 0 0 1-1-1v-13.5a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
              <path d="M12.5 4.75v4h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9.5 12h5M12 9.5v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
          </span>
          <span>Pozorovanie</span>
        </button>

        <button
          type="button"
          class="createPickerItem"
          role="menuitem"
          data-create-type="poll"
          @click="selectCreateType('poll')"
        >
          <span class="createPickerIcon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M5 7h14M5 12h14M5 17h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              <circle cx="8" cy="7" r="1.2" fill="currentColor"/>
              <circle cx="16" cy="12" r="1.2" fill="currentColor"/>
              <circle cx="11" cy="17" r="1.2" fill="currentColor"/>
            </svg>
          </span>
          <span>Anketa</span>
        </button>

        <button
          type="button"
          class="createPickerItem"
          role="menuitem"
          data-create-type="event"
          @click="selectCreateType('event')"
        >
          <span class="createPickerIcon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <rect x="3.5" y="5" width="17" height="15" rx="2.5" stroke="currentColor" stroke-width="1.7"/>
              <path d="M8 3.5v3M16 3.5v3M3.5 9.5h17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
          </span>
          <span>Udalosť</span>
        </button>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { computed, defineComponent, h, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { useBadgeAnimateOnIncrease } from '@/composables/useBadgeAnimateOnIncrease'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const route = useRoute()
const router = useRouter()
const { showBrandLogo } = defineProps({
  showBrandLogo: {
    type: Boolean,
    default: true,
  },
})
const isWipEnabled = String(import.meta.env.VITE_FEATURE_WIP || 'false').toLowerCase() === 'true'
const isMoreOpen = ref(false)
const moreWrapperRef = ref(null)
const isAdminOpen = ref(false)
const adminWrapperRef = ref(null)
const isNotificationsOpen = ref(false)
const isCreatePickerOpen = ref(false)
const createPickerWrapperRef = ref(null)
const unreadCount = computed(() => Number(notifications.unreadCount || 0))
const unreadCountHydrated = computed(() => Boolean(notifications.unreadCountHydrated))
const { shouldAnimate: shouldAnimateUnreadBadge } = useBadgeAnimateOnIncrease(unreadCount, {
  readyRef: unreadCountHydrated,
})

const isMoreActive = computed(() => {
  return route.path.startsWith('/settings') || (isWipEnabled && route.path === '/creator-studio')
})

const isAdminActive = computed(() => {
  return route.path.startsWith('/admin/')
})

const createNavIconComponent = (paths, filled = false) =>
  defineComponent({
    name: filled ? 'NavFilledIcon' : 'NavOutlineIcon',
    render() {
      return h(
        'svg',
        {
          class: ['navIcon', filled ? 'navIcon--filled' : 'navIcon--outline'],
          width: '20',
          height: '20',
          viewBox: '0 0 24 24',
          fill: filled ? 'currentColor' : 'none',
          stroke: filled ? 'none' : 'currentColor',
          'stroke-width': filled ? undefined : '1.9',
          'fill-rule': filled ? 'evenodd' : undefined,
          'clip-rule': filled ? 'evenodd' : undefined,
          'stroke-linecap': 'round',
          'stroke-linejoin': 'round',
          'aria-hidden': 'true',
        },
        paths.map((path, index) => h('path', { key: `path-${index}`, d: path })),
      )
    },
  })

const homeSaturnOutlinePaths = [
  'M12 7.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Z',
  'M3.25 12.05c0-1.9 3.92-3.45 8.75-3.45s8.75 1.55 8.75 3.45-3.92 3.45-8.75 3.45-8.75-1.55-8.75-3.45Z',
]

const homeSaturnFilledPaths = [
  'M12 7.35a4.65 4.65 0 1 0 0 9.3 4.65 4.65 0 0 0 0-9.3Z',
  'M12 8.2c-4.95 0-9 1.57-9 3.5s4.05 3.5 9 3.5 9-1.57 9-3.5-4.05-3.5-9-3.5Zm0 1.55c4.22 0 7.45 1.24 7.45 1.95s-3.23 1.95-7.45 1.95-7.45-1.24-7.45-1.95 3.23-1.95 7.45-1.95Z',
]

const navIcons = {
  home: {
    outline: createNavIconComponent(homeSaturnOutlinePaths),
    filled: createNavIconComponent(homeSaturnFilledPaths, true),
  },
  search: {
    outline: createNavIconComponent(['M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z', 'm20 20-3.5-3.5']),
    filled: createNavIconComponent(
      ['M10.75 3.5a7.25 7.25 0 1 0 4.61 12.85l4.12 4.12a1 1 0 1 0 1.42-1.42l-4.12-4.12A7.25 7.25 0 0 0 10.75 3.5Z'],
      true,
    ),
  },
  notifications: {
    outline: createNavIconComponent([
      'M6.5 8a5.5 5.5 0 1 1 11 0c0 2.6.7 4.4 1.8 5.8.5.6.1 1.2-.7 1.2H5.4c-.8 0-1.2-.7-.7-1.2C5.8 12.4 6.5 10.6 6.5 8Z',
      'M9.5 18a2.5 2.5 0 0 0 5 0',
    ]),
    filled: createNavIconComponent(
      [
        'M12 3a5.75 5.75 0 0 0-5.75 5.75c0 2.42-.65 4.02-1.61 5.28-.53.69-.02 1.72.88 1.72h13c.9 0 1.4-1.03.88-1.72-.96-1.26-1.61-2.86-1.61-5.28A5.75 5.75 0 0 0 12 3Z',
        'M9.55 17.1a2.45 2.45 0 0 0 4.9 0h-4.9Z',
      ],
      true,
    ),
  },
  events: {
    outline: createNavIconComponent([
      'M7 3.75v2.5',
      'M17 3.75v2.5',
      'M4.75 9.25h14.5',
      'M6 5.75h12A1.25 1.25 0 0 1 19.25 7v11A1.25 1.25 0 0 1 18 19.25H6A1.25 1.25 0 0 1 4.75 18V7A1.25 1.25 0 0 1 6 5.75Z',
      'M8.25 13h3.5',
      'M8.25 16h6.5',
    ]),
    filled: createNavIconComponent(
      [
        'M7.75 3.5a1 1 0 0 1 1 1v1.25h6.5V4.5a1 1 0 1 1 2 0v1.3A2.25 2.25 0 0 1 19.5 8v10.25A2.25 2.25 0 0 1 17.25 20.5H6.75A2.25 2.25 0 0 1 4.5 18.25V8A2.25 2.25 0 0 1 6.75 5.8V4.5a1 1 0 0 1 1-1Z',
        'M4.5 10.25h15v-.5a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 9.75v.5Z',
      ],
      true,
    ),
  },
  learn: {
    outline: createNavIconComponent([
      'M6 5.75h12A1.25 1.25 0 0 1 19.25 7v10A1.25 1.25 0 0 1 18 18.25H6A1.25 1.25 0 0 1 4.75 17V7A1.25 1.25 0 0 1 6 5.75Z',
      'M8 9h8',
      'M8 12h8',
      'M8 15h5',
    ]),
    filled: createNavIconComponent(
      ['M6.75 4.75A2.25 2.25 0 0 0 4.5 7v10A2.25 2.25 0 0 0 6.75 19.25h10.5A2.25 2.25 0 0 0 19.5 17V7a2.25 2.25 0 0 0-2.25-2.25H6.75Z'],
      true,
    ),
  },
  admin: {
    outline: createNavIconComponent([
      'M4 18.25h16',
      'M5.1 18.25 6.35 9.5l4.05 2.95L12 7.5l1.6 4.95 4.05-2.95 1.25 8.75',
    ]),
    filled: createNavIconComponent([
      'M4 18.75h16l-1.25-9.2-4.15 3.05L12 7.35 9.4 12.6 5.25 9.55 4 18.75Z',
    ], true),
  },
  user: {
    outline: createNavIconComponent([
      'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
      'M4.75 20a7.25 7.25 0 0 1 14.5 0',
    ]),
    filled: createNavIconComponent(
      [
        'M12 3.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Z',
        'M12 13.75c-4.2 0-7.75 2.7-8.55 6.4-.1.47.27.9.75.9h15.6c.48 0 .85-.43.75-.9-.8-3.7-4.35-6.4-8.55-6.4Z',
      ],
      true,
    ),
  },
  settings: {
    outline: createNavIconComponent([
      'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.757.426 1.757 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.757-2.924 1.757-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.757-.426-1.757-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.607 2.296.07 2.572-1.065Z',
      'M12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z',
    ]),
    filled: createNavIconComponent(
      [
        'M10.483 1.904a1.875 1.875 0 0 1 1.5 0 1.875 1.875 0 0 1 1.734 1.113l.332.744a1.875 1.875 0 0 0 2.28 1.018l.781-.23a1.875 1.875 0 0 1 2.188.918l.75 1.299a1.875 1.875 0 0 1-.454 2.358l-.578.5a1.875 1.875 0 0 0 0 2.752l.578.5a1.875 1.875 0 0 1 .454 2.358l-.75 1.3a1.875 1.875 0 0 1-2.188.917l-.78-.23a1.875 1.875 0 0 0-2.281 1.018l-.332.744a1.875 1.875 0 0 1-1.734 1.113h-1.5a1.875 1.875 0 0 1-1.734-1.113l-.332-.744a1.875 1.875 0 0 0-2.28-1.018l-.781.23a1.875 1.875 0 0 1-2.188-.918l-.75-1.299a1.875 1.875 0 0 1 .454-2.358l.578-.5a1.875 1.875 0 0 0 0-2.752l-.578-.5a1.875 1.875 0 0 1-.454-2.358l.75-1.3a1.875 1.875 0 0 1 2.188-.917l.78.23a1.875 1.875 0 0 0 2.281-1.018l.332-.744A1.875 1.875 0 0 1 10.483 1.904Z',
        'M12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z',
      ],
      true,
    ),
  },
  creatorStudio: {
    outline: createNavIconComponent([
      'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z',
      'M18.5 3.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z',
    ]),
    filled: createNavIconComponent(
      [
        'M12 2.75 14.1 8.3l5.55 2.1-5.55 2.1L12 18.05l-2.1-5.55-5.55-2.1 5.55-2.1L12 2.75Z',
        'M18.5 2.9l.78 2 .02.03 2 .78-2 .78-.8 2.02-.78-2.02-2-.78 2-.78z',
      ],
      true,
    ),
  },
}

const primaryLinks = computed(() => {
  const links = [
    {
      key: 'home',
      to: '/',
      label: 'Domov',
      icon: 'D',
      iconOutline: navIcons.home.outline,
      iconFilled: navIcons.home.filled,
    },
    {
      key: 'search',
      to: '/search',
      label: 'Preskúmať',
      icon: 'P',
      iconOutline: navIcons.search.outline,
      iconFilled: navIcons.search.filled,
      matchPrefix: '/search',
    },
    {
      key: 'events',
      to: '/events',
      label: 'Udalosti',
      icon: 'U',
      iconOutline: navIcons.events.outline,
      iconFilled: navIcons.events.filled,
      matchPrefix: '/events',
    },
    {
      key: 'learn',
      to: '/clanky',
      label: 'Články',
      icon: 'V',
      iconOutline: navIcons.learn.outline,
      iconFilled: navIcons.learn.filled,
      matchPrefix: '/clanky',
    },
  ]
  if (auth.isAuthed) {
    links.splice(2, 0, {
      key: 'notifications',
      to: '/notifications',
      label: 'Notifikácie',
      icon: 'U',
      iconOutline: navIcons.notifications.outline,
      iconFilled: navIcons.notifications.filled,
      matchPrefix: '/notifications',
      badge: notifications.unreadBadge,
    })
  }

  if (auth.user) {
    links.push({
      key: 'profile',
      to: '/profile',
      label: 'Profil',
      icon: 'P',
      iconOutline: navIcons.user.outline,
      iconFilled: navIcons.user.filled,
      matchPrefix: '/profile',
    })
  }

  if (auth.isAuthed) {
    links.push({
      key: 'settings',
      to: '/settings',
      label: 'Nastavenia',
      icon: 'S',
      iconOutline: navIcons.settings.outline,
      iconFilled: navIcons.settings.filled,
      matchPrefix: '/settings',
    })
  }

  if (auth.isAdmin || auth.isEditor) {
    links.push({
      key: 'admin',
      to: auth.isAdmin ? { name: 'admin.dashboard' } : { name: 'admin.blog' },
      label: auth.isAdmin ? 'Admin' : 'Editor',
      icon: 'A',
      iconOutline: navIcons.admin.outline,
      iconFilled: navIcons.admin.filled,
      matchPrefix: '/admin',
    })
  }

  if (isWipEnabled) {
    links.push({
      key: 'creator-studio',
      to: '/creator-studio',
      label: 'Štúdio tvorcu',
      icon: 'C',
      iconOutline: navIcons.creatorStudio.outline,
      iconFilled: navIcons.creatorStudio.filled,
      matchPrefix: '/creator-studio',
    })
  }

  return links
})

const isPrimaryLinkActive = (item, isActive, isExactActive) => {
  if (!item) return false
  const targetPath = typeof item.to === 'string' ? item.to : item.to?.path

  // Domov je aktívny iba na koreňovej route.
  if (targetPath === '/') {
    return Boolean(isExactActive)
  }

  if (item.matchPrefix) {
    return route.path.startsWith(item.matchPrefix)
  }

  return Boolean(isActive)
}

const toggleMore = () => {
  closeNotifications()
  closeCreatePicker()
  isMoreOpen.value = !isMoreOpen.value
}

const closeMore = () => {
  isMoreOpen.value = false
}

const toggleAdmin = () => {
  closeNotifications()
  closeCreatePicker()
  isAdminOpen.value = !isAdminOpen.value
}

const closeAdmin = () => {
  isAdminOpen.value = false
}

const closeNotifications = () => {
  isNotificationsOpen.value = false
}

const firstElementRef = (value) => {
  if (Array.isArray(value)) return value[0] || null
  return value || null
}

const handleClickOutside = (event) => {
  const target = event.target
  const wrapper = firstElementRef(moreWrapperRef.value)
  const adminWrapper = firstElementRef(adminWrapperRef.value)
  const createPickerWrapper = firstElementRef(createPickerWrapperRef.value)

  if (isMoreOpen.value && wrapper instanceof Element && target instanceof Node && !wrapper.contains(target)) {
    closeMore()
  }
  if (isAdminOpen.value && adminWrapper instanceof Element && target instanceof Node && !adminWrapper.contains(target)) {
    closeAdmin()
  }
  if (isCreatePickerOpen.value && createPickerWrapper instanceof Element && target instanceof Node && !createPickerWrapper.contains(target)) {
    closeCreatePicker()
  }
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    if (isMoreOpen.value) closeMore()
    if (isAdminOpen.value) closeAdmin()
    if (isCreatePickerOpen.value) closeCreatePicker()
  }
}

const openComposer = (action = 'post') => {
  closeMore()
  closeAdmin()
  closeNotifications()
  closeCreatePicker()

  if (typeof window === 'undefined') return
  window.dispatchEvent(new CustomEvent('post:composer:open', {
    detail: {
      action,
    },
  }))
}

const closeCreatePicker = () => {
  isCreatePickerOpen.value = false
}

const toggleCreatePicker = () => {
  closeMore()
  closeAdmin()
  closeNotifications()
  isCreatePickerOpen.value = !isCreatePickerOpen.value
}

const selectCreateType = (type) => {
  closeCreatePicker()

  if (type === 'observation') {
    openComposer('observation')
    return
  }

  if (type === 'poll') {
    openComposer('poll')
    return
  }

  if (type === 'event') {
    openComposer('event')
    return
  }

  openComposer('post')
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('keydown', handleKeydown)
  if (auth.isAuthed) notifications.fetchUnreadCount()
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('keydown', handleKeydown)
})

watch(
  () => auth.isAuthed,
  (isAuthed) => {
    if (isAuthed) {
      notifications.fetchUnreadCount()
      return
    }

    closeNotifications()
  }
)

watch(
  () => route.fullPath,
  () => {
    closeMore()
    closeAdmin()
    closeNotifications()
    closeCreatePicker()
  }
)
</script>

<style scoped>
.mainNavbar {
  border-right: 1px solid var(--color-divider);
  background: linear-gradient(180deg, rgb(var(--bg-app-rgb) / 0.98), rgb(var(--bg-app-rgb) / 0.94));
}

.navScroll {
  -ms-overflow-style: none; /* IE and old Edge */
  scrollbar-width: none; /* Firefox */
}

.navScroll::-webkit-scrollbar {
  width: 0;
  height: 0;
}

.navIcon {
  filter: none;
}

.navIcon--filled {
  opacity: 1;
}

.navIcon--outline {
  opacity: 1;
}

.navItem {
  position: relative;
  display: flex;
  width: 100%;
  align-items: center;
  min-height: 2.85rem;
  gap: 0.56rem;
  border-radius: var(--radius-md);
  padding: 0.35rem 0.44rem 0.35rem 0.38rem;
  color: var(--color-text-secondary);
  text-decoration: none;
  transition:
    background-color var(--motion-fast),
    color var(--motion-fast),
    border-color var(--motion-fast),
    transform 120ms ease;
}

.navItem:hover {
  background: var(--interactive-hover);
  color: var(--color-text-primary);
  transform: translateY(-1px);
}

.navItem.active {
  background: rgb(var(--color-accent-rgb) / 0.18);
  color: var(--color-text-primary);
}

.navItem:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
  box-shadow: var(--focus-ring);
}

.navIconChip {
  display: inline-flex;
  height: auto;
  width: auto;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: currentColor;
  transition: color var(--motion-fast);
}

.navItem.active .navIconChip {
  color: inherit;
}

.navLabel {
  font-size: 0.96rem;
  font-weight: 600;
  line-height: 1.2;
  letter-spacing: 0;
}

.notificationBadge {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transform-origin: center;
  will-change: transform;
  min-width: 1.55rem;
  border: 1px solid rgb(var(--color-accent-rgb) / 0.5);
  background: rgb(var(--color-accent-rgb) / 0.22);
  color: var(--color-text-primary);
  box-shadow: none;
}

.notificationBadge::after {
  content: '';
  position: absolute;
  inset: -0.2rem;
  border-radius: inherit;
  pointer-events: none;
  opacity: 0;
  box-shadow: 0 0 0 0 rgb(var(--color-primary-rgb) / 0.38);
}

.notificationBadge--ping {
  animation: badge-bounce 550ms ease-out;
}

.notificationBadge--ping::after {
  animation: badge-pulse 650ms ease-out;
}

.createTrigger {
  width: 100%;
  min-height: var(--control-height-lg);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border: 1px solid rgb(var(--color-accent-rgb) / 0.45);
  border-radius: var(--radius-pill);
  background: rgb(var(--color-accent-rgb) / 0.9);
  color: var(--color-white);
  padding: 8px 14px;
  font-size: var(--font-size-md);
  font-weight: 600;
  transition:
    border-color var(--motion-fast),
    background-color var(--motion-fast),
    color var(--motion-fast),
    transform 120ms ease;
}

.createTrigger:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.55);
  background: var(--color-primary-hover);
  transform: translateY(-1px);
}

.createTrigger:active {
  transform: scale(0.98);
}

.createTrigger:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.createPickerMenu {
  position: absolute;
  right: 0;
  left: 0;
  bottom: calc(100% + 0.55rem);
  z-index: 50;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  background: var(--color-card);
  padding: var(--space-2);
  backdrop-filter: blur(10px);
  box-shadow: var(--shadow-medium);
}

.createPickerItem {
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-pill);
  background: rgb(var(--bg-app-rgb) / 0.46);
  color: var(--color-text-primary);
  min-height: var(--control-height-md);
  font-size: 14px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.45rem 0.6rem;
  text-align: left;
  transition:
    border-color var(--motion-fast),
    background-color var(--motion-fast),
    transform 120ms ease;
}

.createPickerItem + .createPickerItem {
  margin-top: 0.3rem;
}

.createPickerItem:hover {
  border-color: rgb(var(--color-accent-rgb) / 0.55);
  background: rgb(var(--color-accent-rgb) / 0.14);
  transform: translateY(-1px);
}

.createPickerItem:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.createPickerIcon {
  width: 1rem;
  height: 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.createPickerIcon svg {
  width: 100%;
  height: 100%;
}

.guestAuthActions {
  display: flex;
  gap: 0.55rem;
  width: 100%;
}

.guestAuthActions__btn {
  flex: 1 1 0;
  min-width: 0;
  font-weight: 600;
  font-size: 0.75rem;
  padding: 0.5rem 0.5rem;
  white-space: nowrap;
  text-align: center;
}

.guestAuthActions__btn--register {
  background: #1699f4;
  border-color: #4fb2f7;
  color: #04233a;
}

.guestAuthActions__btn--register:hover:not(:disabled):not([aria-disabled='true']) {
  background: #108be4;
  border-color: #66bdfa;
  color: #031c2f;
}

@keyframes badge-bounce {
  0% {
    transform: scale(0.95);
  }
  40% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
  }
}

@keyframes badge-pulse {
  0% {
    opacity: 0.45;
    transform: scale(0.85);
    box-shadow: 0 0 0 0 rgb(var(--color-primary-rgb) / 0.38);
  }
  70% {
    opacity: 0;
    transform: scale(1.35);
    box-shadow: 0 0 0 0.45rem rgb(var(--color-primary-rgb) / 0);
  }
  100% {
    opacity: 0;
    transform: scale(1.35);
    box-shadow: 0 0 0 0.45rem rgb(var(--color-primary-rgb) / 0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .notificationBadge,
  .notificationBadge::after,
  .notificationBadge--ping,
  .notificationBadge--ping::after {
    animation: none !important;
    transition: none !important;
  }
}
</style>
