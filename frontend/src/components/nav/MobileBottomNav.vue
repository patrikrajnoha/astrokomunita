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
  left: 0.72rem;
  right: 0.72rem;
  bottom: 0.7rem;
  z-index: 65;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.32rem;
  padding: 0.38rem 0.38rem calc(0.52rem + env(safe-area-inset-bottom));
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.25);
  border-radius: 1.4rem;
  background: #151d28;
  backdrop-filter: blur(18px) saturate(135%);
  box-shadow:
    0 16px 38px rgb(var(--bg-app-rgb) / 0.56),
    0 1px 0 rgb(var(--color-text-primary-rgb) / 0.06) inset;
  animation: mobileDockEnter 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.mobileBottomNav__item {
  position: relative;
  min-height: 3.55rem;
  border-radius: 1rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0);
  background: transparent;
  color: var(--color-text-secondary);
  text-decoration: none;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 0.25rem;
  padding: 0.28rem 0.16rem;
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    color 160ms ease,
    transform 160ms ease,
    box-shadow 160ms ease;
}

.mobileBottomNav__item:hover {
  background: rgb(var(--color-text-primary-rgb) / 0.04);
  color: var(--color-text-primary);
}

.mobileBottomNav__item:active {
  transform: translateY(1px) scale(0.985);
}

.mobileBottomNav__item:focus-visible {
  outline: 2px solid rgb(var(--primary-rgb) / 0.9);
  outline-offset: 2px;
}

.mobileBottomNav__item.is-active {
  border-color: rgb(var(--color-accent-rgb) / 0.58);
  background: linear-gradient(
    180deg,
    rgb(var(--color-accent-rgb) / 0.32),
    rgb(var(--color-accent-rgb) / 0.16)
  );
  color: var(--color-text-primary);
  box-shadow:
    0 0 0 1px rgb(var(--color-accent-rgb) / 0.25),
    0 10px 18px rgb(var(--color-accent-rgb) / 0.2);
}

.mobileBottomNav__item.is-active::after {
  content: '';
  position: absolute;
  left: 50%;
  bottom: 0.28rem;
  width: 1.2rem;
  height: 0.18rem;
  border-radius: var(--radius-pill);
  transform: translateX(-50%);
  background: rgb(var(--color-text-primary-rgb) / 0.95);
  opacity: 0.9;
}

.mobileBottomNav__iconWrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.78rem;
  border: 1px solid rgb(var(--color-text-secondary-rgb) / 0.14);
  background: linear-gradient(
    180deg,
    rgb(var(--bg-app-rgb) / 0.42),
    rgb(var(--bg-app-rgb) / 0.22)
  );
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    transform 160ms ease;
}

.mobileBottomNav__item.is-active .mobileBottomNav__iconWrap {
  border-color: rgb(var(--color-accent-rgb) / 0.64);
  background: linear-gradient(
    180deg,
    rgb(var(--color-accent-rgb) / 0.28),
    rgb(var(--color-accent-rgb) / 0.16)
  );
  transform: translateY(-1px);
}

.mobileBottomNav__icon {
  display: block;
  width: 1.1rem;
  height: 1.1rem;
  color: currentColor;
}

.mobileBottomNav__label {
  font-size: clamp(0.6rem, 2.25vw, 0.68rem);
  font-weight: 700;
  line-height: 1;
  letter-spacing: 0.01em;
  text-wrap: balance;
}

@keyframes mobileDockEnter {
  from {
    opacity: 0;
    transform: translateY(14px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .mobileBottomNav {
    animation: none;
  }

  .mobileBottomNav__item,
  .mobileBottomNav__iconWrap {
    transition: none;
  }
}
</style>
