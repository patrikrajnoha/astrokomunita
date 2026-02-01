<template>
  <nav class="flex h-full flex-col gap-5" aria-label="Primary navigation">
    <RouterLink
      to="/"
      class="inline-flex items-center gap-3 rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.45)] px-3 py-2.5 text-base font-semibold text-[var(--color-surface)] shadow-[0_12px_30px_rgb(var(--color-bg-rgb)/0.35)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.6)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
      title="Home"
      aria-label="Home"
    >
      <span
        class="grid h-10 w-10 place-items-center rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[var(--color-surface)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.45)]"
        aria-hidden="true"
      >
        AK
      </span>
      <span>Astrokomunita</span>
    </RouterLink>

    <div class="flex flex-col gap-1.5">
      <RouterLink
        v-for="item in primaryLinks"
        :key="item.to"
        :to="item.to"
        custom
        v-slot="{ href, navigate, isActive }"
      >
        <div v-if="item.isMore" ref="moreWrapperRef" class="relative mt-3 border-t border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pt-3">
          <button
            type="button"
            class="group relative flex w-full items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] transition-all duration-200 ease-out hover:!text-[var(--color-surface)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.55)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
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
            <span class="flex-1">{{ item.label }}</span>
            <span class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">v</span>
          </button>

          <div
            v-if="isMoreOpen"
            id="more-menu"
            class="absolute left-0 top-full z-50 mt-2 w-60 rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.92)] p-2 backdrop-blur-md ring-1 ring-[color:rgb(var(--color-text-secondary-rgb)/0.18)] shadow-[0_18px_55px_rgb(0_0_0/0.55)]"
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
                class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                :class="isMoreItemActive
                  ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                  : ''"
                role="menuitem"
                aria-label="Settings"
              >
                <span
                  class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                  aria-hidden="true"
                >
                  S
                </span>
                <span class="flex-1">Settings</span>
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
                class="group relative mt-1 flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
                :class="isMoreItemActive
                  ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_25px_rgb(var(--color-bg-rgb)/0.25)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
                  : ''"
                role="menuitem"
                aria-label="Creator Studio"
              >
                <span
                  class="grid h-7 w-7 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.65rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
                  aria-hidden="true"
                >
                  C
                </span>
                <span class="flex-1">Creator Studio</span>
              </a>
            </RouterLink>
          </div>
        </div>
        <div v-else-if="item.isAdmin" ref="adminWrapperRef" class="relative mt-3 border-t border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pt-3">
          <button
            type="button"
            class="group relative flex w-full items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] transition-all duration-200 ease-out hover:!text-[var(--color-surface)] hover:bg-[color:rgb(var(--color-bg-rgb)/0.55)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
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
            <span class="text-xs text-[color:rgb(var(--color-text-secondary-rgb)/0.9)]" aria-hidden="true">v</span>
          </button>

          <div
            v-if="isAdminOpen"
            id="admin-menu"
            class="absolute left-0 top-full z-50 mt-2 w-60 rounded-2xl bg-[color:rgb(var(--color-bg-rgb)/0.92)] p-2 backdrop-blur-md ring-1 ring-[color:rgb(var(--color-text-secondary-rgb)/0.18)] shadow-[0_18px_55px_rgb(0_0_0/0.55)]"
            role="menu"
            aria-label="Admin options"
          >
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
              to="/admin/candidates"
              custom
              v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
            >
              <a
                :href="adminHref"
                @click="() => { closeAdmin(); adminNavigate(); }"
                class="group relative mt-1 flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
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
              to="/admin/events"
              custom
              v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
            >
              <a
                :href="adminHref"
                @click="() => { closeAdmin(); adminNavigate(); }"
                class="group relative mt-1 flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
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
              to="/admin/reports"
              custom
              v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
            >
              <a
                :href="adminHref"
                @click="() => { closeAdmin(); adminNavigate(); }"
                class="group relative mt-1 flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
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
              to="/admin/blog-posts"
              custom
              v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
            >
              <a
                :href="adminHref"
                @click="() => { closeAdmin(); adminNavigate(); }"
                class="group relative mt-1 flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
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
              to="/admin/users"
              custom
              v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
            >
              <a
                :href="adminHref"
                @click="() => { closeAdmin(); adminNavigate(); }"
                class="group relative mt-1 flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
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
              to="/admin/astrobot"
              custom
              v-slot="{ href: adminHref, navigate: adminNavigate, isActive: isAdminItemActive }"
            >
              <a
                :href="adminHref"
                @click="() => { closeAdmin(); adminNavigate(); }"
                class="group relative mt-1 flex items-center gap-3 rounded-xl px-3 py-2 text-[0.8125rem] font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
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
        <a
          v-else
          :href="href"
          @click="navigate"
          :title="item.title || item.label"
          :aria-label="item.label"
          :class="[
            'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
            isActive
              ? `bg-[color:rgb(var(--color-bg-rgb)/0.75)] shadow-[0_10px_30px_rgb(var(--color-bg-rgb)/0.35)] before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-[var(--color-surface)]`
              : 'text-[var(--color-surface)]',
          ]"
        >
          <span
            class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-[0.7rem] font-semibold uppercase text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
            aria-hidden="true"
          >
            {{ item.icon }}
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

    <div class="mt-auto border-t border-[color:rgb(var(--color-text-secondary-rgb)/0.12)] pt-5">
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
              'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
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
            <span class="flex-1">{{ auth.user.name }}</span>
          </a>
        </RouterLink>

        <button
          class="group mt-2 flex w-full items-center gap-3 rounded-xl bg-[color:rgb(var(--color-bg-rgb)/0.5)] px-3 py-2.5 text-sm font-semibold !text-[var(--color-surface)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]"
          title="Log out"
          aria-label="Log out"
          @click="logout"
        >
          <span
            class="grid h-8 w-8 place-items-center rounded-lg bg-[color:rgb(var(--color-bg-rgb)/0.6)] text-sm text-[color:rgb(var(--color-text-secondary-rgb)/0.95)] shadow-[0_1px_0_rgb(var(--color-text-secondary-rgb)/0.12)] transition-transform duration-200 ease-out group-hover:scale-105 group-active:scale-95"
            aria-hidden="true"
          >
            X
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
              'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
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
              'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold !text-[var(--color-surface)] transition-all duration-200 ease-out hover:bg-[color:rgb(var(--color-bg-rgb)/0.65)] hover:translate-x-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--color-surface)]',
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
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const isMoreOpen = ref(false)
const moreWrapperRef = ref(null)
const isAdminOpen = ref(false)
const adminWrapperRef = ref(null)

const isMoreActive = computed(() => {
  return route.path === '/settings' || route.path === '/creator-studio'
})

const isAdminActive = computed(() => {
  return route.path.startsWith('/admin/')
})

const primaryLinks = computed(() => {
  const links = [
    { to: '/events', label: 'Events', icon: 'U' },
    { to: '/calendar', label: 'Calendar', icon: 'K' },
    { to: '/learn', label: 'Learning', icon: 'V' },
  ]

  if (auth.user) {
    links.push({ to: '/observations', label: 'Observations', icon: 'P' })
  }

  if (auth.isAdmin) {
    links.push({ to: '/admin', label: 'Admin', icon: 'A', isAdmin: true })
  }

  links.push({ to: '/more', label: 'More', icon: 'M', isMore: true })

  return links
})

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

const handleClickOutside = (event) => {
  const target = event.target
  const wrapper = moreWrapperRef.value
  const adminWrapper = adminWrapperRef.value

  if (isMoreOpen.value && wrapper && target instanceof Node && !wrapper.contains(target)) {
    closeMore()
  }
  if (isAdminOpen.value && adminWrapper && target instanceof Node && !adminWrapper.contains(target)) {
    closeAdmin()
  }
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    if (isMoreOpen.value) closeMore()
    if (isAdminOpen.value) closeAdmin()
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('keydown', handleKeydown)
})

const logout = async () => {
  try {
    await auth.logout()
  } finally {
    router.push({ name: 'login' })
  }
}
</script>


