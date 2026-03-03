<template>
  <nav class="flex h-full flex-col gap-3 bg-[var(--bg-app)]" aria-label="Primary navigation">
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
            <span class="shrink-0 text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">?</span>
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
            <span class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">?</span>
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
                v-if="isWipEnabled"
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
                to="/admin/bots"
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
                  aria-label="Bot Engine"
                >
                  <span
                    class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                    aria-hidden="true"
                  >
                    B
                  </span>
                  <span class="flex-1">Bot Engine</span>
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
          :data-active="isPrimaryLinkActive(item, isActive, isExactActive) ? 'true' : 'false'"
          :class="[
            'navItem group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[0.875rem] font-bold tracking-[0.01em] !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
            isPrimaryLinkActive(item, isActive, isExactActive)
              ? `active bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_30px_rgb(var(--color-bg-rgb)/0.35)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
              : 'text-[var(--color-surface)]',
          ]"
        >
          <span
            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.7rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
            aria-hidden="true"
          >
            <component
              v-if="item.iconOutline && item.iconFilled"
              :is="isPrimaryLinkActive(item, isActive, isExactActive) ? item.iconFilled : item.iconOutline"
            />
            <span v-else>{{ item.icon }}</span>
          </span>
          <span class="navLabel flex-1">{{ item.label }}</span>
          <span
            v-if="item.badge"
            class="notificationBadge rounded-full bg-[color:rgb(var(--color-bg-rgb)/0.55)] px-2 py-0.5 text-[0.65rem] font-semibold text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)]"
            :class="{ 'notificationBadge--ping': item.key === 'notifications' && shouldAnimateUnreadBadge }"
          >
            {{ item.badge }}
          </span>
        </a>
      </RouterLink>
    </div>

    <button
      v-if="auth.isAuthed"
      type="button"
      class="group relative flex items-center gap-3 rounded-xl border border-[color:rgb(var(--color-primary-rgb)/0.55)] bg-[color:rgb(var(--color-primary-rgb)/0.2)] px-3 py-2.5 text-[0.875rem] font-bold tracking-[0.01em] text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-primary-rgb)/0.28)] hover:scale-[1.02] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
      aria-label="Nový príspevok"
      @click="openComposer"
    >
      <span
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[color:rgb(var(--color-primary-rgb)/0.58)] bg-[color:rgb(var(--color-primary-rgb)/0.25)] text-[1rem] font-semibold text-[var(--color-surface)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
        aria-hidden="true"
      >
        +
      </span>
      <span class="navLabel flex-1 text-left">Nový príspevok</span>
    </button>

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

    </div>
  </nav>
</template>

<script setup>
import { computed, defineComponent, h, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { useBadgeAnimateOnIncrease } from '@/composables/useBadgeAnimateOnIncrease'
import TypingText from '@/components/TypingText.vue'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const router = useRouter()
const route = useRoute()
const isWipEnabled = String(import.meta.env.VITE_FEATURE_WIP || 'false').toLowerCase() === 'true'
const isMoreOpen = ref(false)
const moreWrapperRef = ref(null)
const isAdminOpen = ref(false)
const adminWrapperRef = ref(null)
const showGreeting = ref(false)
const greetingText = ref('')
const unreadCount = computed(() => Number(notifications.unreadCount || 0))
const unreadCountHydrated = computed(() => Boolean(notifications.unreadCountHydrated))
const { shouldAnimate: shouldAnimateUnreadBadge } = useBadgeAnimateOnIncrease(unreadCount, {
  readyRef: unreadCountHydrated,
})

let greetingHideTimer = null

const isMoreActive = computed(() => {
  return route.path === '/settings' || (isWipEnabled && route.path === '/creator-studio')
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

  const base = String(import.meta.env.DEV ? '' : (import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_API_URL || '')).replace(/\/+$/, '')
  if (raw.startsWith('/')) return `${base}${raw}`
  return `${base}/${raw}`
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
          'stroke-linecap': 'round',
          'stroke-linejoin': 'round',
          'aria-hidden': 'true',
        },
        paths.map((path, index) => h('path', { key: `path-${index}`, d: path })),
      )
    },
  })

const navIcons = {
  home: {
    outline: createNavIconComponent([
      'M3.75 10.5 12 4l8.25 6.5',
      'M5.75 9.75V19a1 1 0 0 0 1 1h3.75v-5.25a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1V20h3.75a1 1 0 0 0 1-1V9.75',
    ]),
    filled: createNavIconComponent(
      ['M12 3.6 3.75 10v10A1.25 1.25 0 0 0 5 21.25h4.75v-5.3a1 1 0 0 1 1-1h2.5a1 1 0 0 1 1 1v5.3H19A1.25 1.25 0 0 0 20.25 20V10L12 3.6Z'],
      true,
    ),
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
      'M12 3.5 18 6v5.1c0 4.2-2.55 7.98-6 9.4-3.45-1.42-6-5.2-6-9.4V6l6-2.5Z',
      'M9.5 11.75l1.6 1.6 3.4-3.6',
    ]),
    filled: createNavIconComponent(['M12 2.75 5 5.6v5.5c0 4.6 2.8 8.75 7 10.15 4.2-1.4 7-5.55 7-10.15V5.6l-7-2.85Z'], true),
  },
  settings: {
    outline: createNavIconComponent([
      'M12 8.75a3.25 3.25 0 1 1 0 6.5 3.25 3.25 0 0 1 0-6.5Z',
      'M12 2.75v2',
      'M18.54 5.46l-1.42 1.42',
      'M21.25 12h-2',
      'M18.54 18.54l-1.42-1.42',
      'M12 21.25v-2',
      'M5.46 18.54l1.42-1.42',
      'M2.75 12h2',
      'M5.46 5.46l1.42 1.42',
    ]),
    filled: createNavIconComponent(
      [
        'M11.1 2.75a1 1 0 0 1 1.8 0l.52 1.24a1 1 0 0 0 .98.62l1.33-.09a1 1 0 0 1 1.27 1.27l-.09 1.33a1 1 0 0 0 .62.98l1.24.52a1 1 0 0 1 0 1.8l-1.24.52a1 1 0 0 0-.62.98l.09 1.33a1 1 0 0 1-1.27 1.27l-1.33-.09a1 1 0 0 0-.98.62l-.52 1.24a1 1 0 0 1-1.8 0l-.52-1.24a1 1 0 0 0-.98-.62l-1.33.09a1 1 0 0 1-1.27-1.27l.09-1.33a1 1 0 0 0-.62-.98l-1.24-.52a1 1 0 0 1 0-1.8l1.24-.52a1 1 0 0 0 .62-.98l-.09-1.33a1 1 0 0 1 1.27-1.27l1.33.09a1 1 0 0 0 .98-.62l.52-1.24Z',
        'M12 8.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5Z',
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
      label: 'Prehľadávať',
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

  if (auth.isAdmin) {
    links.push({
      key: 'admin',
      to: '/admin/dashboard',
      label: 'Admin Hub',
      icon: 'A',
      iconOutline: navIcons.admin.outline,
      iconFilled: navIcons.admin.filled,
      matchPrefix: '/admin',
    })
  }

  links.push({
    key: 'settings',
    to: '/settings',
    label: 'Settings',
    icon: 'S',
    iconOutline: navIcons.settings.outline,
    iconFilled: navIcons.settings.filled,
    matchPrefix: '/settings',
  })

  if (isWipEnabled) {
    links.push({
      key: 'creator-studio',
      to: '/creator-studio',
      label: 'Creator Studio',
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

const openComposer = () => {
  closeMore()
  closeAdmin()

  if (typeof window === 'undefined') return
  window.dispatchEvent(new CustomEvent('post:composer:open'))
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

.navIcon {
  filter: drop-shadow(0 1px 1px rgb(var(--color-bg-rgb) / 0.22));
}

.navIcon--filled {
  opacity: 0.98;
}

.navIcon--outline {
  opacity: 0.92;
}

.navItem.active {
  font-weight: 600;
}

.navLabel {
  letter-spacing: 0.01em;
}

.brand-fade-enter-active,
.brand-fade-leave-active {
  transition: opacity 0.18s ease;
}

.brand-fade-enter-from,
.brand-fade-leave-to {
  opacity: 0;
}

.notificationBadge {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transform-origin: center;
  will-change: transform;
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

