<template>
  <nav
    class="mobileBottomNav md:hidden"
    aria-label="Spodna mobilna navigacia"
    data-testid="mobile-bottom-nav"
  >
    <RouterLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      custom
      v-slot="{ href, navigate }"
    >
      <a
        :href="href"
        :aria-current="isActive(item) ? 'page' : undefined"
        :aria-label="item.label"
        class="mobileBottomNav__item"
        :class="{ 'is-active': isActive(item) }"
        data-testid="mobile-bottom-nav-item"
        @click="navigate"
      >
        <span class="mobileBottomNav__iconWrap" aria-hidden="true">
          <svg
            class="mobileBottomNav__icon"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path v-for="(path, index) in item.iconPaths" :key="`${item.to}-${index}`" :d="path" />
          </svg>
        </span>
        <span class="mobileBottomNav__label">{{ item.label }}</span>
      </a>
    </RouterLink>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()

const items = computed(() => [
  {
    label: 'Domov',
    to: '/',
    exact: true,
    iconPaths: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z', 'M3 12h18', 'M12 3a12.5 12.5 0 0 1 0 18', 'M12 3a12.5 12.5 0 0 0 0 18'],
  },
  {
    label: 'Preskúmať',
    to: '/search',
    iconPaths: ['M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z', 'm20 20-3.5-3.5'],
  },
  {
    label: 'Udalosti',
    to: '/events',
    matchPrefix: '/events',
    iconPaths: [
      'M7 3v3',
      'M17 3v3',
      'M4 8h16',
      'M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
    ],
  },
  {
    label: 'Notifikácie',
    to: '/notifications',
    iconPaths: ['M6 8a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6', 'M9.5 20a2.5 2.5 0 0 0 5 0'],
  },
  {
    label: 'Články',
    to: '/clanky',
    matchPrefix: '/clanky',
    iconPaths: ['M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14H6.5A2.5 2.5 0 0 0 4 20.5z', 'M8 8h8', 'M8 11h8'],
  },
])

const isActive = (item) => {
  if (!item?.to) return false

  if (item.exact) {
    return route.path === item.to
  }

  if (item.matchPrefix) {
    return route.path === item.to || route.path.startsWith(`${item.matchPrefix}/`)
  }

  return route.path === item.to
}
</script>

<style scoped>
.mobileBottomNav {
  position: fixed;
  left: 0.65rem;
  right: 0.65rem;
  bottom: 0.65rem;
  z-index: 65;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.35rem;
  padding: 0.45rem 0.45rem calc(0.45rem + env(safe-area-inset-bottom));
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  background: rgb(var(--bg-surface-2-rgb) / 0.94);
  backdrop-filter: blur(14px);
  box-shadow: var(--shadow-medium);
}

.mobileBottomNav__item {
  min-height: 3.35rem;
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  background: transparent;
  color: var(--color-text-secondary);
  text-decoration: none;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 0.22rem;
  padding: 0.3rem 0.2rem;
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    color 160ms ease,
    transform 160ms ease;
}

.mobileBottomNav__item:hover {
  color: var(--color-text-primary);
}

.mobileBottomNav__item:active {
  transform: translateY(1px);
}

.mobileBottomNav__item:focus-visible {
  outline: 2px solid rgb(var(--primary-rgb) / 0.9);
  outline-offset: 2px;
}

.mobileBottomNav__item.is-active {
  border-color: rgb(var(--color-accent-rgb) / 0.48);
  background: linear-gradient(
    180deg,
    rgb(var(--color-accent-rgb) / 0.22),
    rgb(var(--color-accent-rgb) / 0.14)
  );
  color: var(--color-text-primary);
}

.mobileBottomNav__iconWrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: var(--radius-sm);
  background: rgb(var(--bg-app-rgb) / 0.22);
}

.mobileBottomNav__item.is-active .mobileBottomNav__iconWrap {
  background: rgb(var(--color-accent-rgb) / 0.2);
}

.mobileBottomNav__icon {
  display: block;
  width: 1.1rem;
  height: 1.1rem;
  color: currentColor;
}

.mobileBottomNav__label {
  font-size: 0.64rem;
  font-weight: 700;
  line-height: 1;
  letter-spacing: 0.01em;
}
</style>
