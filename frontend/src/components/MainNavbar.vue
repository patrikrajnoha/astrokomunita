<template>
  <nav class="flex h-full flex-col gap-3" aria-label="Primary navigation">
    <RouterLink
      to="/"
      class="inline-flex items-center gap-2 rounded-xl bg-[color:rgb(var(--color-bg-rgb)/0.45)] px-3 py-2 text-sm font-semibold text-[var(--color-surface)] shadow-[0_8px_20px_rgb(var(--color-bg-rgb)/0.35)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.6)] hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
      title="Home"
      aria-label="Home"
    >
      <span
        class="grid h-8 w-8 place-items-center rounded-xl bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[var(--color-surface)] shadow-[0_6px_15px_rgb(var(--color-bg-rgb)/0.45)]"
        aria-hidden="true"
      >
        AK
      </span>
      <Transition name="brand-fade" mode="out-in">
        <TypingText
          v-if="showGreeting"
          :text="greetingText"
          :speed-ms="56"
          :start-delay-ms="150"
          class="brandLabel hidden sm:inline font-bold"
          @done="onGreetingDone"
        />
        <span v-else class="brandLabel hidden sm:inline">Astrokomunita</span>
      </Transition>
    </RouterLink>

    <!-- Main Navigation -->
    <div class="navScroll flex flex-col gap-1.5 flex-1 overflow-y-auto overflow-x-hidden">
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
              ? `bg-[color:rgb(var(--color-bg-rgb)/0.65)] !text-[var(--color-surface)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.3)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
              : ''"
            :aria-expanded="isMoreOpen ? 'true' : 'false'"
            aria-controls="more-menu"
            aria-label="More"
            title="More"
            @click="toggleMore"
          >
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
              aria-hidden="true"
            >
              {{ item.icon }}
            </span>
            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
            <span class="shrink-0 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">▾</span>
          </button>

          <div
            v-if="isMoreOpen"
            id="more-menu"
            class="absolute left-0 top-full z-50 mt-2 w-60 max-h-[60vh] overflow-x-hidden overflow-y-auto rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.92)] p-2 backdrop-blur-md ring-1 ring-[color:rgb(var(--color-text-secondary-rgb)/0.18)] shadow-[0_18px_55px_rgb(0_0_0/0.55)]"
            role="menu"
            aria-label="More options"
          >
            <RouterLink
              to="/settings"
              custom
              v-slot="{ href: moreHref, navigate: moreNavigate, isActive: isMoreItemActive }"
            >
              <a
                :href="moreHref"
                @click="() => { closeMore(); moreNavigate(); }"
                class="group relative flex w-full min-w-0 items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                :class="isMoreItemActive
                  ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                  : ''"
                role="menuitem"
                aria-label="Settings"
              >
                <span
                  class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                  aria-hidden="true"
                >
                  S
                </span>
                <span class="min-w-0 flex-1 truncate">Settings</span>
              </a>
            </RouterLink>

            <RouterLink
              to="/creator-studio"
              custom
              v-slot="{ href: moreHref, navigate: moreNavigate, isActive: isMoreItemActive }"
            >
              <a
                :href="moreHref"
                @click="() => { closeMore(); moreNavigate(); }"
                class="group relative mt-1 flex w-full min-w-0 items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                :class="isMoreItemActive
                  ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                  : ''"
                role="menuitem"
                aria-label="Creator Studio"
              >
                <span
                  class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                  aria-hidden="true"
                >
                  C
                </span>
                <span class="min-w-0 flex-1 truncate">Creator Studio</span>
              </a>
            </RouterLink>
          </div>
        </div>
        <div v-else-if="item.isAdmin" ref="adminWrapperRef" class="relative mt-3 border-t border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pt-3">
          <button
            type="button"
            class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[0.8125rem] font-semibold !text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] transition-all duration-200 ease-out hover:!text-[var(--color-surface)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.55)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
            :class="isAdminActive
              ? `bg-[color:rgb(var(--color-bg-rgb)/0.65)] !text-[var(--color-surface)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.3)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
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
            <span class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">▾</span>
          </button>

          <div
            v-if="isAdminOpen"
            id="admin-menu"
            class="absolute left-0 top-full z-50 mt-2 w-60 rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.92)] p-2 backdrop-blur-md ring-1 ring-[color:rgb(var(--color-text-secondary-rgb)/0.18)] shadow-[0_18px_55px_rgb(0_0_0/0.55)]"
            role="menu"
            aria-label="Admin options"
          >
            <!-- Admin Group 1: Core Management -->
            <div class="mb-2 border-b border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pb-2">
              <div class="px-3 py-1 text-xs font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.7)]">
                Core Management
              </div>
              <RouterLink
                to="/admin/dashboard"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Dashboard"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    D
                  </span>
                  <span class="flex-1">Dashboard</span>
                </a>
              </RouterLink>

              <RouterLink
                to="/admin/users"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Users"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    U
                  </span>
                  <span class="flex-1">Users</span>
                </a>
              </RouterLink>

              <RouterLink
                to="/admin/event-candidates"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Candidates"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    C
                  </span>
                  <span class="flex-1">Candidates</span>
                </a>
              </RouterLink>

              <RouterLink
                to="/admin/reports"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Reports"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    R
                  </span>
                  <span class="flex-1">Reports</span>
                </a>
              </RouterLink>

              <RouterLink
                to="/admin/moderation"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Moderation"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    M
                  </span>
                  <span class="flex-1">Moderation</span>
                </a>
              </RouterLink>

              <RouterLink
                to="/admin/banned-words"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Banned words"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    W
                  </span>
                  <span class="flex-1">Banned words</span>
                </a>
              </RouterLink>
            </div>

            <!-- Admin Group 2: Content & Configuration -->
            <div>
              <div class="px-3 py-1 text-xs font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.7)]">
                Content & Configuration
              </div>
              <RouterLink
                to="/admin/events"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Events"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    E
                  </span>
                  <span class="flex-1">Events</span>
                </a>
              </RouterLink>

              <RouterLink
                to="/admin/blog"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Articles"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    B
                  </span>
                  <span class="flex-1">Articles</span>
                </a>
              </RouterLink>

              <RouterLink
                to="/admin/sidebar"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="Sidebar"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    S
                  </span>
                  <span class="flex-1">Sidebar</span>
                </a>
              </RouterLink>

              <RouterLink
                to="/admin/astrobot"
                custom
                v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
              >
                <a
                  :href="adminHref"
                  @click="() => { closeAdmin(); adminNavigate(); }"
                  class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                  :class="isAdminItemActive
                    ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                    : ''"
                  role="menuitem"
                  aria-label="AstroBot"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    A
                  </span>
                  <span class="flex-1">AstroBot</span>
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
          :class="[
            'group relative flex items-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
            isPrimaryLinkActive(item, isActive, isExactActive)
              ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_30px_rgb(var(--color-bg-rgb)/0.35)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
              : 'text-[var(--color-surface)]',
          ]"
        >
          <span
            class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.7rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
            aria-hidden="true"
          >
            <svg
              v-if="Array.isArray(item.iconPaths) && item.iconPaths.length > 0"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path v-for="(path, index) in item.iconPaths" :key="`${item.to}-icon-${index}`" :d="path" />
            </svg>
            <span v-else>{{ item.icon }}</span>
          </span>
          <span class="flex-1">{{ item.label }}</span>
          <span
            v-if="item.badge"
            class="rounded-full bg-[color:rgb(var(--color-bg-rgb)/0.55)] px-2 py-0.5 text-[0.65rem] font-semibold text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)]"
          >
            {{ item.badge }}
          </span>
        </a>
      </RouterLink>
    </div>

    <!-- User Section -->
    <div class="border-t border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pt-4 space-y-2">
      <template v-if="auth.user">
        <RouterLink
          to="/profile"
          custom
          v-slot="{ href, navigate, isActive }"
        >
          <a
            :href="href"
            @click="navigate"
            title="Profile"
            aria-label="Profile"
            :class="[
              'group relative flex items-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
              isActive
                ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_30px_rgb(var(--color-bg-rgb)/0.35)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                : 'text-[var(--color-surface)]',
            ]"
          >
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
              aria-hidden="true"
            >
              <img
                v-if="userAvatarUrl"
                :src="userAvatarUrl"
                alt=""
                class="h-full w-full rounded-lg object-cover"
              />
              <span v-else>{{ userInitials }}</span>
            </span>
            <span class="flex-1 truncate">{{ auth.user.name }}</span>
          </a>
        </RouterLink>

        <!-- Logout -->
        <button
          class="group relative flex w-full items-center gap-2 rounded-lg bg-red-500/15 px-2 py-2 text-xs font-semibold text-red-400 shadow-[0_2px_8px_rgb(var(--color-text-secondary-rgb)/0.1)] transition-all duration-200 ease-out hover:bg-red-500/25 hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-400"
          title="Log out"
          aria-label="Log out"
          @click="logout"
        >
          <span
            class="grid h-6 w-6 place-items-center rounded-lg bg-red-500/25 text-red-400 shadow-[0_1px_3px_rgb(var(--color-text-secondary-rgb)/0.1)] transition-transform duration-200 ease-out group-hover:scale-110 group-active:scale-95"
            aria-hidden="true"
          >
            L
          </span>
          <span class="flex-1">Logout</span>
        </button>
      </template>

      <template v-else>
        <RouterLink
          to="/login"
          custom
          v-slot="{ href, navigate, isActive }"
        >
          <a
            :href="href"
            @click="navigate"
            title="Log in"
            aria-label="Log in"
            :class="[
              'group relative flex items-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
              isActive
                ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_30px_rgb(var(--color-bg-rgb)/0.35)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                : 'text-[var(--color-surface)]',
            ]"
          >
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
              aria-hidden="true"
            >
              L
            </span>
            <span class="flex-1">Login</span>
          </a>
        </RouterLink>

        <RouterLink
          to="/register"
          custom
          v-slot="{ href, navigate, isActive }"
        >
          <a
            :href="href"
            @click="navigate"
            title="Register"
            aria-label="Register"
            :class="[
              'group relative flex items-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
              isActive
                ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_30px_rgb(var(--color-bg-rgb)/0.35)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                : 'text-[var(--color-surface)]',
            ]"
          >
            <span
              class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
              aria-hidden="true"
            >
              R
            </span>
            <span class="flex-1">Register</span>
          </a>
        </RouterLink>
      </template>
    </div>
  </nav>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import TypingText from '@/components/TypingText.vue'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const router = useRouter()
const route = useRoute()
const isMoreOpen = ref(false)
const moreWrapperRef = ref(null)
const isAdminOpen = ref(false)
const adminWrapperRef = ref(null)
const showGreeting = ref(false)
const greetingText = ref('')

let greetingHideTimer = null

const isMoreActive = computed(() => {
  return route.path === '/settings' || route.path === '/creator-studio'
})

const isAdminActive = computed(() => {
  return route.path.startsWith('/admin/')
})

const userInitials = computed(() => {
  const name = String(auth.user?.name || '').trim()
  if (!name) return 'U'
  const parts = name.split(/\s+/).filter(Boolean)
  const first = parts[0]?.[0] || 'U'
  const second = parts[1]?.[0] || ''
  return (first + second).toUpperCase()
})

const userAvatarUrl = computed(() => {
  const raw = auth.user?.avatar_url || auth.user?.avatarUrl || auth.user?.avatar_path || ''
  if (!raw) return ''
  if (/^https?:\/\//i.test(raw)) return raw

  const base = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  if (raw.startsWith('/')) return `${base}${raw}`
  return `${base}/${raw}`
})

const primaryLinks = computed(() => {
  const navIcons = {
    home: [
      'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18Z',
      'M7.2 9.1l1.6-1.3 2.2.4 1.1 1.4-.4 1.8-1.6 1.2-1.9-.5-.5-1.6z',
      'M12.8 13.2l1.9-1.2 2.3.7.8 1.9-1.5 1.9-2 .4-1.6-1.1.3-2.6z',
    ],
    search: ['M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z', 'm20 20-3.5-3.5'],
    notifications: ['M6 8a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6', 'M9.5 20a2.5 2.5 0 0 0 5 0'],
    events: ['M7 3v3', 'M17 3v3', 'M4 8h16', 'M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z'],
    learn: ['M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14H6.5A2.5 2.5 0 0 0 4 20.5z', 'M8 8h8', 'M8 11h8'],
  }

  const links = [
    { key: 'home', to: '/', label: 'Domov', icon: 'D', iconPaths: navIcons.home },
    { key: 'search', to: '/search', label: 'Preskumat', icon: 'P', iconPaths: navIcons.search },
    {
      key: 'notifications',
      to: '/notifications',
      label: 'Upozornenia',
      icon: 'U',
      iconPaths: navIcons.notifications,
      badge: auth.isAuthed ? notifications.unreadBadge : null,
    },
    {
      key: 'events',
      to: '/events',
      label: 'Udalosti',
      icon: 'U',
      iconPaths: navIcons.events,
      matchPrefix: '/events',
    },
    { key: 'learn', to: '/learn', label: 'Vzdelavanie', icon: 'V', iconPaths: navIcons.learn },
  ]

  if (auth.isAdmin) {
    links.push({ key: 'admin', to: '/admin/dashboard', label: 'Admin Hub', icon: 'A', matchPrefix: '/admin' })
  }

  links.push({ key: 'more', to: '/more', label: 'More', icon: 'M', isMore: true })

  return links
})

const isPrimaryLinkActive = (item, isActive, isExactActive) => {
  if (!item) return false
  const targetPath = typeof item.to === 'string' ? item.to : item.to?.path

  // Home should be active only on exact root route.
  if (targetPath === '/') {
    return Boolean(isExactActive)
  }

  if (item.matchPrefix) {
    return route.path.startsWith(item.matchPrefix)
  }

  return Boolean(isActive)
}

const toggleMore = () => {
  isMoreOpen.value = !isMoreOpen.value
}

const closeMore = () => {
  isMoreOpen.value = false
}

const toggleAdmin = () => {
  isAdminOpen.value = !isAdminOpen.value
}

const closeAdmin = () => {
  isAdminOpen.value = false
}

const firstElementRef = (value) => {
  if (Array.isArray(value)) return value[0] || null
  return value || null
}

const handleClickOutside = (event) => {
  const target = event.target
  const wrapper = firstElementRef(moreWrapperRef.value)
  const adminWrapper = firstElementRef(adminWrapperRef.value)

  if (isMoreOpen.value && wrapper instanceof Element && target instanceof Node && !wrapper.contains(target)) {
    closeMore()
  }
  if (isAdminOpen.value && adminWrapper instanceof Element && target instanceof Node && !adminWrapper.contains(target)) {
    closeAdmin()
  }
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    if (isMoreOpen.value) closeMore()
    if (isAdminOpen.value) closeAdmin()
  }
}

const clearGreetingTimer = () => {
  if (greetingHideTimer !== null) {
    window.clearTimeout(greetingHideTimer)
    greetingHideTimer = null
  }
}

const hideGreetingNow = () => {
  clearGreetingTimer()
  showGreeting.value = false
  greetingText.value = ''
}

const onGreetingDone = () => {
  clearGreetingTimer()
  greetingHideTimer = window.setTimeout(() => {
    showGreeting.value = false
  }, 2500)
}

const userGreetingName = (user) => {
  const fromName = String(user?.name || '').trim()
  if (fromName) return fromName
  const fromUsername = String(user?.username || '').trim()
  if (fromUsername) return fromUsername
  return ''
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('keydown', handleKeydown)
  if (auth.isAuthed) notifications.fetchUnreadCount()
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('keydown', handleKeydown)
  clearGreetingTimer()
})

watch(
  () => auth.isAuthed,
  (isAuthed) => {
    if (isAuthed) notifications.fetchUnreadCount()
  }
)

watch(
  () => auth.user,
  (nextUser) => {
    if (!nextUser) {
      hideGreetingNow()
    }
  },
)

watch(
  () => auth.loginSequence,
  (next, prev) => {
    if (!Number.isFinite(next) || next <= 0) return
    if (typeof prev === 'number' && next <= prev) return
    if (!auth.user) return

    const name = userGreetingName(auth.user)
    if (!name) {
      hideGreetingNow()
      return
    }

    clearGreetingTimer()
    greetingText.value = `Ahoj ${name}! \u{1F44B}`
    showGreeting.value = true
  },
  { immediate: true },
)

const logout = async () => {
  try {
    await auth.logout()
  } finally {
    router.push({ name: 'login' })
  }
}
</script>

<style scoped>
.navScroll {
  -ms-overflow-style: none; /* IE and old Edge */
  scrollbar-width: none; /* Firefox */
}

.navScroll::-webkit-scrollbar {
  width: 0;
  height: 0;
}

.brandLabel {
  display: inline-block;
  min-height: 1.2rem;
  white-space: nowrap;
}

.brand-fade-enter-active,
.brand-fade-leave-active {
  transition: opacity 0.18s ease;
}

.brand-fade-enter-from,
.brand-fade-leave-to {
  opacity: 0;
}
</style>


