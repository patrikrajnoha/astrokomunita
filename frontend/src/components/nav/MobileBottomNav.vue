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
            :fill="isActive(item) ? 'currentColor' : 'none'"
            :stroke="isActive(item) ? 'none' : 'currentColor'"
            :stroke-width="isActive(item) ? undefined : '1.8'"
            :fill-rule="isActive(item) ? 'evenodd' : undefined"
            :clip-rule="isActive(item) ? 'evenodd' : undefined"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path
              v-for="(path, index) in (isActive(item) ? item.filled.paths : item.outline.paths)"
              :key="`${item.to}-${index}`"
              :d="path"
            />
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
    outline: {
      paths: [
        'M12 3.75a8.25 8.25 0 1 0 0 16.5 8.25 8.25 0 0 0 0-16.5Z',
        'M8.1 8.2 9.35 8.95 9.05 10.1 9.95 11 9.45 12.3 8.45 12.8 7.95 14.2 6.95 13.4 7.25 11.9 6.55 10.8 7.1 9.4Z',
        'M12.2 7.2 14 7.6 15.1 8.4 16.25 8.3 17.05 9.35 16.35 10.55 15.2 10.65 14.6 11.75 15.2 12.9 14.5 14.05 13.1 13.9 12.45 12.85 11.55 12.2 11.8 10.75 12.85 9.9 12.65 8.75Z',
      ],
      filled: false,
    },
    filled: {
      paths: ['M 12 3.75 A 8.25 8.25 0 0 1 12 20.25 A 8.25 8.25 0 0 1 12 3.75 Z M 8.1 8.2 L 9.35 8.95 L 9.05 10.1 L 9.95 11 L 9.45 12.3 L 8.45 12.8 L 7.95 14.2 L 6.95 13.4 L 7.25 11.9 L 6.55 10.8 L 7.1 9.4 Z M 12.2 7.2 L 14 7.6 L 15.1 8.4 L 16.25 8.3 L 17.05 9.35 L 16.35 10.55 L 15.2 10.65 L 14.6 11.75 L 15.2 12.9 L 14.5 14.05 L 13.1 13.9 L 12.45 12.85 L 11.55 12.2 L 11.8 10.75 L 12.85 9.9 L 12.65 8.75 Z'],
      filled: true,
    },
  },
  {
    label: 'Preskúmať',
    to: '/search',
    outline: {
      paths: ['M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z', 'm20 20-3.5-3.5'],
      filled: false,
    },
    filled: {
      paths: ['M10.75 3.5a7.25 7.25 0 1 0 4.61 12.85l4.12 4.12a1 1 0 1 0 1.42-1.42l-4.12-4.12A7.25 7.25 0 0 0 10.75 3.5Z'],
      filled: true,
    },
  },
  {
    label: 'Udalosti',
    to: '/events',
    matchPrefix: '/events',
    outline: {
      paths: [
        'M7 3.75v2.5',
        'M17 3.75v2.5',
        'M4.75 9.25h14.5',
        'M6 5.75h12A1.25 1.25 0 0 1 19.25 7v11A1.25 1.25 0 0 1 18 19.25H6A1.25 1.25 0 0 1 4.75 18V7A1.25 1.25 0 0 1 6 5.75Z',
        'M8.25 13h3.5',
        'M8.25 16h6.5',
      ],
      filled: false,
    },
    filled: {
      paths: [
        'M7.75 3.5a1 1 0 0 1 1 1v1.25h6.5V4.5a1 1 0 1 1 2 0v1.3A2.25 2.25 0 0 1 19.5 8v10.25A2.25 2.25 0 0 1 17.25 20.5H6.75A2.25 2.25 0 0 1 4.5 18.25V8A2.25 2.25 0 0 1 6.75 5.8V4.5a1 1 0 0 1 1-1Z',
        'M4.5 10.25h15v-.5a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 9.75v.5Z',
      ],
      filled: true,
    },
  },
  {
    label: 'Notifikácie',
    to: '/notifications',
    outline: {
      paths: [
        'M6.5 8a5.5 5.5 0 1 1 11 0c0 2.6.7 4.4 1.8 5.8.5.6.1 1.2-.7 1.2H5.4c-.8 0-1.2-.7-.7-1.2C5.8 12.4 6.5 10.6 6.5 8Z',
        'M9.5 18a2.5 2.5 0 0 0 5 0',
      ],
      filled: false,
    },
    filled: {
      paths: [
        'M12 3a5.75 5.75 0 0 0-5.75 5.75c0 2.42-.65 4.02-1.61 5.28-.53.69-.02 1.72.88 1.72h13c.9 0 1.4-1.03.88-1.72-.96-1.26-1.61-2.86-1.61-5.28A5.75 5.75 0 0 0 12 3Z',
        'M9.55 17.1a2.45 2.45 0 0 0 4.9 0h-4.9Z',
      ],
      filled: true,
    },
  },
  {
    label: 'Články',
    to: '/articles',
    matchPrefix: '/articles',
    outline: {
      paths: [
        'M6 4.75h12A1.25 1.25 0 0 1 19.25 6v12A1.25 1.25 0 0 1 18 19.25H6A1.25 1.25 0 0 1 4.75 18V6A1.25 1.25 0 0 1 6 4.75Z',
        'M8 8h2.75v3.5H8Z',
        'M12.25 8h3.75',
        'M12.25 10.25h3.75',
        'M8 13.5h8',
        'M8 16h8',
      ],
      filled: false,
    },
    filled: {
      paths: ['M6 4.5h12A1.5 1.5 0 0 1 19.5 6v12a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 18V6A1.5 1.5 0 0 1 6 4.5Zm2 3v4h3v-4H8Zm4.25 0V9h3.75V7.5h-3.75Zm0 2.75v1.5h3.75v-1.5h-3.75ZM8 13.5V15h8v-1.5H8Zm0 2.5v1h8v-1H8Z'],
      filled: true,
    },
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
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 65;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.2rem;
  padding:
    0.3rem
    max(0.6rem, env(safe-area-inset-right))
    calc(0.4rem + env(safe-area-inset-bottom))
    max(0.6rem, env(safe-area-inset-left));
  border-top: 1px solid rgb(171 184 201 / 0.12);
  border-radius: 1.5rem 1.5rem 0 0;
  background: #151d28;
  animation: mobileDockEnter 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.mobileBottomNav__item {
  position: relative;
  min-height: 2.6rem;
  border-radius: 1.1rem;
  background: transparent;
  color: #ABB8C9;
  text-decoration: none;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 0;
  padding: 0.25rem 0.12rem;
  transition:
    background-color 160ms ease,
    color 160ms ease,
    transform 160ms ease;
}

.mobileBottomNav__item:hover {
  background: #1c2736;
  color: #FFFFFF;
}

.mobileBottomNav__item:active {
  transform: scale(0.95);
}

.mobileBottomNav__item:focus-visible {
  outline: 2px solid #0F73FF;
  outline-offset: 2px;
}

.mobileBottomNav__item.is-active {
  background: rgba(15, 115, 255, 0.14);
  color: #FFFFFF;
}

.mobileBottomNav__iconWrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 0.75rem;
  transition: transform 160ms ease;
}

.mobileBottomNav__item.is-active .mobileBottomNav__iconWrap {
  transform: translateY(-1px);
}

.mobileBottomNav__item.is-active svg path {
  stroke: none;
}

.mobileBottomNav__icon {
  display: block;
  width: 1.15rem;
  height: 1.15rem;
  color: currentColor;
}

.mobileBottomNav__label {
  display: none;
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
